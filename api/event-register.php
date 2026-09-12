<?php
/**
 * 活动报名 API — 报名 / 取消 / 状态
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/MessageSystem.php';
require_once __DIR__ . '/../lib/EventSystem.php';
header('Content-Type: application/json; charset=utf-8');

$member = member_current();
if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$eventId = trim($_POST['event_id'] ?? '');

$events = json_read(DATA_DIR . '/events/index.json');
$event = null;
foreach ($events as $e) if (($e['id'] ?? '') === $eventId) { $event = $e; break; }
if (!$event) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'活动不存在']); exit; }

$file = DATA_DIR . '/event-registrations.json';
$data = json_read($file);
$list = &$data[$eventId];
if (!is_array($list)) $list = [];

// 我的报名
$mine = null;
foreach ($list as $r) { if (($r['member_id'] ?? '') === $member['id']) { $mine = $r; break; } }

switch ($action) {
    case 'register':
        // 票种解析
        $ticketId = trim($_POST['ticket_id'] ?? '');
        $tickets = event_tickets($event);
        $ticket = $ticketId !== '' ? (event_ticket($event, $ticketId) ?: $tickets[0]) : $tickets[0];
        // 名额校验（优先按票种容量，其次活动总容量）
        $approved = array_values(array_filter($list, fn($r) => ($r['status'] ?? '') !== 'rejected'));
        $ticketCap = (int)($ticket['capacity'] ?? 0);
        if ($ticketCap > 0) {
            $ticketCount = count(array_filter($approved, fn($r) => ($r['ticket_id'] ?? 'general') === $ticket['id']));
            if ($ticketCount >= $ticketCap) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'该票种名额已满']); exit; }
        }
        $capacity = (int)($event['capacity'] ?? 0);
        if ($capacity > 0 && count($approved) >= $capacity) {
            http_response_code(422); echo json_encode(['ok'=>false,'error'=>'名额已满']); exit;
        }
        if ($mine) { echo json_encode(['ok'=>true,'message'=>'你已报名该活动','status'=>$mine['status'] ?? 'pending'], JSON_UNESCAPED_UNICODE); exit; }
        $price = (float)($ticket['price'] ?? 0);
        $reg = [
            'id' => 'reg_' . date('YmdHis') . substr(bin2hex(random_bytes(4)), 0, 6),
            'event_id' => $eventId,
            'member_id' => $member['id'],
            'name' => trim($_POST['name'] ?? ($member['name'] ?? $member['nickname'] ?? '')),
            'email' => trim($_POST['email'] ?? ($member['email'] ?? '')),
            'phone' => trim($_POST['phone'] ?? ($member['phone'] ?? '')),
            'note' => trim($_POST['note'] ?? ''),
            'ticket_id' => $ticket['id'], 'ticket_name' => $ticket['name'], 'ticket_price' => $price,
            // 付费票：线下/人工确认支付；免费票或线上活动直接通过
            'status' => ($price > 0) ? 'pending' : (($event['event_type'] ?? '') === 'offline' ? 'pending' : 'approved'),
            'paid' => $price <= 0,
            'checked_in' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $list[] = $reg;
        json_write($file, $data);
        // 写入增长系统（CRM 线索 + CDP 事件 + 自动化 flow）
        event_register_linkage($event, $reg);
        // 报名成功通知
        try {
            inbox_send($member['id'], '报名成功：' . ($event['title'] ?? ''), "你已成功报名「{$event['title']}」\n票种：{$ticket['name']}\n时间：{$event['start_date']}\n地点：" . ($event['location'] ?? '线上'));
            notify('event', '活动报名', ($member['name'] ?? '') . ' 报名了活动「' . ($event['title'] ?? '') . '」', '/xmp/events');
        } catch (Throwable $e) {}
        echo json_encode(['ok'=>true, 'message'=>'报名成功', 'status'=>$reg['status'], 'ticket'=>$ticket['name'], 'checkin_token'=>event_checkin_token($eventId, $reg['id'])], JSON_UNESCAPED_UNICODE);
        break;

    case 'cancel':
        if (!$mine) { echo json_encode(['ok'=>false,'error'=>'未报名']); exit; }
        $list = array_values(array_filter($list, fn($r) => ($r['member_id'] ?? '') !== $member['id']));
        $data[$eventId] = $list;
        json_write($file, $data);
        echo json_encode(['ok'=>true, 'message'=>'已取消报名'], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['ok'=>true, 'registered'=>!!$mine, 'status'=>$mine['status'] ?? ''], JSON_UNESCAPED_UNICODE);
}
