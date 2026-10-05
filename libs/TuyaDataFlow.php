<?php

include_once __DIR__ . "/TuyaAPI.php";

// Weg zur Tuya Cloud ueber den TuyaClient (IO): SendDataToParent -> TuyaClient::ForwardData
// genutzt von den Geraetemodulen und dem Konfigurator
trait TuyaDataFlow
{
    protected function api(string $method, ...$params)
    {
        if (!$this->HasActiveParent()) {
            throw new TuyaApiException("TuyaClient (IO) is not active");
        }
        $response = $this->SendDataToParent(json_encode([
            'DataID' => '{C459F3BF-8570-E12D-9B2A-14F0343C7F37}',
            'Buffer' => json_encode(['method' => $method, 'params' => $params]),     // Symcon verlangt Buffer als String
        ]));
        $return = json_decode((string) $response);
        if ($return === null) {
            throw new TuyaApiException("No response from TuyaClient (IO)");
        }
        if (isset($return->error)) {
            throw new TuyaApiException($return->error);
        }
        return $return;
    }
}
