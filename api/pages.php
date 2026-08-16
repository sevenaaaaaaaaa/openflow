<?php
require_once __DIR__ . '/../admin/config.php';

$page = $_GET['page'] ?? 'index';
$allowed = ['index', 'about', 'capability', 'courses'];
if (!in_array($page, $allowed)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Page not found']);
    exit;
}

$content = page_content($page);
$seoFile = DATA_DIR . '/seo.json';
$seo = json_read($seoFile);
$pageSeo = $seo[$page] ?? [];

header('Content-Type: application/json; charset=utf-8');
cors_headers();
echo json_encode([
    'ok' => true,
    'page' => $page,
    'content' => $content,
    'seo' => $pageSeo,
], JSON_UNESCAPED_UNICODE);
