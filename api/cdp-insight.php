<?php
/**
 * CDP AI 洞察 API
 * GET ?action=insights&days=30 → 生成洞察（AI 或规则回退）
 * GET ?action=snapshot&days=30 → 数据快照
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CdpInsight.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'insights';
$days = max(1, min(90, (int)($_GET['days'] ?? 30)));

if ($action === 'snapshot') {
    echo json_encode(['ok' => true, 'snapshot' => CdpInsight::snapshot($days)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'insights') {
    $insights = CdpInsight::generate($days);
    echo json_encode(['ok' => true] + $insights, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => '未知 action']);
