<?
require_once __DIR__ . '/../Generic/module.php';  // Base Module.php

class TuyaBLELock extends TuyaGeneric
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
			
			//Variablenprofil anlegen 
			$this->CreateVarProfileModus();
			
			$this->RegisterVariableBoolean("Lock", "Lock", "~Lock", 10 );
			$this->RegisterVariableInteger("Message", "Message", "~String", 30);
			$this->RegisterVariableInteger("Battery", "Battery", "~String", 20);
      $this->RegisterVariableInteger("Sound", "Sound", "~String", 40);
      $this->RegisterVariableInteger("Log", "Log", "~HTML", 50);
			
			$this->EnableAction("Lock");	
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
		  	case "Lock":
				$payload = [ 'code' => 'switch_led' , 'value' => $Value ];
				$ret = $this->CPost($payload);
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

		public function lock(boolean $value)
		{
			// 1. Ticket ID holen
			$payload = [  ];
			$return =	$tuya->devices( $token )->post_password_ticket( $device_id , [ 'commands' => [ $payload ] ] );
			$ticket_ID = $return->result->ticket_id;

			var_dump($return);

			// mit TIcket ID öffnen
			$payload = [ 'ticket_id' => $ticket_ID ];
			$return =	$tuya->devices( $token )->post_remote_unlocking( $device_id , $payload);
			// Antwort prüfen ob msg vorhanden
			@$msg = $return->msg;
			//var_dump($msg);
			if ($msg <> "")
			{
			    SetValue(11655,$msg);
			}
			else
			{
			    SetValue(11655,"");
			}

		}
		
	}
?>
