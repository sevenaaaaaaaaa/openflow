<?php
/**
 * 页面构建器实时预览端点 —— 所见即所得
 * POST {block:{...}} → 返回 builder_render_block 渲染的 HTML
 * 复用同一渲染真源(BlockRegistry::builder_render_block)，不重复渲染逻辑。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/BlockSchema.php';
require_once __DIR__ . '/../lib/BlockRegistry.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !is_array($input['block'] ?? null)) {
    http_response_code(400);
    echo '<div class="of-empty">无效的块数据</div>';
    exit;
}

try {
    $block = $input['block'];
    // 归一化（补 _type/_key）
    $block = block_normalize($block);
    // 渲染（只会输出 HTML，不会执行任何业务写）
    echo builder_render_block($block);
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<div class="of-empty">预览渲染失败</div>';
}
