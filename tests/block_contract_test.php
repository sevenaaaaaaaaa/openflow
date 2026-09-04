<?php
/**
 * 块契约 —— php tests/block_contract_test.php
 *
 * 止血那一步统一了「类型表和渲染器」，这一步统一**形状**。盯三件事：
 *
 *  1. 块身份要活过保存。改造前 page-builder 每次保存都给所有区块重新随机 id，
 *     块身份根本不存在——按块比对版本、给块留批注、看单块转化全都做不了。
 *  2. 形状只能有一份，且是 Portable Text 兼容的（_type + _key）。
 *     不然接任何块编辑器都只是再加一套模型。
 *  3. 老数据不许因为换了形状就读不出来或渲染成空白。
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
ob_start(); require_once "$root/admin/config.php"; ob_end_clean();
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  ✗ $msg\n"; } }

require_once "$root/lib/BlockContract.php";
require_once "$root/lib/BlockRegistry.php";

/* ── 1. 规范形状：Portable Text 的两条硬要求 ── */
ok(is_file("$root/lib/BlockContract.php"), '缺少块契约');
foreach (['block_new_key','block_normalize','block_normalize_all','block_denormalize',
          'block_type_of','block_key_of','block_find','block_validate_all',
          'block_text','block_plain_text','block_blocks_to_html','block_from_html'] as $fn) {
    ok(function_exists($fn), "契约缺少 $fn()");
}
$n = block_normalize(['type' => 'hero', 'id' => 'blk_x1', 'title' => 'T']);
ok(($n['_type'] ?? '') === 'hero', '归一化没有产出 _type');
ok(($n['_key'] ?? '') === 'blk_x1', '归一化没有沿用老 id 作为 _key');
ok(!isset($n['type']) && !isset($n['id']), '归一化后仍留着 type/id，等于两个真源并存');
ok(($n['title'] ?? '') === 'T', '归一化把业务字段弄丢了');

// 认不得的类型也必须原样保留字段，不能在归一化时悄悄吃掉数据
$weird = block_normalize(['type' => 'nonesuch', 'custom_field' => 'keep me']);
ok(($weird['custom_field'] ?? '') === 'keep me', '未知类型的字段被归一化吃掉了');

/* ── 2. 块身份必须活过保存（这次改动的核心）── */
// 模拟 page-builder 的保存往返：第一次没有 key，第二次表单把 key 带回来
$mkSave = function (array $postedKeys, array $types): array {
    $blocks = [];
    foreach ($types as $i => $t) {
        $k = trim((string)($postedKeys[$i] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $k)) $k = block_new_key();
        $blocks[] = ['_key' => $k, '_type' => $t, 'title' => '第' . $i . '块'];
    }
    return block_normalize_all($blocks);
};
$v1 = $mkSave(['', ''], ['hero', 'cta']);
$k1 = array_map('block_key_of', $v1);
$v2 = $mkSave($k1, ['hero', 'cta']);
$k2 = array_map('block_key_of', $v2);
ok($k1 === $k2, '再保存一次块身份就变了——按块比对/批注/统计都会成为孤儿');
ok(count(array_unique($k1)) === count($k1), '同一页里出现了重复的 _key');
ok($k1[0] !== '' && $k1[1] !== '', '有块没有拿到 _key');

// 重复 key 必须被拆开：它比没有 key 更糟，会让「按 key 找块」指向错的那个
$dup = block_normalize_all([['_key' => 'same', '_type' => 'hero'], ['_key' => 'same', '_type' => 'cta']]);
ok(block_key_of($dup[0]) !== block_key_of($dup[1]), '重复的 _key 没有被去重');
ok(block_type_of(block_find($dup, block_key_of($dup[1]))) === 'cta', 'block_find 没能按 key 精确定位');

// 表单必须真的把 key 带回来——PHP 循环与 JS 新建模板都要有，少一边就会下标错位
$builder = file_get_contents("$root/admin/page-builder.php");
ok(substr_count($builder, 'block_key[]') >= 2,
   '构建器没有在两处都输出 block_key[]，块身份带不回来（或 POST 下标会错位）');
ok(str_contains($builder, "\$_POST['block_key']"), '保存时没有读回表单里的 _key，等于还是每次重新生成');
ok(!preg_match("/'id' => 'blk_' \. \\\$bi \. '_' \. substr\(bin2hex/", $builder),
   '每次保存重新随机生成区块 id 的老写法还在');
ok(str_contains($builder, 'block_normalize_all('), '保存时没有归一化，形状会各写各的');
// 契约函数在第 12 行的保存逻辑里就要用到，require 不能留在一百多行之后
ok(preg_match('/BlockContract\.php.*?REQUEST_METHOD/s', $builder) === 1,
   '块契约的 require 在保存逻辑之后，保存路径会因函数不存在而致命错误');

/* ── 3. 老数据必须照常工作 ── */
$legacy = [
    ['id' => 'blk_0_aa', 'type' => 'hero',  'title' => '老标题', 'content' => '老内容'],
    ['type' => 'cta', 'title' => '没有 id 的老块'],
];
$ln = block_normalize_all($legacy);
ok(block_key_of($ln[0]) === 'blk_0_aa', '老的 blk_ id 没有被沿用，历史引用会断');
ok(block_key_of($ln[1]) !== '', '没有 id 的老块没补上 _key');
foreach ($ln as $b) {
    ok(builder_render_block($b) !== '', '老数据归一化后渲染成空白：' . block_type_of($b));
}
// 老形状**不经过**归一化也要能直接渲染（前台可能拿到未归一化的数组）
ok(str_contains(builder_render_block($legacy[0]), '老标题'), '未归一化的老区块直接渲染丢内容');

// denormalize 给还没改过来的消费方
$d = block_denormalize($ln[0]);
ok(($d['type'] ?? '') === 'hero' && ($d['id'] ?? '') === 'blk_0_aa', 'denormalize 没有还原成老形状');

/* ── 4. 体检能挡住坏数据 ── */
ok(block_validate_all($ln) === [], '正常数据被误报问题');
$bad = block_validate_all([['_type' => 'hero'], ['_type' => '', '_key' => 'k']]);
ok(count($bad) >= 2, '缺 _key / 缺类型没有被体检发现');

/* ── 5. 文本块：BlockModel 折进契约，不再是并行模型 ── */
$html = '<h2>标题一</h2><p>一段正文。</p><ul><li>甲</li><li>乙</li></ul><blockquote>引用一句</blockquote>';
$tb = block_from_html($html);
ok(count($tb) === 5, 'HTML 解析出的块数不对（' . count($tb) . '）');
ok(block_type_of($tb[0]) === 'block' && ($tb[0]['style'] ?? '') === 'h2', '标题没有映射成 PT 文本块');
ok(($tb[2]['listItem'] ?? '') === 'bullet', '列表项没有映射成 listItem');
ok(($tb[4]['style'] ?? '') === 'blockquote', '引用没有映射成 blockquote');
ok(block_plain_text($tb[0]) === '标题一', 'span 里没有文本');
foreach ($tb as $i => $b) ok(block_key_of($b) !== '', "第 {$i} 个文本块没有 _key");
// 往返：解析再渲染必须还是原来的 HTML
ok(block_blocks_to_html($tb) === $html, "HTML 往返不一致：\n    原文 " . $html . "\n    回渲 " . block_blocks_to_html($tb));
ok(block_from_html('') === [], '空 HTML 应得到空数组');

// marks 与链接
$m = ['_type'=>'block','_key'=>'k','style'=>'normal',
      'markDefs'=>[['_key'=>'l1','_type'=>'link','href'=>'/a?b="c"']],
      'children'=>[['_type'=>'span','_key'=>'s','text'=>'点我','marks'=>['strong','l1']]]];
$mh = block_text_to_html($m);
ok(str_contains($mh, '<strong>'), 'strong 标记没渲染');
ok(str_contains($mh, 'href="/a?b=&quot;c&quot;"'), '链接没有转义，存在注入风险');
$esc = block_text_to_html(block_text('<script>alert(1)</script>'));
ok(!str_contains($esc, '<script>'), '文本块没有转义，存在 XSS');

/* ── 6. 类型表仍然只有一份（含 AI 生成这条路径）── */
$ai = file_get_contents("$root/api/ai-landing.php");
ok(str_contains($ai, 'block_types()'), 'AI 落地页生成没有用注册表的类型表');
// 只看代码，不看注释——注释里正引用着那份旧白名单说明它错在哪
ok(!preg_match('/^\s*\$allowed\s*=\s*\[/m', $ai),
   'AI 那份写死的白名单还在（它漏掉十来种类型，还把 testimonials 写成了单数 testimonial）');
ok(str_contains($ai, 'block_normalize_all('), 'AI 生成的区块没有归一化，会写进一套自己的形状');

/* ── 7. 模块库：真实保存形状必须能渲染 ── */
// admin/page-modules.php 把区块字段嵌在 block 子对象里存，
// 渲染器读的却是顶层 title/content——不摊平就会渲染成空壳。
$mf = DATA_DIR . '/page-modules.json';
$bk = is_file($mf) ? file_get_contents($mf) : null;
file_put_contents($mf, json_encode([
    ['id'=>'m_nest','name'=>'嵌套形状','type'=>'cta','enabled'=>true,
     'block'=>['type'=>'cta','title'=>'嵌套标题','content'=>'嵌套内容']],
], JSON_UNESCAPED_UNICODE));
$out = builder_render_block(['_type'=>'module','module_id'=>'m_nest']);
ok(str_contains($out, '嵌套标题'), '模块库的真实保存形状渲染成了空壳（嵌套的 block 没摊平）');
ok(str_contains($out, '嵌套内容'), '模块库正文没渲染出来');
$mods = block_modules();
ok(($mods['m_nest']['name'] ?? '') === '嵌套形状', '模块名在归一化时丢了，后台下拉会显示不出来');
ok(block_key_of($mods['m_nest']) === 'm_nest', '模块的 _key 应当就是它的库内 id');
if ($bk === null) @unlink($mf); else file_put_contents($mf, $bk);

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
