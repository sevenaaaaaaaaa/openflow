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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SEO 表现看板 | OpenFlow</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<style>
  body{background:var(--bg);font-family:-apple-system,'PingFang SC','Noto Sans SC',system-ui,sans-serif}
  .metric{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:22px;text-align:center}
  .metric .val{font-size:30px;font-weight:800;margin-top:6px}
  .metric .lab{font-size:12px;color:var(--muted)}
  .q-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--bg)}
</style>
</head>
<body class="min-h-screen">
  <header class="bg-white border-b border-[var(--border)]">
    <div class="mx-auto px-5 py-3 flex items-center justify-between" style="max-width:900px">
      <a href="/" class="font-bold text-lg">OpenFlow</a>
      <span class="text-sm text-gray-600">SEO 表现看板</span>
    </div>
  </header>

  <div class="mx-auto px-5 py-8" style="max-width:900px">
    <div class="text-center py-6">
      <div style="font-size:40px">🔍</div>
      <h1 class="text-3xl font-bold mt-3">搜索表现看板</h1>
      <p class="text-gray-600 mt-2">来自 Google Search Console 的网站搜索数据</p>
    </div>

    <!-- 指标 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px">
      <div class="metric"><div class="lab">近 28 天点击</div><div class="val" style="color:var(--ok)"><?=$sumClick?></div></div>
      <div class="metric"><div class="lab">近 28 天曝光</div><div class="val"><?=$sumImp?></div></div>
      <div class="metric"><div class="lab">平均 CTR</div><div class="val" style="color:#2b5f7e"><?=$ctr?>%</div></div>
      <div class="metric"><div class="lab">平均排名</div><div class="val"><?=$avgPos ?: '—'?></div></div>
    </div>

    <!-- Top 关键词 -->
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px">
      <h2 class="font-bold text-lg mb-4">🏆 热门搜索词</h2>
      <?php if (empty($topQueries)): ?>
      <div class="text-center py-10 text-gray-400">暂无数据，稍后再来</div>
      <?php else: $maxQ = max(array_column($topQueries,'clicks')) ?: 1; ?>
      <?php foreach ($topQueries as $q): ?>
      <div class="q-row">
        <div style="flex:1;font-size:14px;font-weight:600"><?=htmlspecialchars($q['query'])?></div>
        <div style="width:120px;height:8px;background:var(--bg);border-radius:99px;overflow:hidden"><div style="height:100%;width:<?=round($q['clicks']/$maxQ*100)?>%;background:linear-gradient(90deg,var(--ok),var(--accent))"></div></div>
        <div style="width:50px;text-align:right;font-size:13px;color:#2b5f7e"><b><?=$q['clicks']?></b> 点击</div>
        <div style="width:60px;text-align:right;font-size:12px;color:var(--faint)">排名 <?=$q['position']?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <p class="text-center text-xs text-gray-400 mt-8">数据更新于 <?=htmlspecialchars($cache['fetched_at'] ?? '—')?></p>
  </div>
</body>
</html>
