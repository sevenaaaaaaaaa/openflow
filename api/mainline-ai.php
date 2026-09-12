<?php
/**
 * 控制台 AI 判断 API（后台专用）
 *   GET  ?action=judge[&force=1]  → 生成/读取「小福今日判断」
 *   POST action=execute           → 执行计划里的一个动作（审批优先）
 *
 * 【鉴权】必须登录后台（会调 AI、会写站点数据）。
 * 统一走 AiCenter（记账 + 额度闸门 + 分档超时），不直连模型。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiCenter.php';
require_once __DIR__ . '/../lib/RateLimiter.php';
require_once __DIR__ . '/../lib/Mainline.php';
require_once __DIR__ . '/../lib/BusinessContext.php';
require_once __DIR__ . '/../lib/MainlineAi.php';

header('Content-Type: application/json; charset=utf-8');
require_login();
try { RateLimiter::throttle('mainline-ai:' . md5((string)($_SESSION['admin_user'] ?? 'anon')), 30, 600); } catch (\Throwable $e) {}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$action = (string)($input['action'] ?? ($_GET['action'] ?? 'judge'));

if ($action === 'judge') {
    $force = !empty($_GET['force']) || !empty($input['force']);
    $r = mainline_ai_judge(mainline_items(), $force);
    unset($r['context']);        // 不把整包快照回给前端
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'command') {
    csrf_verify();
    $text = (string)($input['text'] ?? '');
    echo json_encode(mainline_ai_command($text, mainline_items()), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'execute') {
    csrf_verify();
    $act = $input['plan_action'] ?? null;
    if (is_string($act)) $act = json_decode($act, true);
    if (!is_array($act)) { http_response_code(400); echo json_encode(['ok' => false, 'error' => '缺少动作']); exit; }
    $r = mainline_ai_execute($act);
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => '未知 action'], JSON_UNESCAPED_UNICODE);
