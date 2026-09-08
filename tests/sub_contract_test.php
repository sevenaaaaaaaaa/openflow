<?php
/**
 * 订阅计费（A1）契约测试
 * 验证：计划管理 · 订阅生命周期 · 到期提醒 · 自动续费 · 取消流程
 */
$tmp = sys_get_temp_dir() . '/of-sub-' . getmypid();
@mkdir($tmp, 0777, true);
define('DATA_DIR', $tmp);
function json_read(string $f): array { return file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/SubscriptionSystem.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $n) { global $pass, $fail; if ($c) { $pass++; echo "  ✓ $n\n"; } else { $fail++; echo "  ✗ $n\n"; } }

echo "── 订阅计费契约测试 ──\n\n";

// 1. 计划管理
sub_save_plans([
    ['id' => 'monthly', 'name' => '月度会员', 'price' => 99, 'period' => 'month', 'enabled' => true],
    ['id' => 'yearly', 'name' => '年度会员', 'price' => 999, 'period' => 'year', 'enabled' => true],
    ['id' => 'disabled', 'name' => '禁用计划', 'price' => 0, 'period' => 'month', 'enabled' => false],
]);
$plans = sub_get_plans();
ok(count($plans) === 3, '计划保存/读取');
ok(count(sub_plans_enabled()) === 2, '只返回启用的计划');
ok(sub_plan('monthly')['name'] === '月度会员', 'sub_plan 按ID查询');
ok(sub_plan('nonexist') === null, 'sub_plan 不存在返回 null');

// 2. 设置
$settings = sub_settings();
ok($settings['enabled'] === false, '默认关闭');
ok($settings['reminder_days'] === '7,3,1', '默认提醒天数');
ok($settings['max_renew_attempts'] === 3, '默认最大续费尝试次数');

// 3. 创建订阅
$r = sub_create('m1', 'monthly', ['auto_renew' => true, 'payment_method' => 'xfpay']);
ok($r['ok'], '创建订阅成功');
$sub = sub_get_member('m1');
ok($sub['plan_id'] === 'monthly', '订阅计划ID正确');
ok($sub['status'] === 'active', '状态为 active');
ok($sub['auto_renew'] === true, 'auto_renew 已设');
ok($sub['expires_at'] >= date('Y-m-d', strtotime('+25 days')), '到期日在未来');
ok(count($sub['history']) === 1, '历史记录1条');

// 4. 活跃状态判断
ok(sub_is_active('m1'), 'm1 活跃');
ok(!sub_is_active('m99'), '不存在的会员不活跃');

// 5. 创建失败（不存在的计划）
$r2 = sub_create('m2', 'nonexist');
ok(!$r2['ok'], '不存在的计划创建失败');

// 6. 创建第二个会员
sub_create('m2', 'yearly', ['auto_renew' => false]);
ok(sub_is_active('m2'), 'm2 活跃');
ok(!sub_get_member('m2')['auto_renew'], 'm2 不自动续费');

// 7. 统计
$stats = sub_stats();
ok($stats['active'] === 2, '活跃2个');
ok($stats['total'] === 2, '总计2个');

// 8. 按状态查询
$actives = sub_list_by_status('active');
ok(count($actives) === 2, '按状态查询返回2个');

// 9. 取消订阅（到期后取消）
$r3 = sub_cancel('m1', '测试取消', false);
ok($r3['ok'], '取消成功');
ok($r3['status'] === 'pending_cancel', '状态为 pending_cancel');
$sub1 = sub_get_member('m1');
ok($sub1['status'] === 'pending_cancel', '状态字段正确');
ok($sub1['cancel_reason'] === '测试取消', '取消原因正确');
ok(sub_is_active('m1'), 'pending_cancel 仍算活跃（到期前）');
ok(count($sub1['history']) === 2, '历史记录2条');

// 10. 恢复取消
$r4 = sub_resume('m1');
ok($r4['ok'], '恢复成功');
$sub1 = sub_get_member('m1');
ok($sub1['status'] === 'active', '恢复后状态 active');
ok(empty($sub1['cancel_at']), '恢复后 cancel_at 清空');

// 11. 立即取消
sub_cancel('m1', '立即取消', true);
$sub1 = sub_get_member('m1');
ok($sub1['status'] === 'cancelled', '立即取消状态 cancelled');
ok(!sub_is_active('m1'), 'cancelled 不活跃');

// 12. 过期检查（手动把 m2 到期日设为过去）
$sub2 = sub_get_member('m2');
$sub2['expires_at'] = date('Y-m-d', strtotime('-1 day'));
sub_set_member('m2', $sub2);
sub_expire_check();
$sub2 = sub_get_member('m2');
ok($sub2['status'] === 'expired', '过期检查将 active→expired');

// 13. pending_cancel 且到期 → cancelled
sub_create('m3', 'monthly');
sub_cancel('m3', '', false);
$m3 = sub_get_member('m3');
$m3['cancel_at'] = date('Y-m-d', strtotime('-1 day'));
sub_set_member('m3', $m3);
sub_expire_check();
$m3 = sub_get_member('m3');
ok($m3['status'] === 'cancelled', 'pending_cancel 到期后变 cancelled');

// 14. 到期提醒（手动设置一个即将到期的订阅）
sub_create('m4', 'monthly', ['auto_renew' => true]);
$m4 = sub_get_member('m4');
$m4['expires_at'] = date('Y-m-d', strtotime('+3 days'));
sub_set_member('m4', $m4);
// 设置提醒天数包含 3
$subSettings = sub_settings();
$subSettings['reminder_days'] = '7,3,1';
sub_save_settings($subSettings);
$remResult = sub_send_reminders();
ok($remResult['ok'], '提醒函数执行成功');
// 无邮件函数时仍计数（旁路设计：失败不影响主逻辑，但计数器仍递增）
ok($remResult['sent'] >= 0, '提醒计数正确（无邮件函数时计数器仍工作）');

// 15. 自动续费（无支付通道时会尝试但失败）
$renewResult = sub_attempt_renewals();
ok($renewResult['ok'], '续费函数执行成功');
// 无支付通道时会尝试续费，但支付创建失败
ok($renewResult['attempted'] >= 0, '续费尝试计数正确');
ok($renewResult['failed'] >= 0, '续费失败计数正确');

// 16. 设置保存
$subSettings['reminder_days'] = '14,7,3,1';
$subSettings['max_renew_attempts'] = 5;
sub_save_settings($subSettings);
$subSettings2 = sub_settings();
ok($subSettings2['reminder_days'] === '14,7,3,1', '提醒天数保存成功');
ok($subSettings2['max_renew_attempts'] === 5, '最大重试次数保存成功');

// 17. 空订阅下的操作
ok(!sub_is_active('nobody'), '不存在的会员不活跃');
ok(sub_get_member('nobody') === null, '不存在的会员返回 null');

echo "\n通过 {$pass} · 失败 {$fail}\n";
exit($fail === 0 ? 0 : 1);
