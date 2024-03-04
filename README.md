# IPSTuya
Zwei IPSymconModule für Tuya Cloud Geräte. Gerne fork machen für weitere integration 

Grundlage der Kommunikation war die Arbeit unter https://github.com/ground-creative/tuyapiphp
Diese wurde in eine lib Klasse kopiert und einige API URLs ergänzt.

Unterstützt und geprüft sind gerade zwei vorliegende Geräte. Ein BLE Türschloss eines chinesischen Anbieters und GU10 Wifi RGB Lampe von Hama. Die BLE Komponente sind mittels BLE/Wifi mit der Tuya Cloud verbunden.

Dieses Modul nutzt die API der Tuya Cloud. Daher nicht Cloud free und es wird ein Developer Account benötigt. Anleitungen wie man die notwendigen Account Informationen besorgt gibt es viele.

Die SocketKlasse stehht keine Verbidnung im Sinne IPSymcon her, sondern dient nur dazu die Verbindungsparameter einmalig bereit zu stellen. (hier könnte künftig eine bessere Integration erfolgen)

Benötigt aus der Cloud werden folgende Parameter: accessKey, secretKey, baseUrl, appId

In den Modulen kann über Geräte Suche die Liste in der Tuya Cloud registrierten Geräte angezeigt und ausgewählt werden. Dabei findet aktuell keine Typ Prüfung statt.
