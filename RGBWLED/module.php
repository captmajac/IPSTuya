<?
require_once __DIR__ . '/../Generic/module.php';  // Base Module.php

class TuyaLEDRGBW extends TuyaGeneric
	{
		public function Create() 
		{
			//Never delete this line!
			parent::Create();
			//$this->RegisterPropertyString("DeviceID", "");
			//$this->RegisterPropertyString("DeviceName", "");
			//$this->RegisterPropertyString("LocalKey", "");
			
			//Connect to available gateway
			//$this->ConnectParent("{A52FEFE9-7858-4B8E-A96E-26E15CB944F7}");
		}
    
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			
			$this->RegisterVariableBoolean("Power", "Power", "~Switch");
			$this->RegisterVariableInteger("Intensity", "Intensity", "~Intensity.100");
      			$this->RegisterVariableInteger("ColorTemperature", "Color Temperature", "~TWColor");
      			$this->RegisterVariableInteger("Color", "Color", "~HexColor");
			
			$this->EnableAction("Power");	
      			$this->EnableAction("Intensity");	
     			$this->EnableAction("ColorTemperature");	
      			$this->EnableAction("Color");	
			
			//$this->SetReceiveDataFilter(".*\"DeviceID\":".(int)hexdec($this->ReadPropertyString("DeviceIDRet")).".*");
		}
		
		/*
		* This function will be available automatically after the module is imported with the module control.
		* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		*
		* IOT_Send($id, $text);
		*
		*public function Send($Text)
		*{
		*	$this->SendDataToParent(json_encode(Array("DataID" => "{B87AC955-F258-468B-92FE-F4E0866A9E18}", "Buffer" => $Text)));
		*}
   		*/
		
		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			$this->SendDebug("TuyaGatewayData", $JSONString, 0);
			//  auswerten
			$this->Process($data);
			
		}
    
		// daten auswerten
		private function Process($Data)
		{ 	
		}

		public function RequestAction($Ident, $Value)
		{
			switch($Ident) {
		  	case "Power":
			  	// todo
		  	case "Intensity":
	  			// todo
  			case "Color Temperature":
			  	// todo
		  	case "Color":
	  			// todo	
			break;
	
			}
			
			// Neuen Wert in die Statusvariable schreiben, wird über die Rückmeldung korrigiert
			SetValue($this->GetIDForIdent($Ident), $Value);
		}
		
		protected function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits)
		{
			if (!IPS_VariableProfileExists($Name))
			{
				IPS_CreateVariableProfile($Name, 2);
			}
			else
			{
				$profile = IPS_GetVariableProfile($Name);
				if ($profile['ProfileType'] != 2)
					throw new Exception("Variable profile type does not match for profile " . $Name);
			}
			IPS_SetVariableProfileIcon($Name, $Icon);
			IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
			IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
			IPS_SetVariableProfileDigits($Name, $Digits);
			
		}

		
		protected function SendDebug($Message, $Data, $Format)
		{
			if (is_array($Data))
			{
			    foreach ($Data as $Key => $DebugData)
			    {
						$this->SendDebug($Message . ":" . $Key, $DebugData, 0);
			    }
			}
			else if (is_object($Data))
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
		
	
	}
?>
