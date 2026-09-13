<?php
/**
 * 自定义报表引擎 契约 —— 维度/指标/聚合/筛选/下钻/保存
 *   php tests/report_engine_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-rpt-' . getmypid());
@mkdir(DATA_DIR . '/reports', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ReportEngine.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "自定义报表引擎\n";

check('维度目录含事件/订单/线索', isset(report_dimensions()['events']['page'], report_dimensions()['orders']['course'], report_dimensions()['leads']['stage']));
check('指标目录按来源', isset(report_metrics('orders')['revenue'], report_metrics('events')['users']));

// 维度取值
check('事件维度取 props', report_dim_value(['properties' => ['channel' => 'wechat']], 'events', 'channel') === 'wechat');
check('订单日期取 paid_at', report_dim_value(['paid_at' => '2026-09-01 10:00:00'], 'orders', 'day') === '2026-09-01');

// 聚合：计数
$events = [
    ['uid' => 'u1', 'event' => 'page_view', 'page' => '/a', 'properties' => ['channel' => 'wechat']],
    ['uid' => 'u2', 'event' => 'page_view', 'page' => '/a', 'properties' => ['channel' => 'wechat']],
    ['uid' => 'u1', 'event' => 'page_view', 'page' => '/b', 'properties' => ['channel' => 'weibo']],
];
$r = report_aggregate($events, 'events', 'events', 'page');
check('计数指标聚合', $r['rows'][0]['label'] === '/a' && (int)$r['rows'][0]['value'] === 2 && (int)$r['total'] === 3);

// 去重人数
$r2 = report_aggregate($events, 'events', 'users', 'channel');
check('去重访客去重正确', $r2['rows'][0]['label'] === 'wechat' && $r2['rows'][0]['value'] === 2);
$r2b = report_aggregate($events, 'events', 'users', 'page');
check('按页去重访客(/a=u1,u2 →2)', $r2b['rows'][0]['label'] === '/a' && $r2b['rows'][0]['value'] === 2);

// 筛选
$r3 = report_aggregate($events, 'events', 'events', 'page', [['field' => 'channel', 'op' => 'eq', 'value' => 'weibo']]);
check('筛选生效', (int)$r3['total'] === 1 && $r3['rows'][0]['label'] === '/b');

// 营收求和
$orders = [
    ['course_title' => 'A', 'amount' => 199, 'status' => 'paid', 'paid_at' => '2026-09-01 09:00:00'],
    ['course_title' => 'A', 'amount' => 301, 'status' => 'paid', 'paid_at' => '2026-09-02 09:00:00'],
    ['course_title' => 'B', 'amount' => 99, 'status' => 'paid', 'paid_at' => '2026-09-02 09:00:00'],
];
$r4 = report_aggregate($orders, 'orders', 'revenue', 'course');
check('营收按商品汇总', $r4['rows'][0]['label'] === 'A' && $r4['rows'][0]['value'] === 500.0 && $r4['total'] === 599.0);

// 保存/读取/删除
$s = report_save(['name' => '渠道事件', 'spec' => ['source' => 'events', 'metric' => 'events', 'dimension' => 'channel']]);
check('保存报表', !empty($s['ok']));
$id = $s['report']['id'];
check('可读取', (report_find($id)['name'] ?? '') === '渠道事件');
check('空名被拒', report_save(['name' => '', 'spec' => []])['ok'] === false);
check('删除报表', report_delete($id) === true && report_find($id) === null);

// 结构守卫
$api = file_get_contents(__DIR__ . '/../api/reports.php');
check('报表API要登录', strpos($api, 'require_login(') !== false);
check('报表API要权限', strpos($api, "require_perm('analytics')") !== false);
$nav = file_get_contents(__DIR__ . '/../includes/admin-nav.php');
check('导航含自定义报表', strpos($nav, "'reports'") !== false);
check('报表页存在', is_file(__DIR__ . '/../admin/reports.php'));

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
