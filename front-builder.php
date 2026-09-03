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

// 区块渲染器与类型表已下沉到 lib/BlockRegistry.php（三处抄了三份，其中四种类型前台根本不认）
require_once __DIR__ . '/lib/BlockRegistry.php';

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
