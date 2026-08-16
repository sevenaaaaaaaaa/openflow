<?php
/**
 * 前端增长工具箱 API
 * POST { action: readability|seo_check|generate_meta|ltv_cac|funnel }
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/WebTools.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? '';

if ($action === 'readability') {
    $text = $input['text'] ?? '';
    if (mb_strlen($text) < 20) { echo json_encode(['ok' => false, 'error' => '文本太短']); exit; }
    echo json_encode(['ok' => true, 'data' => WebTools::readability($text)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'seo_check') {
    $data = WebTools::seoCheck($input['title'] ?? '', $input['description'] ?? '', $input['keywords'] ?? '');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'generate_meta') {
    $data = WebTools::generateMeta($input['title'] ?? '', $input['keywords'] ?? '', $input['description'] ?? '');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'ltv_cac') {
    $data = WebTools::ltvCac($input);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'funnel') {
    $stages = $input['stages'] ?? [];
    if (count($stages) < 2) { echo json_encode(['ok' => false, 'error' => '至少 2 个阶段']); exit; }
    echo json_encode(['ok' => true, 'data' => WebTools::funnel($stages)], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => '未知 action']);
