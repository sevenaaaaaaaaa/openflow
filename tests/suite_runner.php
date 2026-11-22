<?php
/** Shared subprocess runner for standalone OpenFlow contract tests. */

function suite_run(array $files, string $root): array
{
    $results = [];
    $started = hrtime(true);
    // 每个契约测试跑在独立的 data/ + uploads/ 上：共享 data/ 会被前一个测试写脏，
    // 导致同一份代码在本地（有残留数据）与 CI（干净检出）结果不一致。
    $baseline = suite_data_baseline($root);
    $tmpRoot = sys_get_temp_dir() . '/of-suite-' . getmypid();
    $seq = 0;

    foreach ($files as $file) {
        $path = $root . '/' . ltrim($file, '/');
        $seq++;
        $workDir = $tmpRoot . '/' . $seq . '-' . preg_replace('/[^a-z0-9]+/i', '-', basename($file, '.php'));
        $dataDir = $workDir . '/data';
        $uploadDir = $workDir . '/uploads';
        @mkdir($dataDir, 0777, true);
        @mkdir($uploadDir, 0777, true);
        foreach ($baseline as $rel) {
            $src = $root . '/' . $rel;
            $dst = $workDir . '/' . $rel;
            @mkdir(dirname($dst), 0777, true);
            if (is_file($src)) @copy($src, $dst);
        }

        $begin = hrtime(true);
        $output = [];
        $exitCode = 0;
        $cmd = 'OF_DATA_DIR=' . escapeshellarg($dataDir)
            . ' OF_UPLOAD_DIR=' . escapeshellarg($uploadDir)
            . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' 2>&1';
        exec($cmd, $output, $exitCode);
        $durationMs = (hrtime(true) - $begin) / 1_000_000;
        byte_suite_cleanup($workDir);
        $results[] = [
            'file' => $file,
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'duration_ms' => round($durationMs, 2),
            'output_tail' => implode("\n", array_slice($output, -12)),
        ];
    }
    byte_suite_cleanup($tmpRoot);

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

function suite_data_baseline(string $root): array
{
    $out = [];
    $code = 0;
    exec('git -C ' . escapeshellarg($root) . ' ls-files -- data 2>/dev/null', $out, $code);
    if ($code !== 0) return [];
    return array_values(array_filter($out, static fn(string $f): bool => str_starts_with($f, 'data/') && is_file($root . '/' . $f)));
}

function byte_suite_cleanup(string $dir): void
{
    if ($dir === '' || !is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
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
