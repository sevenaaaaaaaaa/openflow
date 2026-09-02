<?php
/**
 * 导航站点详情页
 *
 * v7（2026-09-01）：迁到共享 archetype（reader 站点卡 + 评论部件 + link-grid 相关）。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/nav-icons.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CommentSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/comment-widget.php';

$nav = json_read(DATA_DIR . '/navigation.json');
$sites = $nav['sites'] ?? [];
$categories = $nav['categories'] ?? [];
$siteId = $_GET['site'] ?? '';
$site = null;
foreach ($sites as $s) if ($s['id'] === $siteId) { $site = $s; break; }
if (!$site) { http_response_code(404); die('站点不存在'); }

$catNames = [];
foreach ($categories as $c) $catNames[$c['id']] = $c['name'];

// 相关站点（同分类）
$related = array_values(array_filter($sites, fn($s) => $s['id'] !== $siteId && ($s['category'] ?? '') === ($site['category'] ?? '')));
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($site['name'])?>  | <?=site_config_get("site_name")?> 增长导航</title>
<meta name="robots" content="noindex">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 站点详情独有：站点头。其余全部来自 modules.css。 */
.site-hd{display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap}
.site-hd .fav{position:relative;width:64px;height:64px;border-radius:16px;display:grid;place-items:center;background:var(--accent-soft);color:var(--accent-strong);flex:0 0 auto;overflow:hidden;font-weight:800;font-size:26px;font-family:var(--font-display)}
.site-hd .fav img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;padding:12px;background:var(--surface)}
.site-hd>div{flex:1;min-width:220px;display:flex;flex-direction:column;gap:10px}
.site-hd h1{font-size:clamp(24px,3.5vw,32px);font-weight:800;letter-spacing:-.02em;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.site-hd p{font-size:15px;color:var(--muted);line-height:1.8}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('navigation'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section class="reader reveal in" data-od-id="site">
    <nav class="art-meta" aria-label="面包屑" style="margin-bottom:18px"><a href="/navigation" style="color:var(--faint)">← 增长导航</a></nav>
    <div class="card site-hd">
      <?=nav_site_icon($site)?>
      <div>
        <span class="kicker"><?=htmlspecialchars($catNames[$site['category'] ?? ''] ?? '未分类')?> · <?=($site['region']??'')==='cn'?'国内':'海外'?></span>
        <h1><?=htmlspecialchars($site['name'])?><?php if (!empty($site['featured'])): ?><span class="badge warn">编辑推荐</span><?php endif; ?></h1>
        <p><?=htmlspecialchars($site['description'] ?? '')?></p>
        <div class="cta-row"><a href="<?=htmlspecialchars($site['url'] ?? '#')?>" target="_blank" rel="noopener" class="btn primary">访问网站 →</a><button class="btn ghost" onclick="copyURL()">复制链接</button></div>
      </div>
    </div>
  </section>
  <section class="reader reveal" data-od-id="site-reviews"><?php fc_comment_widget('site', $site['id'], ['title' => '用户点评', 'rating' => true]); ?></section>
  <?php if ($related): ?>
  <section class="reader reveal" data-od-id="site-related">
    <div class="sec-head row"><div><span class="kicker">相关推荐</span><h2>同类站点</h2></div></div>
    <div class="link-grid" style="grid-template-columns:repeat(2,1fr);margin-top:12px">
      <?php foreach (array_slice($related, 0, 4) as $r): ?>
      <a class="link-it top" href="/navigation/<?=urlencode($r['id'])?>"><span class="lt"><b><?=htmlspecialchars($r['name'])?></b><span><?=htmlspecialchars(mb_substr($r['description'] ?? '',0,60))?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
function copyURL() {
  var url = <?=json_encode($site['url'] ?? '')?>;
  navigator.clipboard.writeText(url).then(function() { alert('链接已复制'); });
}
</script>
</body>
</html>
