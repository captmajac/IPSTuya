<?
require_once __DIR__ . '/../Generic/module.php';  // Base Module.php

class TuyaLEDRGBW extends TuyaGeneric
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
			
			$this->RegisterVariableBoolean("Power", "Power", "~Switch");
			$this->RegisterVariableInteger("Intensity", "Intensity", "~Intensity.100");
      			$this->RegisterVariableInteger("ColorTemperature", "Color Temperature", "~TWColor");
      			$this->RegisterVariableInteger("Color", "Color", "~HexColor");
			
			$this->EnableAction("Power");	
      			$this->EnableAction("Intensity");	
     			$this->EnableAction("ColorTemperature");	
      			$this->EnableAction("Color");	
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
		  	case "Power":
				$payload = [ 'code' => 'switch_led' , 'value' => $Value ];
				$ret = $this->CPost($payload);
		  	case "Intensity":	
				$payload = [ 'code' => 'bright_value' , 'value' => $Value *10 ];		// *10 {"min":10,"max":1000,"scale":0,"step":1}
				$ret = $this->CPost($payload);
	  			// todo
  			case "Color Temperature":
			  	// todo
		  	case "Color":
	  			// todo	
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
		
		public function power(bool $state)
    		{
			$tuya = $this->getTuyaClass();
			$token = $this->getToken();
			$device_id = $this->ReadPropertyString("DeviceID");
			$payload = [ 'code' => 'switch_led' , 'value' => $state ];
   			$return = $tuya->devices( $token )->post_commands( $device_id, [ 'commands' => [ $payload ] ]);
			return $return->success;
		}

		
	}
?>
