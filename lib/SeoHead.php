<?php
/**
 * SEO 头部生成器 SeoHead
 * 统一生成：关键词 / Canonical / OpenGraph / 结构化数据 / favicon
 * 供静态页面与动态页面复用
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/SiteConfig.php';

if (!function_exists('seo_head')) {
    /**
     * 输出完整 SEO head 标签（在 <head> 内调用）
     * @param array $opts ['title','description','keywords','canonical','image','type','json_ld']
     */
    function seo_head(array $opts = []): void {
        $siteName = site_config_get('site_name', 'OpenFlow');
        $siteDesc = site_config_get('site_desc', '');
        $siteKeywords = site_config_get('site_keywords', '');
        $siteUrl = site_config_get('site_url', '');

        $title = $opts['title'] ?? ($siteName . ' - ' . $siteDesc);
        $desc = $opts['description'] ?? $siteDesc;
        $keywords = $opts['keywords'] ?? $siteKeywords;
        $canonical = $opts['canonical'] ?? ($siteUrl ? $siteUrl . ($_SERVER['REQUEST_URI'] ?? '/') : '');
        $image = $opts['image'] ?? site_config_get('site_logo', '');
        $type = $opts['type'] ?? 'website';

        // 默认 favicon（内联 SVG 生成，无需文件）
        $faviconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#1e1e1e"/><text x="16" y="22" font-family="Arial" font-size="18" font-weight="bold" fill="#ddff0e" text-anchor="middle">O</text></svg>';
        $faviconData = 'data:image/svg+xml;base64,' . base64_encode($faviconSvg);

        echo "\n";
        // 关键词
        if ($keywords) {
            echo '<meta name="keywords" content="' . htmlspecialchars($keywords, ENT_QUOTES) . '">' . "\n";
        }
        // Canonical
        if ($canonical) {
            echo '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES) . '">' . "\n";
        }
        // hreflang（多语言启用时输出 alternate 链接）
        if (function_exists('i18n_enabled') && i18n_enabled()) {
            try {
                $default = i18n_default_locale();
                foreach (i18n_supported() as $loc) {
                    $path = preg_replace('#^/([a-z]{2}(?:-[A-Z]{2})?)(/|$)#', '/', $_SERVER['REQUEST_URI'] ?? '/') ?: '/';
                    $altUrl = $siteUrl . ($loc === $default ? $path : '/' . $loc . $path);
                    echo '<link rel="alternate" hreflang="' . htmlspecialchars($loc) . '" href="' . htmlspecialchars($altUrl, ENT_QUOTES) . '">' . "\n";
                }
            } catch (Throwable $e) {}
        }
        // favicon（SVG data URI，兼容所有浏览器）
        echo '<link rel="icon" type="image/svg+xml" href="' . $faviconData . '">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . $faviconData . '">' . "\n";
        // OpenGraph
        echo '<meta property="og:site_name" content="' . htmlspecialchars($siteName, ENT_QUOTES) . '">' . "\n";
        echo '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '">' . "\n";
        echo '<meta property="og:description" content="' . htmlspecialchars($desc, ENT_QUOTES) . '">' . "\n";
        echo '<meta property="og:type" content="' . htmlspecialchars($type, ENT_QUOTES) . '">' . "\n";
        if ($image) echo '<meta property="og:image" content="' . htmlspecialchars($image, ENT_QUOTES) . '">' . "\n";
        if ($canonical) echo '<meta property="og:url" content="' . htmlspecialchars($canonical, ENT_QUOTES) . '">' . "\n";
        // Twitter
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . htmlspecialchars($desc, ENT_QUOTES) . '">' . "\n";
        // 结构化数据（Organization / WebSite / 自定义）
        $jsonLd = $opts['json_ld'] ?? [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'description' => $siteDesc,
            'url' => $canonical,
        ];
        echo '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}
