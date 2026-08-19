<?php
/**
 * 模块化页面前端渲染 — 渲染 builder-pages.json 的 blocks 数组
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
$siteName = site_config_get('site_name');

// 区块渲染器
function builder_render_block(array $b): string {
    $t = $b['type'] ?? 'text';
    $title = htmlspecialchars($b['title'] ?? '');
    $sub = htmlspecialchars($b['subtitle'] ?? '');
    $content = $b['content'] ?? '';
    $img = htmlspecialchars($b['image'] ?? '');
    $btnText = htmlspecialchars($b['button_text'] ?? '');
    $btnUrl = htmlspecialchars($b['button_url'] ?? '');
    $bg = $b['bg_color'] ?? '';
    $bgStyle = $bg ? 'style="background:' . htmlspecialchars($bg) . '"' : '';
    $btn = $btnText && $btnUrl ? '<a href="' . $btnUrl . '" style="display:inline-block;margin-top:16px;padding:12px 28px;background:oklch(52% .17 258);color:#fff;border-radius:999px;font-weight:700;text-decoration:none">' . $btnText . '</a>' : '';

    switch ($t) {
        case 'hero':
            return '<section ' . $bgStyle . ' style="padding:clamp(60px,10vw,120px) 0;text-align:center"><div style="max-width:800px;margin:0 auto;padding:0 20px"><h1 style="font-size:clamp(32px,5vw,52px);font-weight:800;letter-spacing:-.03em;margin-bottom:14px">' . $title . '</h1>' . ($sub ? '<p style="font-size:18px;color:var(--muted);line-height:1.8">' . $sub . '</p>' : '') . ($content ? '<p style="color:var(--muted);margin-top:10px">' . $content . '</p>' : '') . $btn . '</div></section>';
        case 'features':
            return '<section ' . $bgStyle . ' style="padding:60px 0"><div style="max-width:1000px;margin:0 auto;padding:0 20px"><h2 style="font-size:28px;font-weight:800;margin-bottom:30px;text-align:center">' . $title . '</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">' . ($content ? $content : '<p style="grid-column:1/-1;color:var(--muted);text-align:center">配置区块内容</p>') . '</div></div></section>';
        case 'cta':
            return '<section ' . $bgStyle . ' style="padding:50px 0;text-align:center"><div style="max-width:640px;margin:0 auto;padding:0 20px"><h2 style="font-size:26px;font-weight:800;margin-bottom:10px">' . $title . '</h2>' . ($content ? '<p style="color:var(--muted)">' . $content . '</p>' : '') . $btn . '</div></section>';
        case 'text':
            return '<section ' . $bgStyle . ' style="padding:50px 0"><div style="max-width:800px;margin:0 auto;padding:0 20px"><h2 style="font-size:24px;font-weight:800;margin-bottom:14px">' . $title . '</h2><div style="color:var(--muted);line-height:1.9">' . $content . '</div></div></section>';
        case 'image-text':
            return '<section ' . $bgStyle . ' style="padding:60px 0"><div style="max-width:1000px;margin:0 auto;padding:0 20px;display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center"><div>' . ($img ? '<img src="' . $img . '" alt="" style="width:100%;border-radius:16px">' : '') . '</div><div><h2 style="font-size:26px;font-weight:800;margin-bottom:12px">' . $title . '</h2>' . ($content ? '<p style="color:var(--muted);line-height:1.8">' . $content . '</p>' : '') . $btn . '</div></div></section>';
        case 'stats':
            return '<section ' . $bgStyle . ' style="padding:50px 0"><div style="max-width:800px;margin:0 auto;padding:0 20px;text-align:center"><h2 style="font-size:24px;font-weight:800;margin-bottom:24px">' . $title . '</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px">' . ($content ?: '<p style="grid-column:1/-1;color:var(--muted)">配置数据</p>') . '</div></div></section>';
        case 'testimonials':
        case 'logo-wall':
        case 'faq':
        case 'gallery':
            return '<section ' . $bgStyle . ' style="padding:50px 0"><div style="max-width:900px;margin:0 auto;padding:0 20px"><h2 style="font-size:24px;font-weight:800;margin-bottom:20px;text-align:center">' . $title . '</h2><div style="color:var(--muted);text-align:center">' . ($content ?: '区块内容') . '</div></div></section>';
        case 'form':
            return '<section ' . $bgStyle . ' style="padding:50px 0"><div style="max-width:560px;margin:0 auto;padding:0 20px"><h2 style="font-size:24px;font-weight:800;margin-bottom:20px;text-align:center">' . $title . '</h2>' . ($content ?: '<p style="color:var(--muted);text-align:center">' . ($sub ?: '配置表单 slug') . '</p>') . '</div></section>';
        case 'newsletter':
            return '<section ' . $bgStyle . ' style="padding:50px 0;text-align:center"><div style="max-width:520px;margin:0 auto;padding:0 20px"><h2 style="font-size:22px;font-weight:800;margin-bottom:12px">' . $title . '</h2>' . ($sub ? '<p style="color:var(--muted)">' . $sub . '</p>' : '') . $btn . '</div></section>';
        case 'video':
            return '<section ' . $bgStyle . ' style="padding:50px 0"><div style="max-width:800px;margin:0 auto;padding:0 20px"><h2 style="font-size:24px;font-weight:800;margin-bottom:16px;text-align:center">' . $title . '</h2>' . ($content ?: '<p style="color:var(--muted);text-align:center">配置视频地址</p>') . '</div></section>';
        default:
            return '<section ' . $bgStyle . ' style="padding:50px 0"><div style="max-width:800px;margin:0 auto;padding:0 20px"><h2>' . $title . '</h2><div style="color:var(--muted)">' . $content . '</div></div></section>';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($page['seo_title'] ?: ($page['title'] . ' | ' . $siteName))?></title>
<meta name="description" content="<?=htmlspecialchars($page['seo_desc'] ?? '')?>">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
:root{--accent:oklch(52% .17 258);--fg:oklch(22% .02 70);--muted:oklch(46% .016 70);--bg:oklch(96.5% .016 85)}
body{font-family:"Space Grotesk","PingFang SC",sans-serif;background:var(--bg);color:var(--fg);-webkit-font-smoothing:antialiased;line-height:1.6;overflow-x:clip}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260816" data-cfasync="false" data-page="home"></script>
<main>
<?php foreach ($blocks as $b) echo builder_render_block($b); ?>
</main>
<footer style="padding:40px 0;text-align:center;color:var(--muted);font-size:13px;border-top:1px solid var(--border);background:var(--bg-soft)">
  <?=htmlspecialchars($siteName)?> · OpenFlow 模块化页面
</footer>
</body>
</html>
