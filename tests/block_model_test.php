<?php
/**
 * T2-1 验收：正文块模型 + 可复用区块（BlockModel）
 *   php tests/block_model_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-bm-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/BlockModel.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$html = '<h2>为什么要做增长</h2><p>因为<strong>流量</strong>会变贵。</p><ul><li>降低获客成本</li><li>提高复购</li></ul><blockquote>增长是系统工程</blockquote><pre><code>echo 1;</code></pre>';

echo "\n── 1. HTML → 块 ──\n";
$blocks = blockmodel_from_html($html);
check('解析出 5 块', count($blocks) === 5, json_encode(array_column($blocks,'type')));
check('标题块带层级', $blocks[0]['type']==='heading' && $blocks[0]['level']===2);
check('标题文本', $blocks[0]['text'] === '为什么要做增长');
check('段落保留内联标签', strpos($blocks[1]['html'], '<strong>') !== false);
check('列表项解析', $blocks[2]['items'] === ['降低获客成本','提高复购']);
check('引用解析', $blocks[3]['text'] === '增长是系统工程');
check('代码解析', trim($blocks[4]['code']) === 'echo 1;');
check('空 HTML → 空数组', blockmodel_from_html('') === []);

echo "\n── 2. 块 → HTML 往返 ──\n";
$back = blockmodel_to_html($blocks);
check('标题回来了', strpos($back, '<h2>为什么要做增长</h2>') !== false);
check('列表回来了', strpos($back, '<li>降低获客成本</li>') !== false);
check('代码转义', strpos($back, '<pre><code>echo 1;</code></pre>') !== false);
check('未知类型走 html 兜底', blockmodel_to_html([['type'=>'weird','html'=>'<div>x</div>']]) === '<div>x</div>');

echo "\n── 3. CTA / 图片块渲染与转义 ──\n";
$cta = blockmodel_to_html([['type'=>'cta','text'=>'立即咨询','url'=>'/consult?a="b"']]);
check('CTA 渲染', strpos($cta, '立即咨询') !== false);
check('URL 引号被转义', strpos($cta, '&quot;') !== false);
$img = blockmodel_to_html([['type'=>'image','src'=>'/a.png','alt'=>'图<x>']]);
check('图片 lazy', strpos($img, 'loading="lazy"') !== false);
check('alt 转义', strpos($img, '&lt;x&gt;') !== false);

echo "\n── 4. 可复用区块：改一处，处处更新 ──\n";
$r = blockmodel_snippet_save(['name'=>'统一免责声明','blocks'=>[['type'=>'paragraph','html'=>'本文仅供参考。']]]);
check('创建成功', $r['ok'] === true);
$sid = $r['snippet']['id'];
$doc = [['type'=>'heading','level'=>2,'text'=>'正文'],['type'=>'snippet','ref'=>$sid]];
check('snippet 被展开', strpos(blockmodel_to_html($doc), '本文仅供参考。') !== false);
blockmodel_snippet_save(['id'=>$sid,'name'=>'统一免责声明','blocks'=>[['type'=>'paragraph','html'=>'已更新的声明。']]]);
check('改一处→引用处同步', strpos(blockmodel_to_html($doc), '已更新的声明。') !== false);
check('不重复新建', count(blockmodel_snippets()) === 1);
check('空名被拒', (blockmodel_snippet_save(['name'=>''])['ok'] ?? true) === false);
check('引用不存在的 snippet 不炸', blockmodel_to_html([['type'=>'snippet','ref'=>'nope']]) === '');

echo "\n── 5. 一写多编译 repurpose ──\n";
$outline = blockmodel_repurpose($blocks, 'outline');
check('提纲含标题', strpos($outline, '- 为什么要做增长') !== false, $outline);
check('提纲含列表项', strpos($outline, '降低获客成本') !== false);
$social = blockmodel_repurpose($blocks, 'social');
check('社媒有钩子', strpos($social, '流量') !== false, $social);
check('社媒有要点符号', strpos($social, '·') !== false);
$email = blockmodel_repurpose($blocks, 'email');
check('邮件带主题行', strpos($email, '主题：') === 0);
$script = blockmodel_repurpose($blocks, 'script');
check('脚本编号', strpos($script, '1. 为什么要做增长') !== false);
check('未知目标回落提纲', blockmodel_repurpose($blocks, 'bogus') === $outline);
check('repurpose 展开 snippet', strpos(blockmodel_repurpose($doc, 'outline'), '正文') !== false);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@unlink(DATA_DIR.'/content-snippets.json'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
