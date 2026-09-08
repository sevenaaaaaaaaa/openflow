<?php
/**
 * A2 购物车契约测试
 * 验证：多商品 · 优惠码 · 结算 · 会员折扣
 */
$tmp = sys_get_temp_dir() . '/of-cart-' . getmypid();
@mkdir($tmp . '/shop', 0777, true);
@mkdir($tmp . '/db', 0777, true);
@mkdir($tmp . '/members', 0777, true);
@mkdir($tmp . '/coupon', 0777, true);
// 让 admin/config.php 用我们指定的 tmp 目录
putenv('OF_DATA_DIR=' . $tmp);
$_SERVER['REQUEST_URI'] = '/test';
$_SERVER['HTTP_HOST'] = 'test.local';
$_SERVER['REQUEST_METHOD'] = 'GET';
session_id('cart_test_' . getmypid());
@session_start();
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CartSystem.php';
require_once __DIR__ . '/../lib/CouponSystem.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $n) { global $pass, $fail; if ($c) { $pass++; echo "  ✓ $n\n"; } else { $fail++; echo "  ✗ $n\n"; } }

echo "── A2 购物车契约测试 ──\n\n";

// 1. 购物车基本操作
cart_clear();
ok(cart_is_empty(), '初始购物车为空');

$r = cart_add('course1', 'course', '增长系统入门', 99.00, 1);
ok($r['ok'], '添加商品成功');
ok(!cart_is_empty(), '购物车非空');
ok(cart_item_count() === 1, '商品数量1');

$r = cart_add('course2', 'course', '高级增长策略', 199.00, 2);
ok($r['ok'], '添加第二个商品');
ok(cart_item_count() === 3, '总数量3（1+2）');

// 2. 更新数量
cart_update_qty('course1', 'course', 3);
ok(cart_item_count() === 5, '更新数量后5个（3+2）');

// 3. 移除商品
cart_remove('course1', 'course');
ok(cart_item_count() === 2, '移除后剩2个');

// 4. 价格计算
$summary = cart_summary();
ok($summary['subtotal'] === 398.00, '小计 199*2=398');
ok($summary['total'] === 398.00, '无优惠码时总价=小计');
ok($summary['item_count'] === 2, '商品数2');

// 5. 清空购物车
cart_clear();
ok(cart_is_empty(), '清空后为空');

// 6. 多类型商品
cart_add('course1', 'course', '课程A', 99.00, 1);
cart_add('skill1', 'skill', '技能包B', 49.00, 2);
cart_add('template1', 'template', '模板C', 29.00, 1);
ok(cart_item_count() === 4, '混合类型4件');

// 7. 优惠码应用
coupon_save([
    'id' => 'c1', 'code' => 'SAVE10', 'name' => '立减10',
    'type' => 'fixed', 'value' => 10, 'min_amount' => 50,
    'max_uses' => 100, 'enabled' => true,
]);
$r = cart_apply_coupon('SAVE10');
ok($r['ok'], '优惠码应用成功');
$summary = cart_summary();
ok($summary['coupon'] === 'SAVE10', '优惠码已记录');
ok($summary['discount'] == 10, '折扣10元');
ok($summary['total'] === 216.00, '总价 99+98+29-10=216');

// 8. 移除优惠码
cart_remove_coupon();
$summary = cart_summary();
ok(empty($summary['coupon']), '优惠码已移除');
ok($summary['discount'] == 0, '折扣清零');

// 9. 无效优惠码
$r = cart_apply_coupon('FAKECODE');
ok(!$r['ok'], '无效优惠码被拒');

// 10. 结算免费订单（价格为0）
cart_clear();
cart_add('free1', 'course', '免费课程', 0.00, 1);
$r = cart_checkout('test_member');
ok($r['ok'], '免费订单结算成功');
ok($r['free'] === true, '标记为免费');
ok(cart_is_empty(), '结算后购物车清空');

// 11. 结算付费订单（不指定支付方式，只创建订单）
cart_clear();
cart_add('paid1', 'course', '付费课程', 199.00, 1);
$r = cart_checkout('test_member');
ok($r['ok'], '付费订单创建成功');
ok($r['total'] === 199.00, '订单金额正确');
ok(!empty($r['order_id']), '有订单ID');

// 12. 空购物车结算
cart_clear();
$r = cart_checkout('test_member');
ok(!$r['ok'], '空购物车结算被拒');

// 13. 重复添加同商品
cart_clear();
cart_add('course1', 'course', '课程A', 99.00, 1);
cart_add('course1', 'course', '课程A', 99.00, 2);
ok(cart_item_count() === 3, '同商品合并数量');

// 14. 数量更新为0
cart_update_qty('course1', 'course', 0);
ok(cart_is_empty(), '数量为0时自动移除');

// 15. 边界情况
$r = cart_apply_coupon('', null);
ok(!$r['ok'], '空优惠码被拒');

echo "\n通过 {$pass} · 失败 {$fail}\n";
exit($fail === 0 ? 0 : 1);
