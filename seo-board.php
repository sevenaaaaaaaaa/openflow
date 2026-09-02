<?php
/**
 * 公开 SEO 看板 — 对外展示搜索表现
 * 访问：/seo-board （或配置的 slug）
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SeoConsole.php';

$settings = seo_console_settings();
// 校验 slug
$requestPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$slug = $settings['public_slug'] ?: 'seo-board';
if (($settings['public_enabled'] ?? false) !== true) { http_response_code(404); die('看板未开放'); }
if ($requestPath !== $slug && $requestPath !== 'seo-board.php') { http_response_code(404); die('404'); }

$cache = seo_cache();
$gsc = $cache['gsc'] ?? [];
$sumClick = array_sum(array_column($gsc, 'clicks'));
$sumImp = array_sum(array_column($gsc, 'impressions'));
$ctr = $sumImp > 0 ? round($sumClick / $sumImp * 100, 2) : 0;
$topQueries = array_slice($gsc, 0, 10);
$avgPos = count($gsc) > 0 ? round(array_sum(array_column($gsc,'position')) / count($gsc), 1) : 0;
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SEO 表现看板 | OpenFlow</title>
<meta name="robots" content="noindex">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 公开 SEO 看板独有：关键词行。指标用共享 .stats。 */
.q-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-soft);font-size:14px}
.q-row .q{flex:1;font-weight:600;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.q-row .bar{width:140px;height:8px;background:var(--bg-soft);border-radius:99px;overflow:hidden;flex:0 0 auto}
.q-row .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--ok),var(--accent))}
.q-row .n{width:70px;text-align:right;font-size:13px;color:var(--accent);font-family:var(--font-mono)}
.q-row .p{width:64px;text-align:right;font-size:12px;color:var(--faint);font-family:var(--font-mono)}
@media (max-width:640px){.q-row .bar{display:none}}
</style>
</head>
<body data-of-main>
<?php of_shell('home'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section id="top" class="reveal in" data-od-anchor data-od-id="seo-hero">
    <div class="hero-center">
      <span class="kicker">SEARCH CONSOLE · 公开看板</span>
      <h1>搜索表现看板</h1>
      <p class="lead">来自 Google Search Console 的网站搜索数据 · 近 28 天</p>
    </div>
  </section>

  <section class="sec reveal" data-od-anchor data-od-id="seo-metrics">
    <div class="stats">
      <div class="st"><div class="st-n" style="color:var(--ok)"><?=$sumClick?></div><span class="st-en">clicks</span><span class="st-t">近 28 天点击</span></div>
      <div class="st"><div class="st-n"><?=$sumImp?></div><span class="st-en">impressions</span><span class="st-t">近 28 天曝光</span></div>
      <div class="st"><div class="st-n" style="color:var(--accent)"><?=$ctr?>%</div><span class="st-en">avg ctr</span><span class="st-t">平均点击率</span></div>
      <div class="st"><div class="st-n"><?=$avgPos ?: '—'?></div><span class="st-en">avg position</span><span class="st-t">平均排名</span></div>
    </div>
  </section>

  <section class="sec reveal" data-od-anchor data-od-id="seo-queries">
    <div class="sec-head row"><div><span class="kicker">TOP QUERIES</span><h2>热门搜索词</h2></div><span class="sub">数据更新于 <?=htmlspecialchars($cache['fetched_at'] ?? '—')?></span></div>
    <div class="card" style="padding:12px 28px">
      <?php if (empty($topQueries)): ?>
      <div class="empty" style="margin:16px 0">暂无数据，稍后再来</div>
      <?php else: $maxQ = max(array_column($topQueries,'clicks')) ?: 1; ?>
      <?php foreach ($topQueries as $q): ?>
      <div class="q-row">
        <div class="q"><?=htmlspecialchars($q['query'])?></div>
        <div class="bar"><i style="width:<?=round($q['clicks']/$maxQ*100)?>%"></i></div>
        <div class="n"><b><?=$q['clicks']?></b> 点击</div>
        <div class="p">排名 <?=$q['position']?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
