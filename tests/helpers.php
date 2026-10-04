<?php

// Gemeinsame Test-Helfer: Fake-IO ohne Netz, Geraete am Fake-IO

// IO ohne Netz: Lib-Aufrufe werden aufgezeichnet und aus einer Warteschlange beantwortet
class FakeIO extends TuyaClient
{
    public array $requests = [];
    public array $responses = [];
    public int $tokenCalls = 0;
    public $tokenResponse = null;
    public ?Throwable $requestError = null;

    protected function request(string $token, string $method, array $params)
    {
        $this->requests[] = [$token, $method, $params];
        if ($this->requestError) {
            throw $this->requestError;
        }
        return array_shift($this->responses) ?? (object) ['success' => true, 'result' => []];
    }

    protected function requestToken()
    {
        $this->tokenCalls++;
        return $this->tokenResponse
            ?? (object) ['success' => true, 'result' => (object) ['access_token' => 'tok' . $this->tokenCalls, 'expire_time' => 7200]];
    }

    public function forward(string $method, ...$params)
    {
        return json_decode($this->ForwardData(json_encode([
            'DataID' => '{C459F3BF-8570-E12D-9B2A-14F0343C7F37}',
            'Buffer' => ['method' => $method, 'params' => $params],
        ])));
    }
}

class CollectorChild extends IPSModule
{
    public array $received = [];
    public function ReceiveData($JSONString) { $this->received[] = json_decode($JSONString, true); }
}

function makeIO(array $props = [])
{
    $io = new FakeIO(1);
    $io->Create();
    $io->properties = array_merge($io->properties, ['AccessKey' => 'a', 'SecretKey' => 's', 'BaseUrl' => 'https://example.invalid', 'AppID' => 'app'], $props);
    $io->ApplyChanges();
    return $io;
}

function cloudDevice(string $id, bool $online, array $dps)
{
    $status = [];
    foreach ($dps as $code => $value) {
        $status[] = (object) ['code' => $code, 'value' => $value];
    }
    return (object) ['id' => $id, 'name' => $id, 'online' => $online, 'local_key' => 'k', 'model' => 'm', 'status' => $status];
}

// Geraete-Instanz am Fake-IO, DeviceID dev1
function make(string $class, array $props = [])
{
    $io = makeIO();
    $m = new $class(2);
    $m->Create();
    $m->properties = array_merge($m->properties, ['DeviceID' => 'dev1'], $props);
    $m->testParent = $io;
    $io->testChildren = [$m];
    $m->ApplyChanges();
    return $m;
}

// Status ueber einen IO-Durchlauf zustellen
function push($m, bool $online, array $dps, string $id = 'dev1')
{
    $m->testParent->responses = [(object) ['success' => true, 'result' => [cloudDevice($id, $online, $dps)]]];
    $m->testParent->Update();
}

// gesendete Kommandos (post_commands) einer Instanz
function sent($m): array
{
    $out = [];
    foreach ($m->testParent->requests as [$token, $method, $params]) {
        if ($method === 'post_commands') {
            $out[] = $params[1]['commands'][0];
        }
    }
    return $out;
}

function methods($m): array
{
    return array_column($m->testParent->requests, 1);
}
