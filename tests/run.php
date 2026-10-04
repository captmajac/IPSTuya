<?php

// Aufruf: php tests/run.php
// Testet das Modulverhalten gegen den Symcon-Stub; Cloud-Aufrufe werden in Testklassen ersetzt.

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../io/module.php';
require_once __DIR__ . '/../BLTGWLock/module.php';
require_once __DIR__ . '/../RGBWLED/module.php';
require_once __DIR__ . '/../Switch/module.php';
require_once __DIR__ . '/../THSensor/module.php';

require_once __DIR__ . '/helpers.php';

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
