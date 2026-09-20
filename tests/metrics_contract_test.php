<?php
declare(strict_types=1);
/**
 * 口径契约测试（防止文档数字再次漂移）
 *
 *   php tests/metrics_contract_test.php
 *
 * 三件事：
 *   1) 指标脚本能算出合理值，且每个指标都写清了口径（没有口径的数字就是下一次漂移）
 *   2) 文档里 <!--m:key-->…<!--/m--> 的数字与代码当前值一致
 *   3) 同一个数不允许两个来源：后台页数必须与可用性审计一致
 *
 * 红了怎么办：不是改期望值，是跑 `php scripts/metrics.php --sync` 把文档刷成当前值，
 * 然后确认这个变化是你有意造成的。
 */

$ROOT = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/scripts/metrics.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $n, string $d = ''): void
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "口径契约\n";

$m = of_metrics($ROOT);
ok($m !== [], '指标脚本可运行');
foreach ($m as $k => [$v, $def]) {
    ok(is_int($v) && $v >= 0, "指标 {$k} 是非负整数", var_export($v, true));
    ok($def !== '' && mb_strlen($def) > 8, "指标 {$k} 写了口径说明");
}
ok($m['admin_pages'][0] > 100, '后台页数在合理量级', (string) $m['admin_pages'][0]);
ok($m['mcp_tools'][0] > 0, 'MCP 工具数 > 0（注册表必须能读到）', (string) $m['mcp_tools'][0]);
ok($m['php_tests'][0] >= 150, '测试数量没有倒退', (string) $m['php_tests'][0]);

// 同一个数只能有一个来源：可用性审计的 pages_total 必须等于指标里的 admin_pages
$json = shell_exec('php ' . escapeshellarg($ROOT . '/scripts/usability-audit.php') . ' --json 2>/dev/null');
$audit = json_decode((string) $json, true);
ok(is_array($audit) && (int) ($audit['pages_total'] ?? -1) === $m['admin_pages'][0],
   '可用性审计与指标脚本同源', '审计 ' . ($audit['pages_total'] ?? '?') . ' vs 指标 ' . $m['admin_pages'][0]);
ok(is_array($audit) && (int) ($audit['excluded']['alias_301'] ?? -1) === $m['admin_alias_301'][0],
   '301 别名数同源');

// MCP 注册表与 server 同源（此前就是因为各写一份，文档把 23 说成了 31）
$server = (string) @file_get_contents($ROOT . '/mcp-server.php');
if ($server !== '') ok(str_contains($server, 'mcp_tools('), 'mcp-server.php 用的是注册表函数而不是自己再写一份');

// 文档标记与代码一致
$r = of_metric_apply($ROOT, $m, false);
ok($r['unknown'] === [], '文档里没有未知的指标标记', implode(', ', $r['unknown']));
foreach ($r['mismatched'] as $x) {
    ok(false, "{$x['file']} 的 {$x['key']} 与代码一致", "文档写 {$x['doc']}，实际 {$x['now']}");
}
ok($r['mismatched'] === [], '文档数字与代码一致（不一致请跑 php scripts/metrics.php --sync）',
   count($r['mismatched']) . ' 处不一致');

// 纳管的文档里至少要真的有标记，否则这个测试就是空转
$marked = 0;
foreach (of_metric_docs() as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    $marked += preg_match_all('/<!--m:[a-z0-9_]+-->/', $src);
}
ok($marked >= 6, '纳管文档里确实存在指标标记（否则契约是空的）', (string) $marked);

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
