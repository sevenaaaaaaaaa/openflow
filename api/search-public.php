<?php
/**
 * 前台搜索 API — 返回相关话题/课程/文章/资料/技能
 * GET /api/search-public.php?q=关键词
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => false, 'error' => '关键词至少 2 个字']); exit;
}

$result = SearchEngine::search($q);

echo json_encode($result, JSON_UNESCAPED_UNICODE);

