<?php
/**
 * 简易测试运行器 — 不依赖 PHPUnit
 * 用法: php tests/run.php
 */

$testDir = __DIR__;
$results = ['pass' => 0, 'fail' => 0, 'errors' => []];

function test(string $name, callable $fn): void {
    global $results;
    try {
        $fn();
        $results['pass']++;
        echo "  ✓ {$name}\n";
    } catch (Throwable $e) {
        $results['fail']++;
        $results['errors'][] = ['name' => $name, 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
        echo "  ✗ {$name}\n    → {$e->getMessage()}\n";
    }
}

function assert_eq($expected, $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        $expStr = var_export($expected, true);
        $actStr = var_export($actual, true);
        throw new \Exception("断言失败: {$msg}\n    期望: {$expStr}\n    实际: {$actStr}");
    }
}

function assert_true(bool $val, string $msg = ''): void {
    if (!$val) throw new \Exception("断言失败: {$msg} — 期望 true, 实际 false");
}

function assert_false(bool $val, string $msg = ''): void {
    if ($val) throw new \Exception("断言失败: {$msg} — 期望 false, 实际 true");
}

function assert_throws(string $exceptionClass, callable $fn, string $msg = ''): void {
    try {
        $fn();
        throw new \Exception("断言失败: {$msg} — 未抛出异常");
    } catch (\Throwable $e) {
        if (!($e instanceof $exceptionClass)) {
            throw new \Exception("断言失败: {$msg} — 抛出了 " . get_class($e) . " 而非 {$exceptionClass}");
        }
    }
}

// 加载核心
require_once __DIR__ . '/../admin/config.php';

echo "═══════════════════════════════════════\n";
echo " OpenFlow 单元测试\n";
echo "═══════════════════════════════════════\n\n";

// 扫描所有测试文件
$files = glob($testDir . '/test_*.php');
sort($files);

foreach ($files as $file) {
    $name = basename($file, '.php');
    echo "▸ {$name}\n";
    require $file;
    echo "\n";
}

echo "═══════════════════════════════════════\n";
echo " 结果: {$results['pass']} 通过, {$results['fail']} 失败\n";
echo "═══════════════════════════════════════\n";

if ($results['fail'] > 0) {
    echo "\n失败详情:\n";
    foreach ($results['errors'] as $e) {
        echo "  ✗ {$e['name']}\n    {$e['error']}\n    at {$e['file']}:{$e['line']}\n\n";
    }
    exit(1);
}
