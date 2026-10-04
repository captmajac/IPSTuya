# IO-Gateway (Paket B) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** TuyaClient (IO) wird die einzige Stelle mit Cloud-Zugriff; ein Durchlauf versorgt alle Geräte mit einem Aufruf; Schloss-Log wird dauerhaft gehalten.

**Architecture:** IO hält Token-Cache und Timer, holt `get_app_list` und schickt je Gerät ein Paket per `SendDataToChildren`. Geräte filtern per `SetReceiveDataFilter`, werten in `applyStatus()` aus und rufen die Cloud nur über `TuyaGeneric::api()` → `SendDataToParent` → `TuyaClient::ForwardData`.

**Tech Stack:** PHP 8.3 (lokal `../tools/php/php`), IP-Symcon 7 SDK, Test-Stub `tests/stubs.php`, Runner `tests/run.php`.

**Spec:** `docs/superpowers/specs/2026-10-04-io-gateway-design.md`

## Global Constraints

- GUIDs unverändert: IO-Modul `{78ABC644-1134-F4E2-3E31-01E45483367B}`, Geräte→IO DataID `{C459F3BF-8570-E12D-9B2A-14F0343C7F37}`, IO→Geräte DataID `{018EF6B5-AB94-40C6-AA53-46943E824ACF}`.
- Eigenschaften, Variablen-Idents, Profile und Präfix `Tuya` unverändert.
- Gerätemodule erben von `TuyaGeneric`.
- Kein Push, kein Konfigurator.
- `<?php`, LF, `$this->SetValue`, Fehler aus der Lib als `TuyaApiException`.

## Entscheidungen, die die Spec präzisieren

1. **Status 201 nur bei Token-/Anmeldefehler**, nicht bei einzelnem fehlgeschlagenem Durchlauf. Grund: Symcon lässt `SendDataToParent` an ein nicht aktives IO nicht zu — ein kurzer Netzfehler würde sonst alle Schaltbefehle bis zum nächsten erfolgreichen Durchlauf sperren. Netzfehler im Durchlauf: nur Log + Debug.
2. **`ForwardData` dekodiert mit `json_decode(..., true)`**, weil `Caller` Payloads nur als PHP-Array erkennt.
3. **Log-Zeitraum in Sekunden** (`start_time`/`end_time`) wie im bisherigen, funktionierenden Code; `update_time` der Einträge ist in Millisekunden.

## Review Focus

- Gerät mit leerer `DeviceID` darf keine Pakete anderer Geräte übernehmen → Test in Task 2.
- Cloud-Antwort `success:false` beim Durchlauf (z. B. falsche AppID) darf nicht als leere Geräteliste verteilt werden → Test in Task 1.
- Token-Fehler 1010 darf nur **einmal** wiederholt werden (keine Schleife) → Test in Task 1.
- Schloss offline → kein Log-Abruf → Test in Task 3.
- Log-Einträge ohne `status` (unvollständige Cloud-Antwort) dürfen den Durchlauf nicht abbrechen → Test in Task 3.

---

### Task 1: IO als Gateway

**Files:**
- Modify: `io/module.php`, `io/form.json`, `tests/stubs.php`, `tests/run.php`
- Create: `tests/test_io.php`

**Interfaces:**
- Produces (TuyaClient):
  - `public function Update(): void` — Durchlauf, als `Tuya_Update($id)` und vom Timer `UpdateTimer`.
  - `public function ForwardData($JSONString): string` — Buffer `{"method": string, "params": array}`; `refresh` → `Update()` und `{"success":true}`; `config` → `{"AppID": string}`; sonst Lib-Antwort als JSON, Fehler als `{"error": string}`.
  - `protected function request(string $token, string $method, array $params)` — einzige Stelle mit `TuyaApi`-Aufruf (Testnaht).
  - `protected function requestToken()` — `TuyaApi->token->get_new()` (Testnaht).
  - Paket an Kinder: `{"DataID":"{018EF6B5-…}","Buffer":{"type":"state","id":string,"online":bool,"status":array}}`.
- Produces (Stub): `IPSModule::$testParent`, `IPSModule::$testChildren`, `RegisterAttribute*/ReadAttribute*/WriteAttribute*`, `SetReceiveDataFilter` (speichert in `$receiveFilter`), `HasActiveParent()`, `RegisterMessage`, Konstanten `KR_READY`, `IPS_KERNELSTARTED`, `IPS_GetKernelRunlevel()` über `TestRegistry::$runlevel`.

- [ ] **Step 1: Stub erweitern und Tests in Dateien aufteilen.** `tests/run.php` bindet `tests/test_*.php` ein (je Datei `$tests[...] = fn`). `SendDataToParent` ruft `$this->testParent->ForwardData($Data)`; `SendDataToChildren` ruft `ReceiveData($Data)` bei jedem Kind, dessen `$receiveFilter` leer ist oder per `preg_match('/'.$filter.'/', $Data)` passt. Bestehende Tests nach `tests/test_devices.php` verschieben; Lauf: 16/16.

- [ ] **Step 2: Fehlschlagende IO-Tests in `tests/test_io.php`** mit `FakeIO extends TuyaClient` (zählt `request`/`requestToken`-Aufrufe, liefert vorgegebene Antworten):
  - `IO: Token wird zwischengespeichert` — zwei `ForwardData`-Aufrufe → `requestToken` 1x.
  - `IO: abgelaufenes Token wird erneuert` — `TokenExpire` = jetzt + 30 → neues Token.
  - `IO: Code 1010 erneuert Token und wiederholt einmal` — erste Antwort `{"success":false,"code":1010}`, zweite ok → 2 Tokens, 2 Requests; bei zweimal 1010 → genau 2 Requests, Antwort mit `success:false` zurück.
  - `IO: Durchlauf = ein get_app_list, ein Paket je Gerät` — 3 Geräte → 1 Request, 3 empfangene Pakete mit `id`, `online`, `status`.
  - `IO: success:false beim Durchlauf verteilt nichts` — 0 Pakete, Eintrag im Log.
  - `IO: Token-Fehler setzt Status 201`, `IO: fehlende Zugangsdaten setzen Status 104`.
  - `IO: Timer startet erst bei Kernel bereit` — Runlevel ≠ `KR_READY` → Intervall 0; `MessageSink(0, IPS_KERNELSTARTED, [])` → `Interval * 60000`.
  - `IO: ForwardData config liefert nur AppID` — Antwort enthält keine `AccessKey`/`SecretKey`.

- [ ] **Step 3: Lauf → neue Tests FAIL** (`../tools/php/php tests/run.php`).

- [ ] **Step 4: `io/module.php` umbauen.** Attribute `Token` (string), `TokenExpire` (int, aus `result->expire_time` Sekunden). Token neu bei leer oder `TokenExpire < time() + 60`. Codes 1010/1011 → Token leeren, einmal wiederholen. `ApplyChanges`: `RegisterMessage(0, IPS_KERNELSTARTED)`; fehlende AccessKey/SecretKey/BaseUrl/AppID → `SetStatus(104)`, Timer 0; sonst 102 und Timer starten wenn `IPS_GetKernelRunlevel() == KR_READY`. Entfernen: `getToken()`, `getTuyaClass()`, `Send()`. `io/form.json`: Aktion `{"type":"Button","caption":"Jetzt aktualisieren","onClick":"Tuya_Update($id);"}`.

- [ ] **Step 5: Lauf → alle Tests PASS, keine Warnings** (`-d error_reporting=-1`).

- [ ] **Step 6: Commit** `IO: token cache, central update timer, ForwardData gateway`.

### Task 2: Geräte über das IO

**Files:**
- Modify: `Generic/module.php`, `Switch/module.php`, `THSensor/module.php`, `RGBWLED/module.php`, `BLTGWLock/module.php`, `tests/test_devices.php`

**Interfaces:**
- Consumes: Paketformat und `ForwardData` aus Task 1.
- Produces (TuyaGeneric):
  - `protected function api(string $method, ...$params)` — liefert dekodiertes Objekt; wirft `TuyaApiException` bei `error`, leerer Antwort oder `!HasActiveParent()`.
  - `public function ReceiveData($JSONString)` — setzt `Online`; online → `applyStatus((object)['result' => $status])`; fängt `TuyaApiException` und loggt.
  - `protected function applyStatus($state): void` — leer in der Basis, von Erben überschrieben.
  - `public function CPost(array $payload): bool`, `public function readDeviceList(): array`, `public function RequestRefresh(): void`, `public function TimerEvent(): void` (ruft `RequestRefresh`).
  - Filter: `'.*"id":"' . preg_quote($DeviceID) . '".*'`; leere DeviceID → `'^$'` (passt nie).
- Entfernt: `getTuyaClass`, `getToken`, `getState`, `GetOnlineStatus`, `updateState`, `Send`.

- [ ] **Step 1: Gerätetests auf Fake-IO umstellen** (`make()` verbindet Instanz mit `FakeIO`, Status per Paket statt `getState`): alle bisherigen Erwartungen bleiben. Neu:
  - `Generic: nur eigene Pakete` — Paket für `dev2` ändert `dev1` nicht.
  - `Generic: leere DeviceID empfängt nichts`.
  - `Generic: offline setzt nur Online` — `Power` bleibt unverändert, `Online` = false.
  - `Generic: Befehl läuft über das IO` — `RequestAction('Power', true)` → FakeIO sieht `post_commands` mit `["dev1", {"commands":[{"code":"switch_1","value":true}]}]`.
  - `Generic: Fehlerantwort wirft TuyaApiException`, `Generic: ohne aktives IO wirft TuyaApiException`.
  - `Generic: TimerEvent löst Durchlauf im IO aus`.
  - Schloss: `Entsperren läuft über das IO` — `post_password_ticket`, dann `post_remote_unlocking` mit `ticket_id`.
- [ ] **Step 2: Lauf → neue Tests FAIL.**
- [ ] **Step 3: `TuyaGeneric` umbauen**, Erben: `updateState()` → `protected function applyStatus($state): void` ohne `parent::`-Aufruf und ohne Cloud; Schloss: `unlock()` über `api()`, `applyStatus` setzt Motor/Ton/Batterie. Timer `UpdateTimer` bleibt registriert, `ApplyChanges` setzt ihn auf 0.
- [ ] **Step 4: Lauf → alle PASS, keine Warnings.**
- [ ] **Step 5: Commit** `Devices: receive state from IO, send commands via ForwardData`.

### Task 3: Dauerhaftes Schloss-Log

**Files:**
- Modify: `BLTGWLock/module.php`
- Create: `tests/test_lock.php` (Schloss-Tests aus `test_devices.php` hierher)

**Interfaces:**
- Consumes: `api()`, `applyStatus()` aus Task 2.
- Produces: `public function RefreshLog(): void` (`Tuya_RefreshLog`), Attribut `LogEntries` (JSON-Liste `{"t": int ms, "code": string, "value": mixed}`, neueste zuerst, max. 50). `LogEvent()` ruft `RefreshLog()`.

- [ ] **Step 1: Fehlschlagende Tests:**
  - `Log: neue Einträge werden angehängt` — 2 Einträge, dann 1 neuer → 3 im Attribut, HTML enthält alle 3.
  - `Log: Duplikate werden verworfen` — gleicher `t`+`code` zweimal → 1 Eintrag.
  - `Log: höchstens 50 Einträge` — 60 Einträge → 50, neuester zuerst.
  - `Log: leere Antwort lässt Log stehen` — Log mit Inhalt, Antwort ohne Logs → Variable und Attribut unverändert.
  - `Log: Abfrage ab letztem Eintrag` — letzter `t` = 1700000000000 → `start_time` = 1700000000; leeres Log → `start_time` ≥ jetzt − 7 Tage.
  - `Log: Schloss offline → kein Abruf`.
  - `Log: Eintrag ohne status bricht nicht ab` — wird übersprungen.
  - `Log: Status-Paket für Online-Schloss liest Log`.
- [ ] **Step 2: Lauf → FAIL.**
- [ ] **Step 3: Implementieren.** `get_openlogs(DeviceID, ['page_no'=>0,'page_size'=>20,'start_time'=>…,'end_time'=>time()])`; HTML wie bisher `d.m.Y H:i:s - code<br>`; Variable nur schreiben, wenn neue Einträge dazukamen. `readLockLog` entfällt.
- [ ] **Step 4: Lauf → alle PASS.**
- [ ] **Step 5: Commit** `Lock: keep open log persistently`.

### Task 4: Kompatibilität und Auslieferung

**Files:**
- Modify: `library.json`, `README.md`
- Create: `tests/test_compat.php`

- [ ] **Step 1: Test `Kompatibilität: Eigenschaften und Idents unverändert`** — je Modul nach `Create()`+`ApplyChanges()` exakt:
  IO `AccessKey, SecretKey, BaseUrl, AppID, Interval`; Generic-Erben `DeviceID, LocalKey` (+ RGBW `Version`);
  Idents Switch `Online, Power`; THSensor `Online, Temperatur, Humidity, Battery`; RGBW `Online, Power, Mode, Intensity, ColorTemperature, Color`; Lock `Online, Lock, Message, MotorState, Battery, Sound, Log`.
- [ ] **Step 2: Lauf → PASS** (sonst Ursache beheben, nicht den Test).
- [ ] **Step 3:** `library.json` `"version": "1.2"`; README: Architektur (IO fragt zentral ab), neue Funktionen `Tuya_Update`, `Tuya_RequestRefresh`, `Tuya_RefreshLog`, entfallene Funktionen.
- [ ] **Step 4:** Alle Tests, `php -l` auf alle PHP-Dateien.
- [ ] **Step 5: Commit und Push** `feature/io-gateway`.
