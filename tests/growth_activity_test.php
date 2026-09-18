<?php
/**
 * GrowthEngine 活跃时段记录 —— recordActivity()/activityHours() 契约
 *   php tests/growth_activity_test.php
 *
 * 背景：api/growth-signal.php 一直在调用不存在的 GrowthEngine::recordActivity()，
 * 被 try/catch 吞掉 → 活跃时段从未落盘。本测试锁住行为，防回归。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-gact-' . getmypid());
@mkdir(DATA_DIR . '/growth', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/GrowthEngine.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "GrowthEngine 活跃时段\n";

check('方法存在（此前缺失导致调用静默失败）', method_exists('GrowthEngine', 'recordActivity'));
check('记录前为空', GrowthEngine::activityHours() === []);

GrowthEngine::recordActivity(9);
GrowthEngine::recordActivity(9);
GrowthEngine::recordActivity(21);

$h = GrowthEngine::activityHours();
check('按小时累计', ($h[9] ?? 0) === 2 && ($h[21] ?? 0) === 1, json_encode($h));
check('结果按小时升序', array_keys($h) === [9, 21], json_encode(array_keys($h)));

// 非法输入必须被忽略（不能因为脏数据把直方图写坏）
GrowthEngine::recordActivity(-1);
GrowthEngine::recordActivity(24);
GrowthEngine::recordActivity(999);
$h2 = GrowthEngine::activityHours();
check('非法小时被忽略', $h2 === $h, json_encode($h2));

// 落盘持久化（新实例读取同一文件）
check('已持久化到 state', (GrowthEngine::state()['activity'][9] ?? 0) === 2);

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
