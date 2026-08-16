<?php
/**
 * 评论/点评 API — 提交 + 点赞 + 拉取
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CommentSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ModerationSystem.php';

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

$allowed = ['article', 'site', 'product', 'book', 'event', 'plugin', 'skill'];

switch ($action) {
    case 'list':
        $type = $_GET['type'] ?? 'article';
        $targetId = $_GET['target_id'] ?? '';
        if (!in_array($type, $allowed) || $targetId === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'参数错误']); exit; }
        echo json_encode(['ok'=>true, 'comments'=>comments_for($type, $targetId), 'rating'=>comment_rating_summary($type, $targetId)], JSON_UNESCAPED_UNICODE);
        break;

    case 'add':
        $type = $_POST['type'] ?? 'article';
        $targetId = $_POST['target_id'] ?? '';
        if (!in_array($type, $allowed) || $targetId === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'参数错误']); exit; }
        $member = member_current();
        if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录后再评论']); exit; }
        $r = comment_add($type, $targetId, $_POST, $member);
        if (!$r['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$r['error']]); exit; }
        echo json_encode(['ok'=>true, 'comment'=>$r['comment']], JSON_UNESCAPED_UNICODE);
        break;

    case 'like':
        $id = $_POST['comment_id'] ?? '';
        $r = comment_like($id);
        if (!$r['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$r['error']]); exit; }
        echo json_encode(['ok'=>true, 'likes'=>$r['likes'], 'liked'=>$r['liked']]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
