<?php

// TuyaConfigurator: Liste aus der Cloud, Modulerkennung, Zuordnung bestehender Instanzen

const GUID_LOCK = '{3A4F1BCD-C90E-0977-8E7B-6396455735B7}';
const GUID_RGBW = '{5B9C0F92-91DA-0005-CB08-99844E8F2586}';
const GUID_TH = '{C8CDF2A1-7FF8-6F38-FA32-1590EED383A7}';
const GUID_SWITCH = '{EBCE6DBD-5213-E3B0-6DF1-0BC34504F3F9}';
const GUID_GENERIC = '{C490FACE-78CD-3AF7-918F-CC33DADD7F07}';

function configurator()
{
    $c = new TuyaConfigurator(30);
    $c->Create();
    $c->testParent = makeIO();
    $c->ApplyChanges();
    return $c;
}

// Cloud-Geraet wie von get_app_list; $dps = null -> ohne status-Feld
function tuyaDevice(string $id, string $name, string $model, string $category, ?array $dps, bool $online = true)
{
    $d = (object) ['id' => $id, 'name' => $name, 'model' => $model, 'category' => $category, 'online' => $online, 'local_key' => "k_$id"];
    if ($dps !== null) {
        $d->status = [];
        foreach ($dps as $code => $value) {
            $d->status[] = (object) ['code' => $code, 'value' => $value];
        }
    }
    return $d;
}

function cloudList($c, array $devices)
{
    $c->testParent->responses = [(object) ['success' => true, 'result' => $devices]];
}

function form($c): array
{
    return json_decode($c->GetConfigurationForm(), true);
}

function rows($c): array
{
    foreach (form($c)['actions'] as $element) {
        if ($element['type'] === 'Configurator') {
            return $element['values'];
        }
    }
    return [];
}

function rowFor(array $rows, string $deviceID): array
{
    foreach ($rows as $row) {
        if ($row['DeviceID'] === $deviceID) {
            return $row;
        }
    }
    throw new CheckFailed("keine Zeile fuer $deviceID");
}

function hint($c): ?string
{
    foreach (form($c)['actions'] as $element) {
        if ($element['type'] === 'Label') {
            return $element['caption'];
        }
    }
    return null;
}

$tests['Konfigurator: Modulerkennung'] = function () {
    $c = configurator();
    cloudList($c, [
        tuyaDevice('lamp', 'Lampe', 'Meka GU10 RGBCW', 'dj', ['switch_led' => true, 'bright_value' => 1000]),
        tuyaDevice('lamp2', 'Smart Bulb', 'ALS22L-N', 'dj', ['switch_led' => false, 'bright_value_v2' => 36]),
        tuyaDevice('lock', 'Schloss', 'YSG_T83_RFID_7G', 'ms', ['lock_motor_state' => true, 'residual_electricity' => 100]),
        tuyaDevice('lockms', 'Schloss ohne DP', 'x', 'ms', []),
        tuyaDevice('th', 'Sensor', 'x', 'wsdcg', ['va_temperature' => 215]),
        tuyaDevice('sw', 'Schalter', 'x', 'kg', ['switch_1' => true]),
        tuyaDevice('gw', 'Bluetooth gateway', '', 'wg2', ['up_channel' => '']),
        tuyaDevice('other', 'Anderes', 'x', 'xx', ['countdown' => 0]),
        tuyaDevice('nostatus', 'Ohne Status', 'x', 'xx', null),
    ]);
    $rows = rows($c);
    $expect = ['lamp' => GUID_RGBW, 'lamp2' => GUID_RGBW, 'lock' => GUID_LOCK, 'lockms' => GUID_LOCK, 'th' => GUID_TH,
        'sw' => GUID_SWITCH, 'other' => GUID_GENERIC, 'nostatus' => GUID_GENERIC];
    foreach ($expect as $id => $guid) {
        check((rowFor($rows, $id)['create']['moduleID'] ?? null) === $guid, "$id: " . json_encode(rowFor($rows, $id)));
    }
    check(rowFor($rows, 'lamp')['create']['configuration']['Version'] === '', 'Version Standard');
    check(rowFor($rows, 'lamp2')['create']['configuration']['Version'] === '_v2', 'Version V2');
    check(!isset(rowFor($rows, 'gw')['create']) && rowFor($rows, 'gw')['Module'] === '–', 'Gateway: ' . json_encode(rowFor($rows, 'gw')));
    check(rowFor($rows, 'lock')['Module'] === 'Lock' && rowFor($rows, 'lamp')['Module'] === 'Light', 'Modulnamen');
};

$tests['Konfigurator: create mit Geraetedaten und Kategorie Tuya'] = function () {
    $c = configurator();
    cloudList($c, [tuyaDevice('dev1', 'EGL Lampe', 'Meka GU10 RGBCW', 'dj', ['switch_led' => true]), tuyaDevice('sw', 'Schalter', 'x', 'kg', ['switch_1' => true])]);
    $rows = rows($c);
    $create = rowFor($rows, 'dev1')['create'];
    check($create['configuration'] === ['DeviceID' => 'dev1', 'LocalKey' => 'k_dev1', 'Version' => ''], 'configuration: ' . json_encode($create['configuration']));
    check($create['name'] === 'EGL Lampe' && $create['location'] === ['Tuya'], 'name/location: ' . json_encode($create));
    check(rowFor($rows, 'sw')['create']['configuration'] === ['DeviceID' => 'sw', 'LocalKey' => 'k_sw'], 'Schalter ohne Version');
    check(rowFor($rows, 'dev1')['instanceID'] === 0, 'instanceID');
};

$tests['Konfigurator: Spalten'] = function () {
    $c = configurator();
    cloudList($c, [tuyaDevice('dev1', 'EGL Lampe', 'Meka GU10 RGBCW', 'dj', ['switch_led' => true], false), tuyaDevice('dev2', '', 'm', 'kg', ['switch_1' => true])]);
    $rows = rows($c);
    $r = rowFor($rows, 'dev1');
    check($r['Name'] === 'EGL Lampe' && $r['Model'] === 'Meka GU10 RGBCW' && $r['Online'] === 'No', 'Zeile: ' . json_encode($r));
    check(rowFor($rows, 'dev2')['Name'] === 'dev2' && rowFor($rows, 'dev2')['Online'] === 'Yes', 'ohne Namen: ' . json_encode(rowFor($rows, 'dev2')));
};

$tests['Konfigurator: bestehende Instanz ueber DeviceID zugeordnet, auch anderes Modul'] = function () {
    $c = configurator();
    registerInstance(500, GUID_RGBW, ['DeviceID' => 'dev1'], 'Alt');
    registerInstance(501, GUID_GENERIC, ['DeviceID' => 'dev2'], 'Generic fuer Lampe');
    cloudList($c, [tuyaDevice('dev1', 'Lampe', 'm', 'dj', ['switch_led' => true]), tuyaDevice('dev2', 'Lampe 2', 'm', 'dj', ['switch_led' => true])]);
    $rows = rows($c);
    check(rowFor($rows, 'dev1')['instanceID'] === 500 && rowFor($rows, 'dev2')['instanceID'] === 501, 'Zuordnung: ' . json_encode($rows));
    check(count($rows) === 2, 'Zeilen: ' . count($rows));
};

$tests['Konfigurator: verwaiste, doppelte und leere Instanzen'] = function () {
    $c = configurator();
    registerInstance(500, GUID_RGBW, ['DeviceID' => 'dev1'], 'Erste');
    registerInstance(501, GUID_SWITCH, ['DeviceID' => 'dev1'], 'Doppelt');
    registerInstance(502, GUID_LOCK, ['DeviceID' => 'weg'], 'Alt');
    registerInstance(503, GUID_TH, ['DeviceID' => ''], 'Leer');
    cloudList($c, [tuyaDevice('dev1', 'Lampe', 'm', 'dj', ['switch_led' => true])]);
    $rows = rows($c);
    $byInstance = array_column($rows, null, 'instanceID');
    check(rowFor($rows, 'dev1')['instanceID'] === 500, 'erste Instanz nicht zugeordnet');
    check(isset($byInstance[501]) && !isset($byInstance[501]['create']), 'Doppelte: ' . json_encode($rows));
    check(isset($byInstance[502]) && !isset($byInstance[502]['create']) && $byInstance[502]['Name'] === 'Alt' && $byInstance[502]['DeviceID'] === 'weg', 'Verwaiste: ' . json_encode($rows));
    check(!isset($byInstance[503]), 'leere DeviceID angezeigt');
    check(count($rows) === 3, 'Zeilen: ' . count($rows));
};

$tests['Konfigurator: IO inaktiv'] = function () {
    $c = configurator();
    $c->testParent->status[] = 104;
    registerInstance(500, GUID_RGBW, ['DeviceID' => 'dev1'], 'Lampe');
    $rows = rows($c);
    check(count($rows) === 1 && $rows[0]['instanceID'] === 500 && !isset($rows[0]['create']), 'Zeilen: ' . json_encode($rows));
    check(str_contains((string) hint($c), 'TuyaClient is not active'), 'Hinweis: ' . var_export(hint($c), true));
    check($c->testParent->requests === [], 'Cloud trotzdem abgefragt');
};

$tests['Konfigurator: Cloud-Fehler'] = function () {
    $c = configurator();
    registerInstance(500, GUID_RGBW, ['DeviceID' => 'dev1'], 'Lampe');
    $c->testParent->responses = [(object) ['success' => false, 'code' => 1106, 'msg' => 'permission deny']];
    $hint = (string) hint($c);
    check(str_contains($hint, 'Device list not available') && str_contains($hint, 'permission deny'), 'Hinweis: ' . $hint);
    $c->testParent->requestError = new TuyaApiException('Netzwerk weg');
    $rows = rows($c);
    check(count($rows) === 1 && $rows[0]['instanceID'] === 500, 'Zeilen: ' . json_encode($rows));
    $hint = (string) hint($c);
    check(str_contains($hint, 'Netzwerk weg'), 'Hinweis: ' . $hint);
};

$tests['Konfigurator: ohne Fehler kein Hinweis'] = function () {
    $c = configurator();
    cloudList($c, []);
    check(hint($c) === null, 'Hinweis: ' . var_export(hint($c), true));
};

$tests['Konfigurator: ignoriert Statuspakete'] = function () {
    $c = configurator();
    $io = $c->testParent;
    $io->testChildren = [$c];
    $io->responses = [(object) ['success' => true, 'result' => [tuyaDevice('dev1', 'Lampe', 'm', 'dj', ['switch_led' => true])]]];
    $io->Update();
    check($c->receiveFilter === '^$', 'Filter: ' . $c->receiveFilter);
};
