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
			//$this->CreateVarProfileModus();
			
			$this->RegisterVariableBoolean("Lock", "Lock", "~Lock", 10 );
			$this->RegisterVariableString("Message", "Message", "", 30);
			$this->RegisterVariableString("MotorState", "MotorState", "", 35);
			$this->RegisterVariableInteger("Battery", "Battery", "~Battery.100", 20);
      			$this->RegisterVariableString("Sound", "Sound", "", 40);
      			$this->RegisterVariableString("Log", "Log", "~HTMLBox", 50);

			$this->setDefaults();
			
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
				$ret = $this->unlock();
			break;
	
			}

			// Neuen Wert in die Statusvariable schreiben, wird über die Rückmeldung korrigiert
			if ($ret == true)
			{
				IPS_LogMessage("BLE","erste".$Value);
				SetValue($this->GetIDForIdent($Ident), $Value);
				if ($Ident=="Lock" and $Value == false)
				{	
					IPS_LogMessage("BLE","zerit");
					// da nur kurz aufgeschlossen wird Status nach 2 Sek. wieder auf geschlossen setzen 
					IPS_Sleep(2000);
				 	SetValue($this->GetIDForIdent($Ident), true);
				 	// todo: status log timer anstelle spleep starten damit das log aktualisiert wird
				 	IPS_Sleep(15*1000);		// wait for cloud update log
				 	$this->updateState();
					$this->readLockLog();
				}
			}
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

		public function unlock()
		{
			// 1. Ticket ID holen
			$tuya = $this->getTuyaClass();
			$token = $this->getToken();
			$device_id = $this->ReadPropertyString("DeviceID");
			$payload = [  ];
			
			$return = $tuya->devices( $token )->post_password_ticket( $device_id );
			$ticket_ID = $return->result->ticket_id;
			
			// 2. mit Ticket ID öffnen
			$payload = [ 'ticket_id' => $ticket_ID ];
			$return = $tuya->devices( $token )->post_remote_unlocking( $device_id, $payload);
			
			// Antwort prüfen ob msg vorhanden
			@$msg = $return->msg;
			if ($msg <> "")
				SetValue($this->GetIDForIdent("Message"), $msg);
			else
				SetValue($this->GetIDForIdent("Message"), "");

			return boolval($return->success);

		}

		public function updateState()
		{
			$tuya = $this->getTuyaClass();
			$token = $this->getToken();
			$device_id = $this->ReadPropertyString("DeviceID");
			$return = $tuya->devices( $token )->get_status( $device_id );

			// motor maybe block state
			$motorstate = "".$return->result[$key]->value;
			if ($motorstate == "")
				$motorstate = "OK";
			SetValue($this->GetIDForIdent("MotorState"), $motorstate);
			// info sound volume
			SetValue($this->GetIDForIdent("Sound"), "".$return->result[$key]->value);
			// bat level
			SetValue($this->GetIDForIdent("Battery"), inval( $return->result[$key]->value) );
			
		}

		public function readLockLog()
		{
			$tuya = $this->getTuyaClass();
			$token = $this->getToken();
			
			$start_time =time()-7*24*60*60;
			$end_time = time();
			
			$device_id = $this->ReadPropertyString("DeviceID");
			$payload = [ 'page_no' => 0 , 'page_size' => 20, 'start_time' => $start_time, 'end_time' => $end_time];
			$return = $tuya->devices( $token )->get_openlogs( $device_id , $payload);
			$logs = $return->result->logs;

			$out = "";
			foreach ($logs as &$value) {
			    $msg =  $value->status->code ;                  	// message
			    $tmsp =  $value->update_time ;          		// timestamp 
			    $tmspf = date("d.m.Y H:i:s", ($tmsp/1000) );	// format
			    $out = $out.$tmspf." - ".$msg."<br>";   		// html cr
			}
			SetValue($this->GetIDForIdent("Log"), $out);
		}
		
		public function setDefaults()
		{
			// default lock value
			SetValue($this->GetIDForIdent("Lock"), true);
			
		}
		
	}
?>
