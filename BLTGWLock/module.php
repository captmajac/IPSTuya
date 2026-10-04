<?php
require_once __DIR__ . '/../Generic/module.php';  // Base Module.php

class TuyaBLELock extends TuyaGeneric
{
    const LOG_MAX = 50;             // eintraege im dauerhaften log
    const LOG_FIRST_DAYS = 7;       // zeitraum der ersten abfrage

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // dauerhaftes oeffnungslog [{t: ms, code, value}], neueste zuerst
        $this->RegisterAttributeString("LogEntries", "[]");

        // da nur kurz aufgeschlossen wird Status nach 2 Sek. wieder auf geschlossen setzen, danach log nachlesen
        $this->RegisterTimer("RelockTimer", 0, "Tuya_RelockEvent(\$_IPS['TARGET']);");
        $this->RegisterTimer("LogTimer", 0, "Tuya_LogEvent(\$_IPS['TARGET']);");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $isNew = @$this->GetIDForIdent("Lock") === false;

        $this->RegisterVariableBoolean("Lock", "Lock", "~Lock", 10);
        $this->RegisterVariableString("Message", "Message", "", 30);
        $this->RegisterVariableBoolean("MotorState", "MotorState", "~Lock.Reversed", 35);
        $this->RegisterVariableInteger("Battery", "Battery", "~Battery.100", 20);
        $this->RegisterVariableString("Sound", "Sound", "", 40);
        $this->RegisterVariableString("Log", "Log", "~HTMLBox", 50);

        //Default Values nur beim ersten Anlegen
        if ($isNew) {
            $this->setDefaults();
        }

        // optik
        IPS_SetIcon($this->GetIDForIdent("Message"), "Information");
        IPS_SetIcon($this->GetIDForIdent("MotorState"), "Alert");
        IPS_SetIcon($this->GetIDForIdent("Sound"), "Speaker");
        IPS_SetIcon($this->GetIDForIdent("Log"), "Database");

        $this->EnableAction("Lock");
    }

    protected function handleAction($Ident, $Value)
    {
        switch ($Ident) {
            case "Lock":
                // nur oeffnen ist ueber die cloud moeglich, das schloss verriegelt selbst
                if ($Value == false && $this->unlock()) {
                    $this->SetValue("Lock", false);
                    $this->SetTimerInterval("RelockTimer", 2 * 1000);
                }
                break;
        }
    }

    // timer: status nach dem oeffnen wieder auf geschlossen
    public function RelockEvent()
    {
        $this->SetTimerInterval("RelockTimer", 0);
        $this->SetValue("Lock", true);
        $this->SetTimerInterval("LogTimer", 15 * 1000);        // wait for cloud update log
    }

    // timer: log nach dem oeffnen nachlesen
    public function LogEvent()
    {
        $this->SetTimerInterval("LogTimer", 0);
        try {
            $this->RefreshLog();
        } catch (TuyaApiException $e) {
            IPS_LogMessage("TuyaDevice", "Log Error Device=" . $this->ReadPropertyString("DeviceID") . ": " . $e->getMessage());
        }
    }

    public function unlock()
    {
        // 1. Ticket ID holen
        $device_id = $this->ReadPropertyString("DeviceID");

        $return = $this->api('post_password_ticket', $device_id);
        if (!isset($return->result->ticket_id)) {
            $this->SetValue("Message", $return->msg ?? "no ticket");
            return false;
        }
        $ticket_ID = $return->result->ticket_id;

        // 2. mit Ticket ID öffnen
        $payload = ['ticket_id' => $ticket_ID];
        $return = $this->api('post_remote_unlocking', $device_id, $payload);

        // Antwort prüfen ob msg vorhanden
        $this->SetValue("Message", $return->msg ?? "");

        return (bool) ($return->success ?? false);
    }

    // lock spezifische werte aus dem status paket des IO
    protected function applyStatus($state)
    {
        // motor maybe block state
        $motorstate = $this->getDP($state, 'lock_motor_state');
        if ($motorstate !== null) {
            $this->SetValue("MotorState", (bool) $motorstate);       // false = locked
        }

        // info sound volume
        $sound = $this->getDP($state, 'beep_volume');
        if ($sound !== null) {
            $this->SetValue("Sound", (string) $sound);
        }

        // bat level
        $battery = $this->getDP($state, 'residual_electricity');
        if ($battery !== null) {
            $this->SetValue("Battery", (int) $battery);
        }

        // log nachlesen, nicht bei schlanken durchlaeufen (von befehlen an offline geraete angestossen)
        if ($state->full ?? true) {
            $this->RefreshLog();
        }
    }

    // neue eintraege aus der cloud an das dauerhafte log anhaengen
    public function RefreshLog()
    {
        $entries = json_decode($this->ReadAttributeString("LogEntries"), true) ?: [];

        $start_time = time() - self::LOG_FIRST_DAYS * 24 * 60 * 60;
        if ($entries) {
            $start_time = max($start_time, intdiv($entries[0]['t'], 1000));     // ab letztem eintrag, duplikate fallen unten raus
        }
        $payload = ['page_no' => 0, 'page_size' => 20, 'start_time' => $start_time, 'end_time' => time()];
        $return = $this->api('get_openlogs', $this->ReadPropertyString("DeviceID"), $payload);

        $known = [];
        foreach ($entries as $entry) {
            $known[$entry['t'] . '|' . $entry['code']] = true;
        }
        $added = false;
        foreach ($return->result->logs ?? [] as $log) {
            if (!isset($log->update_time, $log->status->code)) {
                continue;
            }
            $key = $log->update_time . '|' . $log->status->code;
            if (isset($known[$key])) {
                continue;
            }
            $known[$key] = true;
            $entries[] = ['t' => (int) $log->update_time, 'code' => $log->status->code, 'value' => $log->status->value ?? null];
            $added = true;
        }
        if (!$added) {
            return;     // leere antwort aendert nichts
        }

        usort($entries, fn ($a, $b) => $b['t'] <=> $a['t']);
        $entries = array_slice($entries, 0, self::LOG_MAX);
        $this->WriteAttributeString("LogEntries", json_encode($entries));

        $out = "";
        foreach ($entries as $entry) {
            $out .= date("d.m.Y H:i:s", intdiv($entry['t'], 1000)) . " - " . $entry['code'] . "<br>";   // html cr
        }
        $this->SetValue("Log", $out);
    }

    public function setDefaults()
    {
        // default lock value
        $this->SetValue("Lock", true);
    }
}
