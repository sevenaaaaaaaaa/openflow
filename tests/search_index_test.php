<?php
/**
 * T0-4 验收：站内搜索 FTS5（SearchIndex）
 *
 *   php tests/search_index_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-search-' . getmypid());
@mkdir(DATA_DIR . '/db', 0777, true);
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/SearchIndex.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

if (!search_index_available()) { echo "环境无 FTS5，跳过\n"; exit(0); }

$articles = [
    ['slug'=>'growth-guide','title'=>'网站增长完全指南','content'=>'<p>如何用数据驱动增长与转化</p>','tags'=>['增长','SEO'],'status'=>'published','created_at'=>'2026-08-01'],
    ['slug'=>'seo-basics','title'=>'SEO 基础','seo_desc'=>'搜索引擎优化入门','content'=>'关键词与结构化数据','status'=>'published','created_at'=>'2026-08-02'],
    ['slug'=>'draft-x','title'=>'未发布草稿','content'=>'增长秘密','status'=>'draft','created_at'=>'2026-08-03'],
];

echo "\n── 1. 重建索引（只索引已发布）──\n";
$n = search_index_rebuild($articles);
check('索引 2 篇已发布(跳过草稿)', $n === 2, "n={$n}");

echo "\n── 2. 中文 ≥3 字走 FTS5 ──\n";
$r = search_index_query('网站增长');   // 连续子串（与原 mb_stripos 子串语义一致）
check('命中 growth-guide', count(array_filter($r, fn($x)=>$x['slug']==='growth-guide')) === 1, json_encode(array_column($r,'slug')));
$r = search_index_query('结构化数据');
check('正文命中 seo-basics', count(array_filter($r, fn($x)=>$x['slug']==='seo-basics')) === 1);

echo "\n── 3. 英文 + 高亮片段 ──\n";
$r = search_index_query('SEO');
check('英文命中', count($r) >= 1);

echo "\n── 4. 草稿不出现在结果 ──\n";
$r = search_index_query('增长秘密');
check('草稿内容搜不到', count(array_filter($r, fn($x)=>$x['slug']==='draft-x')) === 0);

echo "\n── 5. 2 字回落 LIKE ──\n";
$r = search_index_query('增长');
check('2字也能命中(LIKE回落)', count($r) >= 1, '数量=' . count($r));

echo "\n── 6. 无结果 / 特殊字符不炸 ──\n";
check('无关词 0 结果', count(search_index_query('量子力学薛定谔')) === 0);
check('含引号不报错', is_array(search_index_query('a"b OR c')));

echo "\n── 7. 重建反映删除 ──\n";
search_index_rebuild([$articles[1]]);  // 只留 seo-basics
check('重建后 growth 消失', count(search_index_query('增长指南')) === 0);
check('seo 仍在', count(search_index_query('SEO')) >= 1);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@unlink(DATA_DIR . '/db/openflow.db'); @array_map('unlink', glob(DATA_DIR.'/db/*'));
@rmdir(DATA_DIR . '/db'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
