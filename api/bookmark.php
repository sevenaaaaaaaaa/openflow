<?php
/**
 * 收藏 API
 * POST: toggle (添加/取消收藏)
 * GET: list (用户收藏列表), count (内容收藏数)
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/BookmarkSystem.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' || $method === 'DELETE') {
    $userId = $_COOKIE['member_id'] ?? $_SESSION['member_id'] ?? '';
    if (!$userId) { json_out(['ok' => false, 'error' => '请先登录'], 401); }

    if ($method === 'DELETE') {
        $targetType = $_POST['target_type'] ?? '';
        $targetId = $_POST['target_id'] ?? '';
        $targetUser = $_POST['user_id'] ?? '';
        if (!$targetType || !$targetId) { json_out(['ok' => false, 'error' => '参数缺失'], 400); }
        if ($targetUser) {
            if (empty($_SESSION['admin_login'])) { json_out(['ok' => false, 'error' => '无权限'], 403); }
            csrf_verify();
            $ok = BookmarkSystem::remove($targetUser, $targetType, $targetId);
        } else {
            $ok = BookmarkSystem::remove($userId, $targetType, $targetId);
        }
        json_out(['ok' => $ok]);
    }

    $targetType = $_POST['target_type'] ?? '';
    $targetId = $_POST['target_id'] ?? '';
    $title = $_POST['title'] ?? '';

    if (!$targetType || !$targetId) { json_out(['ok' => false, 'error' => '参数缺失'], 400); }

    $result = BookmarkSystem::toggle($userId, $targetType, $targetId, $title);
    json_out(['ok' => true] + $result);

} elseif ($method === 'GET') {
    if ($action === 'list') {
        $userId = $_GET['user_id'] ?? $_COOKIE['member_id'] ?? $_SESSION['member_id'] ?? '';
        $type = $_GET['type'] ?? null;
        $bookmarks = BookmarkSystem::getUserBookmarks($userId, $type);
        json_out(['ok' => true, 'bookmarks' => $bookmarks, 'total' => count($bookmarks)]);

    } elseif ($action === 'count') {
        $targetType = $_GET['target_type'] ?? '';
        $targetId = $_GET['target_id'] ?? '';
        $count = BookmarkSystem::count($targetType, $targetId);
        json_out(['ok' => true, 'count' => $count]);

    } elseif ($action === 'check') {
        $userId = $_COOKIE['member_id'] ?? $_SESSION['member_id'] ?? '';
        $targetType = $_GET['target_type'] ?? '';
        $targetId = $_GET['target_id'] ?? '';
        $bookmarked = BookmarkSystem::isBookmarked($userId, $targetType, $targetId);
        json_out(['ok' => true, 'bookmarked' => $bookmarked]);

    } else {
        json_out(['ok' => false, 'error' => '未知 action'], 400);
    }
}

function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
