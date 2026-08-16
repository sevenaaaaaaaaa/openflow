<?php
/**
 * 文章互动数据批量查询
 * POST /api/article-stats-batch.php  {slugs: []}
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ArticleStats.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']); exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$slugs = array_map('trim', $input['slugs'] ?? []);
$slugs = array_filter($slugs);
$out = art_stats_batch($slugs);
echo json_encode(['ok' => true, 'stats' => $out]);
