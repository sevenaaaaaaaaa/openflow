<?php
/**
 * 直播 API — 聊天 + 房间状态
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/LiveSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/LiveInteractions.php';

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

    // 拉取聊天记录（顺带带回点赞数与主播推品，省一次轮询）
    case 'chat':
        $id = $_GET['room_id'] ?? '';
        $r = live_room($id);
        $push = null;
        if ($r && !empty($r['push']) && is_array($r['push']) && !empty($r['push']['title'])) {
            $push = $r['push'];   // ['title','link','price','ts']
        }
        echo json_encode(['ok'=>true, 'messages'=>live_chat($id), 'likes'=>live_likes($id), 'push'=>$push,
            'giveaway'=>li_giveaway_current($id), 'flash'=>li_flash($id)], JSON_UNESCAPED_UNICODE);
        break;

    // 发送消息（带风控）
    case 'send':
        $member = member_current();
        $user = $member ? ($member['name'] ?? '用户') : '游客';
        $id = $_POST['room_id'] ?? '';
        $text = trim($_POST['text'] ?? '');
        if ($text === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'消息不能为空']); exit; }
        if (mb_strlen($text) > 100) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'最多 100 字']); exit; }
        $reject = live_risk_check($id, $text);
        if ($reject) { http_response_code(429); echo json_encode(['ok'=>false,'error'=>$reject]); exit; }
        $msg = live_chat_send($id, $user, $text);
        echo json_encode(['ok'=>true, 'message'=>$msg], JSON_UNESCAPED_UNICODE);
        break;

    // 点赞（连击限速，返回最新总数）
    case 'like':        $id = trim((string)($_POST['room_id'] ?? ''));
        if (!live_room($id)) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'房间不存在']); exit; }
        $r = live_like($id);
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        break;

    // 观看心跳（30s/次，LOW_POWER 60s；按会话去重累计观看时长）
    case 'view':
        $id = trim((string)($_POST['room_id'] ?? ''));
        if (!live_room($id)) { http_response_code(404); echo json_encode(['ok'=>false]); exit; }
        live_view_ping($id);
        $s = live_view_stats($id);
        echo json_encode(['ok'=>true] + $s, JSON_UNESCAPED_UNICODE);
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

    // 直播互动：抽奖参与 / 秒杀抢购 / 连麦申请 / 回放切片
    case 'giveaway_enter': {
        $m = member_current();
        if (!$m) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }
        echo json_encode(li_giveaway_enter((string)($_POST['room_id'] ?? ''), (string)($_POST['giveaway_id'] ?? ''), (string)$m['id'], (string)($m['name'] ?? '')), JSON_UNESCAPED_UNICODE);
        break;
    }
    case 'flash_claim': {
        $m = member_current();
        if (!$m) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }
        echo json_encode(li_flash_claim((string)($_POST['room_id'] ?? ''), (string)$m['id'], (string)($m['name'] ?? '')), JSON_UNESCAPED_UNICODE);
        break;
    }
    case 'guest_request': {
        $m = member_current();
        if (!$m) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }
        echo json_encode(li_guest_request((string)($_POST['room_id'] ?? ''), (string)$m['id'], (string)($m['name'] ?? '')), JSON_UNESCAPED_UNICODE);
        break;
    }
    case 'clips': {
        $id = (string)($_GET['room_id'] ?? '');
        $r = live_room($id);
        echo json_encode(['ok'=>true, 'clips'=>$r ? li_clips($r) : []], JSON_UNESCAPED_UNICODE);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
