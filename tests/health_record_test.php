<?php
/**
 * 系统体检 契约 —— 历史/趋势/新增检查项
 *   php tests/health_record_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-health-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/HealthRecord.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "系统体检\n";

$checks = ['系统环境' => [['status' => 'pass', 'weight' => 3]], '安全' => [['status' => 'warn', 'weight' => 2]]];
health_record_add(['score' => 88, 'grade' => '良好', 'stat' => ['pass' => 1, 'warn' => 1, 'fail' => 0]], $checks);
check('首次体检入库', count(health_history()) === 1);
health_record_add(['score' => 90, 'grade' => '健康', 'stat' => ['pass' => 2, 'warn' => 0, 'fail' => 0]], $checks);
check('一小时内覆盖不新增', count(health_history()) === 1);

// 伪造一条更早的历史，模拟"上次"得分
$list = json_read(health_history_file());
$list[0]['ts'] = time() - 7200;
json_write(health_history_file(), $list);
health_record_add(['score' => 95, 'grade' => '健康', 'stat' => ['pass' => 2, 'warn' => 0, 'fail' => 0]], $checks);
check('超时后新增一条', count(health_history()) === 2);

$trend = health_trend();
check('趋势取最近得分', $trend['last'] === 95);
check('趋势给出变化量', $trend['delta'] === 5, (string)$trend['delta']);
check('趋势给出均值', $trend['avg'] === 93, (string)$trend['avg']);
check('趋势点数量正确', count($trend['points']) === 2);
check('趋势折线出 SVG', strpos(health_trend_svg($trend['points']), '<svg') === 0);

// 结构守卫：cron 写运行时间 + 体检页含新分类
$cron = file_get_contents(__DIR__ . '/../api/cron.php');
check('cron 记录运行时间', strpos($cron, "cron-last.json") !== false);
$hc = file_get_contents(__DIR__ . '/../admin/health-check.php');
check('体检含定时任务分类', strpos($hc, '定时任务与运维') !== false);
check('体检含存储分类', strpos($hc, '存储占用') !== false);
check('体检页展示趋势', strpos($hc, 'health_trend_svg(') !== false);
check('体检页并入自优化建议', strpos($hc, 'SelfEvolve::state()') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
