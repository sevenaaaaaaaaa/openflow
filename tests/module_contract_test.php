<?php
/**
 * 模块化契约 —— php tests/module_contract_test.php
 *
 * 这套测试的存在理由，是三处已经发生过的「配了不生效」：
 *
 *  1. 区块类型表被抄了三份（构建器 13 种 / 模块库 17 种 / 前台渲染器 13 种），
 *     结果 contact / pricing / timeline / comparison 四种在后台能配、前台静默降级。
 *  2. 「落地页模块库」写出的 data/page-modules.json，**全站没有任何前台代码读过**——
 *     整个模块体系是死的，用户配半天永远不会出现在页面上。
 *  3. ShortcodeSystem 被 admin/config.php 和 article.php require 进来，
 *     但 shortcode_render() 从来没有被调用过：正文里写 [card ...] 会原样显示成方括号。
 *
 * 所以这里钉三条：类型表只能有一份、每种可配类型必须真能渲染、被引入的机制必须真被调用。
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
ob_start(); require_once "$root/admin/config.php"; ob_end_clean();
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  ✗ $msg\n"; } }

require_once "$root/lib/BlockRegistry.php";

/* ── 1. 类型表只有一份 ── */
$builder = file_get_contents("$root/admin/page-builder.php");
$modules = file_get_contents("$root/admin/page-modules.php");
$front   = file_get_contents("$root/front-builder.php");
$reg     = file_get_contents("$root/lib/BlockRegistry.php");

ok(is_file("$root/lib/BlockRegistry.php"), '缺少区块注册表');
foreach ([['page-builder', $builder], ['page-modules', $modules]] as [$name, $src]) {
    ok(str_contains($src, 'block_types()'), "$name 没有用注册表的类型表");
    ok(!preg_match("/\\\$blockTypes\s*=\s*\[\s*'hero'/", $src), "$name 里还硬编码着自己的一份类型表");
}
ok(str_contains($front, 'BlockRegistry.php'), '前台渲染没有引用注册表');
ok(!str_contains($front, 'function builder_render_block'), '前台仍然自带一份渲染器，会和注册表分叉');

/* ── 2. 每一种可配置的类型都必须真的能渲染 ── */
$types = block_types();
ok(count($types) >= 17, '类型表条目异常（' . count($types) . '）');
foreach ($types as $t => $label) {
    ok($label !== '' && $label !== $t, "类型 {$t} 缺少中文名");
    $html = builder_render_block(['type' => $t, 'title' => '标题样例', 'content' => '内容样例']);
    if ($t === 'module') {
        // 引用类型：没选模块时应静默返回空，而不是渲染出一个空壳
        ok($html === '', 'module 未选模块时不应输出任何东西');
        continue;
    }
    ok($html !== '', "类型 {$t}（{$label}）渲染为空——后台能配、前台不出现");
    ok(str_contains($html, '标题样例') || str_contains($html, '内容样例'),
       "类型 {$t}（{$label}）没有把内容渲染进去，等于静默降级");
}

/* ── 3. 模块库必须真的被读取（这正是当初死掉的那条链路）── */
ok(str_contains($reg, 'page-modules.json'), '注册表没有读取模块库，模块库又会变成死数据');
ok(function_exists('block_modules'), '缺少 block_modules()');
ok(str_contains($builder, 'block_module_id'), '构建器没有引用模块库的入口，模块库仍然无处可用');
ok(str_contains($builder, "'module_id'"), '构建器保存时没有落 module_id，选了也存不下来');
// 新块的 JS 模板必须同步输出该字段，否则 POST 数组下标会错位串块
ok(substr_count($builder, 'block_module_id[]') >= 2,
   '构建器的 JS 新建块模板漏了 block_module_id[]，会导致字段与区块错位');

// module 解引用要能真的把库里的模块渲染出来
$tmpMods = DATA_DIR . '/page-modules.json';
$backup  = is_file($tmpMods) ? file_get_contents($tmpMods) : null;
file_put_contents($tmpMods, json_encode([
    ['id' => 'm_ctest', 'name' => '契约测试模块', 'type' => 'cta', 'title' => '模块库标题', 'enabled' => true],
    ['id' => 'm_off',   'name' => '停用的',       'type' => 'cta', 'title' => '不该出现',   'enabled' => false],
], JSON_UNESCAPED_UNICODE));
$mods = block_modules();
ok(isset($mods['m_ctest']), '启用的模块没被读出来');
ok(!isset($mods['m_off']), '停用的模块不应被读出来');
$out = builder_render_block(['type' => 'module', 'module_id' => 'm_ctest']);
ok(str_contains($out, '模块库标题'), 'module 引用没有把库里的模块渲染出来——模块库依然是死的');
ok(builder_render_block(['type' => 'module', 'module_id' => 'm_off']) === '', '停用的模块不该渲染');
ok(builder_render_block(['type' => 'module', 'module_id' => '不存在']) === '', '引用不存在的模块应静默跳过而不是报错');
if ($backup === null) @unlink($tmpMods); else file_put_contents($tmpMods, $backup);

/* ── 4. 被引入的复用机制必须真的被调用 ── */
$article = file_get_contents("$root/article.php");
ok(str_contains($article, 'shortcode_render('), 'article.php 引入了短代码系统却从不调用，正文里的 [card ...] 会原样显示');
ok(str_contains($article, 'shortcode_style()'), '短代码渲染了却不输出样式，卡片会没有样式');
ok(str_contains($article, '$usedShortcode'), '短代码样式应按需注入，不该给每篇文章都加 CSS');

require_once "$root/lib/ShortcodeSystem.php";
$plain = '<p>普通正文</p>';
ok(shortcode_render($plain) === $plain, '没有短代码的正文被改动了，风险太大');
$card = shortcode_render('[card type="cta" title="预约" desc="一对一" url="/consultation"]');
ok(str_contains($card, 'of-card'), '短代码没有展开成卡片');
ok(str_contains($card, '预约'), '短代码展开后丢了内容');

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
