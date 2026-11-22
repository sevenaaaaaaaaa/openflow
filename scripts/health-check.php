#!/usr/bin/env php
<?php
/**
 * 上线体检（CLI）
 * 用法：php scripts/health-check.php
 * 退出码：0 = 无错误项；1 = 有错误项（warn 不影响退出码）。
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/lib/HealthCheck.php';

$rows = health_check_rows($root);
$marks = ['ok' => '✓', 'warn' => '!', 'err' => '✗'];
foreach ($rows as $row) {
    $mark = $marks[$row['level']] ?? '?';
    echo $mark, ' ', $row['name'], '：', $row['detail'], PHP_EOL;
}

$errors = health_check_errors($rows);
echo PHP_EOL, $errors === 0 ? '无错误项。' : '有 ' . $errors . ' 项错误需要处理。', PHP_EOL;
exit($errors === 0 ? 0 : 1);
