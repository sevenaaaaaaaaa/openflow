<?php
/**
 * 上线体检契约：检查项齐全、等级合法、错误计数正确
 * php tests/health_check_test.php
 */
require_once __DIR__ . '/../lib/HealthCheck.php';

$pass = 0; $fail = 0;
if (!function_exists('ok')) {
    function ok(bool $c, string $msg): void {
        global $pass, $fail;
        if ($c) { $pass++; } else { $fail++; echo "  ✗ {$msg}\n"; }
    }
}

$root = dirname(__DIR__);
$rows = health_check_rows($root);
$names = array_column($rows, 'name');

ok(count($rows) >= 7, '至少 7 项检查');
ok(in_array('PHP 版本', $names, true), '含 PHP 版本');
ok(in_array('PHP 扩展', $names, true), '含 PHP 扩展');
ok(in_array('data 目录可写', $names, true), '含 data 可写');
ok(in_array('安装状态', $names, true), '含安装状态');
ok(in_array('版本文件', $names, true), '含版本文件');
foreach ($rows as $row) {
    ok(in_array($row['level'], ['ok', 'warn', 'err'], true), '等级合法：' . $row['name']);
    ok(isset($row['detail']) && is_string($row['detail']), '有 detail：' . $row['name']);
}
ok(health_check_errors($rows) === count(array_filter($rows, fn ($r) => $r['level'] === 'err')), '错误计数一致');

echo "\n通过 {$pass}，失败 {$fail}\n";
exit($fail === 0 ? 0 : 1);
