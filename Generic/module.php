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

	// tuya socket notwendig für die parameter
        $this->ConnectParent('{78ABC644-1134-F4E2-3E31-01E45483367B}');

	$this->CreateVarProfileModus();    
        $this->RegisterPropertyString("DeviceID", "");
        $this->RegisterPropertyString("LocalKey", "");

	//$this->RegisterTimer("UpdateTimer",0,$Module."_TimerEvent(\$_IPS['TARGET']);");  
	$Module = json_decode(file_get_contents(__DIR__ . "/module.json") , true) ["prefix"];
	$this->RegisterTimer("UpdateTimer",0,$Module."_TimerEvent(\$_IPS['TARGET']);");  
	    
    }

    // changes der instanz
    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();

	$this->RequireParent('{78ABC644-1134-F4E2-3E31-01E45483367B}');
	    
        $this->RegisterVariableBoolean("Online", "Online", "Tuya.Online", 100);   
		    
	// update timer
	// auslesen aus der IO parameter geht irgendwie nicht
	$Interval = 2*60 * 1000; 		// starttimer weil getinstance in apply die instanz nicht erstellen lässt
	$this->SetTimerInterval("UpdateTimer", $Interval);		// $this->ReadPropertyInteger("Interval")
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

    // get online status for one device id	todo: besser wäre es einmalig für alle geräte zu lesen z.b. im socket
    public function GetOnlineStatus(String $device_id)
    {
        $instance = IPS_GetInstance($this->InstanceID);
        $ret = IPS_GetConfiguration($instance['ConnectionID']);
        $para = json_decode($ret);

        $appID = $para->AppID;
	$token = $this->getToken();
        $list = $this->readDeviceList($token, $appID);
	
        $key = array_search($device_id, array_column($list, 'ID'));
	$online = false;
	if ($key!=0)
	{
		$online = (bool) $list[$key]->Online;
	}
	
	return $online;
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

    // status lesen
    public function getState()
		{
			$tuya = $this->getTuyaClass();
			$token = $this->getToken();
			$device_id = $this->ReadPropertyString("DeviceID");
			//IPS_LogMessage("Generic","update ".$device_id);
			
			$return = $tuya->devices( $token )->get_status( $device_id );

			return $return;
		}

	public function updateState()
	{
		// nothing to update
		$device_id = $this->ReadPropertyString("DeviceID");
		$online = $this->GetOnlineStatus($device_id);
		SetValue($this->GetIDForIdent("Online"), $online );
	}
	
	// timer aufruf,
	public function TimerEvent() {
		$this->updateState();

		// workaround, starttimerzeit ändern weil getinstance in applychange nicht korrekt aufgerufen werden kann
		$instance = IPS_GetInstance($this->InstanceID);
        	$ret = IPS_GetConfiguration($instance['ConnectionID']);
        	$para = json_decode($ret);
        	$Interval = $para->Interval * 60 * 1000; 
		//$Interval = 15 * 60 * 1000; 

		$this->SetTimerInterval("UpdateTimer", $Interval);		// $this->ReadPropertyInteger("Interval")
		
	} 
	
    // online, offline
    private function CreateVarProfileModus()
    {
        if (!IPS_VariableProfileExists("Tuya.Online"))
        {
            IPS_CreateVariableProfile("Tuya.Online", 0);
            IPS_SetVariableProfileText("Tuya.Online", "", "");
            IPS_SetVariableProfileIcon("Tuya.Online", "Information");
            IPS_SetVariableProfileAssociation("Tuya.Online", 0, "offline", "", 0xFF2600); // todo farben setzen?
            IPS_SetVariableProfileAssociation("Tuya.Online", 1, "online", "", 0x00F900);

        }
    }

}

?>
