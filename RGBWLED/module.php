<?
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
			
			//Variablenprofil anlegen 
			$this->CreateVarProfileModus();
			
			$this->RegisterVariableBoolean("Power", "Power", "~Switch", 10 );
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
				break;
		  	case "Intensity":	
				$payload = [ 'code' => 'bright_value' , 'value' => $Value *10 ];		// *10 {"min":10,"max":1000,"scale":0,"step":1}
				$ret = $this->CPost($payload);
				if ($ret <> false)
					SetValue($this->GetIDForIdent("Mode"), 0);				// spezifisch wenn helligkeit eingestellt wird verändert wird automatsch auf weiss mode geschaltet
				break;
  			case "ColorTemperature":
 				$colmin = 2700;									// Geraete spezifisch "min":0,"max":1000,"scale":0,"step":1}" 0=kaltweiss 1000=warmweiss 
 				$colmax = 6500;
 				$colvalue = intval ( ($Value-$colmin)/($colmax-$colmin)*100 * 10 );		// * 10 tuya spezifisch
  				$payload = [ 'code' => 'temp_value' , 'value' => $colvalue ];
  				$ret = $this->CPost($payload);
				// Wertbereich begrenzen auf Geraetespezifika 
				if ($Value > $colmax)
					$Value = $colmax;
				elseif ($Value < $colmin)
					$Value = $colmin;
				if ($ret <> false)
					SetValue($this->GetIDForIdent("Mode"), 0);				// spezifisch wenn farbtemperatur verändert wird automatsch auf weiss mode geschaltet
				break;
		  	case "Color":
				 $ValueHex = $this->colinttohex($Value);
				 $payload = [ 'code' => 'colour_data' , 'value' => $ValueHex ];
	 			$ret = $this->CPost($payload);
				break;
			case "Mode":
				//$arr = ["white","colour","scene","music"];
				$payload = [ 'code' => 'work_mode' , 'value' => self::CMODES[$Value] ];		// {"range":["white","colour","scene","music"]}"
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

		// rgb spezifische werte
		public function updateState()
		{
			$return = this->getState(); 
			
			// state
			$key = array_search('switch_led', array_column($return->result, 'code'));
			$state = "".$return->result[$key]->value;
			SetValue($this->GetIDForIdent("Power"), $state);

			//color modes
			$key = array_search('work_mode', array_column($return->result, 'code'));
			$state = "".$return->result[$key]->value;
			SetValue($this->GetIDForIdent("Mode"), self::CMODES[$state]);

			//bright
			$key = array_search('bright_value', array_column($return->result, 'code'));
			$intensity = "".$return->result[$key]->value;
			SetValue($this->GetIDForIdent("Intensity"), (int)$intensity/10 );

			//temp
			$key = array_search('temp_value', array_column($return->result, 'code'));
			$temp = "".$return->result[$key]->value;
			SetValue($this->GetIDForIdent("ColorTemperature"), (int)$temp/10 );

			//color
			$key = array_search('colour_data', array_column($return->result, 'code'));
			$col = "".$return->result[$key]->value;
			// todo hex wert in int umrechnen
			//SetValue($this->GetIDForIdent("Color"), (int)$temp/10 );			
		}

	
	// timer aufruf,
	/*public function TimerEvent() {
		$this->updateState();
	} */
		
		// int color to tuya hex value
		private function colinttohex(int $intval)
    		{
    		$b = ($intval & 255);
    		$g = (($intval >> 8) & 255);
   		$r = (($intval >> 16) & 255);

   		$r = max(0, min((int)$r, 255));
    		$g = max(0, min((int)$g, 255));
      		$b = max(0, min((int)$b, 255));
      		$result = [];
      		$min = min($r, $g, $b);
      		$max = max($r, $g, $b);
      		$delta_min_max = $max - $min;
      		$result_h = 0;
      		if     ($delta_min_max !== 0 && $max === $r && $g >= $b) $result_h = 60 * (($g - $b) / $delta_min_max) +   0;
      		elseif ($delta_min_max !== 0 && $max === $r && $g <  $b) $result_h = 60 * (($g - $b) / $delta_min_max) + 360;
      		elseif ($delta_min_max !== 0 && $max === $g            ) $result_h = 60 * (($b - $r) / $delta_min_max) + 120;
      		elseif ($delta_min_max !== 0 && $max === $b            ) $result_h = 60 * (($r - $g) / $delta_min_max) + 240;
      		$result_s = $max === 0 ? 0 : (1 - ($min / $max));
      		$result_v = $max;
     		$result['h'] = "".substr("000000".dechex( (int)(round($result_h)) ),-4);
      		$result['s'] = "".substr("000000".dechex( (int)($result_s * 100 * 10) ),-4);
      		$result['v'] = "".substr("000000".dechex( (int)($result_v / 2.55) * 10 ),-4);

      		$value = $result['h'].$result['s'].$result['v'];
		return $value;
		}
		
		// {"range":["white","colour","scene","music"]}"
		private function CreateVarProfileModus() {
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
?>
