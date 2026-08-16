<?php
/**
 * RSS 引入 API — 从外部 RSS 拉取文章，导入为草稿
 * POST /api/rss-import.php  {rss_url, category, author, tag}
 * GET  /api/rss-import.php?url=xxx  预览
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

// 简单 RSS 解析（支持 RSS 2.0 / Atom）
function rss_parse(string $xml): array {
    $items = [];
    if (preg_match_all('/<item>(.*?)<\/item>/is', $xml, $m1)) {
        foreach ($m1[1] as $raw) {
            $item = ['title' => '', 'link' => '', 'description' => '', 'date' => ''];
            if (preg_match('/<title>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/title>/is', $raw, $t)) $item['title'] = trim($t[1]);
            if (preg_match('/<link>(.*?)<\/link>/is', $raw, $l)) $item['link'] = trim($l[1]);
            if (preg_match('/<description>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/description>/is', $raw, $d)) $item['description'] = trim($d[1]);
            if (preg_match('/<pubDate>(.*?)<\/pubDate>/is', $raw, $dt)) $item['date'] = trim($dt[1]);
            if ($item['title']) $items[] = $item;
        }
    }
    // Atom 回退
    if (empty($items) && preg_match_all('/<entry>(.*?)<\/entry>/is', $xml, $m2)) {
        foreach ($m2[1] as $raw) {
            $item = ['title' => '', 'link' => '', 'description' => '', 'date' => ''];
            if (preg_match('/<title>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/title>/is', $raw, $t)) $item['title'] = trim($t[1]);
            if (preg_match('/<link[^>]*href="([^"]+)"/is', $raw, $l)) $item['link'] = trim($l[1]);
            if (preg_match('/<content>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/content>/is', $raw, $d)) $item['description'] = trim($d[1]);
            if (preg_match('/<updated>(.*?)<\/updated>/is', $raw, $dt)) $item['date'] = trim($dt[1]);
            if ($item['title']) $items[] = $item;
        }
    }
    return array_slice($items, 0, 20);
}

// 去 HTML 标签生成纯文本 + 简单段落
function rss_content_to_html(string $desc): string {
    $desc = trim($desc);
    if (!$desc) return '';
    // 已是 HTML？粗略判断
    if (preg_match('/<[a-z]+[^>]*>/i', $desc)) return $desc;
    $paras = preg_split('/\n{2,}/', $desc);
    return implode("\n", array_map(fn($p) => '<p>' . htmlspecialchars(trim($p)) . '</p>', $paras));
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$rssUrl = trim($isPost ? ($_POST['rss_url'] ?? '') : ($_GET['url'] ?? ''));

if (!$rssUrl || !filter_var($rssUrl, FILTER_VALIDATE_URL)) {
    echo json_encode(['ok' => false, 'error' => '请输入有效的 RSS URL']); exit;
}

$ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'OpenFlow-RSS-Import/1.0']]);
$xml = @file_get_contents($rssUrl, false, $ctx);
if ($xml === false) {
    echo json_encode(['ok' => false, 'error' => '无法获取 RSS 内容']); exit;
}

$items = rss_parse($xml);
if (empty($items)) {
    echo json_encode(['ok' => false, 'error' => '未解析到文章条目']); exit;
}

// 预览模式
if (!$isPost) {
    echo json_encode(['ok' => true, 'count' => count($items), 'items' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

// 导入模式
$category = trim($_POST['category'] ?? 'insight');
$author = trim($_POST['author'] ?? '导入');
$tags = array_filter(array_map('trim', explode(',', $_POST['tag'] ?? '')));

$articles = json_read(ARTICLES_DIR . '/index.json');
$existingSlugs = array_column($articles, null, 'slug');
$imported = 0;

foreach ($items as $it) {
    $title = $it['title'];
    $slug = 'rss-' . substr(md5($title), 0, 12);
    if (isset($existingSlugs[$slug])) continue;

    $content = rss_content_to_html($it['description']);
    if (!$content) continue;

    $articles[] = [
        'id' => 'art_rss_' . substr(md5($title . time()), 0, 10),
        'title' => $title,
        'slug' => $slug,
        'content' => $content,
        'excerpt' => mb_substr(strip_tags($content), 0, 160),
        'seo_title' => $title . ' | OpenFlow',
        'seo_desc' => mb_substr(strip_tags($content), 0, 160),
        'status' => 'draft',
        'author' => $author,
        'category' => $category,
        'tags' => $tags,
        'source_url' => $it['link'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $existingSlugs[$slug] = true;
    $imported++;
}

if ($imported > 0) {
    json_write(ARTICLES_DIR . '/index.json', $articles);
}
echo json_encode(['ok' => true, 'imported' => $imported, 'total_found' => count($items)], JSON_UNESCAPED_UNICODE);
