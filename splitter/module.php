<?php

//Tuya Klassen einbinden
include_once __DIR__ . "/../libs/TuyaAPI.php";

class TuyaServer extends IPSModule
{
    // erstellung
    public function Create()
    {
        // Never delete this line!
        parent::Create();

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
    
    }

    // changes der instanz
    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();
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

    // Verbindungsprüfung
    public function CheckConnection()
    {
        $tuya = $this->getTuyaClass();
        $token = $tuya->getToken();
        // check ob token gültig
  
        $appID = $this->ReadPropertyString("AppID");
        // check ob appid gültig
    }

    
    public function getToken()
    {
        $tuya = $this->getTuyaClass();
        
        $token = $tuya->token->get_new( )->result->access_token;
        return $token;
    }

    public function getTuyaClass()
    {
         $config = [
            "accessKey" => $this->ReadPropertyString("AccessKey"),
            "secretKey" => $this->ReadPropertyString("SecretKey"),
            "baseUrl" => $this->ReadPropertyString("BaseUrl"),
        ];
        $tuya = new TuyaApi($config);
        return $tuya;
    }
    
}

?>
