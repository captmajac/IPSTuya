<?php

//Tuya Klassen einbinden
include_once __DIR__ . "/../libs/TuyaAPI.php";

class TuyaGeneric extends IPSModule
{
    // erstellung
    public function Create()
    {
        // Never delete this line!
        parent::Create();
        $this->RegisterPropertyString("DeviceID", "");
        //$this->RegisterPropertyString("DeviceName", "");
        $this->RegisterPropertyString("LocalKey", "");

        $this->RegisterPropertyString("AccessKey", "");
        $this->RegisterPropertyString("SecretKey", "");
        $this->RegisterPropertyString("BaseUrl", ""); // z.b. 'https://openapi.tuyaeu.com'
        $this->RegisterPropertyString("AppID", "");

        // modulaufruf ändern
        $Module = $this->GetBuffer("Module");
        if ($Module == "") {
            // default this Module
            $Module = json_decode(
                file_get_contents(__DIR__ . "/module.json"),
                true
            )["prefix"]; // Modul für parent merken
            $this->SetBuffer("Module", $Module);
        }

        //$this->RegisterTimer("SearchTime",0,$Module . "_TimerEvent(\$_IPS['TARGET']);");

        // Connect to available gateway
        // $this->ConnectParent ( "{A52FEFE9-7858-4B8E-A96E-26E15CB944F7}" );
    }

    // changes der instanz
    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();

        $config = [
            "accessKey" => $this->ReadPropertyString("AccessKey"),
            "secretKey" => $this->ReadPropertyString("SecretKey"),
            "baseUrl" => $this->ReadPropertyString("BaseUrl"),
        ];
        $tuya = new TuyaApi($config);

        // data filter actual not used
        //$this->SetReceiveDataFilter(".*\"DeviceID\":".$this->GetID().".*");
        //$this->SetReceiveDataFilter(".*\"DeviceID\":".(int)hexdec($this->ReadPropertyString("DeviceID")).".*");
    }

    /*
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     *
     * IOT_Send($id, $text);
     *
     * public function Send($Text)
     * {
     * $this->SendDataToParent(json_encode(Array("DataID" => "{B87AC955-F258-468B-92FE-F4E0866A9E18}", "Buffer" => $Text)));
     * }
     */
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $this->SendDebug("TuyaGatewayData", $JSONString, 0);
    }

    // analyze recieved data
    private function ProcessData($data)
    {
        // nothing to do
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

    // start/stop search device
    public function SearchModules()
    {
        $appID = $this->ReadPropertyString("AppID");
        $token = $this->getToken();
        $this->readDeviceList($token, $appID);
            
                
        $this->UpdateFormField("Actors", "values", "");
    }


    // auswahl aus der search liste
    public function SetSelectedModul(object $List)
    {
        @$DevID = $List["ID"]; // Kommt ein Error bei keiner Auswahl
        @$LocalKey = $List["LocalKey"]; // Kommt ein Error bei keiner Auswahl

        $this->SetBuffer("List", "");

        if ($DevID != null) {
            IPS_SetProperty($this->InstanceID, "DeviceID", "" . $DevID);
            IPS_SetProperty($this->InstanceID, "LocalKey", "" . $LocalKey);
        }
        // Apply schliesst auch popup
        IPS_ApplyChanges($this->InstanceID);
    }

    // ggf. auch entscheiden was in der Liste aufgenommen werden soll
    // z.b. Filter auf spezielle Geräte
    //
    public function updateList(string $DevID, object $data)
    {
        // Device Liste als Buffer
        $values = json_decode($this->GetBuffer("List")); //json_decode( $this );

        $token = $tuya->token->get_new( )->result->access_token;
        $values = readDeviceList($token, $this->ReadPropertyString("AppID"),);
        
        //$newValue->Reference = $AddInfo;

        if (@in_array($newValue->Ident, array_column($values, "ID")) == false) {
            //$values[] = $newValue;

            $jsValues = json_encode($values);
            $this->SetBuffer("List", $jsValues);

            $this->UpdateFormField("Actors", "values", $jsValues);
        }
    }

    private function readDeviceList(string $token, string $app_id)
    {
        $return = $tuya->devices($token)->get_app_list($app_id);
        $arr = $return->result;

        $values = [];
        foreach ($arr as $value) {
            $newValue = new \stdClass();
            $newValue->ID = $value->id;
            $newValue->LocalKey = $value->local_key;
            $newValue->Model = $value->model;
            $newValue->Name = $value->name;
            $values[] = $newValue;
        }
        return $values;
    }
    
    private function getToken()
    {
        $token = $this->$tuya->token->get_new( )->result->access_token;
    }
}

?>
