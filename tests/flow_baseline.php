<?php
/**
 * Repeatable baseline for the existing human-designed Flow path.
 * This measures current contracts; it does not implement Loop behavior.
 */

$root = dirname(__DIR__);
require_once __DIR__ . '/suite_runner.php';

$dimensions = [
    'event_and_plugin_isolation' => 'tests/cdp_ma_hooks_test.php',
    'crm_to_flow_linkage' => 'tests/crm_flow_hooks_test.php',
    'canvas_conditions' => 'tests/canvas_crm_condition_test.php',
    'canvas_actions' => 'tests/canvas_actions_test.php',
    'conversion_feedback' => 'tests/growth_signal_test.php',
    'payment_hooks' => 'tests/content_payment_hooks_test.php',
    'transaction_recovery' => 'tests/txn_contract_test.php',
    'delivery_reliability' => 'tests/reliability_contract_test.php',
];

$report = suite_run(array_values($dimensions), $root);
$report['baseline'] = 'aitips-flow-v1';
$report['dimensions'] = [];
foreach ($dimensions as $dimension => $file) {
    foreach ($report['results'] as $result) {
        if ($result['file'] !== $file) continue;
        $report['dimensions'][$dimension] = [
            'ok' => $result['ok'],
            'duration_ms' => $result['duration_ms'],
        ];
        break;
    }
}

$reportPath = getenv('OF_FLOW_BASELINE_REPORT') ?: $root . '/artifacts/flow-baseline.json';
suite_write_report($report, $reportPath);
suite_print($report);
echo "Report: {$reportPath}\n";

exit($report['failed'] === 0 ? 0 : 1);
