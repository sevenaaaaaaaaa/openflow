<?php
/**
 * 商城 API — 实体商品下单 / 积分兑换
 * POST /api/mall.php
 *   {action: order_product, product_id, qty}
 *   {action: redeem, points_product_id}
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MallSystem.php';
require_once __DIR__ . '/../lib/ShopSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']); exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$member = member_current();
if (empty($member['id'])) {
    echo json_encode(['ok' => false, 'error' => '请先登录', 'need_login' => true]); exit;
}

$action = trim($input['action'] ?? '');
switch ($action) {
    case 'order_product':
        $r = mall_order_product($member['id'], trim($input['product_id'] ?? ''), max(1, (int)($input['qty'] ?? 1)));
        if (!$r['ok']) { echo json_encode($r); exit; }
        // 虎皮椒支付
        $pay = shop_xfpay_create($r['order'], $member);
        if (!$pay['ok']) { echo json_encode(['ok' => false, 'error' => $pay['error']]); exit; }
        echo json_encode(['ok' => true, 'order' => $r['order'], 'payment' => $pay]);
        exit;

    case 'redeem':
        $r = mall_redeem_points($member['id'], trim($input['points_product_id'] ?? ''));
        echo json_encode($r);
        exit;

    default:
        echo json_encode(['ok' => false, 'error' => 'unknown action']); exit;
}
