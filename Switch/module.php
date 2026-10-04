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

    protected function handleAction($Ident, $Value)
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

    // datenpunkte aus dem status paket des IO
    protected function applyStatus($state)
    {
        $power = $this->getDP($state, 'switch_1');
        if ($power !== null) {
            $this->SetValue("Power", (bool) $power);
        }
    }
}
