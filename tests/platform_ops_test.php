<?php
/**
 * T1-12 验收：平台运营 Agent · 选品驾驶舱（PlatformOps）
 *   php tests/platform_ops_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-ops-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/PlatformOps.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }
$ago = fn(int $d) => date('Y-m-d H:i:s', time() - $d*86400);

$products = [
    ['id'=>'p1','title'=>'高转化好货','sales'=>20,'views'=>100,'featured'=>false,'created_at'=>$ago(60),'status'=>'active'],
    ['id'=>'p2','title'=>'占位不转化','sales'=>0,'views'=>500,'featured'=>true,'created_at'=>$ago(90),'status'=>'active'],
    ['id'=>'p3','title'=>'新品没曝光','sales'=>0,'views'=>5,'featured'=>false,'created_at'=>$ago(3),'status'=>'active'],
    ['id'=>'p4','title'=>'已下架的','sales'=>50,'views'=>60,'featured'=>false,'created_at'=>$ago(10),'status'=>'archived'],
];

echo "\n── 1. 选品建议 ──\n";
$c = platops_curate($products);
$byKind = [];
foreach ($c as $x) $byKind[$x['kind']][] = $x['product_id'];
check('推首页命中高转化 p1', in_array('p1', $byKind['promote'] ?? [], true), json_encode($byKind));
check('曝光命中新品 p3', in_array('p3', $byKind['spotlight'] ?? [], true));
check('换下命中占位 p2', in_array('p2', $byKind['demote'] ?? [], true));
check('已下架不参与', !in_array('p4', array_merge(...array_values($byKind ?: [[]])), true));
check('理由带具体数字', strpos(($c[0]['reason'] ?? ''), '%') !== false || strpos(($c[0]['reason'] ?? ''), '单') !== false);
check('限制条数', count(platops_curate($products, 2)) === 2);

echo "\n── 2. 质量初判：完整商品通过 ──\n";
$good = ['id'=>'g','title'=>'增长手册完整版','description'=>str_repeat('这是一份讲清楚谁该买、解决什么问题的详细说明。', 3),'price'=>99,'cover'=>'/c.png','asset_id'=>'a1','type'=>'digital'];
$r = platops_review($good, $products);
check('verdict=pass', $r['verdict'] === 'pass', json_encode($r));
check('满分', $r['score'] === 100);
check('无 issues', $r['issues'] === []);

echo "\n── 3. 质量初判：缺东西 → 需改/拒 ──\n";
$bad1 = ['id'=>'b1','title'=>'包','description'=>'短','price'=>0,'cover'=>'','asset_id'=>'','type'=>'digital'];
$r1 = platops_review($bad1);
check('多问题→reject', $r1['verdict'] === 'reject', json_encode($r1['issues']));
check('识别标题太短', count(array_filter($r1['issues'], fn($i)=>strpos($i,'标题')!==false)) === 1);
check('识别描述太短', count(array_filter($r1['issues'], fn($i)=>strpos($i,'描述')!==false)) === 1);
check('识别缺封面', count(array_filter($r1['issues'], fn($i)=>strpos($i,'封面')!==false)) === 1);
check('识别价格为0', count(array_filter($r1['issues'], fn($i)=>strpos($i,'价格')!==false)) === 1);

$bad2 = ['id'=>'b2','title'=>'正常标题这里','description'=>str_repeat('说明文字够长了呀。',5),'price'=>50,'cover'=>'/c.png','asset_id'=>'','type'=>'digital'];
$r2 = platops_review($bad2);
check('单问题→revise', $r2['verdict'] === 'revise', json_encode($r2));

echo "\n── 4. 夸大用语识别 ──\n";
$hype = ['id'=>'h','title'=>'全网最强增长秘籍','description'=>str_repeat('内容说明文字。',6),'price'=>50,'cover'=>'/c.png','asset_id'=>'a','type'=>'digital'];
$rh = platops_review($hype);
check('识别夸大用语', count(array_filter($rh['issues'], fn($i)=>strpos($i,'夸大')!==false)) === 1, json_encode($rh['issues']));

echo "\n── 5. 定价异常提示（不阻断）──\n";
$peers = [['price'=>100],['price'=>120],['price'=>90],['price'=>110]];
$expensive = ['id'=>'e','title'=>'超贵套餐包','description'=>str_repeat('详细说明内容。',6),'price'=>2000,'cover'=>'/c.png','asset_id'=>'a','type'=>'digital'];
$re = platops_review($expensive, $peers);
check('定价偏高进 notes 不进 issues', count($re['notes']) === 1 && $re['issues'] === [], json_encode($re));
check('偏高仍可通过', $re['verdict'] === 'pass');
$cheap = array_merge($expensive, ['price'=>5]);
check('过低也提示', count(platops_review($cheap, $peers)['notes']) === 1);

echo "\n── 6. 服务类不要求资产 ──\n";
$svc = ['id'=>'s','title'=>'一对一咨询服务','description'=>str_repeat('服务说明内容。',6),'price'=>500,'cover'=>'/c.png','asset_id'=>'','type'=>'service'];
check('service 不因缺资产扣分', platops_review($svc)['verdict'] === 'pass', json_encode(platops_review($svc)));

echo "\n── 7. 判定标签 ──\n";
check('pass 标签', platops_verdict_label('pass') === '建议通过');
check('未知回落原值', platops_verdict_label('x') === 'x');

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
