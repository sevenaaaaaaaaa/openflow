<?php
/**
 * 关注 API
 * POST: toggle (关注/取关)
 * GET: following (关注列表), followers (粉丝列表), count (关注/粉丝数), check (检查关注状态)
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/FollowSystem.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST') {
    $userId = $_COOKIE['member_id'] ?? $_SESSION['member_id'] ?? '';
    if (!$userId) { json_out(['ok' => false, 'error' => '请先登录'], 401); }

    $targetUserId = $_POST['target_user_id'] ?? '';
    if (!$targetUserId) { json_out(['ok' => false, 'error' => '参数缺失'], 400); }

    $result = FollowSystem::toggle($userId, $targetUserId);
    $result['following_count'] = FollowSystem::followingCount($userId);
    $result['followers_count'] = FollowSystem::followersCount($targetUserId);
    json_out(['ok' => true] + $result);

} elseif ($method === 'GET') {
    if ($action === 'following') {
        $userId = $_GET['user_id'] ?? $_COOKIE['member_id'] ?? '';
        $list = FollowSystem::getFollowing($userId);
        json_out(['ok' => true, 'following' => $list, 'total' => count($list)]);

    } elseif ($action === 'followers') {
        $userId = $_GET['user_id'] ?? $_COOKIE['member_id'] ?? '';
        $list = FollowSystem::getFollowers($userId);
        json_out(['ok' => true, 'followers' => $list, 'total' => count($list)]);

    } elseif ($action === 'count') {
        $userId = $_GET['user_id'] ?? '';
        json_out([
            'ok' => true,
            'following' => FollowSystem::followingCount($userId),
            'followers' => FollowSystem::followersCount($userId),
        ]);

    } elseif ($action === 'check') {
        $userId = $_COOKIE['member_id'] ?? $_SESSION['member_id'] ?? '';
        $targetUserId = $_GET['target_user_id'] ?? '';
        $isFollowing = FollowSystem::isFollowing($userId, $targetUserId);
        json_out(['ok' => true, 'following' => $isFollowing]);

    } else {
        json_out(['ok' => false, 'error' => '未知 action'], 400);
    }
}

function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
