<?php

// Schloss: Entsperren, Status, dauerhaftes Oeffnungslog

function unlockResponses($m)
{
    $m->testParent->responses = [
        (object) ['success' => true, 'result' => (object) ['ticket_id' => 'T1']],
        (object) ['success' => true],
    ];
}

$tests['Schloss: Abschliessen (true) loest kein Entsperren aus'] = function () {
    $m = make(TuyaBLELock::class);
    $m->RequestAction('Lock', true);
    check(!in_array('post_remote_unlocking', methods($m)), 'entsperrt: ' . json_encode(methods($m)));
};

$tests['Schloss: Entsperren laeuft ueber das IO'] = function () {
    $m = make(TuyaBLELock::class);
    unlockResponses($m);
    $m->RequestAction('Lock', false);
    $req = $m->testParent->requests;
    check(methods($m) === ['post_password_ticket', 'post_remote_unlocking'], 'Requests: ' . json_encode(methods($m)));
    check($req[1][2] === ['dev1', ['ticket_id' => 'T1']], 'Params: ' . json_encode($req[1][2]));
    check(TestRegistry::$sleeps === [], 'IPS_Sleep aufgerufen');
};

$tests['Schloss: Entsperren setzt nach kurzer Zeit per Timer auf zu'] = function () {
    $m = make(TuyaBLELock::class);
    unlockResponses($m);
    $m->RequestAction('Lock', false);
    check($m->value('Lock') === false, 'Lock sollte direkt nach dem Entsperren false sein');
    check(($m->timers['RelockTimer'][0] ?? 0) > 0, 'RelockTimer nicht gestartet');
    $m->RelockEvent();
    check($m->value('Lock') === true, 'Lock sollte nach RelockEvent wieder true sein');
    check($m->timers['RelockTimer'][0] === 0, 'RelockTimer laeuft weiter');
};

$tests['Schloss: fehlgeschlagenes Ticket entsperrt nicht'] = function () {
    $m = make(TuyaBLELock::class);
    $m->testParent->responses = [(object) ['success' => false, 'msg' => 'device offline']];
    $m->RequestAction('Lock', false);
    check(methods($m) === ['post_password_ticket'], 'Requests: ' . json_encode(methods($m)));
    check($m->value('Lock') === true && $m->value('Message') === 'device offline', 'Lock/Message');
};

$tests['Schloss: ApplyChanges ueberschreibt den Status nicht'] = function () {
    $m = make(TuyaBLELock::class);
    TestRegistry::$values[$m->variables['Lock']] = false;
    $m->ApplyChanges();
    check($m->value('Lock') === false, 'ApplyChanges hat Lock zurueckgesetzt');
};

$tests['Schloss: Status aus echter Cloud-Antwort'] = function () {
    $m = make(TuyaBLELock::class);
    push($m, true, ['residual_electricity' => 85, 'beep_volume' => 'low', 'lock_motor_state' => false]);
    check($m->value('Battery') === 85 && $m->value('Sound') === 'low', 'Battery/Sound');
};

$tests['Schloss: lock_motor_state false bleibt false'] = function () {
    $m = make(TuyaBLELock::class);
    push($m, true, ['lock_motor_state' => true]);
    check($m->value('MotorState') === true, 'true -> ' . var_export($m->value('MotorState'), true));
    push($m, true, ['lock_motor_state' => false]);
    check($m->value('MotorState') === false, 'false -> ' . var_export($m->value('MotorState'), true));
};

// ---- Oeffnungslog

// Antwort von get_openlogs; Eintraege als [update_time ms, code]
function logs(array $entries)
{
    $logs = [];
    foreach ($entries as [$t, $code]) {
        $logs[] = (object) ['update_time' => $t, 'status' => (object) ['code' => $code, 'value' => '1']];
    }
    return (object) ['success' => true, 'result' => (object) ['logs' => $logs, 'has_more' => false]];
}

function logRequests($m): array
{
    return array_values(array_filter($m->testParent->requests, fn ($r) => $r[1] === 'get_openlogs'));
}

function storedLog($m): array
{
    return json_decode($m->attributes['LogEntries'], true);
}

$tests['Log: neue Eintraege werden angehaengt'] = function () {
    $m = make(TuyaBLELock::class);
    $m->testParent->responses = [logs([[1700000002000, 'unlock_card'], [1700000001000, 'unlock_fingerprint']])];
    $m->RefreshLog();
    $m->testParent->responses = [logs([[1700000003000, 'unlock_password']])];
    $m->RefreshLog();
    check(array_column(storedLog($m), 't') === [1700000003000, 1700000002000, 1700000001000], 'Attribut: ' . $m->attributes['LogEntries']);
    $html = $m->value('Log');
    check(substr_count($html, '<br>') === 3 && str_contains($html, 'unlock_password') && str_contains($html, 'unlock_fingerprint'), 'HTML: ' . $html);
    check(strpos($html, 'unlock_password') < strpos($html, 'unlock_card'), 'neuester nicht zuerst');
};

$tests['Log: Duplikate werden verworfen'] = function () {
    $m = make(TuyaBLELock::class);
    $m->testParent->responses = [logs([[1700000001000, 'unlock_card']]), logs([[1700000001000, 'unlock_card']])];
    $m->RefreshLog();
    $m->RefreshLog();
    check(count(storedLog($m)) === 1, 'Eintraege: ' . count(storedLog($m)));
};

$tests['Log: hoechstens 50 Eintraege, neuester zuerst'] = function () {
    $m = make(TuyaBLELock::class);
    $entries = [];
    for ($i = 1; $i <= 60; $i++) {
        $entries[] = [1700000000000 + $i * 1000, 'unlock_card'];
    }
    $m->testParent->responses = [logs($entries)];
    $m->RefreshLog();
    check(count(storedLog($m)) === 50, 'Eintraege: ' . count(storedLog($m)));
    check(storedLog($m)[0]['t'] === 1700000060000, 'erster: ' . storedLog($m)[0]['t']);
};

$tests['Log: leere Antwort laesst Log stehen'] = function () {
    $m = make(TuyaBLELock::class);
    $m->testParent->responses = [logs([[1700000001000, 'unlock_card']])];
    $m->RefreshLog();
    $before = [$m->attributes['LogEntries'], $m->value('Log')];
    $m->testParent->responses = [logs([])];
    $m->RefreshLog();
    $m->testParent->responses = [(object) ['success' => false, 'msg' => 'error']];
    $m->RefreshLog();
    check([$m->attributes['LogEntries'], $m->value('Log')] === $before, 'Log veraendert');
};

$tests['Log: bestehendes Log bleibt bis zum ersten neuen Eintrag'] = function () {
    $m = make(TuyaBLELock::class);
    TestRegistry::$values[$m->variables['Log']] = 'altes Log';
    $m->testParent->responses = [logs([])];
    $m->RefreshLog();
    check($m->value('Log') === 'altes Log', 'altes Log geloescht');
};

$tests['Log: Abfrage ab letztem Eintrag'] = function () {
    $m = make(TuyaBLELock::class);
    $m->RefreshLog();
    $first = logRequests($m)[0][2];
    check($first[0] === 'dev1' && $first[1]['start_time'] >= time() - 7 * 24 * 60 * 60 - 1, 'erste Abfrage: ' . json_encode($first));
    check($first[1]['end_time'] >= time() - 1 && $first[1]['page_size'] === 20, 'end_time/page_size: ' . json_encode($first));
    $m->attributes['LogEntries'] = json_encode([['t' => time() * 1000 - 3600000, 'code' => 'unlock_card', 'value' => '1']]);
    $m->RefreshLog();
    check(logRequests($m)[1][2][1]['start_time'] === time() - 3600, 'start_time: ' . json_encode(logRequests($m)[1][2]));
};

$tests['Log: Eintrag ohne status wird uebersprungen'] = function () {
    $m = make(TuyaBLELock::class);
    $r = logs([[1700000001000, 'unlock_card']]);
    $r->result->logs[] = (object) ['update_time' => 1700000002000];
    $m->testParent->responses = [$r];
    $m->RefreshLog();
    check(count(storedLog($m)) === 1, 'Eintraege: ' . count(storedLog($m)));
};

$tests['Log: Status-Paket fuer Online-Schloss liest Log, offline nicht'] = function () {
    $m = make(TuyaBLELock::class);
    push($m, false, ['residual_electricity' => 50]);
    check(logRequests($m) === [], 'offline Log gelesen');
    push($m, true, ['residual_electricity' => 50]);
    check(count(logRequests($m)) === 1, 'online kein Log gelesen');
};

$tests['Log: LogEvent nach dem Oeffnen liest Log'] = function () {
    $m = make(TuyaBLELock::class);
    $m->LogEvent();
    check(count(logRequests($m)) === 1 && $m->timers['LogTimer'][0] === 0, 'Log nicht gelesen');
};
