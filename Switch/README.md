# TuyaSwitch

Schaltaktor mit einem Kanal. Erstellt und geprüft durch thka; Modellname unbekannt.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

- Ein- und Ausschalten (Datenpunkt `switch_1`)
- Online-Status

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0
- eingerichtete Instanz [TuyaClient](../io/README.md)
- Gerät im mit dem Tuya Cloud-Projekt verknüpften App-Konto angelernt

### 3. Software-Installation

Über die Modulverwaltung die Bibliothek `https://github.com/captmajac/IPSTuya` hinzufügen (siehe [Übersicht](../README.md)).

### 4. Einrichten der Instanzen in IP-Symcon

Am einfachsten über den [TuyaConfigurator](../Configurator/README.md): Gerät auswählen und *Erstellen*. Die Instanz wird mit Geräte-ID, Local Key angelegt. Alternativ unter *Instanz hinzufügen* das Modul **TuyaSwitch** (Hersteller *Tuya*) auswählen und die Felder selbst ausfüllen.

| Eigenschaft | Beschreibung |
|---|---|
| Geräte-ID | ID des Geräts in der Tuya Cloud |
| Local Key | Lokaler Schlüssel des Geräts (für die Cloud-Steuerung nicht nötig, wird nur gespeichert) |

Die Felder bleiben änderbar. Um eine Instanz einem anderen Gerät zuzuordnen, die Geräte-ID aus der Spalte *Geräte-ID* des Konfigurators kopieren und hier einfügen.

### 5. Statusvariablen und Profile

Die Namen werden je nach Spracheinstellung deutsch oder englisch angelegt. Die Variablen lassen sich umbenennen; ausschlaggebend ist der Ident.

| Ident | Name | Typ | Beschreibung |
|---|---|---|---|
| Power | Status | Boolean (`~Switch`) | Schaltzustand |
| Online | Online | Boolean (Profil `Tuya.Online`) | Gerät laut Tuya Cloud erreichbar |

### 6. WebFront

Status schaltbar, Online nur Anzeige.

### 7. PHP-Befehlsreferenz

Schalten über `RequestAction(int $VariablenID, mixed $Wert)` auf die Statusvariablen.

`void Tuya_RequestRefresh(int $InstanzID);`
Fordert eine Aktualisierung im TuyaClient an (höchstens ein Abruf pro Minute, sonst der Stand des letzten Durchlaufs).

`void Tuya_TimerEvent(int $InstanzID);`
Wie `Tuya_RequestRefresh`, für bestehende Skripte erhalten.
