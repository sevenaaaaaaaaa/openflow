<?php
declare(strict_types=1);
/**
 * 空态契约测试（S2）
 *
 *   php tests/empty_state_contract_test.php
 *
 * 三件事：
 *   1) 组件 empty_state() 行为正确（渲染结构、HTML 转义、有/无 CTA）
 *   2) 已改的核心页面**必须**用组件并带 CTA（防止以后被改回「暂无」）
 *   3) 空态里不允许出现"死链"式 CTA（href 必须是站内绝对路径或带参数的站内地址）
 */

$tmp = sys_get_temp_dir() . '/of-emptystate-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
define('OF_NO_AUTO_CSRF', true);
require_once __DIR__ . '/../admin/config.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $n, string $d = ''): void
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "空态契约\n";

/* 1. 组件行为 */
$html = empty_state('还没有订单', '付款后会出现在这里', '去上架商品', '/xmp/commerce');
ok(str_contains($html, 'class="empty empty-next"'), '组件带 empty-next 标记');
ok(str_contains($html, '还没有订单') && str_contains($html, '付款后会出现在这里'), '组件渲染标题与说明');
ok(str_contains($html, 'href="/xmp/commerce"') && str_contains($html, '去上架商品'), '组件渲染 CTA 链接与文案');

$xss = empty_state('<script>alert(1)</script>', 'a & b', 'x', '/xmp/x');
ok(!str_contains($xss, '<script>'), '标题被转义（防 XSS）');
ok(str_contains($xss, '&amp;'), '说明被转义（& → &amp;）');

$noCta = empty_state('空', '只有说明');
ok(str_contains($noCta, 'empty-next') && !str_contains($noCta, '<a '), '不给 CTA 时不渲染链接（审计会判定为"没给出路"）');

/* 2. 通用规则：扫全部后台页的 empty_state() 调用 */
/*    - 只允许 2 个参数（说明型）或 4 个参数（带 CTA）；3 个参数 = 有文案没地址，禁止
 *    - CTA 地址必须是站内 /xmp/… 或页面内锚点 #xxx；用 #add 时页面必须真有 id="add"
 */
// 排除 config.php：它是 empty_state() 的定义处（参数列表会被误当成一次调用）
$pages = array_values(array_filter(
    array_map(static fn(string $f): string => basename($f, '.php'), glob(dirname(__DIR__) . '/admin/*.php') ?: []),
    static fn(string $b): bool => $b !== 'config'
));
$badArity = [];
$badHref = [];
$badAnchor = [];
$withCta = 0;
$totalCalls = 0;
foreach ($pages as $page) {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/admin/' . $page . '.php');
    if (!preg_match_all('/empty_state\(((?:[^()]|\([^()]*\))*)\)/s', $src, $m)) continue;
    foreach ($m[1] as $args) {
        $totalCalls++;
        $argc = substr_count($args, ',') + 1;
        if ($argc !== 2 && $argc !== 4 && $argc !== 1) $badArity[] = "{$page}({$argc} 个参数)";
        if ($argc !== 4) continue;
        $withCta++;
        if (!preg_match("/'((?:\/xmp\/|#)[^']*)'/", $args, $u)) { $badHref[] = "{$page}(无有效地址)"; continue; }
        $href = $u[1];
        if (!str_starts_with($href, '/xmp/') && !str_starts_with($href, '#')) $badHref[] = "{$page}({$href})";
        if ($href === '#add' && !str_contains($src, 'id="add"')) $badAnchor[] = $page;
    }
}
ok($totalCalls >= 60, '后台页里有足够多的空态调用（≥60 处）', (string) $totalCalls);
ok($badArity === [], '没有"有文案没地址"的 3 参数写法', implode('；', array_slice($badArity, 0, 6)));
ok($badHref === [], 'CTA 地址都是站内路径或页面内锚点', implode('；', array_slice($badHref, 0, 6)));
ok($badAnchor === [], '用 #add 的页面里确实有 id="add"', implode('；', array_slice($badAnchor, 0, 6)));
ok($withCta >= 40, '带 CTA 的空态 ≥40 处', (string) $withCta);

/* 2.5 抽查语义：三个关键页面的 CTA 必须指向真实下一步 */
foreach ([
    'orders' => '/xmp/commerce',
    'knowledge' => '/xmp/ingest',
    'agent' => '/xmp/ai-config',
] as $page => $want) {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/admin/' . $page . '.php');
    ok(str_contains($src, "'" . $want . "'"), "{$page} 的 CTA 指向 {$want}", '');
}

/* 3. 审计口径：不应该再有"页面级空态缺出路"，也不该有死链 CTA */
$json = shell_exec('php ' . escapeshellarg(dirname(__DIR__) . '/scripts/usability-audit.php') . ' --json 2>/dev/null');
$audit = json_decode((string) $json, true);
ok(is_array($audit), '可用性审计可运行');
if (is_array($audit)) {
    ok((int) ($audit['empty_state']['primary_without_action'] ?? -1) === 0,
        '页面级空态缺出路 = 0', (string) ($audit['empty_state']['primary_without_action'] ?? '?'));
}

@exec('rm -rf ' . escapeshellarg($tmp));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
