<?php
require_once __DIR__ . '/../admin/config.php';

$type = $_GET['type'] ?? 'list';
$slug = $_GET['slug'] ?? '';

if ($type === 'list') {
    $articles = get_articles();
    $articles = array_values(array_filter($articles, fn($a) => ($a['status'] ?? 'draft') === 'published'));

    $cat = $_GET['category'] ?? '';
    $tag = $_GET['tag'] ?? '';
    $search = $_GET['search'] ?? '';

    if ($cat) $articles = array_values(array_filter($articles, fn($a) => ($a['category'] ?? '') === $cat));
    if ($tag) $articles = array_values(array_filter($articles, fn($a) => in_array($tag, $a['tags'] ?? [])));
    if ($search) {
        $s = mb_strtolower($search);
        $articles = array_values(array_filter($articles, function($a) use ($s) {
            return mb_strpos(mb_strtolower($a['title'] ?? ''), $s) !== false
                || mb_strpos(mb_strtolower($a['content'] ?? ''), $s) !== false;
        }));
    }

    $articles = PluginSystem::apply_filters('articles_list_before_output', $articles);

    header('Content-Type: application/json; charset=utf-8');
    cors_headers();
    echo json_encode(['ok' => true, 'count' => count($articles), 'articles' => $articles], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'get' && $slug) {
    foreach (get_articles() as $a) {
        if (($a['slug'] ?? '') === $slug && ($a['status'] ?? 'draft') === 'published') {
            $a = PluginSystem::apply_filters('article_output_before', $a, $slug);
            header('Content-Type: application/json; charset=utf-8');
            cors_headers();
            echo json_encode(['ok' => true, 'article' => $a], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Not found']);
    exit;
}

if ($type === 'categories') {
    $cats = get_categories();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'categories' => $cats], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'published-counts') {
    $articles = array_filter(get_articles(), fn($a) => ($a['status'] ?? 'draft') === 'published');
    $cats = get_categories();
    $counts = [];
    foreach ($cats as $c) {
        $counts[$c['key']] = count(array_filter($articles, fn($a) => ($a['category'] ?? '') === $c['key']));
    }
    $counts['all'] = count($articles);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'counts' => $counts]);
    exit;
}
