<?php
/**
 * 课程学员端 API — 收藏 / 评分评价 / 课时笔记
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/CommentSystem.php';
header('Content-Type: application/json; charset=utf-8');

$member = member_current();
if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
switch ($action) {
    // 收藏/取消收藏课程
    case 'toggle_fav':
        $courseId = trim($_POST['course_id'] ?? '');
        $favFile = DATA_DIR . '/course-favorites.json';
        $favs = json_read($favFile);
        $has = !empty($favs[$member['id']][$courseId]);
        if ($has) unset($favs[$member['id']][$courseId]);
        else $favs[$member['id']][$courseId] = date('Y-m-d H:i:s');
        json_write($favFile, $favs);
        echo json_encode(['ok'=>true, 'fav'=>!$has]);
        break;

    // 提交课程评分评价
    case 'rate_course':
        $courseId = trim($_POST['course_id'] ?? '');
        $rating = (int)($_POST['rating'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if ($rating < 1 || $rating > 5) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'评分需 1-5 星']); exit; }
        $r = comment_add('course', $courseId, ['text'=>$content, 'rating'=>$rating], $member);
        echo json_encode(['ok'=>$r['ok'] ?? true, 'message'=>$r['error'] ?? '评价已提交'], JSON_UNESCAPED_UNICODE);
        break;

    // 保存课时笔记
    case 'save_note':
        $courseId = trim($_POST['course_id'] ?? '');
        $lessonId = trim($_POST['lesson_id'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $notesFile = DATA_DIR . '/course-notes.json';
        $notes = json_read($notesFile);
        if ($note === '') unset($notes[$member['id']][$courseId][$lessonId]);
        else $notes[$member['id']][$courseId][$lessonId] = ['note'=>$note, 'at'=>date('Y-m-d H:i:s')];
        json_write($notesFile, $notes);
        echo json_encode(['ok'=>true, 'message'=>$note === '' ? '笔记已删除' : '笔记已保存'], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>'未知操作']);
}