<?
require_once __DIR__ . '/../Generic/module.php';  // Base Module.php

class THSensor extends TuyaGeneric
	{
		
		
		public function Create() 
		{
			//Never delete this line!
			parent::Create();

			// tuya socket notwendig für die parameter
			$this->ConnectParent('{78ABC644-1134-F4E2-3E31-01E45483367B}');
		}
    
		public function ApplyChanges()
		{
			//$this->GetConfigurationForParent();
			
			//Never delete this line!
			parent::ApplyChanges();
			
			$this->RegisterVariableFloat("Temperatur", "Temperatur", "~Temperature", 10 );
			$this->RegisterVariableFloat("Humidity", "Humidity", "~Humidity.F", 20);
			$this->RegisterVariableString("Battery", "Battery", "", 30);

		#	IPS_SetIcon($this->GetIDForIdent("Mode"), "Menu");
			
		#	$this->EnableAction("Power");	
		
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
	

		public function RequestAction($Ident, $Value)
		{
			$ret = false;
			switch($Ident) {
		  	case "Temperatur1":
				$payload = [ 'code' => 'va_temperature' , 'value' => $Value ];
				$ret = $this->CPost($payload);
				break;
		  	case "Humidity1":	
				$payload = [ 'code' => 'va_humidity' , 'value' => $Value ];		// *10 {"min":10,"max":1000,"scale":0,"step":1}
				$ret = $this->CPost($payload);
				break;
 			break;
	
			}
			
			// Neuen Wert in die Statusvariable schreiben, wird über die Rückmeldung korrigiert
			if ($ret <> false)
				SetValue($this->GetIDForIdent($Ident), $Value);
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

		
		public function CPost(array $payload)
    		{
			$tuya = $this->getTuyaClass();
			$token = $this->getToken();
			$device_id = $this->ReadPropertyString("DeviceID");
			
   			$return = $tuya->devices( $token )->post_commands( $device_id, [ 'commands' => [ $payload ] ]);
			return $return->success;
		}

		// 
		public function updateState()
		{
			parent::updateState();
			$return = $this->getState(); 
			
			if (is_null($return) == true or is_null($return->result) == true)
			{
				IPS_LogMessage("TuyaDevice","State Error Device=".$this->ReadPropertyString("DeviceID") );
				return;
			}
			
			// Temperatur
			$key = array_search('va_temperature', array_column($return->result, 'code'));
			$temp = (float)$return->result[$key]->value;
			SetValue($this->GetIDForIdent("Temperatur"), $temp/10);

			//Humidity
			$key = array_search('va_humidity', array_column($return->result, 'code'));
            $humidity = (float)$return->result[$key]->value;
            SetValue($this->GetIDForIdent("Humidity"), $humidity);

			//Battery
			$key = array_search('battery_state', array_column($return->result, 'code'));
            $Batterie = (string)$return->result[$key]->value;
            SetValue($this->GetIDForIdent("Battery"), $Batterie);
		}



		
	}
?>
