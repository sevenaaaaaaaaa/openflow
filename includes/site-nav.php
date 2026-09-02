<?php
/**
 * OpenFlow · 前台导航唯一数据源
 * ---------------------------------------------------------------
 * 契约：所有前台页面的顶部 chrome 导航 / 侧栏 / 命令面板，
 *       都由 assets/site-shell.js 渲染，导航数据只来自这里。
 *
 *   data/nav.json  →  of_nav_data()  →  window.OF_NAV  →  site-shell.js
 *
 * data/ 目录被 .htaccess 拒绝直连（Deny from all），因此不走浏览器 fetch，
 * 由 PHP 服务端读取后内联注入，零额外请求、零渲染闪烁。
 * data/nav.json 缺失或损坏时回落到 site-shell.js 内置的 NAV_FALLBACK。
 */

if (!defined('OF_SHELL_VER')) define('OF_SHELL_VER', '20260903a');

if (!function_exists('of_nav_data')) {
    /**
     * 读取导航数据源。失败返回 null（让 site-shell.js 用内置兜底）。
     */
    function of_nav_data(): ?array
    {
        static $cache = false;
        if ($cache !== false) return $cache;

        $file = __DIR__ . '/../data/nav.json';
        $cache = null;

        if (is_readable($file)) {
            $raw = file_get_contents($file);
            if ($raw !== false && $raw !== '') {
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    $items = $json['items'] ?? $json;
                    if (is_array($items) && $items !== []) {
                        // 最小校验：每一项必须有 id / label / href
                        $ok = [];
                        foreach ($items as $it) {
                            if (!is_array($it)) continue;
                            if (empty($it['id']) || empty($it['label']) || !isset($it['href'])) continue;
                            $ok[] = $it;
                        }
                        if ($ok !== []) $cache = $ok;
                    }
                }
            }
        }
        return $cache;
    }
}

if (!function_exists('of_nav_boot')) {
    /**
     * 输出 <script>window.OF_NAV=[…]</script>。
     * 必须在 site-shell.js 之前调用。
     */
    function of_nav_boot(): void
    {
        $nav = of_nav_data();
        if ($nav === null) return; // 静默回落到 site-shell.js 内置 NAV
        $json = json_encode($nav, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return;
        // </script> 与 HTML 注释序列转义，避免提前闭合脚本块
        $json = str_replace(['</', '<!--'], ['<\/', '<\!--'], $json);
        echo '<script>window.OF_NAV=' . $json . ';</script>' . "\n";
    }
}

if (!function_exists('of_shell')) {
    /**
     * 前台页面接入共享外壳的唯一入口。
     *   <?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('about'); ?>
     * 放在 <body> 之后第一行。
     *
     * @param string $page site-shell 的 data-page（home/growth/product/capability/
     *                     courses/articles/marketplace/about …）
     */
    function of_shell(string $page = 'home'): void
    {
        of_nav_boot();
        echo '<script src="/assets/site-shell.js?v=' . OF_SHELL_VER . '" data-cfasync="false" data-page="'
            . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
    }
}

