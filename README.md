# IPSTuya
IPSymcon Module für Tuya Cloud Geräte (Türschloss, RGBW Lampe, Schalter, Temperatur/Feuchte Sensor). Gerne fork machen für weitere integration 

Grundlage der Kommunikation war die Arbeit unter https://github.com/ground-creative/tuyapiphp
Diese wurde in eine lib Klasse kopiert und einige API URLs ergänzt.

Unterstützt und geprüft sind gerade zwei vorliegende Geräte. Ein BLE Türschloss eines chinesischen Anbieters und GU10 Wifi RGB Lampe von Hama. Die BLE Komponente sind mittels BLE/Wifi mit der Tuya Cloud verbunden.

Dieses Modul nutzt die API der Tuya Cloud. Daher nicht Cloud free und es wird ein Developer Account benötigt. Anleitungen wie man die notwendigen Account Informationen besorgt gibt es viele.

Die IO Instanz (TuyaClient) ist die einzige Verbindung zur Tuya Cloud. Benötigt aus der Cloud werden folgende Parameter: accessKey, secretKey, baseUrl, appId.
Das IO hält das Token, fragt im eingestellten Intervall (Minuten) mit einem einzigen Aufruf alle Geräte samt Status ab und verteilt das Ergebnis an die Geräte Instanzen. Schaltbefehle der Geräte laufen ebenfalls über das IO.
Pro Durchlauf: 1 Cloud Aufruf für alle Geräte, dazu 1 je online Türschloss für das Öffnungslog.

In den Modulen kann über Geräte Suche die Liste in der Tuya Cloud registrierten Geräte angezeigt und ausgewählt werden. Dabei findet aktuell keine Typ Prüfung statt.

Ist ein Gerät laut Cloud offline, wird nur die Variable Online aktualisiert, die übrigen Werte bleiben stehen.
Schaltbefehle an ein offline Gerät (z. B. Lampe am ausgeschalteten Wandschalter in einer Szene) werden ohne Fehlermeldung ignoriert und nur im Debug vermerkt. Dabei prüft das IO den Stand mit einem schlanken Abruf (nur Geräteliste, ohne Öffnungslogs, höchstens einmal pro Minute). Ist das Gerät wieder online, werden Befehle wieder ausgeführt. Andere Ablehnungen von Tuya erscheinen als Fehler.
Das Debug des IO zeigt pro Durchlauf eine Zeile je Gerät (Name, online/offline, Anzahl Datenpunkte), ohne local_key, IP und Standort; das Öffnungslog als eine Zeile je Schloss.
Das Öffnungslog der Türschlösser wird in IP-Symcon dauerhaft gehalten (die letzten 50 Einträge), auch wenn die Cloud keine Einträge mehr liefert.

Funktionen:
- `Tuya_Update($IO_ID)` sofort alle Geräte aktualisieren (auch Schaltfläche im IO)
- `Tuya_RequestRefresh($ID)` bzw. `Tuya_TimerEvent($ID)` an einer Geräte Instanz löst ebenfalls einen Durchlauf im IO aus
- `Tuya_RefreshLog($ID)` Öffnungslog eines Türschlosses nachlesen

Seit Version 1.2 entfallen: `Tuya_getToken`, `Tuya_getTuyaClass`, `Tuya_getState`, `Tuya_updateState`, `Tuya_GetOnlineStatus`, `Tuya_readLockLog`. `Tuya_readDeviceList($ID)` hat keine Parameter mehr.

known issues:
- Der Status von Modulen ist auch nur als one direction. Also Aktionen über die Tuya App kommen erst mit der nächsten Abfrage nach IPS.
- Anstelle der Geräte Suche wäre eine Konfigurator Instanz die bessere Wahl 

Tests (ohne IP-Symcon, mit Stub):
```
php tests/run.php
```
