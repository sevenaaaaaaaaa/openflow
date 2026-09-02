<?php
/**
 * 导航站门户 — 首屏搜索 + 热搜 + 编辑首推 + 分类侧栏 + 楼层
 *
 * v7（2026-09-01）：从 tailwind + 行内样式（含 #2b5f7e/#b45309 等调色板外硬编码色）迁到共享 archetype。
 * 数据与筛选逻辑原样保留。站点分类图标来自数据（emoji），保留；界面自身的装饰性 emoji 去掉。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CommentSystem.php';
require_once __DIR__ . '/includes/nav-icons.php';

$nav = json_read(DATA_DIR . '/navigation.json');
$categories = $nav['categories'] ?? [];
$sites = $nav['sites'] ?? [];
$hotSearches = $nav['hot_searches'] ?? [];
$banner = $nav['banner'] ?? [];

// 分类排序
usort($categories, fn($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

$catNames = []; $catIcons = [];
foreach ($categories as $c) { $catNames[$c['id']] = $c['name']; $catIcons[$c['id']] = nav_cat_icon((string)$c['id'], (string)$c['name']); }
$allIcon = nav_cat_icon('all', '');

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
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>增长导航 | 优秀网站增长·SEO·AI 运营工具</title>
<meta name="description" content="收录国内外优秀的网站增长、SEO、AI 运营工具与学习资源，一站直达高质量增长资源。">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260902a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260902a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260902a">
<style>
/* 导航站独有：搜索框、热搜、分类侧栏项、站点卡评分。其余全部来自 modules.css。 */
.search{display:flex;gap:10px;width:min(560px,100%);margin:0 auto}
.search .inp{border-radius:999px;padding-left:22px}
.search .btn{border-radius:999px;flex:0 0 auto}
.hots{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;align-items:center}
.hots .lbl{font-family:var(--font-mono);font-size:12px;color:var(--faint);letter-spacing:.06em;text-transform:uppercase;margin-right:4px}
.cat-nav{display:flex;flex-direction:column;gap:2px}
.cat-nav a{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:10px;font-size:14px;color:var(--muted);transition:background .15s,color .15s}
.cat-nav a:hover{background:var(--hover);color:var(--fg)}
.cat-nav a.active{background:var(--accent-soft);color:var(--accent-strong);font-weight:600}
.cat-nav .em{width:16px;height:16px;flex:0 0 auto;color:var(--faint)}.cat-nav .em svg{width:16px;height:16px}.cat-nav a.active .em,.cat-nav a:hover .em{color:var(--accent)}
.fl-h{display:flex;align-items:center;gap:10px}.fl-h .em{width:30px;height:30px;border-radius:9px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center}.fl-h .em svg{width:16px;height:16px}
.g-main-aside.aside-left{grid-template-columns:minmax(0,220px) minmax(0,1fr)}
.g-main-aside.aside-left>aside{position:sticky;top:calc(var(--chrome-h) + 24px)}
.site-card{display:flex;flex-direction:column;gap:8px;padding:18px 20px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);backdrop-filter:blur(16px) saturate(150%);transition:transform .3s var(--ease-spring),box-shadow .3s,border-color .3s}
.site-card:hover{transform:translateY(-3px);border-color:var(--border-strong);box-shadow:var(--shadow)}
.site-card .hd{display:flex;align-items:center;gap:10px}
.fav{position:relative;width:38px;height:38px;border-radius:11px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;flex:0 0 auto;overflow:hidden;font-weight:800;font-size:15px;font-family:var(--font-display)}
.fav img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;padding:8px;background:var(--surface)}
.site-card .hd b{font-size:15px;font-weight:700}
.site-card .hd .badge{margin-left:auto}
.site-card p{font-size:13.5px;color:var(--muted);line-height:1.7;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.site-card .rating{font-size:12.5px;display:flex;align-items:center;gap:6px}
.site-card .rating .stars{color:var(--warn);letter-spacing:.1em}
.site-card .rating b{color:var(--warn)}
.site-card .meta{margin-top:auto;font-size:11.5px;color:var(--faint);font-family:var(--font-mono);display:flex;gap:6px;min-width:0}
.site-card .meta span:last-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.floor+.floor{margin-top:clamp(36px,5vw,56px)}
.site-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(250px,1fr))}
@media (max-width:1080px){.g-main-aside.aside-left{grid-template-columns:1fr}.g-main-aside.aside-left>aside{position:static}.cat-nav{flex-direction:row;flex-wrap:wrap}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('navigation'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏：搜索 + 热搜 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="nav-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">增长导航</span>
      <h1>一人公司<i class="si">真正在用</i>的工具</h1>
      <p class="lead">收录国内外网站增长、SEO、AI 运营工具 · 共 <?=count($sites)?> 个优质资源</p>
      <form class="search" onsubmit="return navSearch(event)" role="search">
        <input class="inp" type="text" id="navQ" value="<?=htmlspecialchars($q)?>" placeholder="搜索网站、关键词…" aria-label="搜索网站">
        <button class="btn primary" type="submit">搜索</button>
      </form>
      <?php if ($hotSearches): ?>
      <div class="hots">
        <span class="lbl">热搜</span>
        <?php foreach (array_slice($hotSearches, 0, 6) as $h): ?>
        <button type="button" class="pill neutral" onclick="navHot('<?=htmlspecialchars($h)?>')"><?=htmlspecialchars($h)?></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ══ 编辑首推 ══ -->
  <?php if ($bannerSite || $featured): $bs = $bannerSite ?: ($featured[0] ?? null); if ($bs): ?>
  <section id="banner" class="sec reveal" data-od-anchor data-od-id="nav-banner">
    <a class="strip" href="/navigation/<?=urlencode($bs['id'])?>">
      <span class="ic"><?=$catIcons[$bs['category'] ?? ''] ?? $allIcon?></span>
      <div class="tx"><span class="kicker" style="font-size:11px"><?=htmlspecialchars($banner['title'] ?? '编辑首推')?></span><b><?=htmlspecialchars($bs['name'])?></b><span><?=htmlspecialchars($bs['description'] ?? '')?></span></div>
      <span class="btn primary">立即访问 →</span>
    </a>
  </section>
  <?php endif; endif; ?>

  <!-- ══ 分类侧栏 + 楼层 ══ -->
  <section id="sites" class="sec reveal" data-od-anchor data-od-id="nav-sites">
    <div class="g-main-aside aside-left">
      <aside>
        <div class="aside-box">
          <h3>全部分类</h3>
          <nav class="cat-nav" aria-label="分类">
            <a class="<?=!$cat?'active':''?>" href="/navigation?region=<?=$region?>"><span class="em"><?=$allIcon?></span>全部</a>
            <?php foreach ($categories as $c): ?>
            <a class="<?=$cat===$c['id']?'active':''?>" href="?cat=<?=urlencode($c['id'])?>&region=<?=$region?>"><span class="em"><?=$catIcons[$c['id']] ?? $allIcon?></span><?=htmlspecialchars($c['name'])?></a>
            <?php endforeach; ?>
          </nav>
        </div>
        <div class="aside-box">
          <h3>地区</h3>
          <nav class="cat-nav" aria-label="地区">
            <a class="<?=$region==='all'?'active':''?>" href="?region=all"><span class="em"><?=nav_region_icon('all')?></span>全部</a>
            <a class="<?=$region==='cn'?'active':''?>" href="?region=cn"><span class="em"><?=nav_region_icon('cn')?></span>国内</a>
            <a class="<?=$region==='intl'?'active':''?>" href="?region=intl"><span class="em"><?=nav_region_icon('intl')?></span>海外</a>
          </nav>
        </div>
      </aside>
      <div>
        <?php if ($q): ?>
        <p class="note">搜索「<strong><?=htmlspecialchars($q)?></strong>」找到 <?=count($filtered)?> 个结果 · <a href="/navigation" style="color:var(--accent)">清除</a></p>
        <?php endif; ?>
        <?php if (empty($byCat)): ?>
        <div class="empty">该分类下暂无站点</div>
        <?php else: foreach ($byCat as $cid => $list): ?>
        <div class="floor">
          <div class="sec-head row">
            <div><span class="kicker"><?=count($list)?> 个</span><h2 class="fl-h"><span class="em"><?=$catIcons[$cid] ?? $allIcon?></span><?=htmlspecialchars($catNames[$cid] ?? '未分类')?></h2></div>
            <?php if ($cid): ?><a class="more" href="?cat=<?=urlencode($cid)?>">查看全部 →</a><?php endif; ?>
          </div>
          <div class="site-grid" style="margin-top:18px">
            <?php foreach ($list as $s): $rm = $ratingMap[$s['id']] ?? ['avg' => 0, 'count' => 0]; ?>
            <a href="/navigation/<?=urlencode($s['id'])?>" class="site-card">
              <div class="hd"><?=nav_site_icon($s)?><b><?=htmlspecialchars($s['name'])?></b><?php if (!empty($s['featured'])): ?><span class="badge warn">推荐</span><?php endif; ?></div>
              <p><?=htmlspecialchars($s['description'] ?? '')?></p>
              <?php if (!empty($rm['count'])): ?>
              <div class="rating"><span class="stars"><?=str_repeat('★', max(0, min(5, (int)round($rm['avg']))))?><?=str_repeat('☆', max(0, 5 - (int)round($rm['avg'])))?></span><b><?=number_format($rm['avg'], 1)?></b><span class="note"><?=$rm['count']?> 条点评</span></div>
              <?php endif; ?>
              <div class="meta"><span><?=htmlspecialchars($catNames[$s['category'] ?? ''] ?? '')?></span><span>·</span><span><?=htmlspecialchars($s['url'] ?? '')?></span></div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
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
