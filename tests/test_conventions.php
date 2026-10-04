<?php

// Symcon Best Practice / Store-Kriterien
// https://gist.github.com/paresy/236bfbfcb26e6936eaae919b3cfdfc4f

const MODULE_DIRS = ['io', 'Generic', 'Switch', 'THSensor', 'RGBWLED', 'BLTGWLock'];

function moduleSource(string $dir): string
{
    return file_get_contents(__DIR__ . "/../$dir/module.php");
}

$tests['Konvention: kein IPS_LogMessage, sondern $this->LogMessage'] = function () {
    foreach (array_merge(MODULE_DIRS, ['libs']) as $dir) {
        foreach (glob(__DIR__ . "/../$dir/*.php") as $file) {
            check(!str_contains(file_get_contents($file), 'IPS_LogMessage'), basename($dir) . '/' . basename($file));
        }
    }
};

$tests['Konvention: Modul konfiguriert und benennt sich nicht selbst'] = function () {
    foreach (MODULE_DIRS as $dir) {
        foreach (['IPS_SetProperty', 'IPS_SetConfiguration', 'IPS_ApplyChanges', 'IPS_SetName'] as $fn) {
            check(!str_contains(moduleSource($dir), $fn), "$dir nutzt $fn");
        }
    }
};

$tests['Konvention: nur noetige oeffentliche Funktionen'] = function () {
    $allowed = [
        TuyaClient::class => ['Create', 'ApplyChanges', 'MessageSink', 'Update', 'ForwardData'],
        TuyaGeneric::class => ['Create', 'ApplyChanges', 'ReceiveData', 'RequestAction', 'SearchModules', 'SetSelectedModul', 'RequestRefresh', 'TimerEvent'],
        TuyaSwitch::class => ['Create', 'ApplyChanges'],
        THSensor::class => ['Create', 'ApplyChanges'],
        TuyaLEDRGBW::class => ['Create', 'ApplyChanges'],
        TuyaBLELock::class => ['Create', 'ApplyChanges', 'RelockEvent', 'LogEvent', 'RefreshLog'],
    ];
    foreach ($allowed as $class => $names) {
        $public = [];
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class === $class) {
                $public[] = $method->name;
            }
        }
        sort($public); sort($names);
        check($public === $names, "$class: " . implode(', ', array_diff($public, $names)));
    }
};

$tests['Geraetesuche: Auswahl fuellt nur das Formular'] = function () {
    $m = make(TuyaSwitch::class, ['DeviceID' => '']);
    $m->SetSelectedModul(new ArrayObject(['ID' => 'dev9', 'LocalKey' => 'k9', 'Name' => 'Lampe', 'Online' => true, 'Model' => 'm']));
    check(TestRegistry::$configCalls === [], 'Konfiguration geaendert: ' . json_encode(TestRegistry::$configCalls));
    check(($m->formFields['DeviceID']['value'] ?? null) === 'dev9' && ($m->formFields['LocalKey']['value'] ?? null) === 'k9', 'Formular: ' . json_encode($m->formFields));
};

$tests['Geraetesuche: ohne Auswahl passiert nichts'] = function () {
    $m = make(TuyaSwitch::class);
    $m->SetSelectedModul(new ArrayObject([]));
    check(TestRegistry::$configCalls === [] && !isset($m->formFields['DeviceID']), 'Formular: ' . json_encode($m->formFields));
};

$tests['Geraetesuche: Liste kommt ueber das IO ins Popup'] = function () {
    $m = make(TuyaSwitch::class);
    $m->testParent->responses = [(object) ['success' => true, 'result' => [cloudDevice('dev9', true, [])]]];
    $m->SearchModules();
    check($m->testParent->requests[0][2] === ['app'], 'AppID nicht vom IO');
    $list = json_decode($m->formFields['Devices']['values'], true);
    check($list[0]['ID'] === 'dev9' && $list[0]['Online'] === true, 'Liste: ' . json_encode($list));
};

// ---- Sprache: Englisch als Basis, Deutsch ueber locale.json

// alle uebersetzbaren Texte eines Formulars (caption, label)
function formStrings($node): array
{
    $out = [];
    if (is_array($node)) {
        foreach ($node as $key => $value) {
            if (in_array($key, ['caption', 'label'], true) && is_string($value) && $value !== '') {
                $out[] = $value;
            }
            $out = array_merge($out, formStrings($value));
        }
    }
    return $out;
}

// Texte aus $this->Translate("...") im Code des Moduls und seiner Basisklasse
function codeStrings(string $dir): array
{
    $sources = [moduleSource($dir)];
    if (!in_array($dir, ['io', 'Generic'], true)) {
        $sources[] = moduleSource('Generic');
    }
    preg_match_all('/->Translate\("((?:[^"\\\\]|\\\\.)*)"\)/', implode("\n", $sources), $m);
    return $m[1];
}

$tests['Sprache: jedes Modul hat eine gueltige locale.json mit Deutsch'] = function () {
    foreach (MODULE_DIRS as $dir) {
        $file = __DIR__ . "/../$dir/locale.json";
        check(is_file($file), "$dir/locale.json fehlt");
        $locale = json_decode(file_get_contents($file), true);
        check(is_array($locale['translations']['de'] ?? null), "$dir/locale.json ohne translations.de");
    }
};

$tests['Sprache: alle Texte aus Formular und Code sind uebersetzt'] = function () {
    foreach (MODULE_DIRS as $dir) {
        $de = json_decode(@file_get_contents(__DIR__ . "/../$dir/locale.json") ?: '{}', true)['translations']['de'] ?? [];
        $form = json_decode(file_get_contents(__DIR__ . "/../$dir/form.json"), true);
        $missing = array_diff(array_unique(array_merge(formStrings($form), codeStrings($dir))), array_keys($de));
        check($missing === [], "$dir: " . implode(' | ', $missing));
    }
};

$tests['Sprache: Formulare und Variablennamen sind englisch'] = function () {
    foreach (MODULE_DIRS as $dir) {
        $text = file_get_contents(__DIR__ . "/../$dir/form.json") . moduleSource($dir);
        foreach (['Jetzt aktualisieren', 'ausfüllen', 'fehlgeschlagen', '"Temperatur", "Temperatur"'] as $german) {
            check(!str_contains($text, $german), "$dir enthaelt '$german'");
        }
    }
};
