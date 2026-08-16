<?php
/**
 * Push article to distribution channels
 * POST /api/push-article.php
 * Body: { "article_id": "article_xxx", "channels": ["wx_mp", "linkedin"], "format": "draft" }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$articleId = $input['article_id'] ?? '';
$channelIds = $input['channels'] ?? [];
$format = $input['format'] ?? 'draft';

if (empty($articleId) || empty($channelIds)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '缺少文章 ID 或渠道']);
    exit;
}

$article = get_article($articleId);
if (!$article) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => '文章不存在']);
    exit;
}

$channelsCfg = json_read(DATA_DIR . '/channels.json');
$allChannels = array_merge($channelsCfg['domestic'] ?? [], $channelsCfg['international'] ?? []);

$results = [];
foreach ($allChannels as $ch) {
    if (!in_array($ch['id'], $channelIds)) continue;
    if (!$ch['enabled'] || empty($ch['api_url'])) {
        $results[$ch['id']] = ['ok' => false, 'error' => '渠道未启用或未配置'];
        continue;
    }

    $payload = [
        'title' => $article['title'],
        'content' => $article['content'],
        'slug' => $article['slug'],
        'tags' => $article['tags'] ?? [],
        'category' => $article['category'] ?? '',
        'format' => $format,
        'source_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/article/' . $article['slug'],
    ];

    $ch = curl_init($ch['api_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . ($ch['api_key'] ?? ''),
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $results[$ch['id']] = [
        'ok' => $http >= 200 && $http < 300,
        'http' => $http,
        'response' => mb_substr($resp, 0, 200),
    ];
}

echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
