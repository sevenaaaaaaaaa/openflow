<?php
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ReportSystem.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_login'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '需要登录']);
    exit;
}
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$id = $_POST['id'] ?? '';
$status = $_POST['status'] ?? '';
$note = $_POST['note'] ?? '';

if (!$id || !in_array($status, ['resolved', 'dismissed'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '参数错误']);
    exit;
}

$ok = ReportSystem::resolve($id, $status, $note);
echo json_encode(['ok' => $ok]);
