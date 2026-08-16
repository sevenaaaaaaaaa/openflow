<?php
/**
 * NPS 提交 API
 * POST /api/nps-submit.php?id=nps_xxx
 * Body: { score: 8, comment: "...", name: "...", email: "..." }
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../admin/nps-lib.php';
require_once __DIR__ . '/../lib/AutomationSystem.php';
require_once __DIR__ . '/../lib/CanvasSystem.php';

header('Content-Type: application/json; charset=utf-8');

$project = nps_get_project($_GET['id'] ?? '');
if (!$project) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => '项目不存在']);
    exit;
}
if (($project['status'] ?? 'active') !== 'active') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => '调研已结束']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$score = (int)($input['score'] ?? -1);
if ($score < 0 || $score > 10) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '评分必须在 0-10 之间']);
    exit;
}

$response = [
    'id' => 'npsr_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
    'project_id' => $project['id'],
    'score' => $score,
    'comment' => trim($input['comment'] ?? ''),
    'name' => trim($input['name'] ?? ''),
    'email' => trim($input['email'] ?? ''),
    'source' => trim($input['source'] ?? ''),
    'created_at' => date('Y-m-d H:i:s'),
];

nps_add_response($project['id'], $response);

// 营销自动化触发（按评分）
canvas_trigger('nps_submit', [
    'score' => $score,
    'email' => $response['email'],
    'name' => $response['name'],
    'comment' => $response['comment'],
    'project' => $project['title'],
]);
automation_trigger('nps_submit', [
    'score' => $score,
    'email' => $response['email'],
    'name' => $response['name'],
    'comment' => $response['comment'],
    'project' => $project['title'],
]);

// 通知
$cat = $score >= 9 ? '推荐者' : ($score >= 7 ? '被动者' : '贬损者');
notify('NPS', "「{$project['title']}」收到评分 {$score}（{$cat}）", trim($input['comment'] ?? '') ?: '无评论', 'admin/nps.php?project=' . $project['id']);

echo json_encode(['ok' => true, 'message' => '提交成功，感谢反馈！'], JSON_UNESCAPED_UNICODE);
