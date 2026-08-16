<?php
/**
 * Public Landing Page API
 * GET /api/landing.php?slug=content-overview
 * Returns: { "page": {...}, "articles": [...] }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

$slug = $_GET['slug'] ?? '';

$pages = get_landing_pages();
$landing = null;
foreach ($pages as $p) {
    if (($p['slug'] ?? '') === $slug && ($p['status'] ?? 'draft') === 'published') {
        $landing = $p;
        break;
    }
}

if (!$landing) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Not found']);
    exit;
}

// Get aggregated articles
$articles = get_articles();
$mode = $landing['aggregate_mode'] ?? 'tag';
$matched = [];
foreach ($articles as $a) {
    if (($a['status'] ?? 'draft') !== 'published') continue;
    $hit = false;
    if ($mode === 'all') $hit = true;
    elseif ($mode === 'tag') {
        foreach (($landing['aggregate_tags'] ?? []) as $t) {
            if (in_array($t, $a['tags'] ?? [])) { $hit = true; break; }
        }
    } elseif ($mode === 'category') {
        $cat = trim(strtolower($landing['aggregate_category'] ?? ''));
        if ($cat && (strtolower($a['category'] ?? '') === $cat || in_array($cat, array_map('strtolower', $a['tags'] ?? [])))) $hit = true;
    } elseif ($mode === 'author') {
        $author = trim(strtolower($landing['aggregate_author'] ?? ''));
        if ($author && (strtolower($a['author'] ?? '') === $author || stripos($a['author_name'] ?? '', $author) !== false)) $hit = true;
    }
    if ($hit) {
        $matched[] = [
            'id' => $a['id'],
            'title' => $a['title'],
            'slug' => $a['slug'],
            'excerpt' => mb_substr(strip_tags($a['content'] ?? ''), 0, 200),
            'cover' => $a['cover'] ?? '',
            'tags' => $a['tags'] ?? [],
            'category' => $a['category'] ?? '',
            'views' => $a['views'] ?? 0,
            'created_at' => $a['created_at'] ?? '',
        ];
    }
}
if (($landing['sort_by'] ?? 'newest') === 'popular') {
    usort($matched, fn($x, $y) => ($y['views'] <=> $x['views']));
} else {
    usort($matched, fn($x, $y) => strcmp($y['created_at'], $x['created_at']));
}
$matched = array_slice($matched, 0, $landing['max_articles'] ?? 20);

echo json_encode([
    'ok' => true,
    'page' => [
        'title' => $landing['title'],
        'slug' => $landing['slug'],
        'description' => $landing['description'] ?? '',
        'seo_title' => $landing['seo_title'] ?? '',
        'seo_desc' => $landing['seo_desc'] ?? '',
        'layout' => $landing['layout'] ?? 'grid',
        'show_description' => $landing['show_description'] ?? true,
        'aggregate_mode' => $mode,
        'aggregate_tags' => $landing['aggregate_tags'] ?? [],
        'aggregate_category' => $landing['aggregate_category'] ?? '',
        'aggregate_author' => $landing['aggregate_author'] ?? '',
    ],
    'articles' => $matched,
    'total' => count($matched),
], JSON_UNESCAPED_UNICODE);
