<?php
/**
 * Agent 运行时 API（后台专用）
 *   POST action=run     {goal, auto?}      给目标 → Agent 自主链式调工具
 *   POST action=approve {run_id}           批准待执行动作并继续
 *   GET  action=list                       最近运行
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/RateLimiter.php';
require_once __DIR__ . '/../lib/AgentRuntime.php';
require_login();
require_perm('settings');

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();
try { RateLimiter::throttle('agent:' . md5((string)($_SESSION['admin_user'] ?? 'anon')), 30, 600); } catch (\Throwable $e) {}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$action = (string)($input['action'] ?? ($_GET['action'] ?? ''));

switch ($action) {
    case 'run':
        echo json_encode(agent_run((string)($input['goal'] ?? ''), ['auto' => !empty($input['auto'])]), JSON_UNESCAPED_UNICODE);
        break;
    case 'approve':
        echo json_encode(agent_approve((string)($input['run_id'] ?? '')), JSON_UNESCAPED_UNICODE);
        break;
    case 'list':
        echo json_encode(['ok' => true, 'runs' => array_slice(agent_stored(), 0, 20)], JSON_UNESCAPED_UNICODE);
        break;
    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '未知 action'], JSON_UNESCAPED_UNICODE);
}
