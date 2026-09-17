<?php
/**
 * 组件工厂契约 —— php tests/ai_block_contract_test.php
 *
 * 组件工厂（api/ai-block.php）让用户用「描述 / 粘贴 HTML / 网址 / 截图」四种方式
 * 生成可复用模块。它同时是**对外写入口**（会落盘 data/block-types.json），所以盯四件事：
 *
 *  1. 鉴权：必须要求登录（会员或后台），不能是匿名可写的落盘入口
 *  2. 四种模式都在，且 prompt 里带了防版权约束（url 模式不许照抄原文案）
 *  3. 产物必须走模块工厂契约（blockschema_save），不能自己另写一份存储
 *  4. 视觉输入有落点（AiCenter 支持 image_dataurl 多模态），否则截图模式是死代码
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
ob_start(); require_once "$root/admin/config.php"; ob_end_clean();
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  ✗ $msg\n"; } }

$f = "$root/api/ai-block.php";
ok(is_file($f), '缺少 api/ai-block.php');
$src = (string)file_get_contents($f);

/* ── 1. 鉴权与落盘纪律 ── */
ok(str_contains($src, 'member_current'), '没有会员态判断');
ok(str_contains($src, 'is_logged_in'), '没有后台态判断');
ok(str_contains($src, 'http_response_code(401)'), '未登录时没有返回 401');
ok(str_contains($src, 'blockschema_save'), '产物没有走模块工厂契约（blockschema_save）');
ok(str_contains($src, "require_once __DIR__ . '/../lib/BlockSchema.php'"), '没有引入 BlockSchema');
$reg = (string)file_get_contents("$root/lib/BlockRegistry.php");
ok(str_contains($reg, 'blockschema_is_custom'), '渲染器没有自定义模块分支（生成的模块会落到默认渲染）');

/* ── 2. 四种模式 ── */
foreach (['describe', 'html', 'url', 'screenshot'] as $mode) {
    ok(str_contains($src, "case '{$mode}'"), "缺少 {$mode} 模式");
}
ok(str_contains($src, "?? 'describe'"), '默认模式异常');

/* ── 3. 输入边界 ── */
ok(str_contains($src, 'mb_strlen($modeInput) > 60000'), 'html 模式没有长度上限（防超长内容打爆 AI）');
ok(str_contains($src, 'CURLOPT_TIMEOUT'), 'url 模式没有抓取超时');
ok(str_contains($src, 'preg_replace(\'#<(script|style|svg|noscript)'), 'url 模式没有剥离脚本/样式');
ok(str_contains($src, 'mb_substr($fetched, 0, 40000)'), 'url 模式没有截断抓取内容');
ok(str_contains($src, '8_000_000'), '截图模式没有体积上限');

/* ── 4. 防版权约束（这是"从别的网站学习"的合规红线） ── */
ok(mb_strpos($src, '文案全部替换为通用占位') !== false, 'url 模式缺少「文案换占位」的防版权约束');
ok(mb_strpos($src, '不复制原站受版权保护') !== false, 'url 模式缺少「不复制原文案」的明确指令');

/* ── 5. 视觉输入落点（截图模式依赖） ── */
ok(str_contains($src, 'image_dataurl'), '截图模式没有把图交给 AI（image_dataurl）');
$aicent = (string)file_get_contents("$root/lib/AiCenter.php");
ok(str_contains($aicent, 'image_dataurl'), 'AiCenter 不支持多模态图片输入 → 截图模式是死代码');
ok(str_contains($aicent, "'image_url'"), 'AiCenter 没有构造 image_url 消息体');

/* ── 6. 产物可渲染（冒烟：用模块工厂的通用渲染器跑一遍） ── */
require_once "$root/lib/BlockSchema.php";
require_once "$root/lib/BlockRegistry.php";
$probe = ['key' => 'contract_probe_' . substr(bin2hex(random_bytes(3)), 0, 5), 'name' => '契约探针',
          'status' => 'active', 'fields' => [['key' => 'title', 'type' => 'title', 'label' => '标题']],
          'custom_html' => '<section style="padding:20px"><h3>{{title}}</h3></section>'];
$saved = blockschema_save($probe);
ok(!empty($saved['ok']), '模块工厂保存失败（契约探针）');
if (!empty($saved['ok'])) {
    $html = builder_render_block(['_type' => $probe['key'], '_key' => 'k1', 'title' => '契约']);
    ok(str_contains($html, '契约'), '自定义模块渲染不出内容');
    ok(stripos($html, 'empty') === false, '自定义模块渲染落到空态');
    blockschema_delete($probe['key']);
}

/* ── 7. 不引入新依赖 / 不硬编码密钥 ── */
ok(!preg_match('/\bcurl_exec\b.*https:\/\/api\.(openai|deepseek)/s', $src), '端点里直连了模型 API（应走 AiCenter）');
ok(!preg_match('/sk-[a-zA-Z0-9]{20,}|cfat_[a-zA-Z0-9]{20,}/', $src), '端点里有硬编码密钥');

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
