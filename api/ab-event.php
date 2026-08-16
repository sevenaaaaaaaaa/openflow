<?php
/**
 * A/B 测试事件收集 API
 * POST /api/ab-event.php
 * Body: { "ab_id": "ab_xxx", "variant": "A|B", "event": "impression|conversion|custom", "label": "自定义事件名" }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$abId = trim($input['ab_id'] ?? '');
$variant = strtoupper(trim($input['variant'] ?? ''));
$event = trim($input['event'] ?? 'impression');
$label = trim($input['label'] ?? '');

if (empty($abId) || !in_array($variant, ['A', 'B'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '缺少 ab_id 或 variant']);
    exit;
}
if (!in_array($event, ['impression', 'conversion', 'click', 'custom'])) $event = 'custom';

// 去重：同会话同实验同事件只计一次（防刷新重复计数）
$uid = $_COOKIE['fc_uid'] ?? '';
if ($uid === '') {
    $uid = 'u_' . bin2hex(random_bytes(8));
    setcookie('fc_uid', $uid, time() + 86400 * 365, '/');
}
$dedupeKey = $abId . '|' . $variant . '|' . $event . '|' . $label . '|' . $uid . '|' . substr($_SERVER['HTTP_REFERER'] ?? '/', 0, 120);

$statsFile = DATA_DIR . '/abstats.json';
$stats = json_read($statsFile);

// 防重复（最近 100 个 key）
$recent = $stats['_recent'] ?? [];
if (in_array($dedupeKey, $recent)) {
    echo json_encode(['ok' => true, 'deduped' => true]);
    exit;
}
$recent[] = $dedupeKey;
$recent = array_slice($recent, -100);

// 累加
$stats[$abId][$variant][$event][$label] = ($stats[$abId][$variant][$event][$label] ?? 0) + 1;
$stats['_recent'] = $recent;
json_write($statsFile, $stats);

echo json_encode(['ok' => true, 'recorded' => true]);
