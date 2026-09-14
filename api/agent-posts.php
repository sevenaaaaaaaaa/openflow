<?php
/**
 * AI 岗位 API — run(立即交班) / toggle(启停) / kpi(考核)
 * 后台专用:登录 + settings 权限 + CSRF(config.php 统一关卡)。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AgentPost.php';
require_once __DIR__ . '/../lib/RateLimiter.php';

header('Content-Type: application/json; charset=utf-8');
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$id = (string)($_POST['id'] ?? $_GET['id'] ?? '');

try { RateLimiter::throttle('agentpost:' . md5((string)($_SESSION['admin_user'] ?? 'anon')), 30, 600); } catch (\Throwable $e) {}

switch ($action) {
    case 'list':
        $posts = agent_posts();
        foreach ($posts as &$p) {
            $p['kpi_now'] = agent_post_evaluate((string)$p['id']);
            $shifts = agent_post_shifts((string)$p['id']);
            $p['last_shift'] = $shifts ? end($shifts) : null;
        }
        echo json_encode(['ok' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE);
        break;

    case 'run':
        if ($id === '') { echo json_encode(['ok' => false, 'error' => '缺少岗位 id']); break; }
        $r = agent_post_run($id, ['force' => !empty($_POST['force'])]);
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        break;

    case 'toggle':
        if ($id === '') { echo json_encode(['ok' => false, 'error' => '缺少岗位 id']); break; }
        csrf_verify();
        $posts = agent_posts();
        foreach ($posts as $i => $p) if (($p['id'] ?? '') === $id) {
            $posts[$i]['status'] = (($p['status'] ?? '') === 'suspended') ? 'active' : 'suspended';
            $posts[$i]['note'] = '';
            agent_posts_save($posts);
            echo json_encode(['ok' => true, 'status' => $posts[$i]['status']], JSON_UNESCAPED_UNICODE);
            break 2;
        }
        echo json_encode(['ok' => false, 'error' => '岗位不存在']);
        break;

    case 'reactivate':
        // 降级为「仅建议」后的岗位,人工确认恢复
        if ($id === '') { echo json_encode(['ok' => false, 'error' => '缺少岗位 id']); break; }
        csrf_verify();
        $posts = agent_posts();
        foreach ($posts as $i => $p) if (($p['id'] ?? '') === $id) {
            $posts[$i]['status'] = 'active';
            $posts[$i]['note'] = '';
            agent_posts_save($posts);
            echo json_encode(['ok' => true, 'status' => 'active'], JSON_UNESCAPED_UNICODE);
            break 2;
        }
        echo json_encode(['ok' => false, 'error' => '岗位不存在']);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => '未知操作']);
}
