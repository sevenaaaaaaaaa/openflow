<?php
/**
 * 生长信号上报 API — 前端页面访问 / 后台模块使用 记录到生长引擎
 * POST /api/growth-signal.php  Body: { type: "view_page", key: "/academy" }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

// 限流
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rl = json_read(DATA_DIR . '/growth-rate.json');
$k = $ip . ':' . (int)floor(time() / 60);
$rl[$k] = ($rl[$k] ?? 0) + 1;
if ($rl[$k] > 60) { echo json_encode(['ok' => false, 'error' => 'rate']); exit; }
if (count($rl) > 200) $rl = array_slice($rl, -100);
json_write(DATA_DIR . '/growth-rate.json', $rl);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$type = $input['type'] ?? '';
$key = mb_substr((string)($input['key'] ?? ''), 0, 120);
if (!$type || !$key) { echo json_encode(['ok' => false, 'error' => '缺参数']); exit; }

// 只记录有效页面路径，过滤 admin/api 等内部路径
if (preg_match('#^/(admin|api|data|login|member)/#', $key)) {
    echo json_encode(['ok' => true, 'skip' => true]); exit;
}

try {
    GrowthEngine::signal($type, $key);
    if ($type === 'view_page') {
        GrowthEngine::recordActivity((int)date('G')); // 记录活跃时段
    }
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]); exit;
}
echo json_encode(['ok' => true]);
