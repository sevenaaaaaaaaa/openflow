<?php
/**
 * 实时数据 API
 * GET ?type=serp&q=关键词&engine=bing    → 实时 SERP 查询
 * GET ?type=sentiment&topic=主题          → 实时舆情
 * GET ?type=sentiment_summary&topic=主题  → 舆情 AI 摘要
 * GET ?type=local                         → 站点实时指标
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/RealtimeData.php';
header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? 'local';

if ($type === 'serp') {
    $q = $_GET['q'] ?? '';
    $engine = $_GET['engine'] ?? 'bing';
    if (!$q) { http_response_code(400); echo json_encode(['ok' => false, 'error' => '缺少关键词']); exit; }
    echo json_encode(['ok' => true, 'data' => RealtimeData::serp($q, $engine)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'sentiment') {
    $topic = $_GET['topic'] ?? '';
    if (!$topic) { http_response_code(400); echo json_encode(['ok' => false, 'error' => '缺少主题']); exit; }
    echo json_encode(['ok' => true, 'data' => RealtimeData::sentiment($topic)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'sentiment_summary') {
    $topic = $_GET['topic'] ?? '';
    if (!$topic) { http_response_code(400); echo json_encode(['ok' => false, 'error' => '缺少主题']); exit; }
    $sent = RealtimeData::sentiment($topic);
    $summary = RealtimeData::sentimentSummary($sent);
    echo json_encode(['ok' => true] + $summary, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'local') {
    echo json_encode(['ok' => true, 'data' => RealtimeData::local()], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => '未知 type']);
