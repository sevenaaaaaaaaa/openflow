<?php
/**
 * 内链扫描 / 一键插链 API
 *   POST action=scan   { id, content }         → 未被链接的提及建议（跳过已链接 URL）
 *   POST action=apply  { id, url, anchor }     → 服务端把首处未链接提及包成内链并保存
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/SeoAudit.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$articleId = (string)($_POST['id'] ?? '');
$action = (string)($_POST['action'] ?? 'scan');

if ($action === 'apply') {
    $r = seo_apply_internal_link($articleId, (string)($_POST['url'] ?? ''), (string)($_POST['anchor'] ?? ''));
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

$content = (string)($_POST['content'] ?? '');
$raw = seo_internal_link_suggestions($content, $articleId);
$suggestions = [];
foreach ($raw as $s) {
    if (!empty($s['already_linked'])) continue;   // 已链接的不再建议
    $suggestions[] = ['title' => $s['article_title'], 'url' => $s['url'], 'anchor' => $s['anchor']];
}

echo json_encode(['ok' => true, 'suggestions' => $suggestions, 'count' => count($suggestions)], JSON_UNESCAPED_UNICODE);
