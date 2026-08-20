<?php
/**
 * 公开内容 API — 数据层对接（SSR / 外部前端渲染）
 * 只读，无需登录。供已有技术/产品站点通过 API 拉取 OpenFlow 内容渲染。
 * 端点：
 *   ?type=articles&category=xxx&limit=N&page=N
 *   ?type=article&id=xxx / &slug=xxx
 *   ?type=courses&limit=N / ?type=course&id=xxx
 *   ?type=products&limit=N / ?type=product&id=xxx
 *   ?type=events&limit=N / ?type=event&id=xxx
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$type = $_GET['type'] ?? 'articles';
$limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
$page = max(1, (int)($_GET['page'] ?? 1));
$base = site_config_get('site_url');

try {
    switch ($type) {
        case 'articles':
            $list = array_values(array_filter(get_articles(), fn($a) => ($a['status'] ?? '') === 'published'));
            if (!empty($_GET['category'])) $list = array_values(array_filter($list, fn($a) => ($a['category'] ?? '') === $_GET['category']));
            if (!empty($_GET['search'])) $list = array_values(array_filter($list, fn($a) => mb_strpos(($a['title'] ?? '') . ($a['content'] ?? ''), $_GET['search']) !== false));
            $total = count($list);
            $slice = array_slice($list, ($page - 1) * $limit, $limit);
            echo json_encode(['ok' => true, 'type' => 'articles', 'total' => $total, 'page' => $page, 'items' => array_map(fn($a) => [
                'id' => $a['id'], 'title' => $a['title'], 'slug' => $a['slug'], 'category' => $a['category'] ?? '',
                'tags' => $a['tags'] ?? [], 'cover' => $a['cover'] ?? '', 'views' => $a['views'] ?? 0,
                'created_at' => $a['created_at'] ?? '', 'url' => $base . '/article/' . ($a['slug'] ?? ''),
            ], $slice)], JSON_UNESCAPED_UNICODE);
            break;

        case 'article':
            $id = $_GET['id'] ?? '';
            $slug = $_GET['slug'] ?? '';
            $a = null;
            foreach (get_articles() as $x) { if (($x['id'] === $id || ($x['slug'] ?? '') === $slug) && ($x['status'] ?? '') === 'published') { $a = $x; break; } }
            if (!$a) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'文章不存在']); exit; }
            echo json_encode(['ok' => true, 'article' => ['id' => $a['id'], 'title' => $a['title'], 'slug' => $a['slug'], 'content' => $a['content'] ?? '', 'category' => $a['category'] ?? '', 'tags' => $a['tags'] ?? [], 'cover' => $a['cover'] ?? '', 'author' => $a['author'] ?? '', 'created_at' => $a['created_at'] ?? '', 'seo' => ['title' => $a['seo_title'] ?? '', 'desc' => $a['seo_desc'] ?? '']]], JSON_UNESCAPED_UNICODE);
            break;

        case 'courses':
            try { $shopCfg = shop_settings(); } catch (Throwable $e) { $shopCfg = []; }
            $list = array_values(array_filter(json_read(DATA_DIR . '/courses/index.json'), fn($c) => ($c['status'] ?? '') === 'published'));
            echo json_encode(['ok' => true, 'type' => 'courses', 'items' => array_map(fn($c) => [
                'id' => $c['id'], 'title' => $c['title'], 'type' => $c['type'] ?? '课程', 'description' => $c['description'] ?? '',
                'cover' => $c['cover'] ?? '', 'price' => $shopCfg['course_prices'][$c['id']] ?? 0, 'chapters' => count($c['chapters'] ?? []),
                'author' => $c['author_name'] ?? '', 'url' => $base . '/course/' . $c['id'] . '?id=' . $c['id'],
            ], array_slice($list, 0, $limit))], JSON_UNESCAPED_UNICODE);
            break;

        case 'products':
            require_once __DIR__ . '/../lib/CommerceSystem.php';
            $list = array_values(array_filter(CommerceSystem::allPublished(), fn($p) => (float)($p['pricing']['price'] ?? 0) > 0));
            echo json_encode(['ok' => true, 'type' => 'products', 'items' => array_map(fn($p) => [
                'id' => $p['id'], 'title' => $p['title'], 'type' => $p['type'], 'description' => $p['description'] ?? '',
                'price' => (float)($p['pricing']['price'] ?? 0), 'tags' => $p['tags'] ?? [], 'sales' => $p['sales_count'] ?? 0,
                'url' => $base . '/' . $p['type'] . '/' . $p['id'],
            ], array_slice($list, 0, $limit))], JSON_UNESCAPED_UNICODE);
            break;

        case 'pages':
            // 落地页 blocks（供已有前端组件网站拉取渲染，实现 CMS 与外部前端兼容）
            $pages = array_values(array_filter(json_read(DATA_DIR . '/builder-pages.json'), fn($p) => ($p['status'] ?? 'draft') === 'published'));
            $onlyBlocks = !empty($_GET['blocks']) && $_GET['blocks'] === '1';
            echo json_encode(['ok' => true, 'type' => 'pages', 'items' => array_map(function($p) use ($base, $onlyBlocks) {
                $out = ['id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'seo_title' => $p['seo_title'] ?? '', 'seo_desc' => $p['seo_desc'] ?? ''];
                if ($onlyBlocks) { $out['blocks'] = $p['blocks'] ?? []; $out['html'] = $out['html'] ?? ''; }
                else $out['url'] = $base . '/b/' . ($p['slug'] ?? $p['id']);
                return $out;
            }, $pages)], JSON_UNESCAPED_UNICODE);
            break;

        case 'events':
            $list = array_values(array_filter(json_read(DATA_DIR . '/events/index.json'), fn($e) => ($e['status'] ?? '') === 'published'));
            echo json_encode(['ok' => true, 'type' => 'events', 'items' => array_map(fn($e) => [
                'id' => $e['id'], 'title' => $e['title'], 'type' => $e['event_type'] ?? 'online', 'start_date' => $e['start_date'] ?? '', 'end_date' => $e['end_date'] ?? '',
                'location' => $e['location'] ?? '', 'capacity' => $e['capacity'] ?? 0, 'cover' => $e['cover'] ?? '', 'url' => $base . '/event/' . ($e['slug'] ?? ''),
            ], array_slice($list, 0, $limit))], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => '未知类型']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
