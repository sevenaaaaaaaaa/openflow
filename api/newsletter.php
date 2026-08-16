<?php
/**
 * Newsletter 订阅 API
 * POST /api/newsletter.php  {email}
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$email = trim($input['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => '请输入有效的邮箱地址']);
    exit;
}

$file = DATA_DIR . '/newsletter/subscribers.json';
$subs = json_read($file);

// dedupe by email
foreach ($subs as $s) {
    if (strtolower($s['email']) === strtolower($email)) {
        echo json_encode(['ok' => false, 'error' => '该邮箱已订阅']);
        exit;
    }
}

$subs[] = [
    'email' => $email,
    'source' => trim($input['source'] ?? 'article'),
    'article_id' => trim($input['article_id'] ?? ''),
    'created_at' => date('Y-m-d H:i:s'),
    'status' => 'subscribed',
];

$ok = json_write($file, $subs);
if ($ok) {
    // Trigger any hook (FlowSystem event)
    if (function_exists('flow_handle')) {
        @flow_handle('newsletter_subscribed', ['email' => $email]);
    }
    echo json_encode(['ok' => true, 'message' => '订阅成功']);
} else {
    echo json_encode(['ok' => false, 'error' => '保存失败，请重试']);
}
