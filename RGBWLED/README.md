# TuyaLEDRGBW

WLAN RGB(W) Lampe mit Farbe und Farbtemperatur.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

- Ein/Aus, Helligkeit, Farbtemperatur, Farbe, Modus (Weiß, Farbe, Szene, Musik)
- Datenpunkte `switch_led`, `work_mode`, `bright_value`, `temp_value`, `colour_data`; bei Version *V2* die Varianten mit `_v2`
- Online-Status

Geprüfte Geräte:

| Gerät | Modell (Spalte *Modell* im Konfigurator) | Tuya Produktname | Kategorie | Version | Status |
|---|---|---|---|---|---|
| Hama RGB(W) GU10 WLAN (Art.-Nr. 176582/176598) | Meka GU10 RGBCW | 176582/176598 | dj | Standard | geprüft |
| Smart Bulb | ALS22L-N | – | dj | V2 | mit dieser Version des Moduls noch nicht geprüft |
| Avatar RGB Lampe E14 | – | E14蜡烛灯 | dj | unbekannt | geprüft (frühere Version des Moduls) |

Welche Version eine Lampe nutzt, zeigt die Tuya Developer Platform beim Gerät: Datenpunkte mit `_v2` am Ende (z. B. `bright_value_v2`) bedeuten *V2*.

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0
- eingerichtete Instanz [TuyaClient](../io/README.md)
- Gerät im mit dem Tuya Cloud-Projekt verknüpften App-Konto angelernt

### 3. Software-Installation

Über die Modulverwaltung die Bibliothek `https://github.com/captmajac/IPSTuya` hinzufügen (siehe [Übersicht](../README.md)).

### 4. Einrichten der Instanzen in IP-Symcon

Am einfachsten über den [TuyaConfigurator](../Configurator/README.md): Gerät auswählen und *Erstellen*. Die Instanz wird mit Geräte-ID, Local Key und Version angelegt. Alternativ unter *Instanz hinzufügen* das Modul **TuyaLEDRGBW** (Hersteller *Tuya*) auswählen und die Felder selbst ausfüllen.

| Eigenschaft | Beschreibung |
|---|---|
| Geräte-ID | ID des Geräts in der Tuya Cloud |
| Local Key | Lokaler Schlüssel des Geräts (für die Cloud-Steuerung nicht nötig, wird nur gespeichert) |
| Version | *Standard* oder *V2* (Datenpunkte mit `_v2`) |

Die Felder bleiben änderbar. Um eine Instanz einem anderen Gerät zuzuordnen, die Geräte-ID aus der Spalte *Geräte-ID* des Konfigurators kopieren und hier einfügen.

### 5. Statusvariablen und Profile

Die Namen werden je nach Spracheinstellung deutsch oder englisch angelegt. Die Variablen lassen sich umbenennen; ausschlaggebend ist der Ident.

| Ident | Name | Typ | Beschreibung |
|---|---|---|---|
| Power | Status | Boolean (`~Switch`) | Ein/Aus |
| Intensity | Helligkeit | Integer (`~Intensity.100`) | Helligkeit 0–100 % |
| Mode | Modus | Integer (Profil `Tuya.LightMode`) | 0 Weiß, 1 Farbe, 2 Szene, 3 Musik |
| ColorTemperature | Farbtemperatur | Integer (`~TWColor`) | 2700–6500 K |
| Color | Farbe | Integer (`~HexColor`) | Farbe (nur Schalten, wird nicht aus der Cloud zurückgelesen) |
| Online | Online | Boolean (Profil `Tuya.Online`) | Gerät laut Tuya Cloud erreichbar |

### 6. WebFront

Alle Variablen außer Online sind schaltbar. Helligkeit und Farbtemperatur schalten die Lampe in den Weiß-Modus.

### 7. PHP-Befehlsreferenz

Schalten über `RequestAction(int $VariablenID, mixed $Wert)` auf die Statusvariablen.

`void Tuya_RequestRefresh(int $InstanzID);`
Fordert eine Aktualisierung im TuyaClient an (höchstens ein Abruf pro Minute, sonst der Stand des letzten Durchlaufs).

`void Tuya_TimerEvent(int $InstanzID);`
Wie `Tuya_RequestRefresh`, für bestehende Skripte erhalten.

Hinweis: Die Zuordnung warm/kalt der Farbtemperatur ist noch nicht an allen Lampen geprüft.
