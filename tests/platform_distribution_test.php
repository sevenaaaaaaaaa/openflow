<?php
/**
 * T2-7 验收：平台级 AI 分发（PlatformDistribution）
 *   php tests/platform_distribution_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-pd-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/PlatformDistribution.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }
$ago = fn(int $d) => date('Y-m-d H:i:s', time() - $d*86400);

$products = [
    ['id'=>'p1','title'=>'SEO 增长手册','tags'=>['SEO'],'author'=>'张三','sales'=>10,'status'=>'active','created_at'=>$ago(100)],
    ['id'=>'p2','title'=>'配色指南','tags'=>['设计'],'author'=>'李四','sales'=>3,'status'=>'active','created_at'=>$ago(100)],
    ['id'=>'p3','title'=>'新出的 SEO 工具','tags'=>['SEO'],'author'=>'王五','sales'=>0,'status'=>'active','created_at'=>$ago(3)],
    ['id'=>'p4','title'=>'已下架的','tags'=>['SEO'],'author'=>'张三','sales'=>99,'status'=>'archived','created_at'=>$ago(50)],
    ['id'=>'p5','title'=>'缺货的','tags'=>['SEO'],'author'=>'赵六','sales'=>50,'status'=>'active','stock'=>0,'created_at'=>$ago(50)],
];
$profile = ['tags'=>['SEO'],'purchased'=>[],'bought_authors'=>[],'source'=>''];

echo "\n── 1. 兴趣匹配打分 ──\n";
$s1 = pdist_score($products[0], $profile);
check('SEO 命中得分', $s1['score'] > 0);
check('给出理由', count($s1['why']) >= 1, json_encode($s1['why']));
$s2 = pdist_score($products[1], $profile);
check('不相关分更低', $s2['score'] < $s1['score']);

echo "\n── 2. 已购不重复推 ──\n";
$bought = array_merge($profile, ['purchased'=>['p1']]);
check('已购返回 -1', pdist_score($products[0], $bought)['score'] === -1);
check('理由说明', strpos(implode('', pdist_score($products[0], $bought)['why']), '已购买') !== false);

echo "\n── 3. 复购信号加成 ──\n";
$fan = array_merge($profile, ['bought_authors'=>['张三']]);
check('买过该作者→加分', pdist_score($products[0], $fan)['score'] > pdist_score($products[0], $profile)['score']);

echo "\n── 4. 新品扶持 ──\n";
$new = pdist_score($products[2], $profile);
check('新品有扶持分', count(array_filter($new['why'], fn($w)=>strpos($w,'新品')!==false)) === 1, json_encode($new['why']));

echo "\n── 5. 强来源加成 ──\n";
$truth = ['sources'=>[['key'=>'自然搜索','revenue'=>9000]]];
$fromTop = array_merge($profile, ['source'=>'自然搜索']);
check('高转化来源加分', pdist_score($products[0], $fromTop, $truth)['score'] > pdist_score($products[0], $profile, $truth)['score']);

echo "\n── 6. 推荐：过滤不可推 ──\n";
$rec = pdist_recommend($products, $profile, 10);
$ids = array_map(fn($r)=>$r['product']['id'], $rec);
check('排除已下架', !in_array('p4', $ids, true), json_encode($ids));
check('排除缺货', !in_array('p5', $ids, true));
check('SEO 相关排前', $ids[0] === 'p1', json_encode($ids));
check('limit 生效', count(pdist_recommend($products, $profile, 2)) === 2);
check('已购的被过滤', !in_array('p1', array_map(fn($r)=>$r['product']['id'], pdist_recommend($products, $bought, 10)), true));

echo "\n── 7. 曝光公平：单个创作者不霸屏 ──\n";
$many = [];
for ($i=0;$i<5;$i++) $many[] = ['id'=>"a$i",'title'=>'SEO 系列'.$i,'tags'=>['SEO'],'author'=>'张三','sales'=>10,'status'=>'active','created_at'=>$ago(50)];
$many[] = ['id'=>'other','title'=>'SEO 新秀','tags'=>['SEO'],'author'=>'新人','sales'=>1,'status'=>'active','created_at'=>$ago(50)];
$ranked = pdist_recommend($many, $profile, 10);
$div = pdist_diversify($ranked, 2);
$authors = array_count_values(array_map(fn($r)=>$r['product']['author'], $div));
check('张三最多占 2 席', ($authors['张三'] ?? 0) === 2, json_encode($authors));
check('新人拿到位置', ($authors['新人'] ?? 0) === 1);
check('总数变少（被限流）', count($div) < count($ranked));

echo "\n── 8. 边界 ──\n";
check('空商品列表→空', pdist_recommend([], $profile) === []);
check('非数组条目跳过', count(pdist_recommend(['x', $products[0]], $profile, 10)) === 1);
check('无画像也能排', count(pdist_recommend($products, [], 10)) >= 1);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
