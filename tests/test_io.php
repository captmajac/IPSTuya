<?php

// TuyaClient (IO): Token-Cache, Durchlauf, ForwardData

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

$tests['IO: Token wird zwischengespeichert'] = function () {
    $io = makeIO();
    $io->forward('get_status', 'dev1');
    $io->forward('get_status', 'dev1');
    check($io->tokenCalls === 1, "Token {$io->tokenCalls}x geholt");
    check($io->requests[1][0] === 'tok1', 'zweiter Aufruf ohne gespeichertes Token');
};

$tests['IO: abgelaufenes Token wird erneuert'] = function () {
    $io = makeIO();
    $io->attributes['Token'] = 'alt';
    $io->attributes['TokenExpire'] = time() + 30;
    $io->forward('get_status', 'dev1');
    check($io->tokenCalls === 1, 'kein neues Token');
    check($io->requests[0][0] === 'tok1', 'altes Token benutzt');
};

$tests['IO: Code 1010 erneuert Token und wiederholt einmal'] = function () {
    $io = makeIO();
    $io->responses = [(object) ['success' => false, 'code' => 1010], (object) ['success' => true, 'result' => 'ok']];
    $res = $io->forward('get_status', 'dev1');
    check($io->tokenCalls === 2 && count($io->requests) === 2, "Tokens {$io->tokenCalls}, Requests " . count($io->requests));
    check($io->requests[1][0] === 'tok2', 'Wiederholung nicht mit neuem Token');
    check($res->result === 'ok', 'Antwort der Wiederholung fehlt');
};

$tests['IO: Code 1010 wird nur einmal wiederholt'] = function () {
    $io = makeIO();
    $io->responses = [(object) ['success' => false, 'code' => 1010], (object) ['success' => false, 'code' => 1010], (object) ['success' => true]];
    $res = $io->forward('get_status', 'dev1');
    check(count($io->requests) === 2, 'Requests: ' . count($io->requests));
    check($res->success === false, 'Fehlerantwort nicht durchgereicht');
};

$tests['IO: Durchlauf = ein get_app_list, ein Paket je Geraet'] = function () {
    $io = makeIO();
    $child = new CollectorChild(2);
    $io->testChildren = [$child];
    $io->responses = [(object) ['success' => true, 'result' => [
        cloudDevice('dev1', true, ['switch_1' => true]),
        cloudDevice('dev2', false, []),
        cloudDevice('dev3', true, ['va_humidity' => 40]),
    ]]];
    $io->Update();
    check(count($io->requests) === 1 && $io->requests[0][1] === 'get_app_list' && $io->requests[0][2] === ['app'], 'Requests: ' . json_encode($io->requests));
    check(count($child->received) === 3, 'Pakete: ' . count($child->received));
    $p = $child->received[0];
    check($p['DataID'] === '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'DataID: ' . $p['DataID']);
    check($p['Buffer'] === ['type' => 'state', 'id' => 'dev1', 'online' => true, 'status' => [['code' => 'switch_1', 'value' => true]]], 'Buffer: ' . json_encode($p['Buffer']));
    check($child->received[1]['Buffer']['online'] === false, 'dev2 nicht offline');
};

$tests['IO: success:false beim Durchlauf verteilt nichts'] = function () {
    $io = makeIO();
    $child = new CollectorChild(2);
    $io->testChildren = [$child];
    $io->responses = [(object) ['success' => false, 'code' => 1106, 'msg' => 'permission deny']];
    $io->Update();
    check($child->received === [], 'Pakete verteilt');
    check(count(TestRegistry::$log) > 0, 'nichts geloggt');
    check($io->currentStatus() === 102, 'Status: ' . $io->currentStatus());
};

$tests['IO: Token-Fehler setzt Status 201, Erfolg danach 102'] = function () {
    $io = makeIO();
    $io->tokenResponse = (object) ['success' => false, 'code' => 1004, 'msg' => 'sign invalid'];
    $io->Update();
    check($io->currentStatus() === 201, 'Status: ' . $io->currentStatus());
    $io->tokenResponse = null;
    $io->Update();
    check($io->currentStatus() === 102, 'Status nach Erfolg: ' . $io->currentStatus());
};

$tests['IO: fehlende Zugangsdaten setzen Status 104'] = function () {
    $io = makeIO(['SecretKey' => '']);
    check($io->currentStatus() === 104, 'Status: ' . $io->currentStatus());
    check($io->timers['UpdateTimer'][0] === 0, 'Timer laeuft');
};

$tests['IO: Timer startet erst bei Kernel bereit'] = function () {
    TestRegistry::$runlevel = KR_INIT;
    $io = makeIO();
    check($io->timers['UpdateTimer'][0] === 0, 'Timer vor Kernelstart: ' . $io->timers['UpdateTimer'][0]);
    TestRegistry::$runlevel = KR_READY;
    $io->MessageSink(time(), 0, IPS_KERNELSTARTED, []);
    check($io->timers['UpdateTimer'][0] === 15 * 60 * 1000, 'Timer nach Kernelstart: ' . $io->timers['UpdateTimer'][0]);
};

$tests['IO: ForwardData config liefert nur AppID'] = function () {
    $io = makeIO();
    $res = $io->forward('config');
    check(($res->AppID ?? null) === 'app', 'AppID: ' . json_encode($res));
    check(array_keys((array) $res) === ['AppID'], 'weitere Felder: ' . json_encode($res));
};

$tests['IO: ForwardData refresh loest Durchlauf aus'] = function () {
    $io = makeIO();
    $io->forward('refresh');
    check(count($io->requests) === 1 && $io->requests[0][1] === 'get_app_list', 'Requests: ' . json_encode($io->requests));
};

$tests['IO: Lib-Fehler kommt als error zurueck'] = function () {
    $io = makeIO();
    $io->requestError = new TuyaApiException('Method "get_does_not_exist" is not supported!');
    $res = $io->forward('get_does_not_exist');
    check(isset($res->error), 'Antwort: ' . json_encode($res));
};
