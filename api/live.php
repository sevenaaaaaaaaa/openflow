<?php
/**
 * 直播 API — 聊天 + 房间状态
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/LiveSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    // 房间状态（轮询）
    case 'status':
        $id = $_GET['room_id'] ?? '';
        $r = live_room($id);
        if (!$r) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'房间不存在']); exit; }
        echo json_encode(['ok'=>true, 'status'=>live_status($r), 'is_live'=>!empty($r['is_live'])], JSON_UNESCAPED_UNICODE);
        break;

    // 拉取聊天记录
    case 'chat':
        $id = $_GET['room_id'] ?? '';
        echo json_encode(['ok'=>true, 'messages'=>live_chat($id)], JSON_UNESCAPED_UNICODE);
        break;

    // 发送消息
    case 'send':
        $member = member_current();
        $user = $member ? ($member['name'] ?? '用户') : '游客';
        $id = $_POST['room_id'] ?? '';
        $text = trim($_POST['text'] ?? '');
        if ($text === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'消息不能为空']); exit; }
        $msg = live_chat_send($id, $user, $text);
        echo json_encode(['ok'=>true, 'message'=>$msg], JSON_UNESCAPED_UNICODE);
        break;

    // 预约直播提醒（会员记 member_id，游客留邮箱）
    case 'subscribe':
        $id = trim((string)($_POST['room_id'] ?? ''));
        $r = $id ? live_room($id) : null;
        if (!$r) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'房间不存在']); exit; }
        if (live_status($r) === 'live') { echo json_encode(['ok'=>false,'error'=>'已开播，直接看！']); exit; }
        $member = member_current();
        $contact = $member ? (string)($member['id'] ?? '') : trim((string)($_POST['email'] ?? ''));
        if (!$member && !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请填写有效邮箱']); exit;
        }
        $res = live_subscribe($id, $contact);
        echo json_encode(['ok'=>true, 'dup'=>!empty($res['dup']), 'count'=>live_sub_count($id)], JSON_UNESCAPED_UNICODE);
        break;

    // 预约人数（观看页展示）
    case 'subs':
        $id = $_GET['room_id'] ?? '';
        echo json_encode(['ok'=>true, 'count'=>live_sub_count($id)]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
