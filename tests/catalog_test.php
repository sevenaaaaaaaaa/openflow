<?php
/**
 * T1-13 验收：统一商品目录（UnifiedCatalog）
 *   php tests/catalog_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-cat-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/UnifiedCatalog.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$inject = [
    'digital' => [
        ['id'=>'d1','title'=>'增长手册','pricing'=>['price'=>99],'author_name'=>'张三','status'=>'active','created_at'=>'2026-08-30','cover'=>'/a.png'],
        ['id'=>'d2','title'=>'下架插件','price'=>50,'author_name'=>'李四','status'=>'archived','created_at'=>'2026-08-20'],
    ],
    'physical' => [['id'=>'p1','title'=>'实体周边','price'=>39,'stock'=>0,'status'=>'active','image'=>'/p.png','created_at'=>'2026-08-29']],
    'points'   => [['id'=>'pt1','title'=>'积分兑好礼','points'=>500,'stock'=>10,'status'=>'active','created_at'=>'2026-08-28']],
    'course'   => [['id'=>'c1','title'=>'增长课','price'=>299,'author'=>'张三','status'=>'active','created_at'=>'2026-08-27']],
];

echo "\n── 1. 跨四源聚合 ──\n";
$all = catalog_all($inject);
check('共 5 件', count($all) === 5, (string)count($all));
check('时间倒序：最新 d1', $all[0]['id'] === 'd1', $all[0]['id']);
check('uid 带类型前缀', $all[0]['uid'] === 'digital:d1');
check('数字商品价格从 pricing 取', $all[0]['price'] === 99.0);
$phys = array_values(array_filter($all, fn($i)=>$i['kind']==='physical'))[0];
check('实物封面从 image 取', $phys['cover'] === '/p.png');
$pts = array_values(array_filter($all, fn($i)=>$i['kind']==='points'))[0];
check('积分商品记 points', $pts['points'] === 500 && $pts['price'] === 0.0);
check('各类型都有编辑入口', count(array_filter($all, fn($i)=>$i['edit_url']!=='')) === 5);

echo "\n── 2. 检索 ──\n";
check('按标题搜', count(catalog_search($all, '增长')) === 2, json_encode(array_column(catalog_search($all,'增长'),'id')));
check('按作者搜', count(catalog_search($all, '张三')) === 2);
check('按 id 搜', count(catalog_search($all, 'pt1')) === 1);
check('大小写/空格容错', count(catalog_search($all, '  增长  ')) === 2);
check('按类型筛', count(catalog_search($all, '', 'course')) === 1);
check('按状态筛', count(catalog_search($all, '', '', 'archived')) === 1);
check('组合筛', count(catalog_search($all, '', 'digital', 'active')) === 1);
check('无匹配返回空', count(catalog_search($all, '不存在的东西')) === 0);

echo "\n── 3. 平台概览 ──\n";
$s = catalog_summary($all);
check('总数 5', $s['total'] === 5);
check('在售 4', $s['active'] === 4, (string)$s['active']);
check('缺货 1(实物 stock=0)', $s['out_of_stock'] === 1);
check('创作者 2(去重张三)', $s['creators'] === 2, (string)$s['creators']);
check('按类型计数', ($s['by_kind']['digital'] ?? 0) === 2 && ($s['by_kind']['course'] ?? 0) === 1);

echo "\n── 4. 容错 ──\n";
$messy = catalog_all(['digital'=>['not-array', ['id'=>'ok','title'=>'正常']], 'physical'=>[], 'points'=>[], 'course'=>[]]);
check('非数组条目被跳过', count($messy) === 1);
check('缺字段有默认值', $messy[0]['status'] === 'active' && $messy[0]['stock'] === null);
$emptyS = catalog_summary([]);
check('空目录概览不炸', $emptyS['total'] === 0 && $emptyS['creators'] === 0);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
