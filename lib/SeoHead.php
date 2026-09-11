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
        // A2 可用化：seo.json 页面级 SEO 优先（后台可配、前台生效）
        if (function_exists('_json_read_file')) $__seo = _json_read_file(DATA_DIR . '/seo.json');
        else $__seo = (function_exists('json_read') ? json_read(DATA_DIR . '/seo.json') : (is_file(DATA_DIR . '/seo.json') ? (json_decode((string)file_get_contents(DATA_DIR . '/seo.json'), true) ?: []) : []));
        $__page = trim(preg_replace('#^/|\.php$#', '', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
        $__pageSeo = is_array($__seo) ? ($__seo[$__page] ?? ($__seo['/' . $__page] ?? null)) : null;
        if (is_array($__pageSeo)) {
            if (!empty($__pageSeo['title'])) $opts['title'] = $__pageSeo['title'];
            if (!empty($__pageSeo['description'])) $opts['description'] = $__pageSeo['description'];
            if (!empty($__pageSeo['keywords'])) $opts['keywords'] = $__pageSeo['keywords'];
        }

        $title = $opts['title'] ?? ($siteName . ' - ' . $siteDesc);
        $desc = $opts['description'] ?? $siteDesc;
        $keywords = $opts['keywords'] ?? $siteKeywords;
        $canonical = $opts['canonical'] ?? ($siteUrl ? $siteUrl . ($_SERVER['REQUEST_URI'] ?? '/') : '');
        $image = $opts['image'] ?? site_config_get('site_logo', '');
        // og:image 兜底：优先后台配置的 og_image，否则用 1200×630 品牌分享横幅（需绝对 URL）
        if (!$image && function_exists('site_config_get')) $image = site_config_get('og_image', '');
        if (!$image && $siteUrl) $image = $siteUrl . '/assets/images/og-cover.png';
        $type = $opts['type'] ?? 'website';

        // 默认 favicon（流环 Logo v2 内联 SVG，无需文件；与 /favicon.svg 同稿）
        $faviconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none"><defs><linearGradient id="g" x1="0" y1="64" x2="64" y2="0" gradientUnits="userSpaceOnUse"><stop stop-color="#1d4ed8"/><stop offset="1" stop-color="#6d4aff"/></linearGradient></defs><rect width="64" height="64" rx="15" fill="url(#g)"/><path d="M42.4 43.5 A15.5 15.5 0 1 1 42.4 20.5" stroke="#fff" stroke-width="5.6" stroke-linecap="round"/><path d="M38.1 18.9 L46.6 15.8 L44.4 24.5" stroke="#fff" stroke-width="5.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
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
        // A3 可用化：seo-settings.json 站点级 meta 注入（后台可配、前台真正生效）
        $__ss = is_file(DATA_DIR . '/seo-settings.json') ? (json_decode((string)file_get_contents(DATA_DIR . '/seo-settings.json'), true) ?: []) : [];
        if (is_array($__ss)) {
            if (empty($image) && !empty($__ss['og_image'])) $image = $__ss['og_image'];
            $__robots = [];
            if (!empty($__ss['meta_robots_index']) && $__ss['meta_robots_index'] === 'noindex') $__robots[] = 'noindex';
            if (!empty($__ss['meta_robots_follow']) && $__ss['meta_robots_follow'] === 'nofollow') $__robots[] = 'nofollow';
            if ($__robots) echo '<meta name="robots" content="' . htmlspecialchars(implode(',', $__robots), ENT_QUOTES) . '">' . "\n";
            if (!empty($__ss['google_verify'])) echo '<meta name="google-site-verification" content="' . htmlspecialchars($__ss['google_verify'], ENT_QUOTES) . '">' . "\n";
            if (!empty($__ss['baidu_verify'])) echo '<meta name="baidu-site-verification" content="' . htmlspecialchars($__ss['baidu_verify'], ENT_QUOTES) . '">' . "\n";
            // Bing 站点验证 meta（此前后台可填但前台无输出路径，等于死配置）
            if (!empty($__ss['bing_verify'])) echo '<meta name="msvalidate.01" content="' . htmlspecialchars($__ss['bing_verify'], ENT_QUOTES) . '">' . "\n";
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
        // A4 可用化：接后台「结构化数据」(data/structured/{type}/{id}.json)
        $__type = preg_match('#^/(article|articles)/([^/]+)#', $_SERVER['REQUEST_URI'] ?? '/', $__m) ? 'article' : 'page';
        $__id = isset($__m[2]) ? $__m[2] : (trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: 'index');
        $__structFile = DATA_DIR . '/structured/' . $__type . '/' . $__id . '.json';
        if (is_file($__structFile)) {
            $__struct = json_decode((string)file_get_contents($__structFile), true);
            if (is_array($__struct) && !empty($__struct)) $jsonLd = $__struct;
        }
        $jsonLd = $jsonLd ?? $opts['json_ld'] ?? [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'description' => $siteDesc,
            'url' => $canonical,
        ];
        echo '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
    }
}
