# TuyaClient

IO-Instanz der Bibliothek: einzige Verbindung zur Tuya Cloud.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

- Anmeldung an der Tuya Cloud, das Zugriffstoken wird zwischengespeichert und nur bei Ablauf erneuert
- Abfrage aller Geräte samt Status mit einem Aufruf im eingestellten Intervall, Verteilung an die Geräte-Instanzen
- Ausführen der Befehle der Geräte-Instanzen
- Start der Abfrage erst, wenn IP-Symcon vollständig gestartet ist

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0
- Cloud-Projekt auf der [Tuya Developer Platform](https://platform.tuya.com) mit verknüpftem App-Konto (Smart Life oder Tuya Smart)

### 3. Software-Installation

Über die Modulverwaltung die Bibliothek `https://github.com/captmajac/IPSTuya` hinzufügen (siehe [Übersicht](../README.md)).

### 4. Einrichten der Instanzen in IP-Symcon

Unter *Instanz hinzufügen* das Modul **TuyaClient** (Hersteller *Tuya*) auswählen.

| Eigenschaft | Beschreibung |
|---|---|
| Access ID | *Access ID / Client ID* des Cloud-Projekts (Overview des Projekts) |
| Access Secret | *Access Secret / Client Secret* des Cloud-Projekts |
| Basis-URL | Rechenzentrum des Projekts, z. B. `https://openapi.tuyaeu.com` (Europa), `https://openapi.tuyaus.com` (Amerika), `https://openapi.tuyacn.com` (China), `https://openapi.tuyain.com` (Indien) |
| App-ID | User-ID (UID) des verknüpften App-Kontos (*Devices → Link App Account*) |
| Aktualisierungsintervall | Minuten zwischen zwei Abfragen, 0 = keine automatische Abfrage. Standard 15 |

*Jetzt aktualisieren* fragt sofort alle Geräte ab.

| Status | Bedeutung |
|---|---|
| 102 | aktiv |
| 104 | Zugangsdaten unvollständig |
| 201 | Anmeldung an der Tuya Cloud fehlgeschlagen |

### 5. Statusvariablen und Profile

Keine.

### 6. WebFront

Keine Anzeige.

### 7. PHP-Befehlsreferenz

`void Tuya_Update(int $InstanzID);`
Fragt sofort alle Geräte ab und verteilt den Status an die Geräte-Instanzen.
