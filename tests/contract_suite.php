<?php
/** Run every standalone *_test.php contract as a blocking suite. */

$root = dirname(__DIR__);
require_once __DIR__ . '/suite_runner.php';

$files = array_map(
    fn(string $path): string => 'tests/' . basename($path),
    glob(__DIR__ . '/*_test.php') ?: []
);
sort($files);

$report = suite_run($files, $root);
$reportPath = getenv('OF_TEST_REPORT');
if ($reportPath) suite_write_report($report, $reportPath);
suite_print($report);

exit($report['failed'] === 0 ? 0 : 1);
