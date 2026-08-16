<?php
/**
 * 广告位系统 — 文章顶/底、社区 banner、文章首页广告
 *
 * 数据文件：data/ads.json
 * 结构：
 * {
 *   "article_top":   {"enabled":true,"html":"","image":"","link":"","title":""},
 *   "article_bottom":{"enabled":true,"html":"","image":"","link":"","title":""},
 *   "community_banner":{"enabled":true,"html":"","image":"","link":"","title":""},
 *   "feed_1":        {"enabled":true,"html":"","image":"","link":"","title":""},
 *   ...
 * }
 */

if (!function_exists('ads_get')) {

function ads_get(): array {
    $defaults = [
        'article_top'    => ['enabled' => false, 'html' => '', 'image' => '', 'link' => '', 'title' => '广告位 · 文章顶部'],
        'article_bottom' => ['enabled' => false, 'html' => '', 'image' => '', 'link' => '', 'title' => '广告位 · 文章底部'],
        'community_banner' => ['enabled' => false, 'html' => '', 'image' => '', 'link' => '', 'title' => '社区 Banner'],
        'feed_1'         => ['enabled' => false, 'html' => '', 'image' => '', 'link' => '', 'title' => '瀑布流广告位 1'],
        'feed_2'         => ['enabled' => false, 'html' => '', 'image' => '', 'link' => '', 'title' => '瀑布流广告位 2'],
    ];
    return array_merge($defaults, json_read(DATA_DIR . '/ads.json'));
}

function ads_save(array $ads): bool {
    return json_write(DATA_DIR . '/ads.json', $ads);
}

// 渲染指定广告位（若全局广告开关关闭则返回空）
function ads_render(string $slot): string {
    $settings = json_read(DATA_DIR . '/settings.json');
    if (empty($settings['enable_ads'])) return '';
    $ads = ads_get();
    $ad = $ads[$slot] ?? null;
    if (!$ad || empty($ad['enabled'])) return '';

    $html = trim($ad['html'] ?? '');
    if ($html) return $html;

    if (!empty($ad['image'])) {
        $img = '<img loading="lazy" src="' . htmlspecialchars($ad['image']) . '" alt="' . htmlspecialchars($ad['title'] ?? '广告') . '" style="max-width:100%;border-radius:12px">';
        $link = trim($ad['link'] ?? '');
        return $link ? '<a href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener" style="display:block">' . $img . '</a>' : $img;
    }
    return '';
}

} // end if function_exists
