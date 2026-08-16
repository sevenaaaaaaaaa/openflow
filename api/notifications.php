<?php
/**
 * Notification API — mark read, list
 */
require_once __DIR__ . '/../admin/config.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? '';

if ($action === 'mark_read') {
    mark_notifications_read();
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'list') {
    echo json_encode(['ok' => true, 'notifications' => get_notifications(20)]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'unknown action']);
