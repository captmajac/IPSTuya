# IPSTuya

IP-Symcon Bibliothek für Geräte, die über die **Tuya Cloud** angebunden sind (Smart Life / Tuya App): Türschlösser, RGBW-Lampen, Schalter und Temperatur-/Feuchtesensoren.

Die Bibliothek nutzt die API der Tuya Cloud. Sie ist daher nicht cloudfrei und benötigt ein Cloud-Projekt auf der [Tuya Developer Platform](https://platform.tuya.com).

### Inhaltsverzeichnis

1. [Module](#1-module)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Aufbau und Cloud-Aufrufe](#4-aufbau-und-cloud-aufrufe)
5. [Verhalten bei Offline-Geräten und Fehlern](#5-verhalten-bei-offline-geräten-und-fehlern)
6. [Bekannte Einschränkungen](#6-bekannte-einschränkungen)
7. [Entwicklung und Tests](#7-entwicklung-und-tests)
8. [Änderungen](#8-änderungen)

### 1. Module

| Modul | Beschreibung |
|---|---|
| [TuyaClient](io/README.md) | IO-Instanz: Verbindung zur Tuya Cloud, fragt alle Geräte ab und verteilt den Status |
| [TuyaBLELock](BLTGWLock/README.md) | Bluetooth-Türschloss über Tuya Bluetooth Gateway, mit dauerhaftem Öffnungsprotokoll |
| [TuyaLEDRGBW](RGBWLED/README.md) | WLAN RGB(W) Lampe: Ein/Aus, Helligkeit, Farbtemperatur, Farbe, Modus |
| [TuyaSwitch](Switch/README.md) | Schaltaktor, 1 Kanal |
| [THSensor](THSensor/README.md) | Temperatur- und Luftfeuchtesensor |
| [TuyaGeneric](Generic/README.md) | Basis aller Gerätemodule, als Instanz nur mit Online-Status |

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0
- Cloud-Projekt auf der Tuya Developer Platform mit verknüpftem App-Konto (Smart Life oder Tuya Smart), in dem die Geräte angelernt sind
- Internetzugang des IP-Symcon Servers

### 3. Software-Installation

Über die Modulverwaltung folgende URL hinzufügen:

```
https://github.com/captmajac/IPSTuya
```

Danach eine Instanz **TuyaClient** anlegen und einrichten, anschließend die Geräte-Instanzen. Diese verbinden sich automatisch mit dem TuyaClient.

### 4. Aufbau und Cloud-Aufrufe

Der TuyaClient (IO) ist die einzige Verbindung zur Tuya Cloud. Er hält das Zugriffstoken, fragt im eingestellten Intervall mit **einem** Aufruf alle Geräte samt Status ab und verteilt das Ergebnis an die Geräte-Instanzen. Schaltbefehle der Geräte laufen ebenfalls über den TuyaClient.

Pro Durchlauf: 1 Aufruf für alle Geräte, dazu 1 Aufruf je Türschloss (Öffnungsprotokoll). Das Token wird nur bei Ablauf (ca. alle 2 Stunden) erneuert. Den Verbrauch zeigt die Tuya Developer Platform unter *Cloud → Data Statistics → API Statistics*.

### 5. Verhalten bei Offline-Geräten und Fehlern

- Ist ein Gerät laut Tuya offline, wird nur die Variable *Online* aktualisiert, die übrigen Werte bleiben stehen.
- Befehle an ein offline Gerät (z. B. Lampe am ausgeschalteten Wandschalter in einer Szene) werden ohne Fehlermeldung ignoriert und nur im Debug vermerkt. Dabei prüft der TuyaClient den Stand mit einem schlanken Abruf (nur Geräteliste, höchstens einmal pro Minute). Ist das Gerät wieder online, werden Befehle wieder ausgeführt.
- Andere Fehler (Ablehnung durch Tuya, keine Verbindung, IO inaktiv) werden nicht an den Aufrufer (WebFront, Skript, Szene) weitergegeben. Sie stehen im Debug und je Fehler einmal im Meldungsfenster; der Wert der Variable bleibt unverändert.
- Das Debug des TuyaClient zeigt je Durchlauf eine Zeile pro Gerät, ohne Local Key, IP-Adresse und Standort.

### 6. Bekannte Einschränkungen

- Keine Push-Benachrichtigungen: Änderungen über die Tuya App oder am Gerät kommen erst mit der nächsten Abfrage in IP-Symcon an.
- Statt der Gerätesuche je Instanz wäre eine Konfigurator-Instanz komfortabler.
- Bei der Gerätesuche findet keine Typprüfung statt.

### 7. Entwicklung und Tests

Grundlage der Kommunikation ist [tuyapiphp](https://github.com/ground-creative/tuyapiphp), übernommen nach `libs/TuyaAPI.php` und um weitere API-Aufrufe ergänzt. Gerne forken für weitere Geräte.

Die Tests laufen ohne IP-Symcon gegen einen Stub:

```
php tests/run.php
```

### 8. Änderungen

**1.2**
- TuyaClient fragt zentral ab, Token-Cache, deutlich weniger Cloud-Aufrufe
- Dauerhaftes Öffnungsprotokoll der Türschlösser
- Offline-Geräte und Fehler blockieren keine Szenen mehr
- Oberfläche englisch mit deutscher Übersetzung
- Gerätesuche trägt die Auswahl nur ins Formular ein, die Instanz wird nicht mehr umbenannt
- Entfallene Funktionen: `Tuya_getToken`, `Tuya_getTuyaClass`, `Tuya_getState`, `Tuya_updateState`, `Tuya_GetOnlineStatus`, `Tuya_readLockLog`, `Tuya_readDeviceList`, `Tuya_CPost`, `Tuya_unlock`, `Tuya_setDefaults`
