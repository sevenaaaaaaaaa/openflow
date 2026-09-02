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
$pages = json_read(DATA_DIR . '/builder-pages.json');
$page = null;
foreach ((array)$pages as $p) if (($p['slug'] ?? '') === $slug && ($p['status'] ?? '') === 'published') { $page = $p; break; }
if (!$page) { http_response_code(404); echo '<h1 style="padding:80px;text-align:center;font-family:sans-serif">页面不存在</h1>'; exit; }

$blocks = $page['blocks'] ?? [];
// 区块级人群定向（BACKLOG T1-8）：按访客画像过滤区块；无定向的区块照常显示。
try {
    require_once __DIR__ . '/lib/BlockTargeting.php';
    $blocks = blocktarget_filter($blocks);
} catch (Throwable $e) {}
$siteName = site_config_get('site_name');

// 区块渲染器 —— v7：每种区块映射到 modules.css 的共享 archetype，后台搭出来的页与站点其他页同一套零件。
function builder_render_block(array $b): string {
    $t = $b['type'] ?? 'text';
    $title = htmlspecialchars($b['title'] ?? '');
    $sub = htmlspecialchars($b['subtitle'] ?? '');
    $content = $b['content'] ?? '';
    $img = htmlspecialchars($b['image'] ?? '');
    $btnText = htmlspecialchars($b['button_text'] ?? '');
    $btnUrl = htmlspecialchars($b['button_url'] ?? '');
    $bg = $b['bg_color'] ?? '';
    $bgStyle = $bg ? ' style="background:' . htmlspecialchars($bg) . ';border-radius:var(--r-lg);padding:clamp(28px,4vw,48px)"' : '';
    $btn = $btnText && $btnUrl ? '<div class="cta-row"><a class="btn primary" href="' . $btnUrl . '">' . $btnText . '</a></div>' : '';
    $head = fn(string $tag = 'h2', bool $center = true) => '<div class="sec-head' . ($center ? ' center' : '') . '">' . ($sub && $tag === 'h1' ? '<span class="kicker">' . $sub . '</span>' : '') . '<' . $tag . '>' . $title . '</' . $tag . '>' . ($sub && $tag !== 'h1' ? '<p class="lead">' . $sub . '</p>' : '') . '</div>';
    $muted = fn(string $html) => '<div class="prose" style="color:var(--muted)">' . $html . '</div>';
    switch ($t) {
        case 'hero':
            return '<section class="reveal in"' . $bgStyle . '><div class="hero-center">' . ($sub ? '<span class="kicker">' . $sub . '</span>' : '') . '<h1>' . $title . '</h1>' . ($content ? '<p class="lead">' . $content . '</p>' : '') . $btn . '</div></section>';
        case 'features':
            return '<section class="sec reveal"' . $bgStyle . '>' . $head() . ($content ? '<div class="cols n4">' . $content . '</div>' : '<div class="empty">配置区块内容</div>') . '</section>';
        case 'cta':
        case 'newsletter':
            return '<section class="reveal"' . $bgStyle . '><div class="cta-band">' . ($sub ? '<span class="kicker">' . $sub . '</span>' : '') . '<h2>' . $title . '</h2>' . ($content ? '<p class="lead">' . $content . '</p>' : '') . $btn . '</div></section>';
        case 'text':
            return '<section class="sec reveal reader"' . $bgStyle . '>' . $head('h2', false) . $muted($content) . '</section>';
        case 'image-text':
            return '<section class="sec reveal"' . $bgStyle . '><div class="split"><div class="sp-txt"><h3>' . $title . '</h3>' . ($content ? '<p class="lead">' . $content . '</p>' : '') . $btn . '</div><div class="sp-vis">' . ($img ? '<img src="' . $img . '" alt="" style="width:100%;border-radius:var(--r-md);border:1px solid var(--border)">' : '') . '</div></div></section>';
        case 'stats':
            return '<section class="sec reveal"' . $bgStyle . '>' . $head() . ($content ? '<div class="stats">' . $content . '</div>' : '<div class="empty">配置数据</div>') . '</section>';
        case 'form':
            return '<section class="sec reveal reader"' . $bgStyle . '>' . $head() . '<div class="form-card">' . ($content ?: '<p class="note" style="text-align:center">' . ($sub ?: '配置表单 slug') . '</p>') . '</div></section>';
        case 'video':
            return '<section class="sec reveal reader"' . $bgStyle . '>' . $head() . '<div class="sp-win">' . ($content ?: '<div class="empty" style="margin:18px;border:none">配置视频地址</div>') . '</div></section>';
        case 'testimonials':
        case 'logo-wall':
        case 'faq':
        case 'gallery':
        default:
            return '<section class="sec reveal"' . $bgStyle . '>' . $head() . ($content ? $muted($content) : '<div class="empty">区块内容</div>') . '</section>';
    }
}
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($page['seo_title'] ?: ($page['title'] . ' | ' . $siteName))?></title>
<meta name="description" content="<?=htmlspecialchars($page['seo_desc'] ?? '')?>">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
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
