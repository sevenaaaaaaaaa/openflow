<?php
/**
 * 帮助中心反馈端点 — POST {slug, vote: up|down}
 * 轻限速：同 IP 同 slug 60 秒内只记一次。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/HelpCenter.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }

$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['slug'] ?? ''));
$vote = (string)($_POST['vote'] ?? '');
if ($slug === '' || !in_array($vote, ['up', 'down'], true) || !HelpCenter::get($slug)) {
    echo json_encode(['ok' => false, 'error' => '参数无效']); exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$lockFile = DATA_DIR . '/help-fb-lock.json';
$locks = json_read($lockFile);
$key = $ip . ':' . $slug;
$now = time();
foreach ($locks as $k => $ts) if ($now - $ts > 3600) unset($locks[$k]); // 清理过期
if (isset($locks[$key]) && $now - $locks[$key] < 60) { echo json_encode(['ok' => true, 'dedup' => true]); exit; }
$locks[$key] = $now;
json_write($lockFile, $locks);

$fb = HelpCenter::feedback($slug, $vote);
echo json_encode(['ok' => true, 'up' => $fb['up'], 'down' => $fb['down']], JSON_UNESCAPED_UNICODE);
