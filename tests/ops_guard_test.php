<?php
/**
 * T2-8 验收：渐进自治运营护栏（OpsGuard）
 *   php tests/ops_guard_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-og-' . getmypid());
@mkdir(DATA_DIR . '/ecosystem', 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/OpsGuard.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 默认保守 ──\n";
$c = opsguard_settings();
check('默认不自动上架', $c['auto_approve'] === false);
check('默认不自动下架', $c['auto_takedown'] === false);

echo "\n── 2. 审核关口 ──\n";
check('非 pass 必须人工', opsguard_can_auto_approve('revise')['allow'] === false);
check('reject 必须人工', opsguard_can_auto_approve('reject')['allow'] === false);
check('pass 但未开自动→仍人工', opsguard_can_auto_approve('pass')['allow'] === false);
opsguard_save(['auto_approve'=>true]);
check('开了自动 + pass → 放行', opsguard_can_auto_approve('pass')['allow'] === true);
check('开了自动但 revise 仍拦', opsguard_can_auto_approve('revise')['allow'] === false);

echo "\n── 3. 异常检测：样本不足不下结论（防误伤新人）──\n";
$few = opsguard_detect(['sales'=>2,'refunds'=>2,'complaints'=>0]);
check('样本不足标记', $few['sample_ok'] === false);
check('不因高退款率报异常', $few['abnormal'] === false, json_encode($few['issues']));

echo "\n── 4. 异常检测：够样本才判 ──\n";
opsguard_save(['refund_rate_limit'=>30,'complaint_limit'=>5]);
$high = opsguard_detect(['sales'=>10,'refunds'=>5,'complaints'=>0]);
check('退款率 50% 报异常', $high['abnormal'] === true);
check('建议观察(单项)', $high['suggest'] === 'watch', $high['suggest']);
$multi = opsguard_detect(['sales'=>10,'refunds'=>5,'complaints'=>6]);
check('两项异常→建议下架', $multi['suggest'] === 'takedown');
check('交付失败也算', opsguard_detect(['sales'=>10,'refunds'=>0,'complaints'=>0,'delivery_failures'=>3])['abnormal'] === true);
$clean = opsguard_detect(['sales'=>100,'refunds'=>2,'complaints'=>0]);
check('正常商品无异常', $clean['abnormal'] === false && $clean['suggest'] === 'none');

echo "\n── 5. 自动下架必须显式开启 ──\n";
check('默认不自动执行', $multi['auto'] === false);
opsguard_save(['auto_takedown'=>true,'refund_rate_limit'=>30,'complaint_limit'=>5]);
$multi2 = opsguard_detect(['sales'=>10,'refunds'=>5,'complaints'=>6]);
check('开启后才允许自动', $multi2['auto'] === true);
check('仅观察级别不自动', opsguard_detect(['sales'=>10,'refunds'=>5,'complaints'=>0])['auto'] === false);

echo "\n── 6. 处置留痕 + 申诉 ──\n";
$log = opsguard_log('p1', 'takedown', '退款率 50%', 'agent');
check('记录已写', !empty($log['id']));
check('默认未申诉', $log['appealed'] === false);
check('可按商品查', count(opsguard_logs('p1')) === 1);
check('别的商品查不到', count(opsguard_logs('p999')) === 0);
check('申诉标记成功', opsguard_appeal($log['id'], '客户批量误操作') === true);
$after = opsguard_logs('p1')[0];
check('申诉已记录', $after['appealed'] === true && strpos($after['appeal_note'], '误操作') !== false);
check('不存在的记录申诉失败', opsguard_appeal('nope') === false);

echo "\n── 7. 可解释排序 ──\n";
$ex = opsguard_explain_rank(['id'=>'p1'], ['兴趣匹配'=>20,'已售加成'=>10,'新品扶持'=>0,'重复曝光惩罚'=>-5]);
check('总分正确', $ex['total'] === 25.0, (string)$ex['total']);
check('按影响力排序', $ex['factors'][0]['factor'] === '兴趣匹配');
check('负因子也纳入排序', in_array('重复曝光惩罚', array_column($ex['factors'],'factor'), true));
check('给出首要原因', strpos($ex['top_reason'], '兴趣匹配') !== false);
check('空因子不炸', opsguard_explain_rank(['id'=>'x'], [])['total'] === 0.0);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR.'/ecosystem/*')); @unlink(DATA_DIR.'/settings.json');
@rmdir(DATA_DIR.'/ecosystem'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
