<?php
/**
 * 购物车 API（CartSystem 接线 —— 293 行完整实现此前零调用）
 * POST /api/cart.php  {action: add|update|remove|coupon|remove_coupon|summary|checkout, ...}
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CartSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
cors_headers();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = (string)($input['action'] ?? '');
$member = function_exists('member_current') ? member_current() : null;

switch ($action) {
    case 'summary':
        echo json_encode(['ok' => true] + cart_summary($member), JSON_UNESCAPED_UNICODE);
        break;

    case 'add':
        $r = cart_add(
            trim((string)($input['item_id'] ?? '')),
            in_array($input['type'] ?? '', ['course','product','skill','bundle'], true) ? $input['type'] : 'product',
            trim((string)($input['name'] ?? '')),
            (float)($input['price'] ?? 0),
            max(1, (int)($input['qty'] ?? 1))
        );
        echo json_encode(['ok' => ($r['ok'] ?? false)] + $r + cart_summary($member), JSON_UNESCAPED_UNICODE);
        break;

    case 'update':
        $r = cart_update_qty(trim((string)($input['item_id'] ?? '')), (string)($input['type'] ?? 'product'), max(0, (int)($input['qty'] ?? 1)));
        echo json_encode(['ok' => ($r['ok'] ?? false)] + $r + cart_summary($member), JSON_UNESCAPED_UNICODE);
        break;

    case 'remove':
        $r = cart_remove(trim((string)($input['item_id'] ?? '')), (string)($input['type'] ?? 'product'));
        echo json_encode(['ok' => ($r['ok'] ?? false)] + cart_summary($member), JSON_UNESCAPED_UNICODE);
        break;

    case 'coupon':
        $r = cart_apply_coupon(trim((string)($input['code'] ?? '')), $member);
        echo json_encode($r + cart_summary($member), JSON_UNESCAPED_UNICODE);
        break;

    case 'remove_coupon':
        cart_remove_coupon();
        echo json_encode(['ok' => true] + cart_summary($member), JSON_UNESCAPED_UNICODE);
        break;

    case 'checkout':
        if (!$member) { http_response_code(401); echo json_encode(['ok' => false, 'error' => '请先登录']); exit; }
        $r = cart_checkout((string)$member['id'], (string)($input['method'] ?? ''));
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        break;

    case 'clear':
        cart_clear();
        echo json_encode(['ok' => true] + cart_summary($member), JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '未知操作']);
}
