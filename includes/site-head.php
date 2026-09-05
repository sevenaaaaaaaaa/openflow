<?php
/**
 * 共享 <head> 资源 —— 主题早绑定脚本 + fonts / tokens / modules 三条 <link>。
 *
 *   <?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
 *
 * 版本号与 includes/site-nav.php 的 OF_SHELL_VER 同源：改一处，全站失效缓存。
 */
require_once __DIR__ . '/site-nav.php'; // 只定义函数与 OF_SHELL_VER，不输出
if (!function_exists('of_head_assets')) {
    function of_head_assets(): void {
        $v = defined('OF_SHELL_VER') ? OF_SHELL_VER : '20260903a';
        echo '<script>try{var t=JSON.parse(localStorage.getItem(\'openflow-site-v3\')||\'{}\');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia(\'(prefers-reduced-motion: reduce)\').matches)document.documentElement.classList.add(\'rm\');}catch(e){}</script>' . "\n";
        echo '<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=' . $v . '">' . "\n";
        echo '<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=' . $v . '">' . "\n";
        echo '<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=' . $v . '">' . "\n";
        // 全局 SEO 兜底（不破坏已有 title/meta/canonical —— 只补缺失）：
        // 用输出缓冲监听 </head>，页面已写的 title/description/canonical 保留，
        // 缺了的自动补站点默认，保证 46 页全站不再裸奔。
        if (function_exists('of_seo_bootstrap')) of_seo_bootstrap();
    }
}

function of_seo_bootstrap(): void {
    if (defined('OF_SEO_BOOTSTRAPPED')) return;
    define('OF_SEO_BOOTSTRAPPED', true);
    // 启动一个输出缓冲，把本页其余输出收进来；脚本结束 flush 时回调拿到**整个页面**，
    // 只补缺失的 SEO meta（title/description/canonical/og 页面已有则保留），对现有 46 页零侵入。
    ob_start(function (string $html): string {
        if (stripos($html, '</head>') === false) return $html;
        $siteUrl = function_exists('site_config_get') ? rtrim(site_config_get('site_url', ''), '/') : '';
        $req = preg_replace('/\?.*$/', '', preg_replace('/#.*$/', '', (string)($_SERVER['REQUEST_URI'] ?? '/')));
        // canonical：两种引号形式都没有才补，用当前请求 URL + 站点域名（修 example.com bug）
        $hasCanonical = (stripos($html, 'rel="canonical"') !== false || stripos($html, "rel='canonical'") !== false);
        if (!$hasCanonical && $siteUrl !== '') {
            $html = str_ireplace('</head>', '<link rel="canonical" href="' . htmlspecialchars($siteUrl . $req, ENT_QUOTES) . '">' . "\n</head>", $html);
        }
        // description：页面没写就补站点默认
        $hasDesc = (stripos($html, 'name="description"') !== false || stripos($html, "name='description'") !== false);
        if (!$hasDesc) {
            $desc = function_exists('site_config_get') ? site_config_get('site_desc', '') : '';
            if ($desc !== '') $html = str_ireplace('</head>', '<meta name="description" content="' . htmlspecialchars($desc, ENT_QUOTES) . '">' . "\n</head>", $html);
        }
        // OG title：页面没写 og:title 就补（优先取已捕获的 <title>，否则用站点默认名）
        $hasOg = (stripos($html, 'property="og:title"') !== false || stripos($html, "property='og:title'") !== false);
        if (!$hasOg) {
            $ogTitle = '';
            if (preg_match('/<title>(.*?)<\/title>/s', $html, $tm)) $ogTitle = trim(strip_tags($tm[1]));
            if ($ogTitle === '') {
                $ogTitle = function_exists('site_config_get') ? site_config_get('site_name', '') : '';
                $desc2   = function_exists('site_config_get') ? site_config_get('site_desc', '') : '';
                if ($ogTitle !== '' && $desc2 !== '') $ogTitle .= ' - ' . $desc2;
            }
            if ($ogTitle !== '') {
                $html = str_ireplace('</head>', '<meta property="og:title" content="' . htmlspecialchars($ogTitle, ENT_QUOTES) . '">' . "\n</head>", $html);
            }
        }
        return $html;
    });
}
