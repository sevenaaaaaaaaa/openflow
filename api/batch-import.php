<?php
/**
 * Batch import articles via API
 * POST /api/batch-import.php
 * Content-Type: application/json
 *
 * Body: { "articles": [{ "title": "...", "content": "...", "category": "...", ... }], "secret": "your-secret-key" }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

$secretFile = DATA_DIR . '/import_secret.json';
$secretData = json_read($secretFile);
$secret = $secretData['secret'] ?? '';

// If no secret set, generate one
if (!$secret) {
    $secret = bin2hex(random_bytes(16));
    json_write($secretFile, ['secret' => $secret]);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['articles']) || !is_array($input['articles'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request. Send { "articles": [...], "secret": "..." }']);
    exit;
}

// Verify secret
if (!isset($input['secret']) || $input['secret'] !== $secret) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid secret']);
    exit;
}

$imported = 0;
$errors = [];
$results = []; // 逐条结果

$cats = get_categories();
$catKeys = array_map(fn($c) => $c['key'], $cats);

// 已有标题集合（用于去重检测）
$existingTitles = [];
foreach (get_articles() as $ea) $existingTitles[mb_strtolower(trim($ea['title'] ?? ''))] = true;

foreach ($input['articles'] as $idx => $item) {
    $title = trim($item['title'] ?? '');
    $titleKey = mb_strtolower($title);

    if (empty($title)) {
        $errors[] = "Item #{$idx}: title is required";
        $results[] = ['index' => $idx, 'title' => '', 'status' => 'error', 'reason' => '标题为空'];
        continue;
    }

    // 去重：标题已存在则跳过
    if (isset($existingTitles[$titleKey])) {
        $errors[] = "Item #{$idx}: duplicate title \"{$title}\"";
        $results[] = ['index' => $idx, 'title' => $title, 'status' => 'skipped', 'reason' => '标题已存在，跳过（防止重复导入）'];
        continue;
    }
    $existingTitles[$titleKey] = true;

    $slug = trim($item['slug'] ?? '');
    if (empty($slug)) {
        $slug = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fff}-]/u', '-', $title);
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));
        $slug = mb_substr($slug, 0, 80);
    }
    if (article_slug_exists($slug)) {
        $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    $category = $item['category'] ?? '';
    if ($category && !in_array($category, $catKeys)) $category = '';

    $article = [
        'id' => 'article_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
        'title' => $title,
        'slug' => $slug,
        'content' => $item['content'] ?? '',
        'editor_mode' => $item['editor_mode'] ?? 'richtext',
        'category' => $category,
        'tags' => $item['tags'] ?? [],
        'cover' => $item['cover'] ?? '',
        'author' => $item['author'] ?? 'API Import',
        'status' => $item['status'] ?? 'draft',
        'seo_title' => $item['seo_title'] ?? '',
        'seo_desc' => $item['seo_desc'] ?? '',
        'seo_keywords' => $item['seo_keywords'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    save_article($article['id'], $article);
    $imported++;
    $results[] = ['index' => $idx, 'title' => $title, 'status' => 'success', 'id' => $article['id'], 'slug' => $slug];
}

echo json_encode([
    'ok' => true,
    'imported' => $imported,
    'errors' => $errors,
    'total' => count($input['articles']),
    'results' => $results,
], JSON_UNESCAPED_UNICODE);
