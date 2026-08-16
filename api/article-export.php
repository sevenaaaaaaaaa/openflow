<?php
/**
 * 文章导出/分享 API
 * GET ?action=md&id=xxx      → 返回 Markdown 文本
 * GET ?action=prompt&id=xxx&target=claude → 返回该平台的分享 prompt
 * GET ?action=download&id=xxx → 下载 .md 文件
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ArticleExport.php';

$action = $_GET['action'] ?? 'md';
$id = $_GET['id'] ?? '';
$article = get_article($id);
if (!$article) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => '文章不存在']);
    exit;
}

if ($action === 'md') {
    header('Content-Type: text/plain; charset=utf-8');
    echo ArticleExport::toMarkdown($article);
    exit;
}

if ($action === 'prompt') {
    $target = $_GET['target'] ?? 'claude';
    $prompt = ArticleExport::sharePrompt($article, $target);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'target' => $target,
        'target_name' => (ArticleExport::targets()[$target]['name'] ?? $target),
        'prompt' => $prompt,
        'url' => ArticleExport::shareUrl($target, $prompt),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'download') {
    $md = ArticleExport::toMarkdown($article);
    $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower($article['slug'] ?? $article['id']));
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $slug . '.md"');
    echo $md;
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => '未知 action']);
