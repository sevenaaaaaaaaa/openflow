<?php
declare(strict_types=1);
/**
 * 命令面板全量索引 契约测试（S1：18% → 全量）
 *
 *   php tests/palette_index_test.php
 *
 * 背景：面板原来手工登记 41 条，222 个后台页里 96 个**只有侧栏入口**——
 * 不记得在哪就等于找不到。这里把"已存在的页面"自动纳入索引，并守住三条底线：
 *   1) 覆盖率（不许回退）
 *   2) 不索引"会跳走的 301 别名"和"被 include 的片段"
 *   3) 策展项不丢，且同分时排在自动索引之前
 */

$tmp = sys_get_temp_dir() . '/of-palette-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

define('OF_NO_AUTO_CSRF', true);
require_once __DIR__ . '/../admin/config.php';
// 全权限角色：面板条目是权限感知的，测试要覆盖"能看到的全部"
json_write(DATA_DIR . '/roles.json', ['__all' => ['label' => '全权', 'perms' => array_values(of_perm_registry())]]);
$_SESSION = ['admin_login' => true, 'admin_role' => '__all', 'admin_user' => 't'];

$pass = 0; $fail = 0;
function ok(bool $c, string $n, string $d = ''): void
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "命令面板全量索引\n";

require_once __DIR__ . '/../lib/CommandPalette.php';
$items = cp_items();
$paths = [];
foreach ($items as $it) {
    $p = (string) parse_url((string) ($it['url'] ?? ''), PHP_URL_PATH);
    if ($p !== '') $paths[$p] = true;
}
$root = dirname(__DIR__);

/* 1. 覆盖：后台页面里绝大多数应可搜到 */
$ht = (string) file_get_contents($root . '/.htaccess');
$aliases = [];
if (preg_match_all('#^RewriteRule\s+\^xmp/([a-z0-9-]+)/\?\$\s+\S+\s+\[R=301#mi', $ht, $am)) foreach ($am[1] as $a) $aliases[(string) $a] = true;

$pageCount = 0;
$covered = 0;
$missing = [];
foreach (glob($root . '/admin/*.php') ?: [] as $f) {
    $b = basename($f, '.php');
    if (str_starts_with($b, '_') || in_array($b, ['config', 'login', 'logout'], true)) continue;
    if (isset($aliases[$b])) continue;                            // 301 别名按设计不索引
    $src = (string) file_get_contents($f);
    if (!str_contains($src, 'admin_header(')) continue;          // 片段不算页面
    $pageCount++;
    $path = '/xmp/' . ($b === 'index' ? 'dashboard' : $b);
    if (isset($paths[$path])) $covered++; else $missing[] = $b;
}
$ratio = $pageCount > 0 ? $covered / $pageCount : 0;
ok($ratio >= 0.95, '面板覆盖 ≥95% 的后台页面', sprintf('%d/%d = %.0f%%，缺：%s', $covered, $pageCount, $ratio * 100, implode(',', array_slice($missing, 0, 6))));

/* 2. 不索引 301 别名（点进去会跳走）与片段文件 */
ok(!isset($paths['/xmp/payment-settings']), '不索引 301 别名（payment-settings → shop-settings）');
ok(!isset($paths['/xmp/tags']), '不索引 301 别名（tags → content-hub）');
ok(!isset($paths['/xmp/ma-sync-lib']), '不索引被 include 的片段（ma-sync-lib）');
ok(!isset($paths['/xmp/seo-functions']), '不索引被 include 的工具文件（seo-functions）');

/* 3. 策展项保留（原有手工条目不能丢） */
$labels = array_map(static fn(array $i): string => (string) ($i['label'] ?? ''), $items);
ok(in_array('开播 / 直播间管理', $labels, true), '策展项仍在（开播 / 直播间管理）');
ok(in_array('清理 Cloudflare 缓存', $labels, true), '带 action 的策展项仍在（清理缓存）');
ok((int) (array_values(array_filter($items, static fn(array $i): bool => (string) ($i['source'] ?? '') === 'curated'))[0]['weight'] ?? -1) === 0, '策展项权重为 0');

/* 4. 自动索引项字段完整、权重大于策展项 */
$auto = array_values(array_filter($items, static fn(array $i): bool => (string) ($i['source'] ?? '') === 'page'));
ok(count($auto) > 100, '自动索引条目超过 100 条', (string) count($auto));
$bad = [];
foreach (array_slice($auto, 0, 200) as $i) {
    if (trim((string) ($i['label'] ?? '')) === '' || trim((string) ($i['url'] ?? '')) === '' || (int) ($i['weight'] ?? 0) <= 0) $bad[] = (string) ($i['url'] ?? '?');
}
ok($bad === [], '自动条目字段完整（label/url/weight）', implode(',', array_slice($bad, 0, 5)));

/* 4.5 权限感知：无权限的能力不该在面板里露出名字 */
$allPaths = $paths;
// 造一个"零权限"角色（不能只 unset 角色：has_perm 对"有用户名无角色"会按 admin 兜底）
json_write(DATA_DIR . '/roles.json', [
    '__all' => ['label' => '全权', 'perms' => array_values(of_perm_registry())],
    '__none' => ['label' => '零权限', 'perms' => []],
]);
$_SESSION['admin_role'] = '__none';
$blindItems = cp_items();
$blindPaths = [];
foreach ($blindItems as $it) {
    $p3 = (string) parse_url((string) ($it['url'] ?? ''), PHP_URL_PATH);
    if ($p3 !== '') $blindPaths[$p3] = true;
}
ok(count($blindPaths) < count($allPaths), '无权限时会话看到的面板条目更少（权限感知生效）', count($blindPaths) . ' vs ' . count($allPaths));
ok(!isset($blindPaths['/xmp/users']), '无权限时看不到「权限管理」');
$_SESSION['admin_role'] = '__all';

/* 5. 搜索：能搜到深页；策展项同分优先 */
$r1 = cp_search('系统体检', 5);
ok(($r1[0]['url'] ?? '') === '/xmp/evolution', '按标题搜到深页（系统体检 → evolution）', json_encode(array_map(static fn($x) => $x['url'] ?? '', array_slice($r1, 0, 3))));
$r2 = cp_search('重定向', 5);
$r2urls = array_map(static fn($x) => (string) ($x['url'] ?? ''), $r2);
ok(in_array('/xmp/seo-center?tab=redirects', $r2urls, true), '中文标题可搜（重定向 → seo-center 的 tab）', json_encode($r2urls));
$r3 = cp_search('observe', 5);
ok(is_array($r3), '英文/拼音式输入不报错');
$r4 = cp_search('文章', 12);
$firstCuratedIdx = null; $firstAutoIdx = null;
foreach ($r4 as $i => $x) {
    if ($firstAutoIdx === null && (string) ($x['source'] ?? '') === 'page') $firstAutoIdx = $i;
    if ($firstCuratedIdx === null && (string) ($x['source'] ?? '') === 'curated') $firstCuratedIdx = $i;
}
ok($firstCuratedIdx !== null && ($firstAutoIdx === null || $firstCuratedIdx <= $firstAutoIdx), '同分时策展项排在自动索引之前');

@exec('rm -rf ' . escapeshellarg($tmp));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
