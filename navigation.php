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

$catNames = []; $catIcons = []; $catNamesEn = [];
foreach ($categories as $c) { $catNames[$c['id']] = $c['name']; $catNamesEn[$c['id']] = $c['name_en'] ?? ''; $catIcons[$c['id']] = $c['icon'] ?? '🌐'; }

// 站点 logo：有则用，无则自动取 favicon（DuckDuckGo 国内可达性好）
function nav_logo(array $s): string {
    if (!empty($s['logo'])) return $s['logo'];
    $host = parse_url($s['url'] ?? '', PHP_URL_HOST);
    return $host ? 'https://icons.duckduckgo.com/ip3/' . $host . '.ico' : '';
}
// favicon 加载失败兜底（隐藏 img，显示 🌐）——注意属性用单引号避免双引号嵌套
function nav_logo_fallback(): string {
    return "onerror='this.onerror=null;this.style.display=\"none\";this.parentElement.textContent=\"\xF0\x9F\x8C\x90\";this.parentElement.style.fontSize=\"18px\"'";
}
// 多语言：当前为 en 且有名则用英文名
function nav_name(array $d, string $key = 'name', string $keyEn = 'name_en'): string {
    $locale = function_exists('i18n_current') ? i18n_current() : 'zh-CN';
    if (strpos($locale, 'en') === 0 && !empty($d[$keyEn])) return $d[$keyEn];
    return $d[$key] ?? '';
}

// 筛选
$region = $_GET['region'] ?? 'all';
$cat = $_GET['cat'] ?? '';
$q = trim($_GET['q'] ?? '');
$tag = trim($_GET['tag'] ?? '');
// 只展示已上架站点
$sites = array_values(array_filter($sites, fn($s) => ($s['status'] ?? 'published') === 'published'));
// 全局按权重排序（weight 大在前）
usort($sites, fn($a, $b) => ((int)($b['weight'] ?? 0)) <=> ((int)($a['weight'] ?? 0)));
// 卡片标签黑名单：无区分度的高频通用标签（渲染时过滤，避免每张卡都是 #AI #开源 影响排版）
$navTagBlacklist = ['AI', '开源', '创作', '精选', 'Agent', '商业', '自动化', '效率', '工具', '平台', '国内', '小众', '网站', '应用', '工具集', '开源工具'];
// 热门标签聚合（按出现次数）
$tagCount = [];
foreach ($sites as $s) foreach (($s['tags'] ?? []) as $t) { $tagCount[$t] = ($tagCount[$t] ?? 0) + 1; }
arsort($tagCount);
$filtered = $sites;
if ($region !== 'all') $filtered = array_values(array_filter($filtered, fn($s) => ($s['region'] ?? 'cn') === $region));
if ($cat) $filtered = array_values(array_filter($filtered, fn($s) => ($s['category'] ?? '') === $cat));
if ($tag) $filtered = array_values(array_filter($filtered, fn($s) => in_array($tag, $s['tags'] ?? [], true)));
if ($q) $filtered = array_values(array_filter($filtered, fn($s) => mb_strpos(($s['name'] ?? '') . ($s['description'] ?? '') . implode(' ', $s['tags'] ?? []), $q) !== false));

// 按分类分组
$byCat = [];
foreach ($filtered as $s) { $byCat[$s['category'] ?? ''] = $byCat[$s['category'] ?? ''] ?? []; $byCat[$s['category'] ?? ''][] = $s; }
// 分类内排序（featured 优先，然后按权重）
foreach ($byCat as &$list) usort($list, fn($a, $b) => (empty($a['featured']) <=> empty($b['featured'])) ?: (((int)($b['weight'] ?? 0)) <=> ((int)($a['weight'] ?? 0))));

// 各站点评分汇总（大众点评：平均分 + 点评数）
$ratingMap = [];
foreach ($sites as $s) $ratingMap[$s['id']] = comment_rating_summary('site', $s['id']);
unset($list);

$featured = array_values(array_filter($sites, fn($s) => !empty($s['featured'])));
$bannerSite = null;
foreach ($sites as $s) if (($banner['site_id'] ?? '') === $s['id']) { $bannerSite = $s; break; }

// 热搜：点击跳转到搜索
$siteBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');

// ── 首页懒加载：每子分类首屏 8 个，其余滚动加载（大幅减小首屏 HTML） ──
$PER = 8;
$isHome = ($region === 'all' && $cat === '' && $tag === '' && $q === '');
$gridIdx = 0;
$lazyGroups = [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>增长导航 | 优秀网站增长·SEO·AI 运营工具</title>
<meta name="description" content="收录国内外优秀的网站增长、SEO、AI 运营工具与学习资源，一站直达高质量增长资源。">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:-apple-system,'PingFang SC','Noto Sans SC',system-ui,sans-serif}
  .site-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;transition:.15s;text-decoration:none;color:inherit;display:block}
  .site-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(30,30,30,.08);border-color:var(--accent)}
  .cat-nav-item{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;font-size:14px;color:var(--muted);cursor:pointer;transition:.12s;text-decoration:none}
  .cat-nav-item:hover{background:var(--surface);color:var(--fg)}
  .cat-nav-item.active{background:var(--accent);color:var(--on-accent);font-weight:600}
  .hot-tag{display:inline-block;padding:4px 12px;border-radius:999px;background:var(--surface);border:1px solid var(--border);font-size:12px;color:var(--muted);cursor:pointer;transition:.12s;box-shadow:0 1px 3px rgba(30,30,30,.05)}
  .hot-tag:hover{border-color:var(--accent);background:var(--accent-soft);color:var(--accent);transform:translateY(-1px)}
  .site-meta{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--faint);margin-top:6px}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260816" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-6" style="max-width:1200px">
    <!-- 首屏：精致 Hero -->
    <div style="position:relative;padding:52px 32px 44px;border-radius:24px;margin-bottom:28px;background:linear-gradient(150deg,var(--surface) 0%,rgba(221,255,14,.06) 45%,rgba(56,189,248,.10) 100%);border:1px solid var(--border);overflow:hidden">
      <!-- 装饰：光晕 + 网格 -->
      <div style="position:absolute;top:-80px;right:-60px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(56,189,248,.18),transparent 70%)"></div>
      <div style="position:absolute;bottom:-100px;left:-40px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(221,255,14,.15),transparent 70%)"></div>
      <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(30,30,30,.04) 1px,transparent 1px);background-size:22px 22px;pointer-events:none"></div>

      <div style="position:relative;text-align:center">
        <div style="display:inline-flex;align-items:center;gap:8px;padding:6px 16px;border-radius:999px;background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.2);font-size:12.5px;color:var(--accent);font-weight:600">
          <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--ok)"></span>
          AI 时代 · 开源 · 自动化 · 增长工具集
        </div>
        <h1 style="font-size:42px;font-weight:800;letter-spacing:-.02em;margin:20px 0 12px;line-height:1.15;background:linear-gradient(135deg,var(--fg),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">优秀增长工具导航</h1>
        <p style="font-size:15px;color:var(--muted);max-width:560px;margin:0 auto">收录国内外 <b style="color:var(--fg)"><?=count($sites)?></b> 个增长、SEO、AI 与开源工具 · 一站式直达高质量资源</p>

        <!-- 搜索 -->
        <form class="mt-7 mx-auto flex" style="max-width:540px;background:var(--surface);border:1.5px solid var(--border);border-radius:999px;padding:6px;box-shadow:0 8px 30px rgba(30,30,30,.08)" onsubmit="return navSearch(event)">
          <span style="display:grid;place-items:center;padding:0 14px;color:var(--faint)"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg></span>
          <input type="text" id="navQ" value="<?=htmlspecialchars($q)?>" placeholder="搜索网站、关键词、标签…" style="flex:1;padding:10px 6px;border:none;outline:none;font-size:15px;background:transparent">
          <button class="rounded-full px-6 font-bold" style="background:var(--accent);color:var(--on-accent);border:none;padding:10px 24px;font-size:14px">搜索</button>
        </form>

        <!-- 快捷分类 + 热搜 -->
        <div class="mt-6 flex justify-center gap-2 flex-wrap">
          <?php foreach (array_slice($categories, 0, 7) as $c): ?>
          <a href="?cat=<?=urlencode($c['id'])?>" style="padding:5px 14px;border-radius:999px;border:1px solid var(--border);background:var(--surface);font-size:12.5px;color:var(--muted);text-decoration:none"><?=$c['icon']?> <?=htmlspecialchars(nav_name($c))?></a>
          <?php endforeach; ?>
          <button onclick="openSubmit()" style="padding:5px 14px;border-radius:999px;border:1px dashed var(--accent);background:transparent;font-size:12.5px;color:var(--accent);cursor:pointer">➕ 提交收录</button>
        </div>
        <?php if ($hotSearches): ?>
        <div class="mt-4 flex gap-2 justify-center flex-wrap items-center">
          <span class="text-sm" style="color:var(--faint)">🔥 热搜</span>
          <?php foreach (array_slice($hotSearches, 0, 5) as $h): ?>
          <span class="hot-tag" onclick="navHot('<?=htmlspecialchars($h)?>')"><?=htmlspecialchars($h)?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Banner 首推 -->
    <?php if ($bannerSite || $featured): $bs = $bannerSite ?: ($featured[0] ?? null); if ($bs): ?>
    <a href="/navigation-site.php?site=<?=urlencode($bs['id'])?>" class="site-card" style="display:flex;gap:16px;align-items:center;margin-bottom:24px;padding:20px;background:linear-gradient(135deg,var(--surface),#fdfce9);border:2px solid var(--accent)">
      <div style="font-size:36px"><?=$catIcons[$bs['category'] ?? ''] ?? '🌐'?></div>
      <div style="flex:1">
        <div class="text-xs font-bold" style="color:#5b7a00"><?=htmlspecialchars($banner['title'] ?? '🏆 编辑首推')?></div>
        <div class="font-bold text-lg mt-1"><?=htmlspecialchars(nav_name($bs))?></div>
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
          <a class="cat-nav-item <?=!$cat?'active':''?>" href="navigation.php?region=<?=$region?>">🌐 全部</a>
          <?php foreach ($categories as $c): ?>
          <a class="cat-nav-item <?=$cat===$c['id']?'active':''?>" href="?cat=<?=urlencode($c['id'])?>&region=<?=$region?>"><?=$catIcons[$c['id']]??'🌐'?> <?=htmlspecialchars(nav_name($c))?></a>
          <?php endforeach; ?>
          <div style="border-top:1px solid var(--bg);margin:8px 0;padding-top:8px">
            <div class="px-2 py-1 font-bold text-sm">🌍 地区</div>
            <a class="cat-nav-item <?=$region==='all'?'active':''?>" href="?region=all<?=$cat?'&cat='.urlencode($cat):''?><?=$tag?'&tag='.urlencode($tag):''?>">🌐 全部</a>
            <a class="cat-nav-item <?=$region==='cn'?'active':''?>" href="?region=cn<?=$cat?'&cat='.urlencode($cat):''?><?=$tag?'&tag='.urlencode($tag):''?>">🇨🇳 国内</a>
            <a class="cat-nav-item <?=$region==='intl'?'active':''?>" href="?region=intl<?=$cat?'&cat='.urlencode($cat):''?><?=$tag?'&tag='.urlencode($tag):''?>">🌍 海外</a>
          </div>
          <?php if (!empty($tagCount)): ?>
          <div style="border-top:1px solid var(--bg);margin:8px 0;padding-top:8px">
            <div class="px-2 py-1 font-bold text-sm">🏷 热门标签</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;padding:2px 4px">
              <?php foreach (array_slice(array_keys($tagCount), 0, 12) as $t): ?>
              <a href="?tag=<?=urlencode($t)?><?=$cat?'&cat='.urlencode($cat):''?><?=$region!=='all'?'&region='.$region:''?>" style="display:inline-block;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding:3px 10px;border-radius:999px;font-size:11px;border:1px solid <?=$tag===$t?'var(--accent)':'var(--border)'?>;<?=$tag===$t?'background:var(--accent);color:var(--on-accent)':''?>"><?=htmlspecialchars($t)?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </aside>

      <!-- 楼层内容 -->
      <main>
        <?php if ($q): ?>
        <div class="mb-4 text-sm text-gray-600">搜索「<strong><?=htmlspecialchars($q)?></strong>」找到 <?=count($filtered)?> 个结果 <a href="navigation.php" class="text-[#2b5f7e]">清除</a></div>
        <?php endif; ?>

        <?php if (empty($byCat)): ?>
        <div class="text-center py-16 text-gray-600">该分类下暂无站点</div>
        <?php else: foreach ($byCat as $cid => $list): ?>
        <div class="mb-8">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
            <span style="font-size:22px"><?=$catIcons[$cid] ?? '🌐'?></span>
            <h2 class="font-bold text-xl"><?=htmlspecialchars(nav_name(['name'=>$catNames[$cid] ?? '未分类','name_en'=>$catNamesEn[$cid] ?? '']))?></h2>
            <span class="text-sm text-gray-400"><?=count($list)?> 个</span>
            <?php if ($cid): ?><a href="?cat=<?=urlencode($cid)?>" class="ml-auto text-sm text-[#2b5f7e]">查看全部 →</a><?php endif; ?>
          </div>
          <?php
          // 子分类分组
          $subGroups = [];
          foreach ($list as $s) { $sub = $s['sub'] ?? '全部'; $subGroups[$sub][] = $s; }
          foreach ($subGroups as $subName => $subList): ?>
          <div style="font-size:12px;font-weight:700;color:var(--faint);margin:12px 0 8px">▍<?=htmlspecialchars(nav_name(['name'=>$subName,'name_en'=>($subGroups[$subName][0]['sub_en'] ?? '')]))?></div>
          <div class="grid gap-4" id="ng-<?=$gridIdx?>" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
            <?php $gridIdx++; foreach (array_slice($subList, 0, $isHome ? $PER : PHP_INT_MAX) as $s): ?>
            <a href="/navigation-site.php?site=<?=urlencode($s['id'])?>" class="site-card">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <div style="width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:18px;background:var(--bg);overflow:hidden"><?php $logo = nav_logo($s); if ($logo): ?><img src="<?=htmlspecialchars($logo)?>" alt="" <?=nav_logo_fallback()?> style="width:100%;height:100%;object-fit:cover"><?php else: ?><?=$s['region']==='cn'?'🇨🇳':'🌍'?><?php endif; ?></div>
                <div class="font-bold"><?=htmlspecialchars(nav_name($s))?><?php if (!empty($s['featured'])): ?> <span style="font-size:10px;color:#b45309">⭐</span><?php endif; ?></div>
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
              <?php $cardTags = array_values(array_filter($s['tags'] ?? [], fn($t) => trim((string)$t) !== '' && !in_array((string)$t, $navTagBlacklist, true))); ?>
              <?php if (!empty($cardTags)): ?>
              <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:6px">
                <?php foreach (array_slice($cardTags, 0, 2) as $t): ?>
                <a href="?tag=<?=urlencode($t)?>" style="font-size:10px;color:var(--faint);max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">#<?=htmlspecialchars($t)?></a>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
          <?php
          // 懒加载：超出首屏的部分入列（滚动加载）
          if ($isHome && count($subList) > $PER):
            $lazyGroups[] = ['grid' => 'ng-' . ($gridIdx - 1), 'sites' => array_map(function ($s) use ($ratingMap) {
                $rm = $ratingMap[$s['id']] ?? ['avg' => 0, 'count' => 0];
                $host = parse_url($s['url'] ?? '', PHP_URL_HOST);
                return [
                    'id' => $s['id'], 'name' => $s['name'] ?? '', 'name_en' => $s['name_en'] ?? '',
                    'desc' => $s['description'] ?? '', 'url' => $s['url'] ?? '',
                    'logo' => !empty($s['logo']) ? $s['logo'] : ($host ? 'https://icons.duckduckgo.com/ip3/' . $host . '.ico' : ''),
                    'region' => $s['region'] ?? 'cn', 'featured' => !empty($s['featured']),
                    'cat' => $s['category'] ?? '', 'sub' => $s['sub'] ?? '',
                    'tags' => array_values(array_filter($s['tags'] ?? [], fn($t) => trim((string)$t) !== '')),
                    'rating' => ['avg' => $rm['avg'], 'count' => $rm['count']],
                ];
            }, array_slice($subList, $PER))];
          endif;
          endforeach; // subGroups ?>
        </div>
        <?php endforeach; endif; ?>
        <?php if ($isHome && !empty($lazyGroups)): ?>
        <div id="navSentinel" style="height:1px"></div>
        <script>window.NAV_LAZY = <?=json_encode($lazyGroups, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)?>;</script>
        <?php endif; ?>
      </main>
    </div>
  </div>

<script>
function navSearch(e) {
  e.preventDefault();
  var q = document.getElementById('navQ').value.trim();
  location.href = 'navigation.php' + (q ? '?q=' + encodeURIComponent(q) : '');
  return false;
}
function navHot(h) {
  location.href = 'navigation.php?q=' + encodeURIComponent(h);
}
/* 首页滚动懒加载 */
if (window.NAV_LAZY) {
  var __navBl = <?=json_encode($navTagBlacklist, JSON_UNESCAPED_UNICODE)?>;
  function __nh(s) { return String(s ?? '').replace(/[&<>]/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]); }); }
  function __na(s) { return String(s ?? '').replace(/"/g, '&quot;'); }
  function __navCard(s) {
    var rm = s.rating || {}; var star = '';
    if (rm.count) {
      var on = Math.max(0, Math.min(5, Math.round(rm.avg))), off = 5 - on;
      star = '<div style="margin-top:6px;font-size:12px"><span style="color:var(--warn);letter-spacing:1px">' + '★'.repeat(on) + '☆'.repeat(off) + '</span><span style="color:#b45309;font-weight:600"> ' + Number(rm.avg).toFixed(1) + '</span><span style="color:var(--faint)"> · ' + rm.count + ' 条点评</span></div>';
    }
    var tags = (s.tags || []).filter(function (t) { return String(t).trim() !== '' && __navBl.indexOf(String(t)) === -1; }).slice(0, 2).map(function (t) {
      return '<a href="navigation.php?tag=' + encodeURIComponent(t) + '" style="font-size:10px;color:var(--faint);max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">#' + __nh(t) + '</a>';
    }).join('');
    var logo = s.logo ? '<img src="' + __na(s.logo) + '" alt="" onerror="this.onerror=null;this.style.display=\'none\';this.parentElement.textContent=\'🌐\';this.parentElement.style.fontSize=\'18px\'" style="width:100%;height:100%;object-fit:cover">' : (s.region === 'cn' ? '🇨🇳' : '🌍');
    var el = document.createElement('a');
    el.href = '/navigation-site.php?site=' + encodeURIComponent(s.id);
    el.className = 'site-card';
    el.innerHTML = '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px"><div style="width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:18px;background:var(--bg);overflow:hidden">' + logo + '</div><div class="font-bold">' + __nh(s.name) + (s.featured ? ' <span style="font-size:10px;color:#b45309">⭐</span>' : '') + '</div></div>' + '<div class="text-sm text-gray-600 line-clamp-2">' + __nh(s.desc) + '</div>' + star + '<div class="site-meta"><span></span><span>·</span><span class="truncate">' + __nh(s.url) + '</span></div>' + (tags ? '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">' + tags + '</div>' : '');
    return el;
  }
  var __navCur = 0, __navBusy = false;
  function __navMore() {
    if (__navCur >= window.NAV_LAZY.length) { var se = document.getElementById('navSentinel'); if (se) se.style.display = 'none'; return; }
    if (__navBusy) return; __navBusy = true;
    window.NAV_LAZY.slice(__navCur, __navCur + 3).forEach(function (g) { var grid = document.getElementById(g.grid); if (grid) g.sites.forEach(function (s) { grid.appendChild(__navCard(s)); }); });
    __navCur += 3; __navBusy = false;
  }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (en) { if (en[0].isIntersecting) __navMore(); }, { rootMargin: '900px' }).observe(document.getElementById('navSentinel'));
  } else { window.addEventListener('scroll', function () { if (window.innerHeight + window.scrollY > document.body.scrollHeight - 800) __navMore(); }); }
  __navMore();
}
/* 提交收录弹窗 */
function openSubmit() {
  document.getElementById('navSubmitModal').style.display = 'flex';
}
function closeSubmit() {
  document.getElementById('navSubmitModal').style.display = 'none';
}
function submitSite() {
  var f = document.getElementById('navSubmitForm');
  var msg = document.getElementById('navSubmitMsg');
  var fd = new FormData();
  fd.append('name', f.querySelector('[name=name]').value.trim());
  fd.append('url', f.querySelector('[name=url]').value.trim());
  fd.append('description', f.querySelector('[name=description]').value.trim());
  fd.append('category', f.querySelector('[name=category]').value);
  fd.append('contact', f.querySelector('[name=contact]').value.trim());
  msg.style.color = 'var(--muted)'; msg.textContent = '提交中…';
  fetch('/api/nav-submit.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)'; msg.textContent = d.message || d.error; if (d.ok) setTimeout(closeSubmit, 1500); })
    .catch(function(){ msg.textContent = '网络异常'; msg.style.color = 'var(--danger)'; });
}
</script>

<!-- 提交收录弹窗 -->
<div id="navSubmitModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);align-items:center;justify-content:center" onclick="if(event.target===this)closeSubmit()">
  <div style="background:var(--surface);border-radius:18px;padding:26px;width:90%;max-width:440px">
    <div style="display:flex;align-items:center;margin-bottom:16px"><h3 style="font-size:17px;font-weight:700;margin:0">➕ 提交站点收录</h3><button onclick="closeSubmit()" style="margin-left:auto;border:none;background:none;font-size:18px;cursor:pointer;color:var(--muted)">✕</button></div>
    <form id="navSubmitForm">
      <div style="margin-bottom:10px"><label style="font-size:12px;color:var(--muted)">站点名称 *</label><input type="text" name="name" placeholder="如：AI 导航站" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;margin-top:4px"></div>
      <div style="margin-bottom:10px"><label style="font-size:12px;color:var(--muted)">网址 *</label><input type="text" name="url" placeholder="https://…" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;margin-top:4px"></div>
      <div style="margin-bottom:10px"><label style="font-size:12px;color:var(--muted)">一句话介绍</label><input type="text" name="description" placeholder="这个站点是做什么的" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;margin-top:4px"></div>
      <div style="margin-bottom:10px"><label style="font-size:12px;color:var(--muted)">分类</label>
        <select name="category" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;margin-top:4px">
          <option value="">— 选择 —</option>
          <?php foreach ($categories as $c): ?><option value="<?=htmlspecialchars($c['id'])?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:14px"><label style="font-size:12px;color:var(--muted)">你的联系方式 <span style="color:var(--faint)">(选填，用于审核沟通)</span></label><input type="text" name="contact" placeholder="邮箱或微信" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;margin-top:4px"></div>
      <button type="button" onclick="submitSite()" style="width:100%;padding:11px;border:none;border-radius:999px;background:var(--accent);color:var(--on-accent);font-weight:700;font-size:14px;cursor:pointer">提交（审核后上架）</button>
      <div id="navSubmitMsg" style="margin-top:10px;font-size:12.5px;text-align:center"></div>
    </form>
  </div>
</div>
</body>
</html>
