<?php
/**
 * B1 推荐码体系契约测试
 * 验证：推荐码生成 · 点击归因 · 注册归因 · 成交归因 · 佣金结算 · 提现
 */
$tmp = sys_get_temp_dir() . '/of-ref-' . getmypid();
@mkdir($tmp . '/referral', 0777, true);
@mkdir($tmp . '/shop', 0777, true);
@mkdir($tmp . '/members', 0777, true);
@mkdir($tmp . '/db', 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
$_SERVER['REQUEST_URI'] = '/test';
$_SERVER['HTTP_HOST'] = 'test.local';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_SCHEME'] = 'https';
session_id('ref_test_' . getmypid());
@session_start();
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ReferralSystem.php';

// 初始化设置
file_put_contents($tmp . '/referral/settings.json', json_encode([
    'enabled' => true,
    'commission_rate' => 0.10,
    'settlement_threshold' => 100,
    'cookie_days' => 30,
]));

$pass = 0; $fail = 0;
function ok(bool $c, string $n) { global $pass, $fail; if ($c) { $pass++; echo "  ✓ $n\n"; } else { $fail++; echo "  ✗ $n\n"; } }

echo "── B1 推荐码体系契约测试 ──\n\n";

// 1. 创建推荐码
$r = ref_create_code('aff1', '一级分销码');
ok($r['ok'], '创建推荐码成功');
ok(!empty($r['code']['code']), '推荐码非空');
ok(!empty($r['code']['url']), '推荐链接非空');
$affCode = $r['code']['code'];

// 2. 查询分销商推荐码
$codes = ref_codes_for('aff1');
ok(count($codes) === 1, '分销商有1个推荐码');

// 3. 创建多个推荐码
ref_create_code('aff1', '二级码');
ref_create_code('aff1', '活动码');
ok(count(ref_codes_for('aff1')) === 3, '分销商有3个推荐码');

// 4. 推荐码上限（5个）
ref_create_code('aff1', '码4');
ref_create_code('aff1', '码5');
$r = ref_create_code('aff1', '码6');
ok(!$r['ok'], '超过5个限制被拒');

// 5. 推荐码查找
$found = ref_find_code($affCode);
ok($found !== null, '推荐码查找成功');
ok($found['member_id'] === 'aff1', '归属分销商正确');
ok(ref_find_code('INVALID') === null, '无效码返回null');

// 6. 点击归因
$r = ref_track_click($affCode);
ok($r['ok'], '点击归因成功');
ok($r['member_id'] === 'aff1', '点击归因到正确分销商');

// 7. 点击计数
$codes = ref_codes_for('aff1');
$refCode = array_filter($codes, fn($c) => $c['code'] === $affCode);
$refCode = array_values($refCode)[0] ?? [];
ok(($refCode['clicks'] ?? 0) === 1, '点击计数正确');

// 8. 注册归因（CLI 无 cookie，传 code）
$r = ref_attribute_registration('newuser1', $affCode);
ok($r['ok'], '注册归因成功');
ok($r['referrer'] === 'aff1', '归因到正确分销商');

// 9. 成交归因 + 佣金计算（CLI 无 cookie，传 code）
$r = ref_attribute_order('order1', 1000.00, $affCode);
ok($r['ok'], '成交归因成功');
ok($r['commission'] === 100.00, '佣金金额正确（10%×1000）');
ok($r['referrer'] === 'aff1', '归因到正确分销商');

// 10. 分销商统计
$stats = ref_stats('aff1');
ok($stats['clicks'] === 1, '点击数1');
ok($stats['conversions'] === 1, '转化数1');
ok($stats['pending_commission'] === 100.00, '待结算佣金100');

// 11. 结算
$r = ref_settle('aff1');
ok($r['ok'], '结算成功');
ok($r['settled'] == 100, '结算金额100');

// 12. 结算后余额
ok(ref_balance('aff1') === 100.00, '可提现余额100');

// 13. 提现申请（金额需≥门槛100）
$r = ref_request_payout('aff1', 100.00, 'wechat', 'wx_user123');
ok($r['ok'], '提现申请成功');
ok(ref_balance('aff1') === 0.00, '提现后余额0');

// 14. 余额不足
$r = ref_request_payout('aff1', 1.00);
ok(!$r['ok'], '余额不足被拒');

// 15. 多分销商独立
ref_create_code('aff2', '分销商2码');
$r = ref_track_click(ref_codes_for('aff2')[0]['code'] ?? '');
ok($r['ok'], '分销商2点击成功');
ok(ref_stats('aff2')['clicks'] === 1, '分销商2独立计数');

// 16. 结算单记录
$settlements = ref_settlements_for('aff1');
ok(count($settlements) === 2, '2条结算记录（1结算+1提现）');

echo "\n通过 {$pass} · 失败 {$fail}\n";
exit($fail === 0 ? 0 : 1);
