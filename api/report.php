<?php
/**
 * 举报 API
 * POST: submit (提交举报)
 * GET: reasons (举报原因列表)
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ReportSystem.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $userId = $_COOKIE['member_id'] ?? $_SESSION['member_id'] ?? '';
    if (!$userId) { json_out(['ok' => false, 'error' => '请先登录'], 401); }

    $data = [
        'user_id' => $userId,
        'user_name' => $_POST['user_name'] ?? '',
        'target_type' => $_POST['target_type'] ?? '',
        'target_id' => $_POST['target_id'] ?? '',
        'target_title' => $_POST['target_title'] ?? '',
        'reason' => $_POST['reason'] ?? '',
        'category' => $_POST['category'] ?? 'other',
    ];

    if (!$data['target_type'] || !$data['target_id']) {
        json_out(['ok' => false, 'error' => '参数缺失'], 400);
    }

    $result = ReportSystem::submit($data);
    json_out($result);

} elseif ($method === 'GET') {
    json_out(['ok' => true, 'reasons' => ReportSystem::reasons()]);
}

function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
