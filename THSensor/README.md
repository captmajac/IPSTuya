# THSensor

Temperatur- und Luftfeuchtesensor. Erstellt und geprüft durch thka; Modellname unbekannt.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

- Temperatur (`va_temperature`, Tuya liefert Zehntelgrad)
- Luftfeuchtigkeit (`va_humidity`)
- Batteriezustand (`battery_state`)
- Online-Status

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0
- eingerichtete Instanz [TuyaClient](../io/README.md)
- Gerät im mit dem Tuya Cloud-Projekt verknüpften App-Konto angelernt

### 3. Software-Installation

Über die Modulverwaltung die Bibliothek `https://github.com/captmajac/IPSTuya` hinzufügen (siehe [Übersicht](../README.md)).

### 4. Einrichten der Instanzen in IP-Symcon

Unter *Instanz hinzufügen* das Modul **THSensor** (Hersteller *Tuya*) auswählen. Die Instanz verbindet sich mit dem TuyaClient.

| Eigenschaft | Beschreibung |
|---|---|
| Geräte-ID | ID des Geräts in der Tuya Cloud |
| Local Key | Lokaler Schlüssel des Geräts (für die Cloud-Steuerung nicht nötig, wird nur gespeichert) |

**Gerät suchen** zeigt alle Geräte des verknüpften App-Kontos. *Auswahl übernehmen* trägt Geräte-ID und Local Key ins Formular ein, gespeichert wird mit *Übernehmen*.

### 5. Statusvariablen und Profile

Die Namen werden je nach Spracheinstellung deutsch oder englisch angelegt. Die Variablen lassen sich umbenennen; ausschlaggebend ist der Ident.

| Ident | Name | Typ | Beschreibung |
|---|---|---|---|
| Temperatur | Temperatur | Float (`~Temperature`) | Temperatur in °C |
| Humidity | Luftfeuchtigkeit | Float (`~Humidity.F`) | relative Luftfeuchte in % |
| Battery | Batterie | String | Batteriezustand laut Tuya (z. B. `high`, `low`) |
| Online | Online | Boolean (Profil `Tuya.Online`) | Gerät laut Tuya Cloud erreichbar |

### 6. WebFront

Nur Anzeige.

### 7. PHP-Befehlsreferenz

Schalten über `RequestAction(int $VariablenID, mixed $Wert)` auf die Statusvariablen.

`void Tuya_RequestRefresh(int $InstanzID);`
Fordert eine Aktualisierung im TuyaClient an (höchstens ein Abruf pro Minute, sonst der Stand des letzten Durchlaufs).

`void Tuya_TimerEvent(int $InstanzID);`
Wie `Tuya_RequestRefresh`, für bestehende Skripte erhalten.
