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
        // 插件前台插槽：head（统计代码/meta/自定义样式）+ 插件 CSS 资产
        if (class_exists('PluginSystem')) {
            PluginSystem::render_front_slot('head');
            PluginSystem::render_front_assets('css');
        }
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
        // canonical：保留内容身份参数（site/id/view/slug/play 等），只剥离跟踪参数（utm_*/ref/fbclid…），
        // 否则 marketplace?view=plugin&id=x 这类无伪静态页会把 canonical 全归并到 /marketplace。
        $reqUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $reqPath = preg_replace('/\?.*$/', '', preg_replace('/#.*$/', '', $reqUri));
        $req = $reqPath;
        $qs = parse_url($reqUri, PHP_URL_QUERY);
        if (is_string($qs) && $qs !== '') {
            parse_str($qs, $q);
            $tracking = array_filter(array_keys($q), fn($k) => preg_match('/^(utm_|fbclid$|gclid$|ref$|_ga$|spm$)/i', (string)$k));
            foreach ($tracking as $tk) unset($q[$tk]);
            // 分页/排序/搜索等浏览态参数不参与 canonical（避免重复内容）
            foreach (['sort', 'page', 'q', 'type', 'cat', 'tab'] as $bk) unset($q[$bk]);
            if ($q) $req .= '?' . http_build_query($q);
        }
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
        $ogTitle = '';
        if (preg_match('/<title>(.*?)<\/title>/s', $html, $tm)) $ogTitle = trim(strip_tags($tm[1]));
        if ($ogTitle === '') {
            $ogTitle = function_exists('site_config_get') ? site_config_get('site_name', '') : '';
            $desc2   = function_exists('site_config_get') ? site_config_get('site_desc', '') : '';
            if ($ogTitle !== '' && $desc2 !== '') $ogTitle .= ' - ' . $desc2;
        }
        if (!$hasOg && $ogTitle !== '') {
            $html = str_ireplace('</head>', '<meta property="og:title" content="' . htmlspecialchars($ogTitle, ENT_QUOTES) . '">' . "\n</head>", $html);
        }
        // 分享卡全量兜底：og:description / og:url / og:type / og:site_name / og:image + Twitter Card
        // （页面已写的保留，缺什么补什么；og:image 默认 1200×630 品牌横幅）
        $inject = '';
        $hasOgDesc = (stripos($html, 'property="og:description"') !== false || stripos($html, "property='og:description'") !== false);
        if (!$hasOgDesc) {
            $ogDesc = '';
            if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $html, $dm)) $ogDesc = trim($dm[1]);
            if ($ogDesc === '' && function_exists('site_config_get')) $ogDesc = site_config_get('site_desc', '');
            if ($ogDesc !== '') $inject .= '<meta property="og:description" content="' . htmlspecialchars($ogDesc, ENT_QUOTES) . '">' . "\n";
        }
        if ($siteUrl !== '') {
            if (stripos($html, 'property="og:url"') === false && stripos($html, "property='og:url'") === false) {
                $inject .= '<meta property="og:url" content="' . htmlspecialchars($siteUrl . $req, ENT_QUOTES) . '">' . "\n";
            }
            if (stripos($html, 'property="og:image"') === false && stripos($html, "property='og:image'") === false) {
                $ogImg = function_exists('site_config_get') ? site_config_get('og_image', '') : '';
                if ($ogImg === '') $ogImg = $siteUrl . '/assets/images/og-cover.png';
                $inject .= '<meta property="og:image" content="' . htmlspecialchars($ogImg, ENT_QUOTES) . '">' . "\n"
                         . '<meta property="og:image:width" content="1200">' . "\n"
                         . '<meta property="og:image:height" content="630">' . "\n";
            }
        }
        if (stripos($html, 'property="og:type"') === false && stripos($html, "property='og:type'") === false) {
            $inject .= '<meta property="og:type" content="website">' . "\n";
        }
        if (stripos($html, 'property="og:site_name"') === false && stripos($html, "property='og:site_name'") === false) {
            $sn = function_exists('site_config_get') ? site_config_get('site_name', '') : '';
            if ($sn !== '') $inject .= '<meta property="og:site_name" content="' . htmlspecialchars($sn, ENT_QUOTES) . '">' . "\n";
        }
        // Twitter Card：有 og:image 就用大卡，标题/描述复用 og 值（Twitter 会自己回退读 og:*，这里只补 card 类型）
        if (stripos($html, 'name="twitter:card"') === false && stripos($html, "name='twitter:card'") === false) {
            $inject .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        }
        // GA4 自动注入：后台配了 ga_id（或 SEO 站长工具里 Google 授权选 GA4 属性时自动写入）就全站注入 gtag，
        // 页面已手动接入 gtag（含 GTM）则不重复。此前 ga_id 设置是死的——保存了前台根本不输出。
        $gaId = function_exists('site_config_get') ? trim(site_config_get('ga_id', '')) : '';
        if ($gaId !== '' && stripos($html, 'googletagmanager.com/gtag') === false && stripos($html, 'googletagmanager.com/gtm.js') === false) {
            $gaIdEsc = htmlspecialchars($gaId, ENT_QUOTES);
            $inject .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $gaIdEsc . '"></script>' . "\n"
                     . '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . $gaIdEsc . '");</script>' . "\n";
        }
        // 百度统计自动注入：baidu_id 与 ga_id 一样是死设置（全站无消费方），此处补齐
        $baiduId = function_exists('site_config_get') ? trim(site_config_get('baidu_id', '')) : '';
        if ($baiduId !== '' && stripos($html, 'hm.baidu.com') === false) {
            $bdEsc = htmlspecialchars($baiduId, ENT_QUOTES);
            $inject .= '<script>var _hmt=_hmt||[];(function(){var hm=document.createElement("script");hm.src="https://hm.baidu.com/hm.js?' . $bdEsc . '";var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(hm,s);})();</script>' . "\n";
        }
        if ($inject !== '') $html = str_ireplace('</head>', $inject . '</head>', $html);
        return $html;
    });
}
