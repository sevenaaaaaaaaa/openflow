<?php
/**
 * T1-11 验收：创作者增长后台（CreatorGrowth）
 *   php tests/creator_growth_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-cg-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/CreatorGrowth.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }
$ago = fn(int $d) => date('Y-m-d H:i:s', time() - $d*86400);

echo "\n── 1. 买家画像切片 ──\n";
$orders = [
    ['member_id'=>'b1','amount'=>50,'source'=>'自然搜索','created_at'=>$ago(3)],
    ['member_id'=>'b1','amount'=>50,'source'=>'自然搜索','created_at'=>$ago(2)],  // 复购
    ['member_id'=>'b2','amount'=>100,'source'=>'自然搜索','created_at'=>$ago(10)],
    ['member_id'=>'b3','amount'=>60,'source'=>'广告','created_at'=>$ago(1)],
];
$s = creator_stats('c1', $orders);
check('买家数=3(去重)', $s['buyers'] === 3, (string)$s['buyers']);
check('订单数=4', $s['orders'] === 4);
check('收入=260', $s['revenue'] === 260.0);
check('复购买家=1', $s['repeat_buyers'] === 1);
check('复购率=33%', $s['repeat_rate'] === 33, (string)$s['repeat_rate']);
check('客单价=65', $s['avg_order'] === 65.0);
check('最强来源=自然搜索', $s['top_source'] === '自然搜索');
check('最近成交 1 天前', $s['last_sale_days'] === 1, (string)$s['last_sale_days']);

echo "\n── 2. 无订单：冷启动动作 ──\n";
$s0 = creator_stats('c2', []);
check('全 0', $s0['buyers'] === 0 && $s0['revenue'] === 0.0);
check('最近成交=9999(无)', $s0['last_sale_days'] === 9999);
$a0 = creator_actions($s0);
check('给冷启动建议', count(array_filter($a0, fn($a)=>$a['kind']==='launch')) === 1, json_encode(array_column($a0,'kind')));

echo "\n── 3. 复购低 → 复购动作 ──\n";
// 4 个买家、0 复购 → repeat_rate=0 < 30，应命中复购建议
$lowRepeat = creator_stats('c9', [
    ['member_id'=>'a','amount'=>50,'created_at'=>$ago(2)],
    ['member_id'=>'b','amount'=>50,'created_at'=>$ago(2)],
    ['member_id'=>'c','amount'=>50,'created_at'=>$ago(2)],
    ['member_id'=>'d','amount'=>50,'created_at'=>$ago(2)],
]);
$aLow = creator_actions($lowRepeat);
$lowKinds = array_column($aLow, 'kind');
check('复购率=0', $lowRepeat['repeat_rate'] === 0);
check('含复购建议', in_array('repurchase', $lowKinds, true), json_encode($lowKinds));
check('理由带具体数字', strpos($aLow[0]['why'], '4 个买家') !== false, $aLow[0]['why']);

echo "\n── 4. 客单价低 → 定价动作 ──\n";
$a1 = creator_actions($s);
$kinds = array_column($a1, 'kind');
check('含定价建议', in_array('pricing', $kinds, true), json_encode($kinds));
check('复购率达标时不推复购', !in_array('repurchase', $kinds, true));

echo "\n── 5. 长期没出单 → 唤醒动作 ──\n";
$stale = creator_stats('c3', [['member_id'=>'x','amount'=>500,'created_at'=>$ago(30)]]);
$a2 = creator_actions($stale);
check('含唤醒建议', in_array('revive', array_column($a2,'kind'), true), json_encode(array_column($a2,'kind')));
check('理由含天数', strpos(implode('', array_column($a2,'title')), '30 天') !== false);

echo "\n── 6. 最多 3 条 + 总有兜底 ──\n";
check('不超过 3 条', count($a1) <= 3, (string)count($a1));
$empty = creator_actions(['buyers'=>1,'orders'=>1,'revenue'=>500,'repeat_rate'=>100,'avg_order'=>500,'last_sale_days'=>1,'top_source'=>'']);
check('无命中也给兜底 1 条', count($empty) >= 1);

echo "\n── 7. AI 润色可注入 + 失败回落 ──\n";
$GLOBALS['CREATOR_AI_FN'] = function($a,$s){ return [['kind'=>'ai','title'=>'AI 建议','why'=>'x','cta'=>'y']]; };
check('AI 覆盖生效', creator_actions_polish($a1, $s)[0]['title'] === 'AI 建议');
$GLOBALS['CREATOR_AI_FN'] = function($a,$s){ return []; };
check('AI 返回空→回落原建议', creator_actions_polish($a1, $s) === $a1);
unset($GLOBALS['CREATOR_AI_FN']);

echo "\n── 8. dashboard 一次拿全 ──\n";
$d = creator_dashboard('c1', $orders);
check('含 stats 与 actions', isset($d['stats']['buyers']) && count($d['actions']) >= 1);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
