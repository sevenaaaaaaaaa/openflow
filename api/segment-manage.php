<?php
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/SegmentEngine.php';
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

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    $data = $_POST;
}

$action = $data['action'] ?? 'create';

if ($action === 'create') {
    $result = SegmentEngine::addSegment([
        'name' => $data['name'] ?? '',
        'description' => $data['description'] ?? '',
        'color' => $data['color'] ?? '#6366f1',
        'rules' => $data['rules'] ?? [],
        'operator' => $data['operator'] ?? 'and',
        'auto_update' => $data['auto_update'] ?? true,
    ]);
    echo json_encode(['ok' => true, 'segment' => $result]);

} elseif ($action === 'update') {
    $id = $data['id'] ?? '';
    $result = SegmentEngine::updateSegment($id, $data);
    echo json_encode(['ok' => $result !== null]);

} elseif ($action === 'delete') {
    $id = $data['id'] ?? '';
    $ok = SegmentEngine::deleteSegment($id);
    echo json_encode(['ok' => $ok]);

} elseif ($action === 'evaluate') {
    $results = SegmentEngine::evaluateAll();
    echo json_encode(['ok' => true, 'results' => $results]);

} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '未知操作']);
}
