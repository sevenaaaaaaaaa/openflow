<?php
/**
 * SSO 统一登录 — 与已有产品站共享用户体系
 * GET  ?action=status            → 当前登录用户（供外部系统判断）
 * GET  ?action=login_url&next=X  → 本站登录/注册链接（外部系统跳转过来）
 * GET  ?action=logout            → 登出
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
header('Content-Type: application/json; charset=utf-8');
cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? 'status';
$base = site_config_get('site_url');

switch ($action) {
    case 'status':
        $member = member_current();
        if ($member) {
            echo json_encode(['ok' => true, 'logged_in' => true, 'user' => [
                'id' => $member['id'], 'name' => $member['name'] ?? $member['nickname'] ?? '', 'email' => $member['email'] ?? '',
                'level' => $member['level'] ?? 'free', 'avatar' => $member['avatar'] ?? '',
            ]], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['ok' => true, 'logged_in' => false]);
        }
        break;

    case 'login_url':
        $next = trim($_GET['next'] ?? '');
        $url = $base . '/member.php?view=login';
        if ($next !== '') $url .= '&next=' . urlencode($next);
        echo json_encode(['ok' => true, 'login_url' => $url, 'register_url' => $base . '/member.php?view=register']);
        break;

    case 'logout':
        if (function_exists('member_logout')) member_logout();
        echo json_encode(['ok' => true, 'logged_out' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '未知操作']);
}
