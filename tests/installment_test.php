<?php
/**
 * T2-6 验收：订金/尾款结构化 + 合同确认（InstallmentSystem）
 *   php tests/installment_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-inst-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/InstallmentSystem.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 按比例拆期 ──\n";
$plan = inst_plan_normalize(10000, inst_templates()['half']['parts']);
check('两期', count($plan) === 2);
check('订金 5000', $plan[0]['amount'] === 5000.0);
check('尾款 5000', $plan[1]['amount'] === 5000.0);
check('默认 pending', $plan[0]['status'] === 'pending');
check('有稳定 id', $plan[0]['id'] === 'inst1' && $plan[1]['id'] === 'inst2');

echo "\n── 2. 尾差补到最后一期（总额必须对得上）──\n";
$p3 = inst_plan_normalize(100, inst_templates()['three']['parts']);
$sum = array_sum(array_column($p3, 'amount'));
check('三期合计=100', round($sum,2) === 100.0, (string)$sum);
$odd = inst_plan_normalize(999.99, [['name'=>'a','ratio'=>1/3],['name'=>'b','ratio'=>1/3],['name'=>'c','ratio'=>1/3]]);
check('除不尽也能对上', round(array_sum(array_column($odd,'amount')),2) === 999.99);

echo "\n── 3. 按金额拆期 + 命名兜底 ──\n";
$byAmt = inst_plan_normalize(1000, [['amount'=>300],['name'=>'','amount'=>700]]);
check('金额优先于比例', $byAmt[0]['amount'] === 300.0);
check('空名字自动编号', $byAmt[1]['name'] === '第2期');
check('负金额夹到 0', inst_plan_normalize(100, [['amount'=>-50]])[0]['amount'] === 100.0, '（负数夹0后尾差补足）');

echo "\n── 4. 汇总与进度 ──\n";
$s0 = inst_summary($plan);
check('未付时已收 0', $s0['paid'] === 0.0);
check('待收 10000', $s0['pending'] === 10000.0);
check('进度 0%', $s0['pct'] === 0);
check('下一期是订金', $s0['next']['name'] === '订金');
check('未结清', $s0['settled'] === false);

$plan = inst_mark_paid($plan, 'inst1', '2026-08-30 10:00:00');
$s1 = inst_summary($plan);
check('标记后已收 5000', $s1['paid'] === 5000.0);
check('进度 50%', $s1['pct'] === 50);
check('下一期变尾款', $s1['next']['name'] === '尾款');
check('记录付款时间', $plan[0]['paid_at'] === '2026-08-30 10:00:00');

$plan = inst_mark_paid($plan, 'inst1', '2026-09-01 00:00:00');
check('重复标记幂等(时间不变)', $plan[0]['paid_at'] === '2026-08-30 10:00:00');

$plan = inst_mark_paid($plan, 'inst2');
check('全付清 settled', inst_summary($plan)['settled'] === true);
check('全付清 100%', inst_summary($plan)['pct'] === 100);

echo "\n── 5. 作废期不计入 ──\n";
$withVoid = [['id'=>'i1','amount'=>100,'status'=>'paid'],['id'=>'i2','amount'=>900,'status'=>'void']];
$sv = inst_summary($withVoid);
check('作废不进总额', $sv['total'] === 100.0, (string)$sv['total']);
check('作废后视为结清', $sv['settled'] === true);

echo "\n── 6. 逾期催款 ──\n";
$due = [
    ['id'=>'a','amount'=>100,'status'=>'pending','due'=>'2026-08-01'],
    ['id'=>'b','amount'=>100,'status'=>'pending','due'=>'2026-12-01'],
    ['id'=>'c','amount'=>100,'status'=>'paid','due'=>'2026-01-01'],
    ['id'=>'d','amount'=>100,'status'=>'pending','due'=>''],
];
$od = inst_overdue($due, '2026-08-30');
check('只报未付且已过期', count($od) === 1 && $od[0]['id'] === 'a', json_encode(array_column($od,'id')));

echo "\n── 7. 合同确认与核验 ──\n";
$contract = "服务内容：官网设计\n总价：¥10000\n交付：30 天";
$rec = contract_sign($contract, '张先生', '1.2.3.4');
check('记录签署人', $rec['signer'] === '张先生');
check('记录时间', !empty($rec['signed_at']));
check('记录 IP', $rec['ip'] === '1.2.3.4');
check('有内容指纹', strlen($rec['hash']) === 64);
$v = contract_verify($contract, $rec);
check('原文核验通过', $v['ok'] === true);
check('空白差异不影响', contract_verify("服务内容：官网设计   \n总价：¥10000\n交付：30 天", $rec)['ok'] === true);
$tampered = contract_verify("服务内容：官网设计\n总价：¥1000\n交付：30 天", $rec);
check('改了金额→核验失败', $tampered['ok'] === false);
check('指出被修改', strpos($tampered['reason'], '修改') !== false);
check('无签署记录→失败', contract_verify($contract, [])['ok'] === false);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
