<?php
/**
 * 商城 API — 下单 + 虎皮椒支付回调
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ShopSystem.php';
require_once __DIR__ . '/../lib/SubscriptionSystem.php';
require_once __DIR__ . '/../lib/MessageSystem.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// 虎皮椒异步回调（无需登录，验签即可）
if ($action === 'notify') {
    $data = $_POST;
    if (shop_xfpay_verify($data)) {
        $orderId = $data['trade_order_id'] ?? '';
        $status = $data['status'] ?? '';
        if ($status === 'OD' && shop_mark_paid($orderId, 'xfpay')) {
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
        $pay = shop_xfpay_create($order, $member);
        echo json_encode(['ok'=>true, 'order'=>$order, 'payment'=>$pay]);
        break;

    // ─── 创建订单 + 支付跳转 ───
    case 'create_order':
        $courseId = trim($_POST['course_id'] ?? '');
        $result = shop_create_order($member['id'], $courseId);
        if (!$result['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$result['error']]); exit; }
        $pay = shop_xfpay_create($result['order'], $member);
        if (!$pay['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$pay['error']]); exit; }
        echo json_encode(['ok'=>true, 'order'=>$result['order'], 'payment'=>$pay]);
        break;

    // ─── 查询订单 ───
    case 'order_status':
        $oid = trim($_POST['order_id'] ?? '');
        foreach (json_read(shop_orders_file()) as $o) {
            if ($o['id'] === $oid && $o['member_id'] === $member['id']) {
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
