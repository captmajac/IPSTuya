<?php

//Tuya Klassen einbinden
include_once __DIR__ . "/../libs/TuyaAPI.php";

// Gateway zur Tuya Cloud: einzige Stelle mit Token und API-Aufrufen.
// Verteilt den Status aller Geraete an die Geraete-Instanzen und fuehrt deren Aufrufe aus.
class TuyaClient extends IPSModule
{
    const CHILD_DATAID = '{018EF6B5-AB94-40C6-AA53-46943E824ACF}';
    const TOKEN_ERRORS = [1010, 1011];      // token invalid / expired

    // erstellung
    public function Create()
    {
        // Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("AccessKey", "");
        $this->RegisterPropertyString("SecretKey", "");
        $this->RegisterPropertyString("BaseUrl", ""); // z.b. 'https://openapi.tuyaeu.com'
        $this->RegisterPropertyString("AppID", "");
        $this->RegisterPropertyInteger("Interval", 15);        // minuten

        $this->RegisterAttributeString("Token", "");
        $this->RegisterAttributeInteger("TokenExpire", 0);

        $this->RegisterTimer("UpdateTimer", 0, "Tuya_Update(\$_IPS['TARGET']);");
    }

    // changes der instanz
    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();

        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // neue zugangsdaten -> altes token verwerfen
        $this->WriteAttributeString("Token", "");
        $this->WriteAttributeInteger("TokenExpire", 0);

        if (!$this->isConfigured()) {
            $this->SetTimerInterval("UpdateTimer", 0);
            $this->SetStatus(104);
            return;
        }
        $this->SetStatus(102);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->startTimer();
        } else {
            $this->SetTimerInterval("UpdateTimer", 0);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message == IPS_KERNELSTARTED && $this->isConfigured()) {
            $this->startTimer();
        }
    }

    // ein durchlauf: alle geraete mit status holen und je geraet ein paket an die instanzen
    public function Update()
    {
        try {
            $return = $this->call('get_app_list', [$this->ReadPropertyString("AppID")]);
        } catch (TuyaApiException $e) {
            IPS_LogMessage("TuyaClient", "Update Error: " . $e->getMessage());
            return;
        }
        if (empty($return->success) || !is_array($return->result ?? null)) {
            IPS_LogMessage("TuyaClient", "Update Error: " . ($return->msg ?? "invalid response"));
            return;
        }

        $this->SendDebug("Update", count($return->result) . " Geräte", 0);
        foreach ($return->result as $device) {
            $this->SendDebug("Update", ($device->name ?? $device->id) . " | " . (!empty($device->online) ? "online" : "offline") . " | " . count($device->status ?? []) . " Datenpunkte", 0);
            $this->SendDataToChildren(json_encode([
                'DataID' => self::CHILD_DATAID,
                'Buffer' => json_encode([           // Symcon verlangt Buffer als String
                    'type' => 'state',
                    'id' => $device->id,
                    'online' => (bool) $device->online,
                    'status' => $device->status ?? [],
                ]),
            ]));
        }
    }

    // aufruf einer geraete-instanz: {"method": ..., "params": [...]}
    public function ForwardData($JSONString)
    {
        $data = json_decode($JSONString, true);
        $buffer = json_decode($data['Buffer'] ?? '', true);     // assoziativ, die lib erkennt payloads nur als array
        $method = $buffer['method'] ?? '';
        $params = $buffer['params'] ?? [];

        switch ($method) {
            case 'refresh':
                $this->Update();
                return json_encode(['success' => true]);
            case 'config':
                return json_encode(['AppID' => $this->ReadPropertyString("AppID")]);
        }

        try {
            return json_encode($this->call($method, $params));
        } catch (TuyaApiException $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    // default debug message
    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_array($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
            }
        } elseif (is_object($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . "." . $Key, $DebugData, 0);
            }
        } else {
            parent::SendDebug($Message, $Data, $Format);
        }
    }

    // api aufruf mit gespeichertem token, bei ungueltigem token einmal mit neuem wiederholen
    private function call(string $method, array $params)
    {
        $return = $this->request($this->token(), $method, $params);
        if (isset($return->success) && !$return->success && in_array($return->code ?? 0, self::TOKEN_ERRORS)) {
            $this->WriteAttributeString("Token", "");
            $return = $this->request($this->token(), $method, $params);
        }
        // geraeteliste enthaelt local_key, ip und standort: nicht ins debug
        if ($method !== 'get_app_list') {
            $this->SendDebug($method, $return, 0);
        }
        return $return;
    }

    // einzige stelle mit api aufruf
    protected function request(string $token, string $method, array $params)
    {
        return $this->tuya()->devices($token)->$method(...$params);
    }

    protected function requestToken()
    {
        return $this->tuya()->token->get_new();
    }

    private function token(): string
    {
        $token = $this->ReadAttributeString("Token");
        if ($token !== "" && $this->ReadAttributeInteger("TokenExpire") > time() + 60) {
            return $token;
        }

        $return = $this->requestToken();
        if (empty($return->success) || !isset($return->result->access_token)) {
            $this->SetStatus(201);
            throw new TuyaApiException("Token Error: " . ($return->msg ?? "invalid response"));
        }
        if ($this->GetStatus() == 201) {
            $this->SetStatus(102);
        }

        $this->WriteAttributeString("Token", $return->result->access_token);
        $this->WriteAttributeInteger("TokenExpire", time() + (int) $return->result->expire_time);
        return $return->result->access_token;
    }

    private function tuya()
    {
        return new TuyaApi([
            "accessKey" => $this->ReadPropertyString("AccessKey"),
            "secretKey" => $this->ReadPropertyString("SecretKey"),
            "baseUrl" => $this->ReadPropertyString("BaseUrl"),
        ]);
    }

    private function isConfigured(): bool
    {
        foreach (["AccessKey", "SecretKey", "BaseUrl", "AppID"] as $name) {
            if ($this->ReadPropertyString($name) === "") {
                return false;
            }
        }
        return true;
    }

    private function startTimer()
    {
        $this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("Interval") * 60 * 1000);
    }
}
