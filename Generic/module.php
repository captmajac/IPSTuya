<?php
//Tuya Klassen einbinden
include_once __DIR__ . "/../libs/TuyaAPI.php";

class TuyaGeneric extends IPSModule
{
    // erstellung
    public function Create()
    {
        // tuya socket notwendig für die parameter
        $this->ConnectParent('{78ABC644-1134-F4E2-3E31-01E45483367B}');

        $this->CreateVarProfileModus();
        
        // Never delete this line!
        parent::Create();
        $this->RegisterPropertyString("DeviceID", "");
        //$this->RegisterPropertyString("DeviceName", "");
        $this->RegisterPropertyString("LocalKey", "");

        $this->RegisterVariableBoolean("Online", "Online", "Tuya.Online", 100);
	
	// update timer
        $this->RegisterTimer("UpdateTimer",0,$Module."_TimerEvent(\$_IPS['TARGET']);");
	$this->SetTimerInterval("SearchTime", 1000*60*2);		// 2 min.

        // modulaufruf ändern
        $Module = $this->GetBuffer("Module");
        if ($Module == "")
        {
            // default this Module
            $Module = json_decode(file_get_contents(__DIR__ . "/module.json") , true) ["prefix"]; // Modul für parent merken
            $this->SetBuffer("Module", $Module);
        }

    }

    // changes der instanz
    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();

        //$instance = IPS_GetInstance($this->InstanceID);
        //$ret = IPS_GetConfiguration ($instance['ConnectionID']);
        //$para = json_decode($ret);
        
    }

    public function Send(string $Text)
    {
        $this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', "Buffer" => $Text]));
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        IPS_LogMessage('Device RECV', utf8_decode($data->Buffer));
    }

    // default debug message
    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_array($Data))
        {
            foreach ($Data as $Key => $DebugData)
            {
                $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
            }
        }
        elseif (is_object($Data))
        {
            foreach ($Data as $Key => $DebugData)
            {
                $this->SendDebug($Message . "." . $Key, $DebugData, 0);
            }
        }
        else
        {
            parent::SendDebug($Message, $Data, $Format);
        }
    }

    // search device
    public function SearchModules()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        $ret = IPS_GetConfiguration($instance['ConnectionID']);
        $para = json_decode($ret);

        //$appID = $this->ReadPropertyString("AppID");
        $appID = $para->AppID;

        $token = $this->getToken();
        $list = $this->readDeviceList($token, $appID);

        $jsValues = json_encode($list);
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

        if ($DevID != null)
        {
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

    public function readDeviceList(string $token, string $app_id)
    {
        $tuya = $this->getTuyaClass();
        //$token = $tuya->getToken();
        $return = $tuya->devices($token)->get_app_list($app_id);
        $arr = $return->result;

        $values = [];
        foreach ($arr as $value)
        {
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

    public function getToken()
    {
        $tuya = $this->getTuyaClass();

        $token = $tuya
            ->token
            ->get_new()
            ->result->access_token;
        return $token;
    }

    public function getTuyaClass()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        $ret = IPS_GetConfiguration($instance['ConnectionID']);
        $para = json_decode($ret);

        $config = ["accessKey" => $para->AccessKey, "secretKey" => $para->SecretKey, "baseUrl" => $para->BaseUrl];

        /*
        $config = [
            "accessKey" => $this->ReadPropertyString("AccessKey"),
            "secretKey" => $this->ReadPropertyString("SecretKey"),
            "baseUrl" => $this->ReadPropertyString("BaseUrl"),
        ];*/
        $tuya = new TuyaApi($config);
        return $tuya;
    }

    /*public function updateState()
    {
        $tuya = $this->getToken();
        $return = $tuya->devices($token)->get_status($device_id);
        return $return;
    }*/

    // status lesen
    public function updateState()
		{
			$tuya = $this->getTuyaClass();
			$token = $this->getToken();
			$device_id = $this->ReadPropertyString("DeviceID");
			$return = $tuya->devices( $token )->get_status( $device_id );

			return $return;
		}
	
	// timer aufruf,
	public function TimerEvent() {
		$this->updateState();
	} 
	
    // online, offline
    private function CreateVarProfileModus()
    {
        if (!IPS_VariableProfileExists("Tuya.Online"))
        {
            IPS_CreateVariableProfile("Tuya.Online", 0);
            IPS_SetVariableProfileText("Tuya.Online", "", "");
            IPS_SetVariableProfileIcon("Tuya.Online", "Information");
            IPS_SetVariableProfileAssociation("Tuya.Online", 0, "offline", "", -1); // todo farben setzen?
            IPS_SetVariableProfileAssociation("Tuya.Online", 1, "online", "", -1);

        }
    }

}

?>
