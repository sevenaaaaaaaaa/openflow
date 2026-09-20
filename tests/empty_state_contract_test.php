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

/* 2. 已改的核心页面必须用组件且带 CTA */
$pages = [
    'orders' => '去上架商品',
    'commerce' => '生态市场',
    'knowledge' => '导入现有文档',
    'landing-pages' => '页面构建器',
    'block-templates' => '页面构建器',
    'action-approvals' => '增长大脑',
    'evolution' => '立即体检',
    'product-scout' => '立即运行',
    'create' => '生成',
    'agent' => 'AI 供应商',
];
$root = dirname(__DIR__);
foreach ($pages as $page => $needle) {
    $src = (string) @file_get_contents($root . '/admin/' . $page . '.php');
    ok(preg_match_all('/empty_state\(((?:[^()]|\([^()]*\))*)\)/s', $src, $m) >= 1, "{$page}：使用统一空态组件");
    $hasCta = false;
    foreach (($m[1] ?? []) as $args) if (substr_count($args, ',') + 1 >= 4) { $hasCta = true; break; }
    ok($hasCta, "{$page}：空态带 CTA");
    // CTA 目标必须是站内地址（以 /xmp/ 开头），避免指到不存在的相对路径
    $urls = [];
    if (preg_match_all("/empty_state\\(((?:[^()]|\\([^()]*\\))*)\\)/s", $src, $mm)) {
        foreach ($mm[1] as $args) {
            if (substr_count($args, ',') + 1 >= 4 && preg_match("/'((?:\\/xmp\\/)[^']*)'/", $args, $u)) $urls[] = $u[1];
        }
    }
    $bad = array_values(array_filter($urls, static fn(string $u): bool => !str_starts_with($u, '/xmp/')));
    ok($urls !== [] && $bad === [], "{$page}：CTA 指向站内路径", implode(',', $bad));
    ok(str_contains($src, $needle), "{$page}：CTA 文案包含「{$needle}」（语义可核对）");
}

@exec('rm -rf ' . escapeshellarg($tmp));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
