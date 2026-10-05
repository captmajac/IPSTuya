<?php
//Tuya Klassen einbinden
include_once __DIR__ . "/../libs/TuyaDataFlow.php";

// Basis aller Tuya Geraete: Status kommt per Paket vom IO (TuyaClient),
// Cloud-Aufrufe laufen ueber api() -> SendDataToParent -> TuyaClient::ForwardData
class TuyaGeneric extends IPSModule
{
    use TuyaDataFlow;

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

        $this->RegisterVariableBoolean("Online", $this->Translate("Online"), "Tuya.Online", 100);
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
            $this->applyStatus((object) ['result' => $buffer->status, 'full' => $buffer->full ?? true]);
        } catch (TuyaApiException $e) {
            $this->LogMessage(sprintf($this->Translate("Update failed: %s"), $e->getMessage()), KL_ERROR);
        }
    }

    // geraete spezifische auswertung der datenpunkte, wird von den modulen ueberschrieben
    protected function applyStatus($state)
    {
    }

    // befehle aus webfront, skripten und anderen modulen (z.b. szenen):
    // fehler werden protokolliert statt an den aufrufer geworfen, im meldungsfenster je fehler nur einmal
    public function RequestAction($Ident, $Value)
    {
        try {
            $this->handleAction($Ident, $Value);
            $this->SetBuffer("LastError", "");
        } catch (TuyaApiException $e) {
            $this->SendDebug("Error", $Ident . ": " . $e->getMessage(), 0);
            if ($this->GetBuffer("LastError") !== $e->getMessage()) {
                $this->SetBuffer("LastError", $e->getMessage());
                $this->LogMessage(sprintf($this->Translate("Command %s failed: %s"), $Ident, $e->getMessage()), KL_WARNING);
            }
        }
    }

    // geraete spezifische befehle, wird von den modulen ueberschrieben
    protected function handleAction($Ident, $Value)
    {
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

    // kommando an das geraet senden
    // offline geraete: befehl wird ohne fehler ignoriert (z.b. lampen am wandschalter in einer szene),
    // andere ablehnungen von tuya werfen TuyaApiException
    protected function CPost(array $payload)
    {
        // als offline bekannt: stand ueber das IO pruefen (hoechstens ein schlanker abruf je minute),
        // statt ~8 s auf die ablehnung der cloud zu warten
        if (!$this->GetValue("Online")) {
            $this->RequestRefresh();
            if (!$this->GetValue("Online")) {
                $this->SendDebug("Command", "device offline, ignored: " . json_encode($payload), 0);
                return false;
            }
        }

        $return = $this->api('post_commands', $this->ReadPropertyString("DeviceID"), ['commands' => [$payload]]);
        if (empty($return->success)) {
            if (($return->code ?? 0) == self::DEVICE_OFFLINE) {
                $this->SetValue("Online", false);
                $this->SendDebug("Command", "device offline, ignored: " . json_encode($payload), 0);
                return false;
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
            $this->LogMessage(sprintf($this->Translate("Update failed: %s"), $e->getMessage()), KL_ERROR);
        }
    }

    // online, offline
    private function CreateVarProfileModus()
    {
        if (!IPS_VariableProfileExists("Tuya.Online")) {
            IPS_CreateVariableProfile("Tuya.Online", 0);
            IPS_SetVariableProfileText("Tuya.Online", "", "");
            IPS_SetVariableProfileIcon("Tuya.Online", "Information");
            IPS_SetVariableProfileAssociation("Tuya.Online", 0, $this->Translate("offline"), "", 0xFF2600);
            IPS_SetVariableProfileAssociation("Tuya.Online", 1, $this->Translate("online"), "", 0x00F900);
        }
    }
}
