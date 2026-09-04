<?php
/** Shared subprocess runner for standalone OpenFlow contract tests. */

function suite_run(array $files, string $root): array
{
    $results = [];
    $started = hrtime(true);

    foreach ($files as $file) {
        $path = $root . '/' . ltrim($file, '/');
        $begin = hrtime(true);
        $output = [];
        $exitCode = 0;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
        $durationMs = (hrtime(true) - $begin) / 1_000_000;
        $results[] = [
            'file' => $file,
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'duration_ms' => round($durationMs, 2),
            'output_tail' => implode("\n", array_slice($output, -12)),
        ];
    }

    $durations = array_column($results, 'duration_ms');
    sort($durations, SORT_NUMERIC);
    $failed = array_values(array_filter($results, fn(array $r): bool => !$r['ok']));

    return [
        'generated_at' => gmdate('c'),
        'php_version' => PHP_VERSION,
        'total' => count($results),
        'passed' => count($results) - count($failed),
        'failed' => count($failed),
        'success_rate' => $results ? round((count($results) - count($failed)) / count($results), 4) : 0,
        'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
        'p95_test_duration_ms' => suite_percentile($durations, 0.95),
        'results' => $results,
    ];
}

function suite_percentile(array $values, float $percentile): float
{
    if (!$values) return 0;
    $index = (int)ceil(count($values) * $percentile) - 1;
    return round((float)$values[max(0, min($index, count($values) - 1))], 2);
}

function suite_write_report(array $report, string $path): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create report directory: {$dir}");
    }
    $json = json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($json === false) {
        throw new RuntimeException('Cannot encode report: ' . json_last_error_msg());
    }
    if (file_put_contents($path, $json . "\n") === false) {
        throw new RuntimeException("Cannot write report: {$path}");
    }
}

function suite_print(array $report): void
{
    foreach ($report['results'] as $result) {
        $mark = $result['ok'] ? '✓' : '✗';
        printf("%s %-48s %8.2f ms\n", $mark, $result['file'], $result['duration_ms']);
        if (!$result['ok']) echo $result['output_tail'] . "\n";
    }
    printf(
        "\n%d passed · %d failed · %.2f ms total · %.2f ms p95\n",
        $report['passed'],
        $report['failed'],
        $report['duration_ms'],
        $report['p95_test_duration_ms']
    );
}
