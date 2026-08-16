<?php
/**
 * 转化组件 API — 返回转化组件配置供前端渲染
 * GET /api/conversion.php
 * 返回：{ ok, conversion: { top_bar, bottom_cta, popup, inline_cta }, forms: [...] }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();
header('Cache-Control: no-cache');

$conv = json_read(DATA_DIR . '/conversion.json');
$forms = [];
foreach (json_read(DATA_DIR . '/forms/index.json') as $f) {
    $forms[] = [
        'slug' => $f['slug'],
        'title' => $f['title'],
        'fields' => $f['fields'] ?? [],
    ];
}

echo json_encode([
    'ok' => true,
    'conversion' => [
        'top_bar' => $conv['top_bar'] ?? [],
        'bottom_cta' => $conv['bottom_cta'] ?? [],
        'popup' => $conv['popup'] ?? [],
        'inline_cta' => $conv['inline_cta'] ?? [],
    ],
    'forms' => $forms,
], JSON_UNESCAPED_UNICODE);
