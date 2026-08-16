<?php
/**
 * 分享追踪 API
 * POST /api/share.php  {action: create|visit|convert, ...}
 *   create  生成分享（返回 share_key + 分享链接）
 *   visit   记录一次由分享带来的访问
 *   convert 标记转化（注册/购买）
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ShareTrack.php';
require_once __DIR__ . '/../lib/MemberSystem.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']); exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$action = trim($input['action'] ?? '');
$member = member_current();
$memberId = $member['id'] ?? '';

switch ($action) {
    case 'create':
        $slug = trim($input['slug'] ?? '');
        $channel = trim($input['channel'] ?? '');
        if (!$slug) { echo json_encode(['ok' => false, 'error' => 'missing slug']); exit; }
        $key = share_track_create($slug, $channel, $memberId);
        $base = site_config_get('site_url');
        if (empty($base) || str_contains($base, 'localhost')) {
            $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        echo json_encode(['ok' => true, 'share_key' => $key, 'url' => rtrim($base, '/') . '/article/' . urlencode($slug) . '?ref=' . $key]);
        exit;

    case 'visit':
        $key = trim($input['share_key'] ?? '');
        if (!$key || !share_track_valid($key)) { echo json_encode(['ok' => false, 'error' => 'invalid share_key']); exit; }
        share_track_visit($key, $memberId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['ok' => true]);
        exit;

    case 'convert':
        $key = trim($input['share_key'] ?? '');
        $event = trim($input['event'] ?? 'register');
        if (!$key || !$memberId) { echo json_encode(['ok' => false, 'error' => 'missing params']); exit; }
        share_track_convert($key, $memberId, $event);
        echo json_encode(['ok' => true]);
        exit;

    default:
        echo json_encode(['ok' => false, 'error' => 'unknown action']); exit;
}
