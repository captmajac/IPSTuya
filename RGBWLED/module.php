<?php
require_once __DIR__ . '/../Generic/module.php';  // Base Module.php

class TuyaLEDRGBW extends TuyaGeneric
{
    // range":["white","colour","scene","music"]}"
    const CMODES = array(
        "white" => 0,
        "colour" => 1,
        "scene" => 2,
        "music" => 3
    );

    // Geraete spezifisch "min":0,"max":1000,"scale":0,"step":1}"
    // todo: am geraet pruefen ob tuya 0 = warm oder 0 = kalt bedeutet, die umrechnung geht von 0 = COLMIN (warm) aus
    const COLMIN = 2700;
    const COLMAX = 6500;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString("Version", "");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Variablenprofil anlegen
        $this->CreateVarProfileLightMode();

        $this->RegisterVariableBoolean("Power", "Power", "~Switch", 10);
        $this->RegisterVariableInteger("Mode", "Mode", "Tuya.LightMode", 30);
        $this->RegisterVariableInteger("Intensity", "Intensity", "~Intensity.100", 20);
        $this->RegisterVariableInteger("ColorTemperature", "ColorTemperature", "~TWColor", 40);
        $this->RegisterVariableInteger("Color", "Color", "~HexColor", 50);

        IPS_SetIcon($this->GetIDForIdent("Mode"), "Menu");

        $this->EnableAction("Power");
        $this->EnableAction("Intensity");
        $this->EnableAction("Mode");
        $this->EnableAction("ColorTemperature");
        $this->EnableAction("Color");
    }

    public function RequestAction($Ident, $Value)
    {
        $ret = false;
        $version = $this->ReadPropertyString("Version");

        switch ($Ident) {
            case "Power":
                $payload = ['code' => 'switch_led', 'value' => $Value];
                $ret = $this->CPost($payload);
                break;
            case "Intensity":
                $payload = ['code' => 'bright_value' . $version, 'value' => $Value * 10];        // *10 {"min":10,"max":1000,"scale":0,"step":1}
                $ret = $this->CPost($payload);
                if ($ret <> false) {
                    $this->SetValue("Mode", 0);                // spezifisch wenn helligkeit eingestellt wird verändert wird automatsch auf weiss mode geschaltet
                }
                break;
            case "ColorTemperature":
                // Wertbereich begrenzen auf Geraetespezifika
                $Value = max(self::COLMIN, min(self::COLMAX, $Value));
                $colvalue = intval(($Value - self::COLMIN) / (self::COLMAX - self::COLMIN) * 100 * 10);        // * 10 tuya spezifisch
                $payload = ['code' => 'temp_value' . $version, 'value' => $colvalue];
                $ret = $this->CPost($payload);
                if ($ret <> false) {
                    $this->SetValue("Mode", 0);                // spezifisch wenn farbtemperatur verändert wird automatsch auf weiss mode geschaltet
                }
                break;
            case "Color":
                if ($version == "_v2") {
                    [$h, $s, $v] = $this->colinttohsv($Value);
                    // {"h":259,"s":570,"v":1000}
                    $val = '{"h":' . $h . ',"s":' . $s . ',"v":' . $v . '}';
                    $payload = ['code' => 'colour_data' . $version, 'value' => $val];
                    $ret = $this->CPost($payload);
                } else {
                    $ValueHex = $this->colinttohex($Value);
                    $payload = ['code' => 'colour_data', 'value' => $ValueHex];
                    $ret = $this->CPost($payload);
                }
                break;
            case "Mode":
                $arr = ["white", "colour", "scene", "music"];
                $payload = ['code' => 'work_mode', 'value' => $arr[$Value]];        // {"range":["white","colour","scene","music"]}"
                $ret = $this->CPost($payload);
                break;
        }

        // Neuen Wert in die Statusvariable schreiben, wird über die Rückmeldung korrigiert
        if ($ret <> false) {
            $this->SetValue($Ident, $Value);
        }
    }

    // rgb spezifische werte
    public function updateState()
    {
        parent::updateState();
        $return = $this->getState();

        if (!isset($return->result)) {
            IPS_LogMessage("TuyaDevice", "State Error Device=" . $this->ReadPropertyString("DeviceID"));
            return;
        }
        $version = $this->ReadPropertyString("Version");

        // state
        $state = $this->getDP($return, 'switch_led');
        if ($state !== null) {
            $this->SetValue("Power", (bool) $state);
        }

        //color modes
        $mode = $this->getDP($return, 'work_mode');
        if ($mode !== null && isset(self::CMODES[$mode])) {
            $this->SetValue("Mode", self::CMODES[$mode]);
        }

        //bright
        $intensity = $this->getDP($return, 'bright_value' . $version);
        if ($intensity !== null) {
            $this->SetValue("Intensity", (int) round($intensity / 10));
        }

        //temp
        $temp = $this->getDP($return, 'temp_value' . $version);
        if ($temp !== null && $temp !== "") {
            $temp = (int) ($temp / 1000 * (self::COLMAX - self::COLMIN) + self::COLMIN);
            $this->SetValue("ColorTemperature", $temp);
        }

        //color
        // todo colour_data in int umrechnen
    }

    // int color to tuya hsv value [h 0-360, s 0-1000, v 0-1000]
    private function colinttohsv(int $intval)
    {
        $b = ($intval & 255);
        $g = (($intval >> 8) & 255);
        $r = (($intval >> 16) & 255);

        $min = min($r, $g, $b);
        $max = max($r, $g, $b);
        $delta_min_max = $max - $min;
        $result_h = 0;
        if ($delta_min_max !== 0 && $max === $r && $g >= $b) $result_h = 60 * (($g - $b) / $delta_min_max) + 0;
        elseif ($delta_min_max !== 0 && $max === $r && $g < $b) $result_h = 60 * (($g - $b) / $delta_min_max) + 360;
        elseif ($delta_min_max !== 0 && $max === $g) $result_h = 60 * (($b - $r) / $delta_min_max) + 120;
        elseif ($delta_min_max !== 0 && $max === $b) $result_h = 60 * (($r - $g) / $delta_min_max) + 240;
        $result_s = $max === 0 ? 0 : (1 - ($min / $max));
        $result_v = $max;
        $h = (int) (round($result_h));
        $s = (int) ($result_s * 100 * 10);
        $v = (int) ($result_v / 2.55) * 10;
        return [$h, $s, $v];
    }

    // int color to tuya hex value
    private function colinttohex(int $intval)
    {
        $value = "";
        foreach ($this->colinttohsv($intval) as $part) {
            $value .= substr("0000" . dechex($part), -4);
        }
        return $value;
    }

    // {"range":["white","colour","scene","music"]}"
    private function CreateVarProfileLightMode()
    {
        if (!IPS_VariableProfileExists("Tuya.LightMode")) {
            IPS_CreateVariableProfile("Tuya.LightMode", 1);
            IPS_SetVariableProfileText("Tuya.LightMode", "", "");
            IPS_SetVariableProfileValues("Tuya.LightMode", 0, 3, 1);
            IPS_SetVariableProfileIcon("Tuya.LightMode", "");
            IPS_SetVariableProfileAssociation("Tuya.LightMode", 0, "white", "", -1);
            IPS_SetVariableProfileAssociation("Tuya.LightMode", 1, "colour", "", -1);
            IPS_SetVariableProfileAssociation("Tuya.LightMode", 2, "scene", "", -1);
            IPS_SetVariableProfileAssociation("Tuya.LightMode", 3, "music", "", -1);
        }
    }
}
