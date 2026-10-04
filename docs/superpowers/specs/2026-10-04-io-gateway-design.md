# Paket B: TuyaClient (IO) als Gateway zur Tuya Cloud

Stand: 2026-10-04 · Branch `feature/io-gateway` (basiert auf `fix/review-bugs`)

## Ziel

- Cloud-Aufrufe pro Abfrage-Durchlauf drastisch senken (heute ca. 60 bei 15 Geräten).
- Das IO wird die einzige Stelle, die mit der Tuya Cloud spricht (Token, Timer, API-Aufrufe).
- Schloss-Öffnungslog bleibt in IP-Symcon erhalten, auch wenn die Cloud nichts mehr liefert.

## Feste Anforderungen

1. **Bestehende Instanzen laufen ohne Neuanlage weiter.** Unverändert bleiben:
   Modul-GUIDs, Datenfluss-GUIDs (`{C459F3BF-8570-E12D-9B2A-14F0343C7F37}` IO, `{018EF6B5-AB94-40C6-AA53-46943E824ACF}` Geräte),
   Eigenschaftsnamen (`AccessKey`, `SecretKey`, `BaseUrl`, `AppID`, `Interval`, `DeviceID`, `LocalKey`, `Version`),
   Variablen-Idents, Profile, Präfix `Tuya`.
2. Gerätemodule erben weiter von `TuyaGeneric`.
3. Nur Abfrage per Timer (kein Push / Tuya Message Service — eigenes späteres Paket).

## Nicht im Umfang

Push-Empfang (Pulsar), Konfigurator (Paket C), Rückrechnung `colour_data` → Farbvariable, Prüfung der V2-Lampen.

## Befund, der den Umbau trägt

`GET /v1.0/users/{AppID}/devices` liefert pro Gerät `online` **und** `status[]` mit allen Datenpunkten
(am System des Nutzers geprüft: Schlösser, V1- und V2-Lampen). Ein Aufruf ersetzt damit alle Einzelabfragen.

Ergebnis pro Durchlauf: 1 Aufruf für alle Geräte + 1 je online-Schloss für das Log (beim Nutzer 4 statt ~60).
Token-Abruf nur bei Ablauf.

## Architektur

### IO (`TuyaClient`)

- **Token-Cache:** Attribute `Token` und `TokenExpire` (Unix-Zeit). Neu holen, wenn leer oder < 60 s gültig.
  Meldet Tuya Token ungültig (`code` 1010 oder 1011), Token verwerfen, neu holen, Aufruf **einmal** wiederholen.
- **Timer `UpdateTimer`:** Intervall = Eigenschaft `Interval` (Minuten), 0 = aus.
  Start erst bei `KR_READY`; sonst Start über `MessageSink(IPS_KERNELSTARTED)`.
- **Durchlauf `Update()`:** `get_app_list(AppID)`, dann pro Gerät ein Paket an die Kinder.
- **Formular:** Schaltfläche „Jetzt aktualisieren“ (`Tuya_Update($id)`); AccessKey/SecretKey bleiben `PasswordTextBox`.
- **Status:** 102 aktiv; 104 inaktiv, wenn AccessKey/SecretKey/BaseUrl/AppID leer; 201 Cloud-Fehler beim letzten Durchlauf.
- Entfällt: öffentliches `getToken()`, `Send()`.

### Datenpakete

IO → Geräte (DataID `{018EF6B5-AB94-40C6-AA53-46943E824ACF}`), ein Paket je Gerät:

```json
{"DataID": "{018EF6B5-AB94-40C6-AA53-46943E824ACF}",
 "Buffer": {"type": "state", "id": "<device_id>", "online": true, "status": [{"code": "switch_led", "value": true}]}}
```

Geräte → IO (DataID `{C459F3BF-8570-E12D-9B2A-14F0343C7F37}`):

```json
{"DataID": "{C459F3BF-8570-E12D-9B2A-14F0343C7F37}",
 "Buffer": {"method": "post_commands", "params": ["<device_id>", {"commands": []}]}}
```

Zusätzlich: `{"method": "refresh"}` → IO führt `Update()` aus; `{"method": "config"}` → IO liefert `{"AppID": "…"}` (keine Schlüssel).

`ForwardData` gibt JSON zurück: bei Erfolg die Tuya-Antwort, bei Fehler `{"error": "<Meldung>"}`.
Erlaubt sind nur Methoden der `Devices`-Klasse der Lib (unbekannte lehnt `Caller` bereits ab) sowie `refresh` und `config`.

### Geräte (`TuyaGeneric` und Erben)

`TuyaGeneric`:
- `ApplyChanges`: Variable `Online`; `SetReceiveDataFilter` auf `"id":"<DeviceID>"` (leer bei leerer DeviceID → nichts empfangen).
- `ReceiveData`: setzt `Online`; bei online `applyStatus($state)` (Objekt mit `result`-Array wie bisher, damit `getDP` unverändert bleibt).
  Offline: nur `Online`, übrige Variablen bleiben.
- `api(string $method, ...$params)`: Paket an IO, Antwort dekodieren, bei `error` → `TuyaApiException`.
  Ohne aktives IO (`HasActiveParent()` false) → `TuyaApiException`.
- `CPost`, `getDP`, `SendDebug` bleiben. `SearchModules` / `readDeviceList` laufen über `api('get_app_list', AppID)`;
  die AppID kommt per `{"method": "config"}` vom IO (nur AppID, keine Schlüssel).
- `RequestRefresh()` (öffentlich, `Tuya_RequestRefresh`) → `api('refresh')`.
- Kompatibilität: Timer `UpdateTimer` bleibt mit Intervall 0 registriert; `TimerEvent()` bleibt und ruft `RequestRefresh()`.
- Entfällt: `getTuyaClass`, `getToken`, `getState`, `GetOnlineStatus`, `updateState`, `Send`, Lesen von `IPS_GetConfiguration` des IO.

Erben implementieren nur `applyStatus($state)` (bisheriger Inhalt von `updateState` ohne Cloud-Abfrage) und `RequestAction`.

### Schloss (`TuyaBLELock`)

- `applyStatus`: Motor, Ton, Batterie wie bisher, danach `refreshLog()`.
- `refreshLog()`: `get_openlogs` ab `max(letzter bekannter Zeitstempel + 1 ms, jetzt − 7 Tage)` bis jetzt, `page_size` 20.
- Attribut `LogEntries` (JSON-Liste `{t, code, value}`), Duplikate über `t` + `code` verworfen, neueste zuerst, maximal 50.
- HTML-Variable `Log` nur neu schreiben, wenn neue Einträge dazugekommen sind. Leere Cloud-Antwort ändert nichts.
- Öffnen: wie Paket A; `LogTimer` (15 s) ruft `refreshLog()` statt komplettem Update.

## Fehlerbehandlung

- IO-Durchlauf: Fehler → `IPS_LogMessage`, Status 201, kein Paket an Kinder. Nächster erfolgreicher Durchlauf → 102.
- `ReceiveData` wirft nie; Fehler beim Log-Abruf werden geloggt.
- `RequestAction`: `TuyaApiException` wird nicht abgefangen → Symcon zeigt die Meldung im WebFront/Konsole.

## Tests (`php tests/run.php`)

Stub erweitern: Attribute, `SetReceiveDataFilter`, `HasActiveParent`, Routing `SendDataToParent` → Fake-IO,
`SendDataToChildren` → Kinder mit Filter, `IPS_GetKernelRunlevel`.
Lib-Aufrufe im IO über eine überschreibbare Methode, damit Tests ohne Netz laufen.

Abzudecken:
- IO: Token wird zwischengespeichert, bei Ablauf und bei Code 1010 erneuert (einmalige Wiederholung).
- IO: ein Durchlauf = ein `get_app_list`, je Gerät ein Paket; Fehler → Status 201, keine Pakete.
- IO: Timer startet erst bei Kernel bereit.
- Geräte: nur eigene Pakete; offline → nur `Online`; Status-Zuordnung je Modul (bestehende Tests umgestellt).
- Geräte: Befehl läuft über IO; Fehlerantwort → `TuyaApiException`; ohne aktives IO → `TuyaApiException`.
- Schloss-Log: Anhängen, Duplikate, Begrenzung 50, leere Antwort lässt Log stehen, Abfrage ab letztem Eintrag.
- Kompatibilität: Eigenschaften und Variablen-Idents je Modul unverändert gegenüber `fix/review-bugs`.

## Auslieferung

`library.json` Version `1.2`. Test auf dem Raspberry Pi über die Modulverwaltung (Branch `feature/io-gateway`).
