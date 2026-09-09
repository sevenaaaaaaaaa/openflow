<?php
/**
 * 生成式封面预览 API — 后台编辑器「换一张」抽卡用
 *
 * GET /api/cover-preview?seed=123&title=...&category=...&tags=...&motif=...
 *   → { ok, seed, motif, html }   html 为列表卡式 16:9 预览（带标题）
 *
 * 仅登录编辑可用；不写任何数据，纯渲染。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CoverRenderer.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$item = [
    'id'          => 'cover-preview',
    'title'       => trim((string)($_GET['title'] ?? '')) ?: '未命名文章',
    'category'    => trim((string)($_GET['category'] ?? '')),
    'tags'        => array_values(array_filter(array_map('trim', explode(',', (string)($_GET['tags'] ?? ''))))),
    'excerpt'     => '',
    'cover_seed'  => max(0, (int)($_GET['seed'] ?? 0)),
    'cover_motif' => trim((string)($_GET['motif'] ?? '')),
];
$item['cover_seed'] = CoverRenderer::seed($item);

echo json_encode([
    'ok'    => true,
    'seed'  => $item['cover_seed'],
    'motif' => CoverRenderer::motif($item),
    'html'  => CoverRenderer::renderCard($item, true),
], JSON_UNESCAPED_UNICODE);
