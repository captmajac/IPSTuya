# TuyaBLELock

Bluetooth-Türschloss, das über ein Tuya Bluetooth Gateway mit der Cloud verbunden ist.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

- Öffnen über IP-Symcon (Ticket-Verfahren der Tuya Cloud). Verriegeln ist über die Cloud nicht möglich, das Schloss verriegelt selbst; die Variable springt nach 2 Sekunden auf *verriegelt*.
- Dauerhaftes Öffnungsprotokoll: neue Einträge aus der Cloud werden angehängt, die letzten 50 bleiben erhalten, auch wenn die Cloud keine Einträge mehr liefert. Erfasst werden auch Öffnungen per PIN, Karte, Fingerabdruck und App.
- Batterie, Motorzustand, Signalton-Lautstärke, Meldung der letzten Öffnung
- Online-Status

Geprüfte Geräte:

| Gerät | Modell (Spalte *Modell* der Gerätesuche) | Tuya Produktname | Kategorie | Status |
|---|---|---|---|---|
| bluetooth smart lock | YSG_T83_RFID_7G | 智能门锁-T83 | ms | geprüft |

Das Schloss braucht ein Tuya Bluetooth Gateway. Das Gateway erscheint in der Gerätesuche als eigenes Gerät („Bluetooth gateway“), dafür wird keine Instanz benötigt.

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0
- eingerichtete Instanz [TuyaClient](../io/README.md)
- Gerät im mit dem Tuya Cloud-Projekt verknüpften App-Konto angelernt

### 3. Software-Installation

Über die Modulverwaltung die Bibliothek `https://github.com/captmajac/IPSTuya` hinzufügen (siehe [Übersicht](../README.md)).

### 4. Einrichten der Instanzen in IP-Symcon

Unter *Instanz hinzufügen* das Modul **TuyaBLELock** (Hersteller *Tuya*) auswählen. Die Instanz verbindet sich mit dem TuyaClient.

| Eigenschaft | Beschreibung |
|---|---|
| Geräte-ID | ID des Geräts in der Tuya Cloud |
| Local Key | Lokaler Schlüssel des Geräts (für die Cloud-Steuerung nicht nötig, wird nur gespeichert) |

**Gerät suchen** zeigt alle Geräte des verknüpften App-Kontos mit ID, Online-Status, Name und Modell; welches Modell zu welchem Modul passt, steht in der [Übersicht](../README.md#1-module). *Auswahl übernehmen* trägt Geräte-ID und Local Key ins Formular ein, gespeichert wird mit *Übernehmen*.

### 5. Statusvariablen und Profile

Die Namen werden je nach Spracheinstellung deutsch oder englisch angelegt. Die Variablen lassen sich umbenennen; ausschlaggebend ist der Ident.

| Ident | Name | Typ | Beschreibung |
|---|---|---|---|
| Lock | Schloss | Boolean (`~Lock`) | Öffnen durch Schalten auf *entriegelt* |
| Battery | Batterie | Integer (`~Battery.100`) | Batterie in % |
| Message | Meldung | String | Meldung der Cloud zur letzten Öffnung |
| MotorState | Motorstatus | Boolean (`~Lock.Reversed`) | Zustand des Schlossmotors |
| Sound | Lautstärke | String | Signalton-Lautstärke |
| Log | Öffnungsprotokoll | String (`~HTMLBox`) | letzte 50 Öffnungen |
| Online | Online | Boolean (Profil `Tuya.Online`) | Gerät laut Tuya Cloud erreichbar |

### 6. WebFront

Schloss schaltbar (nur Öffnen), übrige Variablen nur Anzeige.

### 7. PHP-Befehlsreferenz

Schalten über `RequestAction(int $VariablenID, mixed $Wert)` auf die Statusvariablen.

`void Tuya_RequestRefresh(int $InstanzID);`
Fordert eine Aktualisierung im TuyaClient an (höchstens ein Abruf pro Minute, sonst der Stand des letzten Durchlaufs).

`void Tuya_TimerEvent(int $InstanzID);`
Wie `Tuya_RequestRefresh`, für bestehende Skripte erhalten.

`void Tuya_RefreshLog(int $InstanzID);`
Liest neue Einträge des Öffnungsprotokolls aus der Cloud nach. Das passiert auch bei jedem Durchlauf des TuyaClient und 15 Sekunden nach einem Öffnen.
