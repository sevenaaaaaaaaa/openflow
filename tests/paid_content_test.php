<?php
/**
 * T1-6 验收：付费 Newsletter / 会员专享内容（PaidContent）
 *   php tests/paid_content_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-paid-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
// 桩：套餐顺序 free < annual < lifetime
function mem_plans(): array {
    return [
        ['id'=>'free','name'=>'免费用户','icon'=>'👤'],
        ['id'=>'annual','name'=>'年度会员','icon'=>'⭐'],
        ['id'=>'lifetime','name'=>'永久会员','icon'=>'👑'],
    ];
}
require_once __DIR__ . '/../lib/PaidContent.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$guest = null;
$freeM = ['id'=>'m1','email'=>'f@t.com','membership_plan'=>'free'];
$annual = ['id'=>'m2','email'=>'a@t.com','membership_plan'=>'annual'];
$life = ['id'=>'m3','email'=>'l@t.com','membership_plan'=>'lifetime'];

echo "\n── 1. 等级判定 ──\n";
check('游客 tier=空', paid_member_tier($guest) === '');
check('免费会员 tier=member', paid_member_tier($freeM) === 'member');
check('年度会员 tier=annual', paid_member_tier($annual) === 'annual');

echo "\n── 2. 门禁：公开 ──\n";
check('游客可看公开', paid_can_view($guest, '') === true);

echo "\n── 3. 门禁：仅登录会员 ──\n";
check('游客被拦', paid_can_view($guest, 'member') === false);
check('免费会员可看', paid_can_view($freeM, 'member') === true);
check('年度会员可看', paid_can_view($annual, 'member') === true);

echo "\n── 4. 门禁：年度及以上 ──\n";
check('游客被拦', paid_can_view($guest, 'annual') === false);
check('免费会员被拦', paid_can_view($freeM, 'annual') === false);
check('年度会员可看', paid_can_view($annual, 'annual') === true);
check('永久会员可看(更高)', paid_can_view($life, 'annual') === true);
check('年度看不了永久专享', paid_can_view($annual, 'lifetime') === false);
check('未知门槛不误伤', paid_can_view($freeM, 'bogus_tier') === true);

echo "\n── 5. 付费墙预览 ──\n";
$short = '<p>短内容</p>';
check('短内容不截断', paid_preview($short)['truncated'] === false);
$long = '<p>' . str_repeat('第一段内容', 40) . '</p><p>' . str_repeat('第二段秘密', 40) . '</p>';
$pv = paid_preview($long, 100);
check('长内容截断', $pv['truncated'] === true);
check('保留首段', strpos($pv['preview'], '第一段内容') !== false);
check('隐藏后续段落', strpos($pv['preview'], '第二段秘密') === false);

echo "\n── 6. 升级提示 ──\n";
check('member 提示登录', strpos(paid_upgrade_hint('member'), '登录') !== false);
check('annual 提示套餐名', strpos(paid_upgrade_hint('annual'), '年度会员') !== false, paid_upgrade_hint('annual'));

echo "\n── 7. 付费通讯投递名单 ──\n";
$subs = [
    ['email'=>'f@t.com'], ['email'=>'a@t.com'], ['email'=>'l@t.com'],
    ['email'=>'ghost@t.com'], ['email'=>'gone@t.com','status'=>'unsubscribed'],
];
$members = [$freeM, $annual, $life];
$all = paid_news_audience('', $subs, $members);
check('免费期→全部有效订阅(4)', count($all) === 4, json_encode($all));
check('退订者被排除', !in_array('gone@t.com', $all, true));
$paid = paid_news_audience('annual', $subs, $members);
check('付费期→只发年度及以上(2)', count($paid) === 2, json_encode($paid));
check('含年度会员', in_array('a@t.com', $paid, true));
check('含永久会员', in_array('l@t.com', $paid, true));
check('不含免费会员', !in_array('f@t.com', $paid, true));
check('不含非会员订阅者', !in_array('ghost@t.com', $paid, true));

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
