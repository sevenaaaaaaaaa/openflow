<?php
/**
 * 数据仓库出向导出 — 供外部数仓/BI 拉取
 * ?token=xxx&table=events|profiles|orders&format=json|csv&limit=N&since=日期
 */
require_once __DIR__ . '/../admin/config.php';
header('Content-Type: application/json; charset=utf-8');

// 鉴权：API Key（与 data-export 一致）
$token = trim($_GET['token'] ?? '');
$keys = json_read(DATA_DIR . '/api_key.json');
$valid = !empty($keys['key']) && $token === $keys['key'];
if (!$valid) {
    // 兼容后台导出密钥
    $cfg = json_read(DATA_DIR . '/export.json');
    $valid = !empty($cfg['secret']) && $token === $cfg['secret'];
}
if (!$valid) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'无效 Token']); exit; }

$table = $_GET['table'] ?? 'events';
$format = $_GET['format'] ?? 'json';
$limit = min(100000, max(1, (int)($_GET['limit'] ?? 10000)));
$since = trim($_GET['since'] ?? '');
$where = $since !== '' ? " AND created_at >= '" . addslashes($since) . "'" : '';

$allow = ['events' => 'SELECT * FROM events', 'orders' => 'SELECT * FROM orders', 'submissions' => 'SELECT * FROM submissions', 'members' => 'SELECT * FROM members', 'point_logs' => 'SELECT * FROM point_logs'];
if (!isset($allow[$table])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'非法表名']); exit; }

$sql = $allow[$table] . ' WHERE 1=1' . $where . ' ORDER BY id DESC LIMIT ' . (int)$limit;
try {
    $rows = Database::query($sql);
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $table . '-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    if (!empty($rows)) {
        $fp = fopen('php://output', 'w');
        fputcsv($fp, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($fp, $r);
        fclose($fp);
    }
    exit;
}

echo json_encode(['ok' => true, 'table' => $table, 'count' => count($rows), 'rows' => $rows], JSON_UNESCAPED_UNICODE);
