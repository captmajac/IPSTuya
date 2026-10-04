<?php

// Aufruf: php tests/run.php
// Testet das Modulverhalten gegen den Symcon-Stub; Cloud-Aufrufe werden in Testklassen ersetzt.

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../io/module.php';
require_once __DIR__ . '/../BLTGWLock/module.php';
require_once __DIR__ . '/../RGBWLED/module.php';
require_once __DIR__ . '/../Switch/module.php';
require_once __DIR__ . '/../THSensor/module.php';

// Cloud-Antwort wie von /v1.0/devices/{id}/status
function status(array $dps)
{
    $result = [];
    foreach ($dps as $code => $value) {
        $result[] = (object) ['code' => $code, 'value' => $value];
    }
    return (object) ['success' => true, 'result' => $result];
}

function devices(array $online)
{
    $list = [];
    foreach ($online as $id => $state) {
        $list[] = (object) ['ID' => $id, 'Online' => $state, 'Name' => $id, 'Model' => '', 'LocalKey' => ''];
    }
    return $list;
}

// Gemeinsame Cloud-Ersetzung fuer alle Testklassen
trait FakeCloud
{
    public array $sent = [];
    public $stateResponse = null;
    public array $deviceList = [];
    public ?Throwable $cloudError = null;

    public function CPost(array $payload)
    {
        if ($this->cloudError) {
            throw $this->cloudError;
        }
        $this->sent[] = $payload;
        return true;
    }
    public function getState()
    {
        if ($this->cloudError) {
            throw $this->cloudError;
        }
        return $this->stateResponse;
    }
    public function getToken() { return 'token'; }
    public function readDeviceList(string $token, string $app_id) { return $this->deviceList; }
}

class TestLock extends TuyaBLELock
{
    use FakeCloud;
    public int $unlockCalls = 0;
    public function unlock() { $this->unlockCalls++; return true; }
    public function readLockLog() {}
}
class TestRGBW extends TuyaLEDRGBW { use FakeCloud; }
class TestSwitch extends TuyaSwitch { use FakeCloud; }
class TestTH extends THSensor { use FakeCloud; }

function make(string $class, array $props = [])
{
    $m = new $class(1);
    $m->Create();
    $m->properties = array_merge($m->properties, ['DeviceID' => 'dev1'], $props);
    $m->ApplyChanges();
    $m->deviceList = devices(['dev1' => true]);
    return $m;
}

// ---------------------------------------------------------------- Tests

$tests = [];
foreach (glob(__DIR__ . '/test_*.php') as $file) {
    require $file;
}

// ---------------------------------------------------------------- Runner

class CheckFailed extends Exception {}
function check(bool $ok, string $msg) { if (!$ok) throw new CheckFailed($msg); }

$failed = 0;
foreach ($tests as $name => $test) {
    TestRegistry::reset();
    try {
        $test();
        echo "  ok    $name\n";
    } catch (Throwable $e) {
        $failed++;
        $where = $e instanceof CheckFailed ? '' : ' [' . get_class($e) . ' in ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
        echo "  FAIL  $name: {$e->getMessage()}$where\n";
    }
}
echo "\n" . (count($tests) - $failed) . '/' . count($tests) . " bestanden\n";
exit($failed ? 1 : 0);
