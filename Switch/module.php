<?php
require_once __DIR__ . '/../Generic/module.php';  // Base Module.php

class TuyaSwitch extends TuyaGeneric
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterVariableBoolean("Power", "Power", "~Switch", 10);
        $this->EnableAction("Power");
    }

    public function RequestAction($Ident, $Value)
    {
        $ret = false;
        switch ($Ident) {
            case "Power":
                $payload = ['code' => 'switch_1', 'value' => $Value];
                $ret = $this->CPost($payload);
                break;
        }

        // Neuen Wert in die Statusvariable schreiben, wird über die Rückmeldung korrigiert
        if ($ret <> false) {
            $this->SetValue($Ident, $Value);
        }
    }

    public function updateState()
    {
        parent::updateState();
        $return = $this->getState();

        if (!isset($return->result)) {
            IPS_LogMessage("TuyaDevice", "State Error Device=" . $this->ReadPropertyString("DeviceID"));
            return;
        }

        $state = $this->getDP($return, 'switch_1');
        if ($state !== null) {
            $this->SetValue("Power", (bool) $state);
        }
    }
}
