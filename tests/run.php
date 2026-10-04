<?php

// Aufruf: php tests/run.php
// Testet das Modulverhalten gegen den Symcon-Stub; Cloud-Aufrufe werden in Testklassen ersetzt.

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../BLTGWLock/module.php';
require_once __DIR__ . '/../RGBWLED/module.php';
require_once __DIR__ . '/../Switch/module.php';
require_once __DIR__ . '/../THSensor/module.php';

// Cloud-Antwort wie von /v1.0/devices/{id}/status
function status(array $dps)
{
    $result = [];
    foreach ($dps as $code => $value) {
        $result[] = (object) ['code' => $code, 'value' => $value];
    }
    return (object) ['success' => true, 'result' => $result];
}

function devices(array $online)
{
    $list = [];
    foreach ($online as $id => $state) {
        $list[] = (object) ['ID' => $id, 'Online' => $state, 'Name' => $id, 'Model' => '', 'LocalKey' => ''];
    }
    return $list;
}

// Gemeinsame Cloud-Ersetzung fuer alle Testklassen
trait FakeCloud
{
    public array $sent = [];
    public $stateResponse = null;
    public array $deviceList = [];
    public ?Throwable $cloudError = null;

    public function CPost(array $payload)
    {
        if ($this->cloudError) {
            throw $this->cloudError;
        }
        $this->sent[] = $payload;
        return true;
    }
    public function getState()
    {
        if ($this->cloudError) {
            throw $this->cloudError;
        }
        return $this->stateResponse;
    }
    public function getToken() { return 'token'; }
    public function readDeviceList(string $token, string $app_id) { return $this->deviceList; }
}

class TestLock extends TuyaBLELock
{
    use FakeCloud;
    public int $unlockCalls = 0;
    public function unlock() { $this->unlockCalls++; return true; }
    public function readLockLog() {}
}
class TestRGBW extends TuyaLEDRGBW { use FakeCloud; }
class TestSwitch extends TuyaSwitch { use FakeCloud; }
class TestTH extends THSensor { use FakeCloud; }

function make(string $class, array $props = [])
{
    $m = new $class(1);
    $m->Create();
    $m->properties = array_merge($m->properties, ['DeviceID' => 'dev1'], $props);
    $m->ApplyChanges();
    $m->deviceList = devices(['dev1' => true]);
    return $m;
}

// ---------------------------------------------------------------- Tests

$tests = [];

$tests['Schloss: Abschliessen (true) loest kein Entsperren aus'] = function () {
    $m = make(TestLock::class);
    $m->RequestAction('Lock', true);
    check($m->unlockCalls === 0, "unlock() wurde {$m->unlockCalls}x aufgerufen");
};

$tests['Schloss: Entsperren blockiert nicht mit IPS_Sleep'] = function () {
    $m = make(TestLock::class);
    $m->RequestAction('Lock', false);
    check($m->unlockCalls === 1, 'unlock() nicht aufgerufen');
    check(TestRegistry::$sleeps === [], 'IPS_Sleep aufgerufen: ' . json_encode(TestRegistry::$sleeps));
};

$tests['Schloss: Entsperren setzt nach kurzer Zeit per Timer auf zu'] = function () {
    $m = make(TestLock::class);
    $m->RequestAction('Lock', false);
    check($m->value('Lock') === false, 'Lock sollte direkt nach dem Entsperren false sein');
    check(($m->timers['RelockTimer'][0] ?? 0) > 0, 'RelockTimer nicht gestartet');
    $m->RelockEvent();
    check($m->value('Lock') === true, 'Lock sollte nach RelockEvent wieder true sein');
    check($m->timers['RelockTimer'][0] === 0, 'RelockTimer laeuft weiter');
};

$tests['Schloss: ApplyChanges ueberschreibt den Status nicht'] = function () {
    $m = make(TestLock::class);
    TestRegistry::$values[$m->variables['Lock']] = false;
    $m->ApplyChanges();
    check($m->value('Lock') === false, 'ApplyChanges hat Lock zurueckgesetzt');
};

$tests['Schloss: updateState ohne Cloud-Antwort wirft nicht'] = function () {
    $m = make(TestLock::class);
    $m->stateResponse = null;
    $m->updateState();
    check(true, '');
};

$tests['RGBW V2: Farbe wird als h/s/v in richtiger Reihenfolge gesendet'] = function () {
    $m = make(TestRGBW::class, ['Version' => '_v2']);
    $m->RequestAction('Color', 0x008000); // Gruen, halbe Helligkeit
    $value = json_decode($m->sent[0]['value'], true);
    check($value === ['h' => 120, 's' => 1000, 'v' => 500], 'gesendet: ' . $m->sent[0]['value']);
};

$tests['RGBW alt: Farbe als Hex hhhhssssvvvv'] = function () {
    $m = make(TestRGBW::class);
    $m->RequestAction('Color', 0x008000);
    check($m->sent[0]['value'] === '007803e801f4', 'gesendet: ' . $m->sent[0]['value']);
};

$tests['RGBW: Farbtemperatur ausserhalb des Bereichs wird vor dem Senden begrenzt'] = function () {
    $m = make(TestRGBW::class);
    $m->RequestAction('ColorTemperature', 10000);
    check($m->sent[0]['value'] === 1000, 'gesendet: ' . $m->sent[0]['value']);
    check($m->value('ColorTemperature') === TuyaLEDRGBW::COLMAX, 'Variable: ' . $m->value('ColorTemperature'));
};

$tests['RGBW V2: Status liest bright_value_v2'] = function () {
    $m = make(TestRGBW::class, ['Version' => '_v2']);
    $m->stateResponse = status(['switch_led' => true, 'work_mode' => 'white', 'bright_value_v2' => 500, 'temp_value_v2' => 0]);
    $m->updateState();
    check($m->value('Intensity') === 50, 'Intensity: ' . var_export($m->value('Intensity'), true));
};

$tests['Switch: fehlender Datenpunkt setzt keinen falschen Wert'] = function () {
    $m = make(TestSwitch::class);
    $m->stateResponse = status(['countdown_1' => 500]); // kein switch_1
    $m->updateState();
    check($m->value('Power') === false, 'Power wurde aus countdown_1 gesetzt');
};

$tests['THSensor: fehlender Datenpunkt setzt keinen falschen Wert'] = function () {
    $m = make(TestTH::class);
    $m->stateResponse = status(['va_humidity' => 55]); // keine Temperatur
    $m->updateState();
    check($m->value('Temperatur') === 0.0, 'Temperatur: ' . var_export($m->value('Temperatur'), true));
    check($m->value('Humidity') === 55.0, 'Humidity: ' . var_export($m->value('Humidity'), true));
};

$tests['Generic: erstes Geraet der Liste wird als online erkannt'] = function () {
    $m = make(TestSwitch::class);
    $m->deviceList = devices(['dev1' => true, 'dev2' => false]);
    check($m->GetOnlineStatus('dev1') === true, 'dev1 an Index 0 als offline erkannt');
};

$tests['Generic: unbekanntes Geraet ist offline'] = function () {
    $m = make(TestSwitch::class);
    $m->deviceList = devices(['dev2' => true]);
    check($m->GetOnlineStatus('dev1') === false, 'unbekanntes Geraet als online erkannt');
};

$tests['Generic: Cloud-Fehler im Timer wird geloggt statt geworfen'] = function () {
    $m = make(TestSwitch::class);
    $m->cloudError = new TuyaApiException('Netzwerk weg');
    $m->TimerEvent();
    check(count(TestRegistry::$log) > 0, 'nichts geloggt');
};

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

// ---------------------------------------------------------------- Runner

class CheckFailed extends Exception {}
function check(bool $ok, string $msg) { if (!$ok) throw new CheckFailed($msg); }

$failed = 0;
foreach ($tests as $name => $test) {
    TestRegistry::reset();
    try {
        $test();
        echo "  ok    $name\n";
    } catch (Throwable $e) {
        $failed++;
        $where = $e instanceof CheckFailed ? '' : ' [' . get_class($e) . ' in ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
        echo "  FAIL  $name: {$e->getMessage()}$where\n";
    }
}
echo "\n" . (count($tests) - $failed) . '/' . count($tests) . " bestanden\n";
exit($failed ? 1 : 0);
