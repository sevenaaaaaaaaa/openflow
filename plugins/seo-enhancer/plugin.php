<?php
/**
 * SEO 增强（官方示例 1/3）
 *
 * 演示重点：filter 改写数据 + action 触发副作用。
 *
 *   - article_save_before（filter）：保存前补全缺失的 SEO 描述与关键词。
 *     过滤器必须 return，返回什么就写什么，所以任何一条分支都要有返回值。
 *   - content_published（action）：状态真正变为 published 时才推送收录，
 *     重复保存不会重复推。这是 content_published 与 content_updated 的区别。
 *
 * 这个插件不改任何已经填好的字段，只补空的——自动化不该覆盖人的输入。
 */

require_once __DIR__ . '/../../lib/PluginSDK.php';

$p = plugin('seo-enhancer');

// ── 1. 保存前：补全 SEO 描述与关键词 ──────────────────
$p->filter('article_save_before', function (array $article) use ($p) {
    // 描述留空时，从正文抽前 N 个字
    if (trim((string)($article['seo_description'] ?? '')) === '') {
        $len  = (int)$p->get('desc_length', 120);
        $text = strip_tags((string)($article['content'] ?? ''));
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text !== '') {
            $article['seo_description'] = mb_substr($text, 0, max(40, $len));
        }
    }

    // 关键词留空时，用文章标签兜底
    if (trim((string)($article['seo_keywords'] ?? '')) === '') {
        $tags = array_filter((array)($article['tags'] ?? []), 'is_string');
        if ($tags) $article['seo_keywords'] = implode(',', array_slice($tags, 0, 8));
    }

    // 标题过长时只记一条日志提醒，不擅自截断——标题是作者的表达
    $maxTitle = (int)$p->get('title_warn_length', 60);
    $title    = (string)($article['title'] ?? '');
    if ($maxTitle > 0 && mb_strlen($title) > $maxTitle) {
        $p->log("标题超过 {$maxTitle} 字，搜索结果可能被截断：{$title}", 'warn');
    }

    return $article;   // filter 必须 return
});

// ── 2. 发布后：推送搜索引擎收录 ──────────────────────
$p->on('content_published', function (string $type, string $id, array $article) use ($p) {
    if (!$p->get('indexnow_key')) return;                 // 没配 key 就什么都不做
    $slug = (string)($article['slug'] ?? $id);
    $host = (string)$p->get('site_host', $_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') { $p->log('未配置站点域名，跳过收录推送', 'warn'); return; }

    $url = 'https://' . $host . '/article/' . $slug;
    $r = $p->httpPost('https://api.indexnow.org/indexnow', [
        'host'    => $host,
        'key'     => $p->get('indexnow_key'),
        'urlList' => [$url],
    ]);
    $p->log(($r['ok'] ? '已推送收录：' : '收录推送失败：') . $url
            . ' status=' . $r['status'] . ($r['error'] ? ' ' . $r['error'] : ''));
});

// ── 3. 后台入口 ──────────────────────────────────────
$p->menu('SEO 增强', $p->pageUrl(), '🔍', 'seo');
