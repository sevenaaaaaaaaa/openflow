<?php
/**
 * 商城 API — 下单 + 虎皮椒支付回调
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ShopSystem.php';
require_once __DIR__ . '/../lib/SubscriptionSystem.php';
require_once __DIR__ . '/../lib/MessageSystem.php';
require_once __DIR__ . '/../lib/PaymentChannel.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// 支付异步回调（无需登录，统一验签；channel 指定渠道，默认 xfpay）
if ($action === 'notify') {
    $channel = trim($_GET['channel'] ?? ($_POST['channel'] ?? 'xfpay'));
    $data = $_POST;
    if (payment_channel_verify($channel, $data)) {
        $orderId = $data['trade_order_id'] ?? ($data['out_trade_no'] ?? '');
        $status = $data['status'] ?? '';
        $paid = false;
        if ($channel === 'xfpay') { $paid = ($status === 'OD') && shop_mark_paid($orderId, $channel); }
        else { $paid = shop_mark_paid($orderId, $channel); }
        if ($paid) {
            // 站内信通知（兼容 JSON 订单 + SQLite 订单）
            $notifyMember = '';
            $notifyTitle = '';
            $jsonOrders = json_read(shop_orders_file());
            foreach ($jsonOrders as $o) {
                if ($o['id'] === $orderId) { $notifyMember = $o['member_id'] ?? ''; $notifyTitle = $o['course_title'] ?? $o['goods_title'] ?? ''; break; }
            }
            if (!$notifyMember) {
                try {
                    $rows = Database::query("SELECT * FROM orders WHERE id = ?", [$orderId]);
                    if ($rows) { $notifyMember = $rows[0]['member_id'] ?? ''; $notifyTitle = $rows[0]['course_title'] ?? ''; }
                } catch (Exception $e) {}
            }
            if ($notifyMember) inbox_notify_event('order_paid', ['member_id' => $notifyMember, 'title' => $notifyTitle]);
            echo 'success';
            exit;
        }
        echo 'fail';
        exit;
    }
    echo 'fail';
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ─── 收款链接支付（公开，凭 token，无需登录）───
// 客户不一定是注册会员，所以这条必须在下面的登录闸之前。
if ($action === 'pay_quote') {
    require_once __DIR__ . '/../lib/QuoteSystem.php';
    $order = quote_get_by_token(trim($_POST['token'] ?? ($_GET['token'] ?? '')));
    if (!$order)                                   { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'收款单不存在']); exit; }
    if (($order['status'] ?? '') === 'paid')       { echo json_encode(['ok'=>false,'error'=>'该收款单已支付']); exit; }
    if (($order['status'] ?? '') === 'refunded')   { echo json_encode(['ok'=>false,'error'=>'该收款单已退款']); exit; }
    if (quote_is_expired($order))                  { echo json_encode(['ok'=>false,'error'=>'该收款链接已过期']); exit; }
    $channel = trim($_POST['channel'] ?? 'xfpay');
    $pay = payment_channel_create($channel, $order);
    if (empty($pay['ok'])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$pay['error'] ?? '发起支付失败']); exit; }
    echo json_encode(['ok'=>true, 'order'=>['id'=>$order['id'],'amount'=>$order['amount'],'title'=>$order['goods_title'] ?? '收款'], 'payment'=>$pay]);
    exit;
}

$member = member_current();
if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

switch ($action) {
    // ─── 创建订阅订单 ───
    case 'create_subscription':
        $planId = trim($_POST['plan_id'] ?? '');
        $plan = null;
        foreach (sub_get_plans() as $p) if ($p['id'] === $planId && !empty($p['enabled'])) { $plan = $p; break; }
        if (!$plan) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'订阅计划不存在']); exit; }
        $orders = json_read(shop_orders_file());
        $order = [
            'id' => 'order_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'member_id' => $member['id'],
            'course_id' => 'subscription:' . $planId,
            'course_title' => '订阅：' . $plan['name'],
            'amount' => (float)$plan['price'],
            'status' => 'pending',
            'payment_method' => '',
            'referrer_id' => $member['referred_by'] ?? '',
            'commission' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'paid_at' => '',
            'plan_id' => $planId,
            'period' => $plan['period'] ?? 'month',
            'utm' => shop_current_utm(),
        ];
        $orders[] = $order;
        json_write(shop_orders_file(), $orders);
        $channel = trim($_POST['channel'] ?? 'xfpay');
        $pay = payment_channel_create($channel, $order);
        echo json_encode(['ok'=>true, 'order'=>$order, 'payment'=>$pay]);
        break;

    // ─── 创建订单 + 支付跳转 ───
    case 'create_order':
        $courseId = trim($_POST['course_id'] ?? '');
        $ref = trim($_POST['ref'] ?? ($_GET['ref'] ?? $_COOKIE['of_ref'] ?? ''));
        $result = shop_create_order($member['id'], $courseId, $ref);
        if (!$result['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$result['error']]); exit; }
        $channel = trim($_POST['channel'] ?? 'xfpay');
        $pay = payment_channel_create($channel, $result['order']);
        if (!$pay['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$pay['error']]); exit; }
        if (!empty($result['order']['referrer_id'])) setcookie('of_ref', $result['order']['referrer_id'], time() + 86400 * 30, '/');
        echo json_encode(['ok'=>true, 'order'=>$result['order'], 'payment'=>$pay]);
        break;

    // ─── 查询订单 ───
    case 'order_status':
        $oid = trim($_POST['order_id'] ?? '');
        foreach (shop_orders_for_member($member['id']) as $o) {
            if ($o['id'] === $oid) {
                echo json_encode(['ok'=>true, 'status'=>$o['status']]);
                exit;
            }
        }
        echo json_encode(['ok'=>true, 'status'=>'not_found']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
