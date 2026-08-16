<?php
/**
 * Internal link scanner API
 * POST /api/internal-links.php
 * Params: id (article to exclude), content (text to scan)
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

$articleId = $_POST['id'] ?? '';
$content = $_POST['content'] ?? '';

$suggestions = scan_internal_links($content, $articleId);

echo json_encode(['ok' => true, 'suggestions' => $suggestions, 'count' => count($suggestions)], JSON_UNESCAPED_UNICODE);
