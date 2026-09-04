<?php
/** Print a read-only JSON health report for structured automation shadow logs. */

$root = dirname(__DIR__);
$input = $argv[1] ?? ($root . '/data/automation-log.json');
$raw = is_file($input) ? file_get_contents($input) : '[]';
$logs = json_decode($raw ?: '[]', true);
if (!is_array($logs)) {
    fwrite(STDERR, "Invalid automation log JSON: {$input}\n");
    exit(2);
}

require_once $root . '/lib/ShadowRunObservation.php';
$report = shadow_run_observe($logs);
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
