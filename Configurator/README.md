# TuyaConfigurator

Konfigurator der Bibliothek: zeigt alle Geräte des mit dem Tuya Cloud-Projekt verknüpften App-Kontos, legt fehlende Instanzen mit dem passenden Modul an und ordnet vorhandene Instanzen zu.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

- Liste aller Geräte mit Name, Modell, passendem Modul, Online-Status und Geräte-ID (zum Kopieren)
- *Erstellen* legt die Instanz mit Geräte-ID, Local Key und bei Lampen der Version an, in der Kategorie „Tuya“
- Vorhandene Instanzen werden über ihre Geräte-ID erkannt, auch wenn sie mit einer früheren Version angelegt wurden; von dort lässt sich die Instanz öffnen
- Instanzen, deren Gerät es in der Cloud nicht mehr gibt, und doppelte Geräte-IDs werden als eigene Zeilen angezeigt und lassen sich löschen
- Ist der TuyaClient nicht aktiv oder die Cloud nicht erreichbar, werden die vorhandenen Instanzen trotzdem angezeigt, mit einem Hinweis

Das Modul wird aus den Datenpunkten des Geräts bestimmt (erste passende Regel):

| Merkmal | Modul |
|---|---|
| Datenpunkt `lock_motor_state` oder Kategorie `ms` | TuyaBLELock |
| Datenpunkt `switch_led`; Version *V2*, wenn Datenpunkte auf `_v2` enden | TuyaLEDRGBW |
| Datenpunkt `va_temperature` oder `va_humidity` | THSensor |
| Datenpunkt `switch_1` | TuyaSwitch |
| Gateway (Kategorie `wg`/`wg2` oder nur `up_channel`) | keine Instanz |
| alles andere | TuyaGeneric |

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0
- eingerichtete Instanz [TuyaClient](../io/README.md)

### 3. Software-Installation

Über die Modulverwaltung die Bibliothek `https://github.com/captmajac/IPSTuya` hinzufügen (siehe [Übersicht](../README.md)).

### 4. Einrichten der Instanzen in IP-Symcon

Unter *Instanz hinzufügen* das Modul **TuyaConfigurator** (Hersteller *Tuya*) auswählen. Die Instanz verbindet sich mit dem TuyaClient; Einstellungen gibt es keine.

Farben der Zeilen (Symcon):

| Farbe | Bedeutung |
|---|---|
| grün | Gerät ohne Instanz, kann erstellt werden |
| weiß | Instanz vorhanden und passend |
| grau | Instanz vorhanden, Einstellungen weichen ab (z. B. Local Key nach neuem Anlernen, falsche Version) |
| rot | Instanz ohne Gerät in der Cloud oder doppelte Geräte-ID |

Jedes Öffnen des Konfigurators kostet einen Cloud-Aufruf.

### 5. Statusvariablen und Profile

Keine.

### 6. WebFront

Keine Anzeige.

### 7. PHP-Befehlsreferenz

Keine eigenen Befehle.
