<?php
/**
 * 导航站门户 — 左侧导航 + 首屏搜索 + 热搜 + Banner + 楼层
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CommentSystem.php';

$nav = json_read(DATA_DIR . '/navigation.json');
$categories = $nav['categories'] ?? [];
$sites = $nav['sites'] ?? [];
$hotSearches = $nav['hot_searches'] ?? [];
$banner = $nav['banner'] ?? [];

// 分类排序
usort($categories, fn($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

$catNames = []; $catIcons = [];
foreach ($categories as $c) { $catNames[$c['id']] = $c['name']; $catIcons[$c['id']] = $c['icon'] ?? '🌐'; }

// 筛选
$region = $_GET['region'] ?? 'all';
$cat = $_GET['cat'] ?? '';
$q = trim($_GET['q'] ?? '');
$filtered = $sites;
if ($region !== 'all') $filtered = array_values(array_filter($filtered, fn($s) => ($s['region'] ?? 'cn') === $region));
if ($cat) $filtered = array_values(array_filter($filtered, fn($s) => ($s['category'] ?? '') === $cat));
if ($q) $filtered = array_values(array_filter($filtered, fn($s) => mb_strpos(($s['name'] ?? '') . ($s['description'] ?? ''), $q) !== false));

// 按分类分组
$byCat = [];
foreach ($filtered as $s) { $byCat[$s['category'] ?? ''] = $byCat[$s['category'] ?? ''] ?? []; $byCat[$s['category'] ?? ''][] = $s; }
// 分类内排序（featured 优先，然后按名字）
foreach ($byCat as &$list) usort($list, fn($a, $b) => (empty($a['featured']) <=> empty($b['featured'])) ?: strcmp($a['name'] ?? '', $b['name'] ?? ''));

// 各站点评分汇总（大众点评：平均分 + 点评数）
$ratingMap = [];
foreach ($sites as $s) $ratingMap[$s['id']] = comment_rating_summary('site', $s['id']);
unset($list);

$featured = array_values(array_filter($sites, fn($s) => !empty($s['featured'])));
$bannerSite = null;
foreach ($sites as $s) if (($banner['site_id'] ?? '') === $s['id']) { $bannerSite = $s; break; }

// 热搜：点击跳转到搜索
$siteBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>增长导航 | 优秀网站增长·SEO·AI 运营工具</title>
<meta name="description" content="收录国内外优秀的网站增长、SEO、AI 运营工具与学习资源，一站直达高质量增长资源。">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260830a" defer></script>
<style>
  body{background:var(--bg);font-family:-apple-system,'PingFang SC','Noto Sans SC',system-ui,sans-serif}
  .site-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;transition:.15s;text-decoration:none;color:inherit;display:block}
  .site-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(30,30,30,.08);border-color:var(--accent)}
  .cat-nav-item{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;font-size:14px;color:var(--muted);cursor:pointer;transition:.12s;text-decoration:none}
  .cat-nav-item:hover{background:var(--surface);color:var(--fg)}
  .cat-nav-item.active{background:var(--accent);color:var(--on-accent);font-weight:600}
  .hot-tag{display:inline-block;padding:4px 12px;border-radius:999px;background:var(--surface);border:1px solid var(--border);font-size:12px;color:#2b5f7e;cursor:pointer;transition:.12s}
  .hot-tag:hover{border-color:var(--accent);background:var(--accent-soft);color:var(--accent)}
  .site-meta{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--faint);margin-top:6px}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260826b" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-6" style="max-width:1200px">
    <!-- 首屏：搜索 + 热搜 -->
    <div class="text-center py-8" style="background:linear-gradient(160deg,var(--accent-strong),var(--accent));border-radius:20px;color:var(--surface);margin-bottom:24px">
      <div style="font-size:40px">🧭</div>
      <h1 class="text-3xl font-bold mt-3">优秀增长工具导航</h1>
      <p class="text-[#cbd5e1] mt-2">收录国内外网站增长、SEO、AI 运营工具 · 共 <?=count($sites)?> 个优质资源</p>
      <form class="mt-6 mx-auto flex max-w-lg gap-2" style="max-width:480px" onsubmit="return navSearch(event)">
        <input type="text" id="navQ" value="<?=htmlspecialchars($q)?>" placeholder="搜索网站、关键词…" style="flex:1;padding:12px 18px;border-radius:999px;border:none;font-size:15px;outline:none">
        <button class="rounded-full px-6 py-2.5 font-bold" style="background:var(--accent-soft);color:var(--accent);border:none">搜索</button>
      </form>
      <?php if ($hotSearches): ?>
      <div class="mt-4 flex gap-2 justify-center flex-wrap">
        <span class="text-sm text-[#94a3b8] mr-1">🔥 热搜：</span>
        <?php foreach (array_slice($hotSearches, 0, 6) as $h): ?>
        <span class="hot-tag" onclick="navHot('<?=htmlspecialchars($h)?>')"><?=htmlspecialchars($h)?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Banner 首推 -->
    <?php if ($bannerSite || $featured): $bs = $bannerSite ?: ($featured[0] ?? null); if ($bs): ?>
    <a href="/navigation/<?=urlencode($bs['id'])?>" class="site-card" style="display:flex;gap:16px;align-items:center;margin-bottom:24px;padding:20px;background:linear-gradient(135deg,var(--surface),#fdfce9);border:2px solid var(--accent)">
      <div style="font-size:36px"><?=$catIcons[$bs['category'] ?? ''] ?? '🌐'?></div>
      <div style="flex:1">
        <div class="text-xs font-bold" style="color:#5b7a00"><?=htmlspecialchars($banner['title'] ?? '🏆 编辑首推')?></div>
        <div class="font-bold text-lg mt-1"><?=htmlspecialchars($bs['name'])?></div>
        <div class="text-sm text-gray-600"><?=htmlspecialchars($bs['description'] ?? '')?></div>
      </div>
      <span class="rounded-full px-5 py-2 font-bold text-sm" style="background:var(--accent);color:var(--on-accent)">立即访问 →</span>
    </a>
    <?php endif; endif; ?>

    <div style="display:grid;grid-template-columns:200px 1fr;gap:24px" class="nav-grid">
      <!-- 左侧导航 -->
      <aside style="position:sticky;top:20px;align-self:start" class="hidden sm:block">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px">
          <div class="px-2 py-1 font-bold text-sm mb-1">🏷️ 全部分类</div>
          <a class="cat-nav-item <?=!$cat?'active':''?>" href="/navigation?region=<?=$region?>">🌐 全部</a>
          <?php foreach ($categories as $c): ?>
          <a class="cat-nav-item <?=$cat===$c['id']?'active':''?>" href="?cat=<?=urlencode($c['id'])?>&region=<?=$region?>"><?=$catIcons[$c['id']]??'🌐'?> <?=htmlspecialchars($c['name'])?></a>
          <?php endforeach; ?>
          <div style="border-top:1px solid var(--bg);margin:8px 0;padding-top:8px">
            <div class="px-2 py-1 font-bold text-sm">🌍 地区</div>
            <a class="cat-nav-item <?=$region==='all'?'active':''?>" href="?region=all">🌐 全部</a>
            <a class="cat-nav-item <?=$region==='cn'?'active':''?>" href="?region=cn">🇨🇳 国内</a>
            <a class="cat-nav-item <?=$region==='intl'?'active':''?>" href="?region=intl">🌍 海外</a>
          </div>
        </div>
      </aside>

      <!-- 楼层内容 -->
      <main>
        <?php if ($q): ?>
        <div class="mb-4 text-sm text-gray-600">搜索「<strong><?=htmlspecialchars($q)?></strong>」找到 <?=count($filtered)?> 个结果 <a href="/navigation" class="text-[#2b5f7e]">清除</a></div>
        <?php endif; ?>

        <?php if (empty($byCat)): ?>
        <div class="text-center py-16 text-gray-600">该分类下暂无站点</div>
        <?php else: foreach ($byCat as $cid => $list): ?>
        <div class="mb-8">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
            <span style="font-size:22px"><?=$catIcons[$cid] ?? '🌐'?></span>
            <h2 class="font-bold text-xl"><?=htmlspecialchars($catNames[$cid] ?? '未分类')?></h2>
            <span class="text-sm text-gray-400"><?=count($list)?> 个</span>
            <?php if ($cid): ?><a href="?cat=<?=urlencode($cid)?>" class="ml-auto text-sm text-[#2b5f7e]">查看全部 →</a><?php endif; ?>
          </div>
          <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
            <?php foreach ($list as $s): ?>
            <a href="/navigation/<?=urlencode($s['id'])?>" class="site-card">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <div style="width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:18px;background:var(--bg)"><?=$s['region']==='cn'?'🇨🇳':'🌍'?></div>
                <div class="font-bold"><?=htmlspecialchars($s['name'])?><?php if (!empty($s['featured'])): ?> <span style="font-size:10px;color:#b45309">⭐</span><?php endif; ?></div>
              </div>
              <div class="text-sm text-gray-600 line-clamp-2"><?=htmlspecialchars($s['description'] ?? '')?></div>
              <?php $rm = $ratingMap[$s['id']] ?? ['avg' => 0, 'count' => 0]; if (!empty($rm['count'])): ?>
              <div style="margin-top:6px;font-size:12px">
                <span style="color:var(--warn);letter-spacing:1px"><?=str_repeat('★', max(0, min(5, (int)round($rm['avg']))))?><?=str_repeat('☆', max(0, 5 - (int)round($rm['avg'])))?></span>
                <span style="color:#b45309;font-weight:600"> <?=number_format($rm['avg'], 1)?></span>
                <span style="color:var(--faint)"> · <?=$rm['count']?> 条点评</span>
              </div>
              <?php endif; ?>
              <div class="site-meta">
                <span><?=htmlspecialchars($catNames[$s['category'] ?? ''] ?? '')?></span>
                <span>·</span>
                <span class="truncate"><?=htmlspecialchars($s['url'] ?? '')?></span>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </main>
    </div>
  </div>

<script>
function navSearch(e) {
  e.preventDefault();
  var q = document.getElementById('navQ').value.trim();
  location.href = '/navigation' + (q ? '?q=' + encodeURIComponent(q) : '');
  return false;
}
function navHot(h) {
  location.href = '/navigation?q=' + encodeURIComponent(h);
}
</script>
</body>
</html>
