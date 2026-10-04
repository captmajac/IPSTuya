<?php
require_once __DIR__ . '/../Generic/module.php';  // Base Module.php

class THSensor extends TuyaGeneric
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

        $this->RegisterVariableFloat("Temperatur", $this->Translate("Temperature"), "~Temperature", 10);
        $this->RegisterVariableFloat("Humidity", $this->Translate("Humidity"), "~Humidity.F", 20);
        $this->RegisterVariableString("Battery", $this->Translate("Battery"), "", 30);
    }

    // datenpunkte aus dem status paket des IO
    protected function applyStatus($state)
    {
        $temp = $this->getDP($state, 'va_temperature');
        if ($temp !== null) {
            $this->SetValue("Temperatur", (float) $temp / 10);
        }

        $humidity = $this->getDP($state, 'va_humidity');
        if ($humidity !== null) {
            $this->SetValue("Humidity", (float) $humidity);
        }

        $battery = $this->getDP($state, 'battery_state');
        if ($battery !== null) {
            $this->SetValue("Battery", (string) $battery);
        }
    }
}
