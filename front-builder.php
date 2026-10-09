<?php
/**
 * 模块化页面前端渲染 — 渲染 builder-pages.json 的 blocks 数组
 *
 * v7（2026-09-01）：区块渲染器输出共享 archetype（hero-center / cols / cta-band / split / stats / form-card / prose）。
 * 路由：/b/{slug}
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$slug = $_GET['slug'] ?? '';
require_once __DIR__ . '/lib/BuilderPages.php';
$pages = builder_pages_all();
$page = null;
foreach ((array)$pages as $p) if (($p['slug'] ?? '') === $slug && ($p['status'] ?? '') === 'published') { $page = $p; break; }
if (!$page) { http_response_code(404); echo '<h1 style="padding:80px;text-align:center;font-family:sans-serif">页面不存在</h1>'; exit; }

$blocks = $page['blocks'] ?? [];
// 多语言：页面用 i18n_locales 声明「有译文」的语言（译文来自分段字典 data/lang/content-{locale}.json，见 lib/I18nContent.php）。
// $__loc = 实际要翻译成的语言（null = 保持中文）；当前语言不在声明里 → 仍出中文，canonical 指回默认语言页，避免重复内容。
$__default = function_exists('i18n_default_locale') ? i18n_default_locale() : 'zh-CN';
$__cur     = function_exists('i18n_current') ? i18n_current() : $__default;
$__avail   = array_values(array_filter((array)($page['i18n_locales'] ?? []), fn($l) => in_array($l, i18n_supported(), true)));
$__loc     = function_exists('i18n_content_locale') ? i18n_content_locale($__avail) : null;
$__path    = preg_replace('#^/(?:en|ja|ko|zh-CN|zh-TW|ru|fr)(?=/|$)#', '', (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) ?: '/';
$__siteUrl = rtrim((string)site_config_get('site_url', ''), '/');
if (!$__loc && $__siteUrl !== '') $GLOBALS['OF_SEO_CANONICAL'] = $__siteUrl . $__path;   // 没有对应译文（含不受支持的语言前缀）→ canonical 指回默认语言地址
// ①b/③ 性能：无区块级人群定向的落地页走整页 PageCache（300s）；有 audience 的页面跳过缓存，
// 避免给不同访客缓存出错误内容。登录管理员不缓存（保证「编辑此页」按钮实时 + 编辑后立即可见）。
$hasAudience = false;
foreach ($blocks as $b) if (!empty($b['audience'])) { $hasAudience = true; break; }
if (!$hasAudience && empty($_SESSION['admin_user']) && class_exists('PageCache')) {
    PageCache::begin('b:' . $slug . ':' . $__cur, 300);   // key 带语言，避免多语言串缓存   // 命中则直接输出并 exit
}
// 区块级人群定向（BACKLOG T1-8）：按访客画像过滤区块；无定向的区块照常显示。
try {
    require_once __DIR__ . '/lib/BlockTargeting.php';
    $blocks = blocktarget_filter($blocks);
} catch (Throwable $e) {}
$siteName = site_config_get('site_name');
// 译文：整页输出过一遍分段字典（正文/页脚/meta/title/属性一并覆盖，未命中的段保持中文）
if ($__loc) ob_start(fn($h) => i18n_tr_html($h, $__loc));

// 区块渲染器与类型表已下沉到 lib/BlockRegistry.php（三处抄了三份，其中四种类型前台根本不认）
require_once __DIR__ . '/lib/BlockRegistry.php';

// Demo 预览页不进搜索引擎索引（避免与正式页重复内容；正式替换后此分支自然失效）
if (str_starts_with($slug, 'demo-')) {
    require_once __DIR__ . '/includes/site-head.php';
    if (function_exists('of_seo_noindex')) of_seo_noindex();
}

?>
<!doctype html>
<html lang="<?=htmlspecialchars($__loc ?: $__default)?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($page['seo_title'] ?: ($page['title'] . ' | ' . $siteName))?></title>
<meta name="description" content="<?=htmlspecialchars($page['seo_desc'] ?? '')?>">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<?php if ($__avail && $__siteUrl !== ''): // hreflang 只声明真正有译文的语言
    foreach (array_merge([$__default], $__avail) as $__l) echo '<link rel="alternate" hreflang="' . htmlspecialchars($__l) . '" href="' . htmlspecialchars($__siteUrl . ($__l === $__default ? '' : '/' . $__l) . $__path) . '">' . "\n";
    echo '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($__siteUrl . $__path) . '">' . "\n";
endif; ?>
<style>
/* 模块化页面零私有 CSS */

</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('home'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

<?php foreach ($blocks as $b) echo builder_render_block($b); ?>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
