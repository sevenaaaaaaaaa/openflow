<?php
/**
 * 1v1 咨询 API — 提交报名 + 支付 + 回调
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ConsultationSystem.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// 虎皮椒回调
if ($action === 'notify') {
    $data = $_POST;
    if (con_xfpay_verify($data)) {
        $id = $data['trade_order_id'] ?? '';
        if (($data['status'] ?? '') === 'OD' && con_mark_paid($id, 'xfpay')) {
            echo 'success'; exit;
        }
        echo 'fail'; exit;
    }
    echo 'fail'; exit;
}

header('Content-Type: application/json; charset=utf-8');
$member = member_current();
if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

$settings = con_settings();
if (empty($settings['enabled'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'咨询功能未开启']); exit; }

switch ($action) {
    // 提交报名
    case 'book':
        $mentorId = trim($_POST['mentor_id'] ?? '');
        $data = [
            'company' => $_POST['company'] ?? '',
            'position' => $_POST['position'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'goal' => $_POST['goal'] ?? '',
            'experience' => $_POST['experience'] ?? '',
            'slots' => [
                trim($_POST['slot1'] ?? ''),
                trim($_POST['slot2'] ?? ''),
                trim($_POST['slot3'] ?? ''),
            ],
        ];
        if (empty($data['goal']) || empty($data['phone'])) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'请填写联系方式与咨询目标']);
            exit;
        }
        $result = con_create_booking($member, $mentorId, $data);
        if (!$result['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$result['error']]); exit; }
        echo json_encode(['ok'=>true, 'booking'=>$result['booking']], JSON_UNESCAPED_UNICODE);
        break;

    // 付款
    case 'pay':
        $id = trim($_POST['booking_id'] ?? '');
        $b = con_booking($id);
        if (!$b || $b['member_id'] !== $member['id']) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'预约不存在']); exit; }
        if ($b['status'] !== 'approved') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'当前状态不可付款']); exit; }
        $pay = con_xfpay_create($b, $member);
        if (!$pay['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$pay['error']]); exit; }
        echo json_encode(['ok'=>true, 'payment'=>$pay], JSON_UNESCAPED_UNICODE);
        break;

    // 我的预约列表
    case 'my_bookings':
        $list = array_values(array_filter(con_bookings(), fn($b) => $b['member_id'] === $member['id']));
        usort($list, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        echo json_encode(['ok'=>true, 'bookings'=>$list], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
