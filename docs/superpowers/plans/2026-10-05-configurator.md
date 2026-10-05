# TuyaConfigurator (Paket C) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Neues Modul TuyaConfigurator listet die Geräte der Tuya Cloud, legt fehlende Instanzen mit passendem Modul an und ordnet bestehende über `DeviceID` zu; die Suche verschwindet aus den Geräte-Instanzen.

**Architecture:** Konfigurator (Typ 4) hängt mit den Datenfluss-GUIDs der Geräte am TuyaClient und holt die Liste über den gemeinsamen Trait `TuyaDataFlow::api()`. `GetConfigurationForm()` baut die Zeilen aus Cloud-Liste plus vorhandenen Instanzen.

**Tech Stack:** PHP 8.3 (lokal `../tools/php/php`), IP-Symcon 7 SDK, Stub `tests/stubs.php`, Runner `tests/run.php`.

**Spec:** `docs/superpowers/specs/2026-10-05-configurator-design.md`

## Global Constraints

- Bestehende GUIDs unverändert: IO `{78ABC644-1134-F4E2-3E31-01E45483367B}`, Datenfluss Geräte→IO `{C459F3BF-8570-E12D-9B2A-14F0343C7F37}`, IO→Geräte `{018EF6B5-AB94-40C6-AA53-46943E824ACF}`, Gerätemodule wie in der Spec.
- Neue Modul-GUID Konfigurator: `{E038934B-3A6D-45E3-B6AB-CD85A315E3CD}`, Präfix `Tuya`, vendor `Tuya`, type 4.
- Eigenschaften `DeviceID`, `LocalKey`, `Version` und alle Idents unverändert.
- Englisch als Basis, jeder Text aus Formular und `Translate()` mit deutscher Übersetzung in der `locale.json` des Moduls.
- `$this->LogMessage` statt `IPS_LogMessage`; Modul ändert nie die eigene Konfiguration.

## Review Focus

- Cloud-Gerät ohne `status`-Feld darf die Liste nicht abbrechen → wird Generic (Test in Task 2).
- Cloud-Gerät ohne Namen → Zeile zeigt die Geräte-ID als Namen (Test in Task 2).
- IO vorhanden, aber ohne Zugangsdaten (Status 104) → wie „IO inaktiv“: Instanzen + Hinweis (Test in Task 2).
- Instanz eines Gerätemoduls, die einem *anderen* Modul zugeordnet würde (z. B. Generic-Instanz für eine Lampe) → bleibt der Gerätezeile zugeordnet, kein zweites Anlegen (Test in Task 2).
- Konfigurator als Kind des IO darf die Statuspakete nicht verarbeiten (Test in Task 2).

---

### Task 1: Gemeinsamer Weg zum IO, Suche aus den Geräten entfernen

**Files:**
- Create: `libs/TuyaDataFlow.php`
- Modify: `Generic/module.php`, `Generic/form.json`, `Switch/form.json`, `THSensor/form.json`, `RGBWLED/form.json`, `BLTGWLock/form.json`, alle Geräte-`locale.json`, `tests/test_conventions.php`

**Interfaces:**
- Produces: `trait TuyaDataFlow { protected function api(string $method, ...$params) }` in `libs/TuyaDataFlow.php`; der Trait nutzt die DataID `{C459F3BF-8570-E12D-9B2A-14F0343C7F37}` selbst. Verhalten exakt wie bisher `TuyaGeneric::api()`.
- `TuyaGeneric` nutzt `use TuyaDataFlow;`; entfernt: `SearchModules`, `SetSelectedModul`, `readDeviceList`.

- [ ] **Step 1: Tests anpassen.** In `tests/test_conventions.php`: erlaubte öffentliche Funktionen `TuyaGeneric` = `Create, ApplyChanges, ReceiveData, RequestAction, RequestRefresh, TimerEvent`. Tests „Geraetesuche: …“ (3 Stück) löschen. Neu:
  - `Konvention: Geraeteformulare ohne Suche` — für `Generic, Switch, THSensor, RGBWLED, BLTGWLock`: `form.json` enthält kein `PopupButton` und kein `Tuya_SearchModules`; `elements`-Namen exakt `DeviceID, LocalKey` (RGBWLED zusätzlich `Version`).
  - `Konvention: keine ungenutzten Uebersetzungen in Geraeten` — jeder Schlüssel in `locale.json` der Geräte kommt in `formStrings()` oder `codeStrings()` des Moduls vor.
- [ ] **Step 2: Lauf → FAIL** (öffentliche Funktionen, Formulare, ungenutzte Übersetzungen).
- [ ] **Step 3: Umsetzen.** Trait anlegen, `TuyaGeneric` umstellen (`include_once __DIR__ . "/../libs/TuyaDataFlow.php";`), Suchfunktionen entfernen, Formulare auf die Felder reduzieren, Such-Texte aus den `locale.json` entfernen.
- [ ] **Step 4: Lauf → alle PASS** (`../tools/php/php -d error_reporting=-1 tests/run.php`).
- [ ] **Step 5: Commit** `Move IO access into TuyaDataFlow trait, remove device search from device instances`.

### Task 2: Modul TuyaConfigurator

**Files:**
- Create: `Configurator/module.json`, `Configurator/form.json`, `Configurator/module.php`, `Configurator/locale.json`, `tests/test_configurator.php`
- Modify: `tests/stubs.php`, `tests/run.php`, `tests/test_conventions.php`

**Interfaces:**
- Consumes: `TuyaDataFlow::api()` aus Task 1; IO-Methoden `config` → `{AppID}`, `get_app_list` → `{success, result: [{id, name, model, category, online, local_key, status: [{code, value}]}]}`.
- Produces: `class TuyaConfigurator extends IPSModule` mit öffentlich `Create`, `ApplyChanges`, `ReceiveData`, `GetConfigurationForm` (gibt JSON-String zurück).
- Stub: `registerInstance(int $id, string $moduleID, array $props, string $name)`; `IPS_GetInstanceListByModuleID`, `IPS_GetProperty`, `IPS_GetName` lesen `TestRegistry::$instances`.
- Formular: `actions` enthält das Element `{"type": "Configurator", "name": "Devices", "delete": true, ...}` mit Spalten `Name, Model, Module, Online, DeviceID`; ein Hinweis wird als `{"type": "Label", "caption": "<Text>"}` vor dem Konfigurator in `actions` eingefügt.

- [ ] **Step 1: Fehlschlagende Tests in `tests/test_configurator.php`.** Helfer `configurator()` (Instanz am `FakeIO`, `Create`, `ApplyChanges`), `rows($c)` (dekodiert `GetConfigurationForm()` und liefert die `values` des Elements `Configurator`), `hint($c)` (Caption eines `Label` in `actions` oder `null`). Cloud-Antworten mit den echten Datenpunkten aus der Spec/README:
  - `Konfigurator: Modulerkennung` — Lampe (`switch_led`, `bright_value`) → moduleID RGBW, `configuration.Version === ""`; Lampe V2 (`switch_led`, `bright_value_v2`) → `Version === "_v2"`; Schloss (`lock_motor_state`) → Lock; Kategorie `ms` ohne Datenpunkte → Lock; `va_temperature` → THSensor; `switch_1` → Switch; Gateway (`category: "wg2"`, nur `up_channel`) → kein `create`, `Module === "–"`; `countdown` allein → Generic; Gerät ohne `status`-Feld → Generic.
  - `Konfigurator: create mit Geraetedaten und Kategorie Tuya` — `create.configuration === ['DeviceID' => 'dev1', 'LocalKey' => 'k1']` (Lampe zusätzlich `Version`), `create.name === 'EGL Lampe'`, `create.location === ['Tuya']`.
  - `Konfigurator: Spalten` — `Name`, `Model`, `Online` (`Yes`/`No`), `DeviceID`; Gerät ohne Namen → `Name === DeviceID`.
  - `Konfigurator: bestehende Instanz ueber DeviceID zugeordnet` — `registerInstance(500, RGBW-GUID, ['DeviceID' => 'dev1'], 'Alt')` → Zeile dev1 hat `instanceID === 500`; auch wenn die Instanz ein anderes Modul ist (Generic-GUID) → `instanceID === 500`, nur eine Zeile für dev1.
  - `Konfigurator: verwaiste und doppelte Instanzen` — Instanz mit DeviceID `weg` (nicht in Cloud) → eigene Zeile, `instanceID`, kein `create`, `Name === 'Alt'`; zweite Instanz mit DeviceID `dev1` → eigene Zeile ohne `create`; Instanz mit leerer DeviceID → keine Zeile.
  - `Konfigurator: IO inaktiv` — IO-Status 104 → nur Instanz-Zeilen, `hint()` enthält `TuyaClient is not active`, keine Cloud-Requests.
  - `Konfigurator: Cloud-Fehler` — `requestError` bzw. `success:false, msg: 'permission deny'` → Instanz-Zeilen, `hint()` enthält `Device list not available` und die Meldung.
  - `Konfigurator: ignoriert Statuspakete` — Konfigurator in `testChildren` des IO, `Update()` mit einem Gerät → kein Fehler, `receiveFilter === '^$'`.
  - In `test_conventions.php`: `MODULE_DIRS` um `Configurator` ergänzen; erlaubte öffentliche Funktionen `TuyaConfigurator` = `Create, ApplyChanges, ReceiveData, GetConfigurationForm`; Kompatibilitätstest: `Configurator/module.json` hat id, type 4, parentRequirements und implemented wie in den Global Constraints.
- [ ] **Step 2: Lauf → FAIL** (Klasse fehlt).
- [ ] **Step 3: Umsetzen.** Modulerkennung als `private function moduleFor(object $device): ?array` (liefert `['moduleID', 'caption', 'configuration']` oder `null` für Gateway) nach der Regeltabelle der Spec; Gerätemodul-GUIDs als Konstante `DEVICE_MODULES`. Texte: Modul-Captions `Lock`, `Light`, `Temperature/humidity sensor`, `Switch`, `Generic`; `Yes`, `No`; Hinweise `TuyaClient is not active, device list not available.` und `Device list not available: %s`. `locale.json` mit allen Texten. `run.php` bindet `Configurator/module.php` ein.
- [ ] **Step 4: Lauf → alle PASS, keine Warnings.**
- [ ] **Step 5: Commit** `Add TuyaConfigurator: list cloud devices, create and map instances`.

### Task 3: Doku und Version

**Files:**
- Create: `Configurator/README.md`
- Modify: `README.md`, `Generic/README.md`, `Switch/README.md`, `THSensor/README.md`, `RGBWLED/README.md`, `BLTGWLock/README.md`, `library.json`

- [ ] **Step 1:** `library.json` `"version": "1.3"`.
- [ ] **Step 2:** `Configurator/README.md` in der Symcon-Struktur (Funktionsumfang, Voraussetzungen, Installation, Einrichten inkl. Zeilenfarben und Erkennungstabelle, keine Statusvariablen, keine PHP-Befehle außer Standard). Übersicht: Konfigurator in Modultabelle und Installationsschritten, Änderungen 1.3 (Konfigurator, Suche entfernt, entfallene Funktionen `Tuya_SearchModules`, `Tuya_SetSelectedModul`). Geräte-READMEs: Abschnitt zur Suche durch Verweis auf den Konfigurator und Hinweis zum Neuzuordnen per Geräte-ID ersetzen.
- [ ] **Step 3:** Alle Tests, `php -l` auf alle PHP-Dateien, JSON-Prüfung aller `*.json`.
- [ ] **Step 4: Commit und Push** `feature/configurator`.
