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

        $this->RegisterVariableFloat("Temperatur", "Temperatur", "~Temperature", 10);
        $this->RegisterVariableFloat("Humidity", "Humidity", "~Humidity.F", 20);
        $this->RegisterVariableString("Battery", "Battery", "", 30);
    }

    public function updateState()
    {
        parent::updateState();
        $return = $this->getState();

        if (!isset($return->result)) {
            IPS_LogMessage("TuyaDevice", "State Error Device=" . $this->ReadPropertyString("DeviceID"));
            return;
        }

        $temp = $this->getDP($return, 'va_temperature');
        if ($temp !== null) {
            $this->SetValue("Temperatur", (float) $temp / 10);
        }

        $humidity = $this->getDP($return, 'va_humidity');
        if ($humidity !== null) {
            $this->SetValue("Humidity", (float) $humidity);
        }

        $battery = $this->getDP($return, 'battery_state');
        if ($battery !== null) {
            $this->SetValue("Battery", (string) $battery);
        }
    }
}
