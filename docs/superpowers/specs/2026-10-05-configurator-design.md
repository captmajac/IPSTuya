# Paket C: TuyaConfigurator

Stand: 2026-10-05 · Branch `feature/configurator` (von `main`, Version 1.2)

## Ziel

Die Gerätesuche zieht aus den Geräte-Instanzen in ein Konfigurator-Modul. Dort werden alle Geräte des
verknüpften Tuya-App-Kontos angezeigt, fehlende Instanzen mit dem passenden Modul angelegt und bestehende
Instanzen zugeordnet, sodass man in sie springen kann.

## Feste Anforderungen

1. **Bestehende Instanzen bleiben unverändert und werden nicht neu angelegt.** Modul-GUIDs, Datenfluss-GUIDs,
   Eigenschaften (`DeviceID`, `LocalKey`, `Version`), Idents und Präfix `Tuya` bleiben. Update nur über die Modulverwaltung.
2. Der Konfigurator ist ein **zusätzliches** Modul; IO und Gerätemodule bleiben im Datenfluss unverändert.
3. Geräte-ID, Local Key und Version bleiben in den Geräte-Instanzen **änderbare Felder** (Neuzuordnung per Kopieren/Einfügen).
4. Neue Instanzen landen in der Kategorie **„Tuya“** (wird beim ersten Anlegen erzeugt).

## Nicht im Umfang

Unterkategorien je Geräteart, automatisches Anlegen von Statusvariablen (Idee aus Branch `dev`), mehrere TuyaClient-Instanzen.

## Vorbild

[symcon/HomeConnect](https://github.com/symcon/HomeConnect), Modul „Home Connect Configurator“: Typ 4, gleiche
Datenfluss-GUIDs wie die Geräte, Liste in `GetConfigurationForm()`, Zuordnung über `IPS_GetInstanceListByModuleID`
und die Geräte-ID-Eigenschaft, vorhandene Instanzen bleiben sichtbar, wenn die Cloud nicht erreichbar ist.

## Aufbau

### Modul `Configurator/` (TuyaConfigurator)

`module.json`: id `{E038934B-3A6D-45E3-B6AB-CD85A315E3CD}`, name `TuyaConfigurator`, type 4, vendor `Tuya`, prefix `Tuya`,
`parentRequirements` `["{C459F3BF-8570-E12D-9B2A-14F0343C7F37}"]`, `implemented` `["{018EF6B5-AB94-40C6-AA53-46943E824ACF}"]`,
`childRequirements` `[]`.

- `Create`: `ConnectParent` auf TuyaClient `{78ABC644-1134-F4E2-3E31-01E45483367B}`.
- `ApplyChanges`: `SetReceiveDataFilter('^$')` — Statuspakete des IO sind für den Konfigurator ohne Bedeutung.
- `ReceiveData`: ignoriert.
- `GetConfigurationForm`: baut die Liste (unten) und setzt sie als `values` des Elements `Configurator`.

Formular: Element `Configurator`, `delete: true`, Spalten (Name / Caption):
`Name` (Name, auto), `Model` (Model, 200px), `Module` (Module, 160px), `Online` (Online, 70px), `DeviceID` (Device ID, 220px).

### Geteilter Weg zum IO

`api(string $method, ...$params)` zieht aus `TuyaGeneric` in den Trait `TuyaDataFlow` (`libs/TuyaDataFlow.php`), genutzt von
`TuyaGeneric` und `TuyaConfigurator`. Verhalten unverändert (Fehler → `TuyaApiException`, inaktives IO → `TuyaApiException`).

### Liste

1. Ist das IO aktiv: `AppID` per `api('config')`, Geräte per `api('get_app_list', AppID)`.
2. Je Cloud-Gerät eine Zeile:
   - `Name` = Tuya-Name, `Model`, `Online` (übersetzt „Yes“/„No“), `DeviceID` = Tuya-ID, `Module` = Anzeigename des Moduls oder „–“.
   - `instanceID` = erste Instanz eines Tuya-Gerätemoduls mit `DeviceID` gleich Tuya-ID, sonst 0.
   - `create` (nur wenn ein Modul zugeordnet ist): `moduleID`, `configuration` `{DeviceID, LocalKey}` plus `Version` bei Lampen,
     `name` = Tuya-Name, `location` = `["Tuya"]`.
3. Danach je Instanz eines Tuya-Gerätemoduls mit nicht leerer `DeviceID`, die keiner Zeile zugeordnet wurde
   (Gerät nicht in der Cloud oder doppelte Geräte-ID): Zeile mit `instanceID`, Instanzname, `DeviceID`, ohne `create`.
4. IO inaktiv oder Cloud-Fehler: nur Schritt 3 (alle Instanzen) und ein Hinweis als `Label` über der Liste
   („TuyaClient is not active …“ bzw. „Device list not available: <Meldung>“).

Tuya-Gerätemodule für die Zuordnung: TuyaGeneric, TuyaSwitch, THSensor, TuyaLEDRGBW, TuyaBLELock.

### Modulerkennung (erste passende Regel)

| Regel | Modul | Konfiguration zusätzlich |
|---|---|---|
| Datenpunkt `lock_motor_state` oder Kategorie `ms` | TuyaBLELock `{3A4F1BCD-C90E-0977-8E7B-6396455735B7}` | – |
| Datenpunkt `switch_led` | TuyaLEDRGBW `{5B9C0F92-91DA-0005-CB08-99844E8F2586}` | `Version` `_v2`, wenn ein Datenpunkt auf `_v2` endet, sonst `""` |
| Datenpunkt `va_temperature` oder `va_humidity` | THSensor `{C8CDF2A1-7FF8-6F38-FA32-1590EED383A7}` | – |
| Datenpunkt `switch_1` | TuyaSwitch `{EBCE6DBD-5213-E3B0-6DF1-0BC34504F3F9}` | – |
| Kategorie `wg2` oder `wg`, oder nur Datenpunkt `up_channel` | keines (Gateway) | – |
| sonst | TuyaGeneric `{C490FACE-78CD-3AF7-918F-CC33DADD7F07}` | – |

Anzeigenamen der Module (übersetzt): Lock, Light, Temperature/humidity sensor, Switch, Generic; Gateway „–“.

### Gerätemodule

- Formulare ohne `PopupButton` „Search device“; nur `DeviceID`, `LocalKey` (bei RGBW zusätzlich `Version`).
- Entfernt aus `TuyaGeneric`: `SearchModules`, `SetSelectedModul`, `readDeviceList` (`Tuya_SearchModules`, `Tuya_SetSelectedModul`).
- Übersetzungen der Suche aus den `locale.json` der Geräte entfernen.

## Fehlerbehandlung

| Situation | Verhalten |
|---|---|
| IO inaktiv | vorhandene Instanzen + Hinweis, keine Cloud-Abfrage |
| Cloud-Fehler / `success:false` | vorhandene Instanzen + Hinweis mit Meldung |
| Instanz ohne Gerät in der Cloud | eigene Zeile ohne `create` (rot) |
| doppelte Geräte-ID | erste Instanz in der Gerätezeile, weitere als eigene Zeilen |
| Instanz mit leerer Geräte-ID | nicht angezeigt |
| abweichende Konfiguration (Local Key, Version) | von Symcon grau dargestellt |

Kosten: 1 Cloud-Aufruf je Öffnen des Konfigurators.

## Tests (`php tests/run.php`)

Stub: `IPS_GetInstanceListByModuleID`, `IPS_GetProperty`, `IPS_GetName` über eine Instanz-Registry.

- Modulerkennung mit echten Datenpunkten: Lampe Standard, Lampe V2, Schloss, Sensor, Schalter, Gateway, Unbekannt → Generic.
- Zuordnung bestehender Instanzen über `DeviceID`, auch modulübergreifend; verwaiste Instanz; doppelte Geräte-ID; leere Geräte-ID.
- `create`: moduleID, configuration, name, location `["Tuya"]`.
- IO inaktiv und Cloud-Fehler: Instanzen sichtbar, Hinweis vorhanden, kein `create`.
- Konfigurator ignoriert Statuspakete des IO.
- Bestehende Konventionstests erweitert: Configurator in Übersetzungs- und Public-API-Test, keine Suche in Geräteformularen,
  GUIDs der bestehenden Module unverändert, Datenfluss-GUIDs des Konfigurators.

## Auslieferung

`library.json` Version `1.3`. README für den Konfigurator, Übersicht und Geräte-READMEs angepasst.
Auf dem Pi zu prüfen: Anlegen inklusive Kategorie „Tuya“, Sprung in bestehende Instanzen, Zeilenfarben.
