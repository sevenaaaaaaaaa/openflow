<?php
/**
 * 站内信 API — 收件箱 / 标记已读 / 删除
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/MessageSystem.php';

header('Content-Type: application/json; charset=utf-8');
$member = member_current();
if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
switch ($action) {
    case 'inbox':
        $msgs = inbox_inbox($member);
        echo json_encode(['ok'=>true, 'messages'=>$msgs, 'unread'=>inbox_unread($member)], JSON_UNESCAPED_UNICODE);
        break;

    case 'unread':
        echo json_encode(['ok'=>true, 'unread'=>inbox_unread($member)]);
        break;

    case 'mark_read':
        $id = $_POST['msg_id'] ?? '';
        inbox_mark_read($member, $id);
        echo json_encode(['ok'=>true, 'unread'=>inbox_unread($member)]);
        break;

    case 'delete':
        $id = $_POST['msg_id'] ?? '';
        inbox_delete($id, $member['id']);
        echo json_encode(['ok'=>true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
