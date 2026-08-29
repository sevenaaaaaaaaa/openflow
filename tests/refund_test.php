<?php
/**
 * 退款功能验收
 *
 *   php tests/refund_test.php
 *
 * 退款涉及金额回滚，重点验证「支付发了多少，退款收回多少」严格对称。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-refund-test-' . getmypid());
@mkdir(DATA_DIR . '/shop', 0777, true);

function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    @mkdir(dirname($f), 0777, true);
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}

// ── 依赖桩：记录所有副作用，便于断言对称性 ──
$GLOBALS['BAL']   = [];   // member_id => 余额变动累计
$GLOBALS['LOGS']  = [];   // point_logs
$GLOBALS['SUBS']  = [];   // 订阅状态
$GLOBALS['SKILLS']= [];   // 已解锁技能
$GLOBALS['FLOW']  = [];   // flow_handle 调用
$GLOBALS['INBOX'] = [];

class Database {
    public static function query(string $sql, array $a = []): array {
        if (str_contains($sql, 'unlocked_skills FROM members')) {
            return [['unlocked_skills' => json_encode($GLOBALS['SKILLS'][$a[0]] ?? [])]];
        }
        if (str_contains($sql, 'SELECT id FROM members')) return [['id' => $a[0]]];
        return [];
    }
    public static function execute(string $sql, array $a = []): bool {
        if (str_contains($sql, 'balance = balance + ?')) {
            $GLOBALS['BAL'][$a[1]] = ($GLOBALS['BAL'][$a[1]] ?? 0) + $a[0];
        } elseif (str_contains($sql, 'balance = balance - ?')) {
            $GLOBALS['BAL'][$a[1]] = ($GLOBALS['BAL'][$a[1]] ?? 0) - $a[0];
        } elseif (str_contains($sql, 'SET unlocked_skills = ?')) {
            $GLOBALS['SKILLS'][$a[1]] = json_decode($a[0], true) ?: [];
        } elseif (str_contains($sql, "UPDATE orders SET status = 'refunded'")) {
            $GLOBALS['ORDER']['status'] = 'refunded';
            $GLOBALS['ORDER']['refund_amount'] = $a[1];
            $GLOBALS['ORDER']['refund_reason'] = $a[2];
        }
        return true;
    }
    public static function insert(string $t, array $row): bool { $GLOBALS['LOGS'][] = $row; return true; }
}
function shop_get_order(string $id): ?array {
    return (($GLOBALS['ORDER']['id'] ?? '') === $id) ? $GLOBALS['ORDER'] : null;
}
function shop_orders_file(): string { return DATA_DIR . '/shop/orders.json'; }
function sub_get_member($m) { return $GLOBALS['SUBS'][$m] ?? null; }
function sub_set_member($m, $d) { $GLOBALS['SUBS'][$m] = $d; }
function inbox_send($m, $t, $b) { $GLOBALS['INBOX'][] = [$m, $t, $b]; }
function flow_handle(string $e, array $c = []): array { $GLOBALS['FLOW'][] = [$e, $c]; return []; }

require_once __DIR__ . '/../lib/PluginSystem.php';

// 只抽 shop_refund_order()，避开 ShopSystem 顶部的一堆 require
$src = file_get_contents(__DIR__ . '/../lib/ShopSystem.php');
if (!preg_match('/\nfunction shop_refund_order\(.*?\n\}\n/s', $src, $m)) {
    fwrite(STDERR, "无法抽取 shop_refund_order()\n"); exit(2);
}
eval($m[0]);

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}
function reset_state(array $o) {
    $GLOBALS['ORDER'] = $o;
    $GLOBALS['BAL'] = []; $GLOBALS['LOGS'] = []; $GLOBALS['FLOW'] = []; $GLOBALS['INBOX'] = [];
    $GLOBALS['SUBS'] = ['m1' => ['member_id'=>'m1','status'=>'active','expires_at'=>'2027-01-01']];
    $GLOBALS['SKILLS'] = ['m1' => ['sk_1', 'sk_2']];
}

$courseOrder = [
    'id'=>'o1','status'=>'paid','amount'=>1000.0,'member_id'=>'m1','email'=>'b@t.com',
    'goods_type'=>'course','course_title'=>'增长课','course_id'=>'c1',
    'referrer_id'=>'r1','commission'=>100.0,'author'=>'a1','platform_fee'=>100.0,
];

echo "\n── 1. 全额退款：金额与状态 ──\n";
reset_state($courseOrder);
$r = shop_refund_order('o1', '用户申请');
check('返回 ok', $r['ok'] === true, $r['error'] ?? '');
check('退款金额 = 订单全额 1000', $r['amount'] === 1000.0, (string)$r['amount']);
check('订单状态置为 refunded', ($GLOBALS['ORDER']['status'] ?? '') === 'refunded');
check('退款原因已记录', ($GLOBALS['ORDER']['refund_reason'] ?? '') === '用户申请');

echo "\n── 2. 全额退款：入账严格对称回收 ──\n";
check('分销佣金回收 -100', ($GLOBALS['BAL']['r1'] ?? 0) === -100.0, (string)($GLOBALS['BAL']['r1'] ?? 0));
// 作者分成 = 1000 - 平台费100 - 佣金100 = 800
check('作者分成回收 -800', ($GLOBALS['BAL']['a1'] ?? 0) === -800.0, (string)($GLOBALS['BAL']['a1'] ?? 0));
$types = array_column($GLOBALS['LOGS'], 'type');
check('写了佣金回收流水', in_array('commission_refund', $types, true));
check('写了作者分成回收流水', in_array('course_author_refund', $types, true));

echo "\n── 3. 课程订单不该动订阅（与 shop_mark_paid 对称：订阅只认 plan_id）──\n";
check('课程订单退款后订阅不受影响', ($GLOBALS['SUBS']['m1']['status'] ?? '') === 'active');

echo "\n── 4. 全额退款：事件与通知 ──\n";
check('发射 refund 事件', ($GLOBALS['FLOW'][0][0] ?? '') === 'refund');
check('事件带订单号', ($GLOBALS['FLOW'][0][1]['order_id'] ?? '') === 'o1');
check('事件标记非部分退款', ($GLOBALS['FLOW'][0][1]['props']['partial'] ?? null) === 0);
check('发了站内信', count($GLOBALS['INBOX']) === 1);

echo "\n── 5. 部分退款：按比例回收，且保留权益 ──\n";
reset_state($courseOrder);
$r = shop_refund_order('o1', '部分退', 250);   // 25%
check('退款金额 = 250', $r['amount'] === 250.0, (string)$r['amount']);
check('佣金按比例回收 -25', ($GLOBALS['BAL']['r1'] ?? 0) === -25.0, (string)($GLOBALS['BAL']['r1'] ?? 0));
check('作者分成按比例回收 -200', ($GLOBALS['BAL']['a1'] ?? 0) === -200.0, (string)($GLOBALS['BAL']['a1'] ?? 0));
check('订阅仍为 active（部分退不撤权益）', ($GLOBALS['SUBS']['m1']['status'] ?? '') === 'active');
check('事件标记为部分退款', ($GLOBALS['FLOW'][0][1]['props']['partial'] ?? null) === 1);

echo "\n── 5b. 订阅订单：全额退款置为 cancelled ──\n";
reset_state(['id'=>'os','status'=>'paid','amount'=>299.0,'member_id'=>'m1',
             'plan_id'=>'pro','period'=>'year']);
$r = shop_refund_order('os', '订阅退订');
check('退款成功', $r['ok'] === true);
check('订阅置为 cancelled', ($GLOBALS['SUBS']['m1']['status'] ?? '') === 'cancelled');

echo "\n── 5c. 订阅订单：部分退款保留订阅 ──\n";
reset_state(['id'=>'os','status'=>'paid','amount'=>299.0,'member_id'=>'m1',
             'plan_id'=>'pro','period'=>'year']);
shop_refund_order('os', '', 50);
check('部分退款订阅仍 active', ($GLOBALS['SUBS']['m1']['status'] ?? '') === 'active');

echo "\n── 6. 技能订单：全额退款撤销解锁 ──\n";
reset_state(['id'=>'o2','status'=>'paid','amount'=>99.0,'member_id'=>'m1',
             'goods_type'=>'skill','goods_id'=>'sk_1']);
shop_refund_order('o2');
check('sk_1 已被移除', !in_array('sk_1', $GLOBALS['SKILLS']['m1'], true));
check('sk_2 未受影响', in_array('sk_2', $GLOBALS['SKILLS']['m1'], true));

echo "\n── 7. 技能订单：部分退款不撤销解锁 ──\n";
reset_state(['id'=>'o2','status'=>'paid','amount'=>99.0,'member_id'=>'m1',
             'goods_type'=>'skill','goods_id'=>'sk_1']);
shop_refund_order('o2', '', 50);
check('部分退款保留 sk_1', in_array('sk_1', $GLOBALS['SKILLS']['m1'], true));

echo "\n── 8. 拒绝非法退款 ──\n";
reset_state(['id'=>'o3','status'=>'pending','amount'=>10.0]);
$r = shop_refund_order('o3');
check('待支付订单不可退', $r['ok'] === false && str_contains($r['error'], '已支付'));

reset_state(['id'=>'o4','status'=>'refunded','amount'=>10.0]);
$r = shop_refund_order('o4');
check('已退款订单不可重复退', $r['ok'] === false && str_contains($r['error'], '已退款'));

reset_state($courseOrder);
$r = shop_refund_order('nope');
check('不存在的订单', $r['ok'] === false && str_contains($r['error'], '不存在'));

echo "\n── 9. 超额退款按全额处理 ──\n";
reset_state($courseOrder);
$r = shop_refund_order('o1', '', 99999);
check('退款金额被夹到订单全额', $r['amount'] === 1000.0, (string)$r['amount']);

echo "\n── 10. 插件钩子 ──\n";
$fired = [];
PluginSystem::add_action('payment_refund', function ($id, $o, $amt, $rs) use (&$fired) { $fired[] = [$id, $amt]; });
reset_state($courseOrder);
shop_refund_order('o1', 'hook 测试');
check('触发 payment_refund', count($fired) === 1);
check('钩子收到正确金额', ($fired[0][1] ?? 0) === 1000.0);

PluginSystem::add_action('payment_refund', function () { throw new RuntimeException('插件炸'); });
reset_state($courseOrder);
$broke = false;
try { $r = shop_refund_order('o1'); } catch (\Throwable $e) { $broke = true; }
check('插件抛错不影响退款', !$broke && ($r['ok'] ?? false) === true);

foreach (glob(DATA_DIR . '/shop/*') ?: [] as $f) if (is_file($f)) @unlink($f);
@rmdir(DATA_DIR . '/shop');
foreach (glob(DATA_DIR . '/*') ?: [] as $f) if (is_file($f)) @unlink($f);
@rmdir(DATA_DIR);

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
