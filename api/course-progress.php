<?php
/**
 * 课程学习进度 API — 记录进度 / 断点续播
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ProgressSystem.php';

header('Content-Type: application/json; charset=utf-8');
$member = member_current();
if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

switch ($action) {
    case 'progress':
        $courseId = trim($_POST['course_id'] ?? '');
        $lessonId = trim($_POST['lesson_id'] ?? '');
        if (empty($courseId) || empty($lessonId)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'参数错误']); exit; }
        progress_set($member['id'], $courseId, $lessonId, [
            'done' => !empty($_POST['done']),
            'position' => (int)($_POST['position'] ?? 0),
            'duration' => (int)($_POST['duration'] ?? 0),
        ]);
        echo json_encode(['ok'=>true]);
        break;

    case 'mark_done':
        $courseId = trim($_POST['course_id'] ?? '');
        $lessonId = trim($_POST['lesson_id'] ?? '');
        if (!empty($courseId) && !empty($lessonId)) progress_done($member['id'], $courseId, $lessonId);
        echo json_encode(['ok'=>true]);
        break;

    case 'summary':
        $courseId = trim($_GET['course_id'] ?? '');
        $course = null;
        foreach (json_read(DATA_DIR . '/courses/index.json') as $c) if ($c['id'] === $courseId) { $course = $c; break; }
        if (!$course) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'课程不存在']); exit; }
        echo json_encode(['ok'=>true, 'summary'=>progress_summary($member['id'], $courseId, $course)], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
