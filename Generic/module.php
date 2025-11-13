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
        $this->RegisterPropertyBoolean("AutoCreateVariables", false);

        $this->RegisterVariableBoolean("Online", "Online", "Tuya.Online", 100);
	
        // modulaufruf ändern
        $Module = $this->GetBuffer("Module");
        if ($Module == "")
        {
            // default this Module
            $Module = json_decode(file_get_contents(__DIR__ . "/module.json") , true) ["prefix"]; // Modul für parent merken
            $this->SetBuffer("Module", $Module);
        }
	// update timer
	$this->RegisterTimer("UpdateTimer",0,$Module."_TimerEvent(\$_IPS['TARGET']);");

	$Interval = 2*60; 		// starttimer weil getinstance in apply die instanz nicht erstellen lässt
	$this->SetTimerInterval("UpdateTimer", $Interval);		// $this->ReadPropertyInteger("Interval")

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
	$online = (bool) $list[$key]->Online;
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

        $return = $tuya->devices($token)->get_status($device_id);

        $json = json_encode($return);
        if ($json === false)
        {
            $this->SendDebug('getState', 'JSON encode failed: ' . json_last_error_msg(), 0);
        }
        else
        {
            if ($this->ReadPropertyBoolean('AutoCreateVariables'))
            {
                try
                {
                    $this->UpdateJsonToVariables($json, $this->InstanceID);
                }
                catch (\Exception $exception)
                {
                    $this->SendDebug('getState', 'Variable update failed: ' . $exception->getMessage(), 0);
                }
            }
        }

        return $return;
    }

    public function updateState()
    {
        // nothing to update
        $device_id = $this->ReadPropertyString("DeviceID");
        $online = $this->GetOnlineStatus($device_id);
        SetValue($this->GetIDForIdent("Online"), $online);
    }

    // timer aufruf,
    public function TimerEvent()
    {
        $this->updateState();

        // workaround, starttimerzeit ändern weil getinstance in applychange nicht korrekt aufgerufen werden kann
        $instance = IPS_GetInstance($this->InstanceID);
        $ret = IPS_GetConfiguration($instance['Interval']);
        $para = json_decode($ret);
        $Interval = $para->Interval;

        $this->SetTimerInterval("UpdateTimer", $Interval);              // $this->ReadPropertyInteger("Interval")

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

    private function UpdateJsonToVariables(string $json, int $parentID = 0)
    {
        if ($parentID === 0)
        {
            $parentID = $this->InstanceID;
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE)
        {
            throw new \Exception('JSON-Fehler: ' . json_last_error_msg());
        }

        $this->ProcessNode($data, $parentID);
    }

    private function ProcessNode($data, int $parentID)
    {
        foreach ($data as $key => $value)
        {
            $ident = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $key);
            $name = (string) $key;

            if (is_array($value))
            {
                $catID = $this->GetOrCreateCategory($parentID, $ident, $name);
                $this->ProcessNode($value, $catID);
            }
            else
            {
                list($varType, $castValue) = $this->GetVarTypeAndValue($value);
                $varID = $this->GetOrCreateVariable($parentID, $ident, $name, $varType);
                $this->SetVariableValue($varID, $varType, $castValue);
            }
        }
    }

    private function GetOrCreateCategory(int $parentID, string $ident, string $name): int
    {
        $objID = @IPS_GetObjectIDByIdent($ident, $parentID);
        if ($objID === false)
        {
            $objID = IPS_CreateCategory();
            IPS_SetParent($objID, $parentID);
            IPS_SetIdent($objID, $ident);
            IPS_SetName($objID, $name);
        }

        return $objID;
    }

    private function GetOrCreateVariable(int $parentID, string $ident, string $name, int $varType): int
    {
        $varID = @IPS_GetObjectIDByIdent($ident, $parentID);
        if ($varID === false)
        {
            $varID = IPS_CreateVariable($varType);
            IPS_SetParent($varID, $parentID);
            IPS_SetIdent($varID, $ident);
            IPS_SetName($varID, $name);
        }
        else
        {
            $var = IPS_GetVariable($varID);
            if ($var['VariableType'] !== $varType)
            {
                IPS_DeleteVariable($varID);
                $varID = IPS_CreateVariable($varType);
                IPS_SetParent($varID, $parentID);
                IPS_SetIdent($varID, $ident);
                IPS_SetName($varID, $name);
            }
        }

        return $varID;
    }

    private function GetVarTypeAndValue($value): array
    {
        if (is_bool($value))
        {
            return [0, (bool) $value];
        }
        if (is_int($value))
        {
            return [1, (int) $value];
        }
        if (is_float($value))
        {
            return [2, (float) $value];
        }

        return [3, (string) $value];
    }

    private function SetVariableValue(int $varID, int $varType, $value)
    {
        switch ($varType)
        {
            case 0:
                SetValueBoolean($varID, (bool) $value);
                break;
            case 1:
                SetValueInteger($varID, (int) $value);
                break;
            case 2:
                SetValueFloat($varID, (float) $value);
                break;
            case 3:
            default:
                SetValueString($varID, (string) $value);
                break;
        }
    }

}

?>
