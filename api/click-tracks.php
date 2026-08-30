<?php
/**
 * 圈选埋点定义 API（公开只读）
 *   GET /api/click-tracks.php?path=/article/x  → 该页启用中的埋点定义
 *
 * 只输出 selector/event/name，供 inject.js 绑 click 监听；命中后走已有 /api/track.php。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ClickTracker.php';

header('Content-Type: application/json; charset=utf-8');
if (function_exists('cors_headers')) cors_headers();
header('Cache-Control: public, max-age=120');

$path = (string)($_GET['path'] ?? '/');
if (strlen($path) > 500) $path = substr($path, 0, 500);

echo json_encode(['ok' => true, 'tracks' => clicktrack_for_page($path)], JSON_UNESCAPED_UNICODE);
