<?php
/**
 * 文章互动 API — 阅读/点赞/收藏/分享
 * POST /api/article-stats.php  {action: view|like|favorite|share, slug}
 * GET  /api/article-stats.php  ?slug=xxx  (get stats)
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ArticleStats.php';
require_once __DIR__ . '/../lib/MemberSystem.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $slug = trim($_GET['slug'] ?? '');
    if (!$slug) { echo json_encode(['ok' => false, 'error' => 'missing slug']); exit; }
    echo json_encode(['ok' => true, 'stats' => art_stats_get($slug)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$action = trim($input['action'] ?? '');
$slug = trim($input['slug'] ?? '');
if (!$slug || !in_array($action, ['view', 'like', 'favorite', 'share'])) {
    echo json_encode(['ok' => false, 'error' => 'invalid params']); exit;
}

$member = member_current();
$memberId = $member['id'] ?? '';

// 点赞/收藏走 toggle（需要登录）
if (in_array($action, ['like', 'favorite'])) {
    if (!$memberId) {
        echo json_encode(['ok' => false, 'error' => '请先登录', 'need_login' => true]);
        exit;
    }
    $state = art_stats_toggle($slug, $memberId, $action);
    $stats = art_stats_get($slug);
    echo json_encode(['ok' => true, 'active' => $state, 'stats' => $stats]);
    exit;
}

// view/share 直接计数
art_stats_add($slug, $action);
if ($action === 'share' && function_exists('flow_handle')) {
    @flow_handle('share', ['slug' => $slug, 'member' => $memberId]);
}
$stats = art_stats_get($slug);
echo json_encode(['ok' => true, 'stats' => $stats]);
