<?php
//Tuya Klassen einbinden
include_once __DIR__ . "/../libs/TuyaAPI.php";

// Basis aller Tuya Geraete: Status kommt per Paket vom IO (TuyaClient),
// Cloud-Aufrufe laufen ueber api() -> SendDataToParent -> TuyaClient::ForwardData
class TuyaGeneric extends IPSModule
{
    const PARENT_DATAID = '{C459F3BF-8570-E12D-9B2A-14F0343C7F37}';
    const DEVICE_OFFLINE = 2001;        // tuya: device is offline

    // erstellung
    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // tuya socket notwendig für die parameter
        $this->ConnectParent('{78ABC644-1134-F4E2-3E31-01E45483367B}');

        $this->CreateVarProfileModus();
        $this->RegisterPropertyString("DeviceID", "");
        $this->RegisterPropertyString("LocalKey", "");

        // nur noch fuer bestehende aufrufe von Tuya_TimerEvent, abgefragt wird zentral im IO
        $Module = json_decode(file_get_contents(__DIR__ . "/module.json"), true)["prefix"];
        $this->RegisterTimer("UpdateTimer", 0, $Module . "_TimerEvent(\$_IPS['TARGET']);");
    }

    // changes der instanz
    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();

        $this->RequireParent('{78ABC644-1134-F4E2-3E31-01E45483367B}');

        $this->RegisterVariableBoolean("Online", "Online", "Tuya.Online", 100);
        $this->SetTimerInterval("UpdateTimer", 0);

        // nur pakete fuer das eigene geraet empfangen, der Buffer ist ein JSON-String: \"id\":\"<DeviceID>\"
        $device_id = $this->ReadPropertyString("DeviceID");
        $this->SetReceiveDataFilter($device_id === "" ? '^$' : '.*\\\\"id\\\\":\\\\"' . preg_quote($device_id, '/') . '\\\\".*');
    }

    // status paket vom IO: {"type":"state","id":..,"online":..,"status":[..]}
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $buffer = json_decode($data->Buffer ?? '');
        if (($buffer->type ?? '') !== 'state' || ($buffer->id ?? '') !== $this->ReadPropertyString("DeviceID")) {
            return;
        }

        $this->SetValue("Online", (bool) $buffer->online);
        if (!$buffer->online) {
            return;     // status eines offline geraets ist veraltet
        }

        try {
            $this->applyStatus((object) ['result' => $buffer->status]);
        } catch (TuyaApiException $e) {
            IPS_LogMessage("TuyaDevice", "Update Error Device=" . $buffer->id . ": " . $e->getMessage());
        }
    }

    // geraete spezifische auswertung der datenpunkte, wird von den modulen ueberschrieben
    protected function applyStatus($state)
    {
    }

    // cloud aufruf ueber das IO
    protected function api(string $method, ...$params)
    {
        if (!$this->HasActiveParent()) {
            throw new TuyaApiException("TuyaClient (IO) is not active");
        }
        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::PARENT_DATAID,
            'Buffer' => json_encode(['method' => $method, 'params' => $params]),     // Symcon verlangt Buffer als String
        ]));
        $return = json_decode((string) $response);
        if ($return === null) {
            throw new TuyaApiException("No response from TuyaClient (IO)");
        }
        if (isset($return->error)) {
            throw new TuyaApiException($return->error);
        }
        return $return;
    }

    // default debug message
    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_array($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
            }
        } elseif (is_object($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . "." . $Key, $DebugData, 0);
            }
        } else {
            parent::SendDebug($Message, $Data, $Format);
        }
    }

    // search device
    public function SearchModules()
    {
        $jsValues = json_encode($this->readDeviceList());
        $this->SetBuffer("List", $jsValues);
        $this->UpdateFormField("Devices", "values", $jsValues);
    }

    // auswahl aus der search liste
    public function SetSelectedModul(object $List)
    {
        @$DevID = $List["ID"]; // Kommt ein Error bei keiner Auswahl
        @$LocalKey = $List["LocalKey"]; // Kommt ein Error bei keiner Auswahl
        @$Name = $List["Name"]; // Kommt ein Error bei keiner Auswahl
        $this->SetBuffer("List", "");

        if ($DevID != null) {
            IPS_SetProperty($this->InstanceID, "DeviceID", "" . $DevID);
            IPS_SetProperty($this->InstanceID, "LocalKey", "" . $LocalKey);
        }
        $oldname = IPS_GetName($this->InstanceID);
        $pos = strpos($oldname, "(");
        if ($pos <> false) $oldname = substr($oldname, 0, $pos); // alten namen entfernen
        IPS_SetName($this->InstanceID, $oldname . " (" . $Name . ")");

        // Apply schliesst auch popup
        IPS_ApplyChanges($this->InstanceID);
    }

    public function readDeviceList()
    {
        $appID = $this->api('config')->AppID;
        $return = $this->api('get_app_list', $appID);

        $values = [];
        foreach ($return->result ?? [] as $value) {
            $newValue = new \stdClass();
            $newValue->ID = $value->id;
            $newValue->LocalKey = $value->local_key;
            $newValue->Model = $value->model;
            $newValue->Name = $value->name;
            $newValue->Online = $value->online;
            $values[] = $newValue;
        }
        return $values;
    }

    // kommando an das geraet senden, wirft TuyaApiException wenn das geraet offline ist oder tuya ablehnt
    public function CPost(array $payload)
    {
        // als offline bekannt: erst aktuellen stand holen, statt ~8 s auf die ablehnung der cloud zu warten
        if (!$this->GetValue("Online")) {
            $this->RequestRefresh();
            if (!$this->GetValue("Online")) {
                throw new TuyaApiException("Gerät ist offline");
            }
        }

        $return = $this->api('post_commands', $this->ReadPropertyString("DeviceID"), ['commands' => [$payload]]);
        if (empty($return->success)) {
            if (($return->code ?? 0) == self::DEVICE_OFFLINE) {
                $this->SetValue("Online", false);
            }
            throw new TuyaApiException($return->msg ?? "command failed");
        }
        return true;
    }

    // wert eines datenpunkts aus der status antwort, null wenn das geraet ihn nicht liefert
    protected function getDP($state, string $code)
    {
        if (!isset($state->result) || !is_array($state->result)) {
            return null;
        }
        foreach ($state->result as $dp) {
            if ($dp->code === $code) {
                return $dp->value;
            }
        }
        return null;
    }

    // durchlauf im IO anstossen, das IO verteilt den status an alle geraete
    public function RequestRefresh()
    {
        $this->api('refresh');
    }

    // kompatibilitaet: alter timer bzw. bestehende skripte
    public function TimerEvent()
    {
        try {
            $this->RequestRefresh();
        } catch (TuyaApiException $e) {
            IPS_LogMessage("TuyaDevice", "Refresh Error Device=" . $this->ReadPropertyString("DeviceID") . ": " . $e->getMessage());
        }
    }

    // online, offline
    private function CreateVarProfileModus()
    {
        if (!IPS_VariableProfileExists("Tuya.Online")) {
            IPS_CreateVariableProfile("Tuya.Online", 0);
            IPS_SetVariableProfileText("Tuya.Online", "", "");
            IPS_SetVariableProfileIcon("Tuya.Online", "Information");
            IPS_SetVariableProfileAssociation("Tuya.Online", 0, "offline", "", 0xFF2600); // todo farben setzen?
            IPS_SetVariableProfileAssociation("Tuya.Online", 1, "online", "", 0x00F900);
        }
    }
}
