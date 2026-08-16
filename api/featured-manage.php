<?php
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/FeaturedSystem.php';
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

$action = $_POST['action'] ?? 'add';

if ($action === 'add') {
    $data = [
        'target_type' => $_POST['target_type'] ?? 'article',
        'target_id' => $_POST['target_id'] ?? '',
        'title' => $_POST['title'] ?? '',
        'position' => $_POST['position'] ?? 'homepage',
        'sort_order' => $_POST['sort_order'] ?? 0,
        'start_at' => $_POST['start_at'] ?? '',
        'end_at' => $_POST['end_at'] ?? '',
    ];
    if (!$data['target_id']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '请填写内容ID']);
        exit;
    }
    $item = FeaturedSystem::add($data);
    echo json_encode(['ok' => true, 'item' => $item]);

} elseif ($action === 'toggle') {
    $id = $_POST['id'] ?? '';
    $enabled = ($_POST['enabled'] ?? 'true') === 'true';
    $result = FeaturedSystem::update($id, ['enabled' => $enabled]);
    echo json_encode(['ok' => $result !== null]);

} elseif ($action === 'remove') {
    $id = $_POST['id'] ?? '';
    $ok = FeaturedSystem::remove($id);
    echo json_encode(['ok' => $ok]);

} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '未知操作']);
}
