<?php
/**
 * 活动报名 API — 报名 / 取消 / 状态
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/MessageSystem.php';
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
        // 名额校验
        $capacity = (int)($event['capacity'] ?? 0);
        $approved = array_values(array_filter($list, fn($r) => ($r['status'] ?? '') !== 'rejected'));
        if ($capacity > 0 && count($approved) >= $capacity) {
            http_response_code(422); echo json_encode(['ok'=>false,'error'=>'名额已满']); exit;
        }
        if ($mine) { echo json_encode(['ok'=>true,'message'=>'你已报名该活动','status'=>$mine['status'] ?? 'pending']); exit; }
        $reg = [
            'id' => 'reg_' . date('YmdHis') . substr(bin2hex(random_bytes(4)), 0, 6),
            'event_id' => $eventId,
            'member_id' => $member['id'],
            'name' => trim($_POST['name'] ?? ($member['name'] ?? $member['nickname'] ?? '')),
            'email' => trim($_POST['email'] ?? ($member['email'] ?? '')),
            'phone' => trim($_POST['phone'] ?? ($member['phone'] ?? '')),
            'note' => trim($_POST['note'] ?? ''),
            'status' => ($event['event_type'] ?? '') === 'offline' ? 'pending' : 'approved', // 线下需审核，线上直接通过
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $list[] = $reg;
        json_write($file, $data);
        // 报名成功通知
        try {
            inbox_send($member['id'], '报名成功：' . ($event['title'] ?? ''), "你已成功报名「{$event['title']}」\n时间：{$event['start_date']}\n地点：" . ($event['location'] ?? '线上'));
            notify('event', '活动报名', ($member['name'] ?? '') . ' 报名了活动「' . ($event['title'] ?? '') . '」', '/xmp/events');
        } catch (Throwable $e) {}
        echo json_encode(['ok'=>true, 'message'=>'报名成功', 'status'=>$reg['status']], JSON_UNESCAPED_UNICODE);
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
