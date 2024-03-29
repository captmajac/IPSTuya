<?php

//Tuya Klassen einbinden
include_once __DIR__ . "/../libs/TuyaAPI.php";

class TuyaClient extends IPSModule
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

	$this->RegisterPropertyInteger("Interval", 15);    	// minuten
    
    }

    // changes der instanz
    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();
    }

   public function ForwardData($JSONString)
		{
			$data = json_decode($JSONString);
			IPS_LogMessage('IO FRWD', utf8_decode($data->Buffer));
		}

		public function Send(string $Text)
		{
			$this->SendDataToChildren(json_encode(['DataID' => '', 'Buffer' => $Text]));
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
