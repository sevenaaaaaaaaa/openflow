<?php
/**
 * 激活码 API
 * POST /api/activation.php  {action: activate|validate, code}
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ActivationSystem.php';
require_once __DIR__ . '/../lib/MemberSystem.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']); exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$action = trim($input['action'] ?? '');
$code = trim($input['code'] ?? '');

if ($action === 'validate') {
    echo json_encode(act_validate($code));
    exit;
}

if ($action === 'activate') {
    $member = member_current();
    if (empty($member['id'])) {
        echo json_encode(['ok' => false, 'error' => '请先登录后再激活', 'need_login' => true]);
        exit;
    }
    $result = act_activate($code, $member['id']);
    echo json_encode($result);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'unknown action']);
