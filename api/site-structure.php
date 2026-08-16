<?php
/**
 * 站点结构 API — 返回 site-builder 配置的全局导航/页脚/自定义页面
 * GET /api/site-structure.php
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();
header('Cache-Control: no-cache');

$cfg = json_read(DATA_DIR . '/site-structure.json');

echo json_encode([
    'ok' => true,
    'nav' => $cfg['nav'] ?? [],
    'footer' => $cfg['footer'] ?? ['columns' => []],
    'custom_pages' => $cfg['custom_pages'] ?? [],
    'updated_at' => $cfg['updated_at'] ?? '',
], JSON_UNESCAPED_UNICODE);
