<?php
/**
 * 矩阵账号互通 API
 *   GET  ?action=enter&app=mflow&next=/dashboard   → 签一次性短票并 302 到产品(需本站会员登录)
 *   POST ?action=redeem  {ticket, client_id} + X-Client-Secret → 兑换身份(兄弟产品后端调用)
 *   GET  ?action=apps                              → 已启用互通的应用列表(前台「矩阵入口」渲染用)
 *
 * 权限:enter/apps 为 member 档;redeem 为 public 档但自带 client_secret 强校验(见 ApiPolicy 备注)。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MatrixTicket.php';
require_once __DIR__ . '/../lib/RateLimiter.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$base = site_config_get('site_url');

try { RateLimiter::throttle('matrix:' . md5((string)($_SERVER['REMOTE_ADDR'] ?? '')), 60, 60); } catch (\Throwable $e) {}

switch ($action) {
    case 'enter':
        $member = member_current();
        if (!$member) {
            // 未登录 → 先去登录,登录后回到 enter
            $back = $base . '/api/matrix.php?action=enter&app=' . urlencode((string)($_GET['app'] ?? '')) . '&next=' . urlencode((string)($_GET['next'] ?? ''));
            header('Location: ' . $base . '/member.php?view=login&next=' . urlencode($back));
            exit;
        }
        $r = matrix_enter((string)($_GET['app'] ?? ''), (string)($_GET['next'] ?? ''));
        if (!empty($r['ok']) && !empty($r['redirect'])) { header('Location: ' . $r['redirect']); exit; }
        http_response_code(400);
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        break;

    case 'redeem':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => '仅 POST']); break; }
        $in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
        echo json_encode(matrix_redeem((string)($in['ticket'] ?? ''), (string)($in['client_id'] ?? ''), (string)($_SERVER['HTTP_X_CLIENT_SECRET'] ?? '')), JSON_UNESCAPED_UNICODE);
        break;

    case 'apps':
        $out = [];
        foreach (matrix_clients() as $c) {
            if (empty($c['secret_hash']) || empty($c['redirect_base'])) continue;   // 只列已启用的
            $out[] = ['client_id' => $c['client_id'], 'name' => $c['name'], 'enter_url' => $base . '/api/matrix.php?action=enter&app=' . $c['client_id']];
        }
        echo json_encode(['ok' => true, 'apps' => $out], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '未知操作']);
}
