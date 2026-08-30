<?php
/**
 * T1-8 验收：落地页区块级人群定向（BlockTargeting）
 *   php tests/block_targeting_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-blk-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/BlockTargeting.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$guestNew = ['logged_in'=>false,'visitor'=>'new','segments'=>[],'utm_source'=>''];
$memberRet = ['logged_in'=>true,'visitor'=>'return','segments'=>['seg_vip'],'utm_source'=>'weibo'];

echo "\n── 1. 无定向 = 全员可见（默认行为不变）──\n";
check('无 audience 键', blocktarget_visible(['type'=>'hero'], $guestNew) === true);
check('空 audience', blocktarget_visible(['type'=>'hero','audience'=>[]], $memberRet) === true);
check('全 any 也可见', blocktarget_visible(['audience'=>['login'=>'any','visitor'=>'any','segment'=>'','utm'=>'']], $guestNew) === true);

echo "\n── 2. 登录态定向 ──\n";
$inOnly = ['audience'=>['login'=>'in']];
$outOnly = ['audience'=>['login'=>'out']];
check('仅登录：游客不可见', blocktarget_visible($inOnly, $guestNew) === false);
check('仅登录：会员可见', blocktarget_visible($inOnly, $memberRet) === true);
check('仅未登录：游客可见', blocktarget_visible($outOnly, $guestNew) === true);
check('仅未登录：会员不可见', blocktarget_visible($outOnly, $memberRet) === false);

echo "\n── 3. 新老访客定向 ──\n";
check('新访客块：新可见', blocktarget_visible(['audience'=>['visitor'=>'new']], $guestNew) === true);
check('新访客块：回访不可见', blocktarget_visible(['audience'=>['visitor'=>'new']], $memberRet) === false);
check('回访块：回访可见', blocktarget_visible(['audience'=>['visitor'=>'return']], $memberRet) === true);

echo "\n── 4. CDP 分群定向 ──\n";
check('VIP 块：在群可见', blocktarget_visible(['audience'=>['segment'=>'seg_vip']], $memberRet) === true);
check('VIP 块：不在群不可见', blocktarget_visible(['audience'=>['segment'=>'seg_vip']], $guestNew) === false);

echo "\n── 5. UTM 定向（大小写不敏感）──\n";
check('weibo 匹配', blocktarget_visible(['audience'=>['utm'=>'weibo']], $memberRet) === true);
check('WEIBO 也匹配', blocktarget_visible(['audience'=>['utm'=>'WEIBO']], $memberRet) === true);
check('别的来源不匹配', blocktarget_visible(['audience'=>['utm'=>'google']], $memberRet) === false);

echo "\n── 6. 多条件同时成立才可见 ──\n";
$both = ['audience'=>['login'=>'in','segment'=>'seg_vip']];
check('两条都满足→可见', blocktarget_visible($both, $memberRet) === true);
check('缺一条→不可见', blocktarget_visible($both, ['logged_in'=>true,'visitor'=>'new','segments'=>[],'utm_source'=>'']) === false);

echo "\n── 7. 批量过滤 + has_rules ──\n";
$blocks = [
    ['type'=>'a'],
    ['type'=>'b','audience'=>['login'=>'in']],
    ['type'=>'c','audience'=>['visitor'=>'new']],
    'not-an-array',
];
$vis = blocktarget_filter($blocks, $guestNew);
check('游客新访客看到 a+c', count($vis) === 2 && $vis[0]['type']==='a' && $vis[1]['type']==='c', json_encode(array_column($vis,'type')));
check('非数组被跳过', count(blocktarget_filter($blocks, $memberRet)) === 2);
check('has_rules 识别有定向', blocktarget_has_rules($blocks[1]) === true);
check('has_rules 识别无定向', blocktarget_has_rules($blocks[0]) === false);
check('全 any 视为无定向', blocktarget_has_rules(['audience'=>['login'=>'any','visitor'=>'any']]) === false);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
