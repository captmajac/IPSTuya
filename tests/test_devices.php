<?php

// Geraete-Module am IO: Status per Paket, Befehle ueber ForwardData

// Geraet als online melden, danach Request-Liste leeren
function online($m)
{
    push($m, true, []);
    $m->testParent->requests = [];
}

// ---- Generic

$tests['Generic: Online wird aus dem Paket gesetzt'] = function () {
    $m = make(TuyaSwitch::class);
    push($m, true, ['switch_1' => false]);
    check($m->value('Online') === true, 'Online nicht true');
    push($m, false, []);
    check($m->value('Online') === false, 'Online nicht false');
};

$tests['Generic: nur eigene Pakete'] = function () {
    $m = make(TuyaSwitch::class);
    push($m, true, ['switch_1' => true], 'dev2');
    check($m->value('Power') === false && $m->value('Online') === false, 'Paket von dev2 uebernommen');
};

$tests['Generic: leere DeviceID empfaengt nichts'] = function () {
    $m = make(TuyaSwitch::class, ['DeviceID' => '']);
    push($m, true, ['switch_1' => true], 'dev1');
    push($m, true, ['switch_1' => true], '');
    check($m->value('Power') === false && $m->value('Online') === false, 'Paket uebernommen');
};

$tests['Generic: offline setzt nur Online'] = function () {
    $m = make(TuyaSwitch::class);
    push($m, true, ['switch_1' => true]);
    push($m, false, ['switch_1' => false]);
    check($m->value('Online') === false, 'Online nicht false');
    check($m->value('Power') === true, 'Power aus veraltetem Status ueberschrieben');
};

$tests['Generic: Befehl laeuft ueber das IO'] = function () {
    $m = make(TuyaSwitch::class);
    online($m);
    $m->RequestAction('Power', true);
    $req = $m->testParent->requests[0];
    check($req[1] === 'post_commands' && $req[2] === ['dev1', ['commands' => [['code' => 'switch_1', 'value' => true]]]], 'Request: ' . json_encode($req));
    check($m->value('Power') === true, 'Power nicht gesetzt');
};

$tests['Generic: Fehlerantwort wirft TuyaApiException'] = function () {
    $m = make(TuyaSwitch::class);
    $m->testParent->requestError = new TuyaApiException('Netzwerk weg');
    try {
        $m->RequestAction('Power', true);
    } catch (TuyaApiException $e) {
        check($m->value('Power') === false, 'Power trotz Fehler gesetzt');
        return;
    }
    check(false, 'keine TuyaApiException');
};

$tests['Generic: ohne aktives IO wirft TuyaApiException'] = function () {
    $m = make(TuyaSwitch::class);
    $m->testParent->status[] = 104;
    try {
        $m->RequestAction('Power', true);
    } catch (TuyaApiException $e) {
        check($m->testParent->requests === [], 'trotzdem gesendet');
        return;
    }
    check(false, 'keine TuyaApiException');
};

$tests['Generic: TimerEvent loest Durchlauf im IO aus'] = function () {
    $m = make(TuyaSwitch::class);
    $m->TimerEvent();
    check(methods($m) === ['get_app_list'], 'Requests: ' . json_encode(methods($m)));
    check($m->timers['UpdateTimer'][0] === 0, 'eigener Timer laeuft');
};

$tests['Generic: Geraetesuche ueber das IO'] = function () {
    $m = make(TuyaSwitch::class);
    $m->testParent->responses = [(object) ['success' => true, 'result' => [cloudDevice('dev9', true, [])]]];
    $list = $m->readDeviceList();
    check($m->testParent->requests[0][2] === ['app'], 'AppID nicht vom IO');
    check($list[0]->ID === 'dev9' && $list[0]->Online === true, 'Liste: ' . json_encode($list));
};

// ---- Switch / THSensor

$tests['Switch: fehlender Datenpunkt setzt keinen falschen Wert'] = function () {
    $m = make(TuyaSwitch::class);
    push($m, true, ['countdown_1' => 500]);
    check($m->value('Power') === false, 'Power wurde aus countdown_1 gesetzt');
};

$tests['THSensor: Werte und fehlender Datenpunkt'] = function () {
    $m = make(THSensor::class);
    push($m, true, ['va_humidity' => 55, 'battery_state' => 'high']);
    check($m->value('Temperatur') === 0.0, 'Temperatur: ' . var_export($m->value('Temperatur'), true));
    check($m->value('Humidity') === 55.0, 'Humidity: ' . var_export($m->value('Humidity'), true));
    check($m->value('Battery') === 'high', 'Battery: ' . var_export($m->value('Battery'), true));
    push($m, true, ['va_temperature' => 215]);
    check($m->value('Temperatur') === 21.5, 'Temperatur: ' . var_export($m->value('Temperatur'), true));
};

// ---- RGBW

$tests['RGBW V2: Farbe wird als h/s/v in richtiger Reihenfolge gesendet'] = function () {
    $m = make(TuyaLEDRGBW::class, ['Version' => '_v2']);
    online($m);
    $m->RequestAction('Color', 0x008000); // Gruen, halbe Helligkeit
    $value = json_decode(sent($m)[0]['value'], true);
    check($value === ['h' => 120, 's' => 1000, 'v' => 500], 'gesendet: ' . sent($m)[0]['value']);
};

$tests['RGBW alt: Farbe als Hex hhhhssssvvvv'] = function () {
    $m = make(TuyaLEDRGBW::class);
    online($m);
    $m->RequestAction('Color', 0x008000);
    check(sent($m)[0]['value'] === '007803e801f4', 'gesendet: ' . sent($m)[0]['value']);
};

$tests['RGBW: Farbtemperatur ausserhalb des Bereichs wird vor dem Senden begrenzt'] = function () {
    $m = make(TuyaLEDRGBW::class);
    online($m);
    $m->RequestAction('ColorTemperature', 10000);
    check(sent($m)[0]['value'] === 1000, 'gesendet: ' . sent($m)[0]['value']);
    check($m->value('ColorTemperature') === TuyaLEDRGBW::COLMAX, 'Variable: ' . $m->value('ColorTemperature'));
};

$tests['RGBW V1: Status aus echter Cloud-Antwort'] = function () {
    $m = make(TuyaLEDRGBW::class);
    push($m, true, ['switch_led' => true, 'work_mode' => 'colour', 'bright_value' => 1000, 'temp_value' => 0, 'colour_data' => '016003e803e8']);
    check($m->value('Power') === true && $m->value('Mode') === 1, 'Power/Mode');
    check($m->value('Intensity') === 100, 'Intensity: ' . var_export($m->value('Intensity'), true));
    check($m->value('ColorTemperature') === TuyaLEDRGBW::COLMIN, 'ColorTemperature: ' . $m->value('ColorTemperature'));
};

$tests['RGBW V2: Status liest bright_value_v2'] = function () {
    $m = make(TuyaLEDRGBW::class, ['Version' => '_v2']);
    push($m, true, ['switch_led' => true, 'work_mode' => 'white', 'bright_value_v2' => 500, 'temp_value_v2' => 0]);
    check($m->value('Intensity') === 50, 'Intensity: ' . var_export($m->value('Intensity'), true));
};

// ---- Lib

$tests['Lib: fehlende Zugangsdaten werfen eine Exception statt exit'] = function () {
    try {
        new TuyaApi(['accessKey' => 'a']);
    } catch (TuyaApiException $e) {
        return;
    }
    check(false, 'keine TuyaApiException');
};

$tests['Lib: unbekannte API-Methode wirft eine Exception statt exit'] = function () {
    $tuya = new TuyaApi(['accessKey' => 'a', 'secretKey' => 's', 'baseUrl' => 'https://example.invalid']);
    try {
        $tuya->devices('t')->get_does_not_exist('x');
    } catch (TuyaApiException $e) {
        return;
    }
    check(false, 'keine TuyaApiException');
};

// ---- Offline-Geraete und abgelehnte Befehle

function expectError(callable $fn, string $contains)
{
    try {
        $fn();
    } catch (TuyaApiException $e) {
        check(str_contains($e->getMessage(), $contains), 'Meldung: ' . $e->getMessage());
        return;
    }
    check(false, 'keine TuyaApiException');
}

$tests['Befehl: Geraet offline und weiter offline -> sofort Fehler, kein Befehl'] = function () {
    $m = make(TuyaSwitch::class);
    push($m, false, []);
    $m->testParent->buffers['LastUpdate'] = (string) (microtime(true) - 11);     // letzter Durchlauf aelter als 10 s
    $m->testParent->requests = [];
    $m->testParent->responses = [(object) ['success' => true, 'result' => [cloudDevice('dev1', false, [])]]];
    expectError(fn () => $m->RequestAction('Power', true), 'offline');
    check(methods($m) === ['get_app_list'], 'Requests: ' . json_encode(methods($m)));
};

$tests['Befehl: Geraet gerade erst als offline gemeldet -> Stand von eben, kein neuer Abruf'] = function () {
    $m = make(TuyaSwitch::class);
    push($m, false, []);
    $m->testParent->requests = [];
    expectError(fn () => $m->RequestAction('Power', true), 'offline');
    check($m->testParent->requests === [], 'Requests: ' . json_encode(methods($m)));
};

$tests['Befehl: Geraet war offline, ist wieder online -> wird geschaltet'] = function () {
    $m = make(TuyaSwitch::class);
    push($m, false, []);
    $m->testParent->buffers['LastUpdate'] = (string) (microtime(true) - 11);     // letzter Durchlauf aelter als 10 s
    $m->testParent->requests = [];
    $m->testParent->responses = [(object) ['success' => true, 'result' => [cloudDevice('dev1', true, ['switch_1' => false])]]];
    $m->RequestAction('Power', true);
    check(methods($m) === ['get_app_list', 'post_commands'], 'Requests: ' . json_encode(methods($m)));
    check($m->value('Power') === true && $m->value('Online') === true, 'Power/Online');
};

$tests['Befehl: Tuya meldet device is offline -> Fehler und Online aus'] = function () {
    $m = make(TuyaSwitch::class);
    push($m, true, ['switch_1' => false]);
    $m->testParent->responses = [(object) ['success' => false, 'code' => 2001, 'msg' => 'device is offline']];
    expectError(fn () => $m->RequestAction('Power', true), 'device is offline');
    check($m->value('Online') === false && $m->value('Power') === false, 'Online/Power');
};

$tests['Befehl: andere Ablehnung -> Fehler mit Meldung, Online bleibt'] = function () {
    $m = make(TuyaSwitch::class);
    push($m, true, ['switch_1' => false]);
    $m->testParent->responses = [(object) ['success' => false, 'code' => 2008, 'msg' => 'command or value not support']];
    expectError(fn () => $m->RequestAction('Power', true), 'not support');
    check($m->value('Online') === true, 'Online geaendert');
};

// ---- Debug ohne Schluessel und Standort

$tests['IO-Debug: Durchlauf zeigt Zusammenfassung ohne local_key, IP, Standort'] = function () {
    $io = makeIO();
    $dev = cloudDevice('dev1', false, ['switch_led' => true, 'bright_value' => 10]);
    $dev->name = 'EGL Lampe';
    $dev->local_key = 'GEHEIM-KEY';
    $dev->ip = '31.16.231.113';
    $dev->lat = '52.3699';
    $io->responses = [(object) ['success' => true, 'result' => [$dev]]];
    $io->Update();
    $text = json_encode($io->debug);
    foreach (['GEHEIM-KEY', '31.16.231.113', '52.3699', 'local_key'] as $secret) {
        check(!str_contains($text, $secret), "Debug enthaelt $secret");
    }
    check(str_contains($text, 'EGL Lampe | offline | 2 Datenpunkte'), 'Zusammenfassung fehlt: ' . $text);
};

$tests['IO-Debug: Geraetesuche ueber ForwardData ohne local_key'] = function () {
    $io = makeIO();
    $dev = cloudDevice('dev1', true, []);
    $dev->local_key = 'GEHEIM-KEY';
    $io->responses = [(object) ['success' => true, 'result' => [$dev]]];
    $res = $io->forward('get_app_list', 'app');
    check($res->result[0]->local_key === 'GEHEIM-KEY', 'Antwort an Instanz ohne local_key');
    check(!str_contains(json_encode($io->debug), 'GEHEIM-KEY'), 'Debug enthaelt local_key');
};

$tests['Befehl: Szene mit mehreren Offline-Lampen -> nur ein Durchlauf'] = function () {
    $io = makeIO();
    $lamps = [];
    foreach (['dev1', 'dev2', 'dev3'] as $i => $id) {
        $m = new TuyaSwitch(10 + $i);
        $m->Create();
        $m->properties['DeviceID'] = $id;
        $m->testParent = $io;
        $m->ApplyChanges();
        $lamps[] = $m;
    }
    $io->testChildren = $lamps;
    $io->responses = [(object) ['success' => true, 'result' => [cloudDevice('dev1', false, []), cloudDevice('dev2', false, []), cloudDevice('dev3', false, [])]]];
    foreach ($lamps as $m) {
        expectError(fn () => $m->RequestAction('Power', true), 'offline');
    }
    check(array_column($io->requests, 1) === ['get_app_list'], 'Requests: ' . json_encode(array_column($io->requests, 1)));
};
