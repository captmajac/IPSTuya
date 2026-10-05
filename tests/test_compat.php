<?php

// Bestehende Instanzen muessen nach dem Update ohne Neuanlage weiterlaufen:
// Eigenschaften und Variablen-Idents duerfen sich nicht aendern.

$tests['Kompatibilitaet: Eigenschaften und Idents unveraendert'] = function () {
    $io = makeIO();
    $expected = [
        TuyaGeneric::class => [['DeviceID', 'LocalKey'], ['Online']],
        TuyaSwitch::class => [['DeviceID', 'LocalKey'], ['Online', 'Power']],
        THSensor::class => [['DeviceID', 'LocalKey'], ['Online', 'Temperatur', 'Humidity', 'Battery']],
        TuyaLEDRGBW::class => [['DeviceID', 'LocalKey', 'Version'], ['Online', 'Power', 'Mode', 'Intensity', 'ColorTemperature', 'Color']],
        TuyaBLELock::class => [['DeviceID', 'LocalKey'], ['Online', 'Lock', 'Message', 'MotorState', 'Battery', 'Sound', 'Log']],
    ];
    check(array_keys($io->properties) === ['AccessKey', 'SecretKey', 'BaseUrl', 'AppID', 'Interval'], 'IO: ' . json_encode(array_keys($io->properties)));
    foreach ($expected as $class => [$props, $idents]) {
        $m = make($class);
        $gotProps = array_keys($m->properties);
        $gotIdents = array_keys($m->variables);
        sort($gotProps); sort($props); sort($gotIdents); sort($idents);
        check($gotProps === $props, "$class Eigenschaften: " . json_encode($gotProps));
        check($gotIdents === $idents, "$class Idents: " . json_encode($gotIdents));
    }
};

$tests['Kompatibilitaet: GUIDs in module.json unveraendert'] = function () {
    $root = __DIR__ . '/..';
    $io = json_decode(file_get_contents("$root/io/module.json"), true);
    check($io['id'] === '{78ABC644-1134-F4E2-3E31-01E45483367B}', 'IO id');
    check($io['implemented'] === ['{C459F3BF-8570-E12D-9B2A-14F0343C7F37}'] && $io['childRequirements'] === ['{018EF6B5-AB94-40C6-AA53-46943E824ACF}'], 'IO Datenfluss');
    foreach (['Generic', 'Switch', 'THSensor', 'RGBWLED', 'BLTGWLock'] as $dir) {
        $m = json_decode(file_get_contents("$root/$dir/module.json"), true);
        check($m['parentRequirements'] === ['{C459F3BF-8570-E12D-9B2A-14F0343C7F37}'] && $m['implemented'] === ['{018EF6B5-AB94-40C6-AA53-46943E824ACF}'], "$dir Datenfluss");
        check($m['prefix'] === 'Tuya', "$dir prefix");
    }
};

$tests['Kompatibilitaet: Konfigurator haengt mit den Datenfluss-GUIDs der Geraete am IO'] = function () {
    $m = json_decode(file_get_contents(__DIR__ . '/../Configurator/module.json'), true);
    check($m['id'] === '{E038934B-3A6D-45E3-B6AB-CD85A315E3CD}' && $m['type'] === 4 && $m['prefix'] === 'Tuya', 'id/type/prefix');
    check($m['parentRequirements'] === ['{C459F3BF-8570-E12D-9B2A-14F0343C7F37}'] && $m['implemented'] === ['{018EF6B5-AB94-40C6-AA53-46943E824ACF}'], 'Datenfluss');
};
