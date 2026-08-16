<?php
/**
 * 广告位 API — 供前台 JS 拉取指定广告位
 * GET /api/ads.php?slot=community_banner
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AdSystem.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

$slot = trim($_GET['slot'] ?? '');
if (!in_array($slot, ['community_banner', 'feed_1', 'feed_2', 'article_top', 'article_bottom'])) {
    echo json_encode(['ok' => false, 'error' => 'invalid slot']); exit;
}

$html = ads_render($slot);
echo json_encode(['ok' => true, 'slot' => $slot, 'html' => $html]);
