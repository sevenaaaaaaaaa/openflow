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
        // ② 登录态→前台快速编辑入口：已登录且有编辑权限时，页角浮出「编辑此页」
        echo of_edit_bar_html($page);
    }

    /**
     * 前台「编辑此页」入口 —— 检测后台登录态，按当前 URL 映射到对应后台编辑器。
     * 只在已登录且有 pages/settings 权限时输出；访客/未登录不输出，零影响。
     */
    function of_edit_bar_html(string $page = 'home'): string {
        if (empty($_SESSION['admin_user'])) return '';
        $role = $_SESSION['admin_role'] ?? 'admin';
        $editable = ['admin','marketing','sales'];
        if (!in_array($role, $editable, true)) return '';
        // 按当前请求 URL 判断页面类型 → 后台编辑 URL
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $edit = '';
        if (preg_match('#^/b/([^/]+)#', $path, $m)) $edit = '/xmp/page-builder?edit=' . urlencode($m[1]);
        elseif (preg_match('#^/(article|articles)/([^/]+)#', $path, $m)) $edit = '/xmp/article-edit?id=' . urlencode($m[2]);
        elseif (preg_match('#^/c/([^/]+)#', $path, $m)) $edit = '/xmp/cpt?type=' . urlencode($m[1]);
        elseif ($path === '/' || $path === '') $edit = '/xmp/studio';
        elseif (preg_match('#^/(about|product|capability|courses|docs|academy|community|marketplace)#', $path, $m)) $edit = '/xmp/pages-list';
        if ($edit === '') return '';
        $label = '✏️ 编辑此页';
        return '<a href="' . htmlspecialchars($edit, ENT_QUOTES) . '" style="position:fixed;bottom:16px;left:16px;z-index:99990;background:#1e1e1e;color:#ddff0e;padding:9px 16px;border-radius:999px;font-size:13px;font-weight:700;text-decoration:none;box-shadow:0 8px 24px rgba(0,0,0,.22);font-family:system-ui,-apple-system,sans-serif">' . $label . '</a>';
    }
}


