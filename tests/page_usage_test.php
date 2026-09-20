<?php
declare(strict_types=1);
/**
 * 后台页面清单 + 使用埋点 契约测试
 *
 *   php tests/page_usage_test.php
 *
 * 四件事：
 *   1) 页面清单是**自洽**的单一来源：真实页 + 301 别名 + 片段 = admin/*.php 全部文件，不重不漏
 *   2) 路径 → slug 的解析安全：别名不重复计数、路径穿越被拒
 *   3) 计数正确：PV / UV / 排序 / 跨天汇总 / 从未打开
 *   4) 韧性：损坏的日文件、写不进去、关闭开关，都不能把调用方搞挂
 */

$tmp = sys_get_temp_dir() . '/of-pageusage-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
define('OF_NO_AUTO_CSRF', true);
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/PageUsage.php';

$ROOT = dirname(__DIR__);
$pass = 0; $fail = 0;
function ok(bool $c, string $n, string $d = ''): void
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "后台页面清单 + 使用埋点\n";

// ── 1. 清单自洽（不写死页数：加页面不该让测试红，口径变坏才该红）──
$inv = admin_page_inventory($ROOT);
$files = array_map(static fn(string $f): string => basename($f, '.php'), glob($ROOT . '/admin/*.php') ?: []);
$counted = count($inv['pages']) + count($inv['alias_301']) + count($inv['fragments']);
ok($counted === count($files), '三类相加 = admin/*.php 文件数（不重不漏）', "{$counted} vs " . count($files));
ok(count(array_intersect(array_keys($inv['pages']), array_keys($inv['alias_301']))) === 0, '真实页与 301 别名无交集');
ok(count(array_intersect(array_keys($inv['pages']), $inv['fragments'])) === 0, '真实页与片段无交集');
ok(count($inv['pages']) > 100, '真实页面数量在合理量级', (string) count($inv['pages']));
ok(!isset($inv['pages']['config']) && !isset($inv['pages']['login']), 'config/login 不算页面');
foreach ($inv['alias_301'] as $a => $target) { ok($target !== '', "别名 {$a} 记到了跳转目标"); break; }

// 与可用性审计同源：审计脚本报的总数必须等于清单
$auditJson = shell_exec('php ' . escapeshellarg($ROOT . '/scripts/usability-audit.php') . ' --json 2>/dev/null');
$audit = json_decode((string) $auditJson, true);
ok(is_array($audit) && (int) ($audit['pages_total'] ?? -1) === count($inv['pages']),
   '可用性审计与清单同源（pages_total 一致）',
   (string) ($audit['pages_total'] ?? '审计没出 JSON'));

// ── 2. 路径解析 ──
$anyPage = (string) array_key_first($inv['pages']);
ok(admin_page_slug_from_path('/xmp/' . $anyPage) === $anyPage, '真实页路径能解析');
ok(admin_page_slug_from_path('/xmp/') === 'index', '/xmp/ 归到后台首页');
ok(admin_page_slug_from_path('/xmp/definitely-not-a-page') === '', '不存在的页不计数');
ok(admin_page_slug_from_path('/xmp/../../etc/passwd') === '', '路径穿越被拒');
ok(admin_page_slug_from_path('') === '', '空路径不计数');
ok(admin_page_slug_from_path('/') === '', '前台首页不算后台页');
$anyAlias = (string) array_key_first($inv['alias_301']);
if ($anyAlias !== '') ok(admin_page_slug_from_path('/xmp/' . $anyAlias) === '', "301 别名 {$anyAlias} 不重复计数");

// ── 3. 计数 ──
$today = date('Y-m-d');
$old = date('Y-m-d', strtotime('-2 days'));
for ($i = 0; $i < 3; $i++) page_usage_bump('orders', 'seven', $today);
page_usage_bump('orders', 'amy', $today);
page_usage_bump('cdp', 'seven', $today);
page_usage_bump('orders', 'seven', $old);

$s = page_usage_summary(30);
ok($s['total_pv'] === 6, '总 PV = 6', (string) $s['total_pv']);
ok(($s['pages']['orders']['pv'] ?? 0) === 5, 'orders PV = 5', (string) ($s['pages']['orders']['pv'] ?? 0));
ok(($s['pages']['orders']['uv'] ?? 0) === 2, 'orders UV = 2（按用户去重）', (string) ($s['pages']['orders']['uv'] ?? 0));
ok(array_key_first($s['pages']) === 'orders', '页面按 PV 降序');
ok($s['days_with_data'] === 2, '有数据天数 = 2', (string) $s['days_with_data']);
ok(($s['daily'][$today] ?? 0) === 5, '当天 PV = 5', (string) ($s['daily'][$today] ?? 0));
ok(count($s['daily']) === 30, '日序列补齐到 30 天（没数据的天也要有 0）', (string) count($s['daily']));
ok(($s['users']['seven'] ?? 0) === 5, '按用户汇总正确');

$never = page_usage_never_opened(30);
ok(count($never) === count($inv['pages']) - 2, '从未打开 = 总页数 - 已打开 2 页', (string) count($never));
ok(!in_array('orders', array_column($never, 'page'), true), '打开过的页不在"从未打开"里');

// 只统计近 7 天时，2 天前那条仍在窗口内
ok(page_usage_summary(7)['total_pv'] === 6, '近 7 天窗口包含 2 天前的数据');
ok(page_usage_summary(1)['total_pv'] === 5, '近 1 天窗口只含当天', (string) page_usage_summary(1)['total_pv']);

// ── 4. 韧性 ──
file_put_contents(page_usage_dir() . '/' . date('Y-m-d', strtotime('-1 day')) . '.json', 'NOT JSON{{');
ok(page_usage_summary(30)['total_pv'] === 6, '损坏的日文件被跳过而不是崩溃');

file_put_contents(page_usage_dir() . '/2020-01-01.json', '{"pages":{}}');
ok(page_usage_prune(90) === 1, '过期清理只删过期的');
ok(is_file(page_usage_dir() . '/' . $today . '.json'), '当天数据没被误删');

// 非 GET / AJAX 不记
$_SERVER['REQUEST_METHOD'] = 'POST';
ok(page_usage_record('/xmp/' . $anyPage, 'seven', $today) === false, 'POST 不计数（那是写操作，归审计日志）');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
ok(page_usage_record('/xmp/' . $anyPage, 'seven', $today) === false, 'AJAX 不计数');
unset($_SERVER['HTTP_X_REQUESTED_WITH']);

// 配置
$cfg = page_usage_config_set(['enabled' => false, 'retain_days' => 5]);
ok($cfg['enabled'] === false, '开关可关闭');
ok($cfg['retain_days'] === 7, '保留天数下限收敛到 7', (string) $cfg['retain_days']);

// 埋点不该记敏感信息：日文件里只有 slug / 用户名 / 次数
$raw = (string) file_get_contents(page_usage_dir() . '/' . $today . '.json');
ok(!str_contains($raw, 'REQUEST_URI') && !str_contains($raw, '127.0.0.1') && !str_contains($raw, 'ip'),
   '日文件里不含 IP / URL / 查询串');

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
@exec('rm -rf ' . escapeshellarg($tmp));
exit($fail === 0 ? 0 : 1);
