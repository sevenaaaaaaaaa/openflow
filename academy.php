<?php
/**
 * Academy · 内容学院首页 — 统一 文章 / 资料下载 / 播客 / 视频教程
 *
 * v7（2026-09-01）：从 tailwind + 行内样式迁到共享 archetype（tokens.css + modules.css）。
 * 数据逻辑（楼层 / 文章 / 资料 / 播客 / 课程 / 热读 / 推荐）原样保留，只换渲染层。文案逐字相同。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CoverRenderer.php';
require_once __DIR__ . '/lib/Markdown.php';

// 页面缓存（300 秒）
if (PageCache::begin('academy', 1800)) exit;

$cfg = json_read(DATA_DIR . '/community.json');
$hotReadCount = (int)($cfg['hot_read_count'] ?? 5);
$showReport = $cfg['show_report_section'] ?? true;

// 文章（已发布）
$allArticles = get_articles_list();
$published = array_values(array_filter($allArticles, fn($a) => ($a['status'] ?? '') === 'published'));
$catNames = [];
foreach (get_categories('article') as $c) $catNames[$c['key']] = $c['name'];

// 资料下载
$allDls = json_read(DATA_DIR . '/downloads.json');
$downloads = array_values(array_filter($allDls, fn($d) => ($d['status'] ?? 'draft') === 'published'));
usort($downloads, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$downloads = array_slice($downloads, 0, 4);
$dlCats = ['whitepaper' => '白皮书', 'template' => '模板', 'report' => '报告', 'ebook' => '电子书', 'toolkit' => '工具包'];

// 播客
$pod = json_read(DATA_DIR . '/podcasts.json');
$podItems = array_values(array_filter($pod['items'] ?? [], fn($p) => ($p['status'] ?? 'published') === 'published'));
usort($podItems, fn($a, $b) => strcmp($b['pub_date'] ?? '', $a['pub_date'] ?? ''));
$podItems = array_slice($podItems, 0, 4);

// 视频教程（已发布课程）
$allCourses = json_read(DATA_DIR . '/courses/index.json');
$courses = array_values(array_filter($allCourses, fn($c) => ($c['status'] ?? 'draft') === 'published'));
usort($courses, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$courses = array_slice($courses, 0, 4);

// 热读榜
$hot = $published;
usort($hot, fn($a, $b) => (($b['views'] ?? 0) <=> ($a['views'] ?? 0)));
$hot = array_slice($hot, 0, $hotReadCount);

/* ─── 专题/聚合：把文章按「顶层分类」分组，构建专题入口 + 瀑布流 ─── */
$catTree = json_read(DATA_DIR . '/article-categories.json');   // 顶层段 → {name,icon,desc,sub}
$topCats = [];
foreach ($catTree as $top => $meta) {
    $topCats[$top] = [
        'top' => $top,
        'name' => $meta['name'] ?? ucfirst($top),
        'icon' => $meta['icon'] ?? '📚',
        'desc' => $meta['description'] ?? '',
        'count' => 0,
        'articles' => [],
    ];
}
foreach ($published as $a) {
    $top = explode('/', $a['category'] ?? '')[0] ?: 'trend';
    if (!isset($topCats[$top])) continue;
    $topCats[$top]['count']++;
    if (!isset($a['published_ts']) && !empty($a['created_at'])) $a['published_ts'] = strtotime($a['created_at']);
    $topCats[$top]['articles'][] = $a;
}
// 有内容的专题，按文章数降序
$topCats = array_values(array_filter($topCats, fn($t) => $t['count'] > 0));
usort($topCats, fn($a, $b) => $b['count'] <=> $a['count']);

// 瀑布流：取每个专题前若干篇文章，组成带分类名的列表
$catexplode = function (string $c): string {
    return explode('/', $c)[0];
};
$water = [];
foreach ($topCats as $t) { foreach (array_slice($t['articles'], 0, 4) as $a) $water[] = $a; }

// 轮播：置顶优先，其次最新
$carousel = array_values(array_filter($published, fn($a) => !empty($a['is_pinned'])));
if (empty($carousel)) { $carousel = $published; usort($carousel, fn($a,$b) => strcmp($b['created_at']??'', $a['created_at']??'')); }
$carousel = array_slice($carousel, 0, 4);

$siteName = site_config_get('site_name', 'OpenFlow');
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$baseUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>学院 · 社区知识库 | <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="芭乐派增长方法论内容库：文章 · 资料下载 · 播客 · 视频教程，从利润公式到 Agent 系统，把增长讲清楚、用起来">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260903a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260903a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260911a">
<style>
/* 学院页独有：首屏搜索框与统计行。其余全部来自 modules.css。 */
.a-card .a-excerpt{font-size:13px;color:var(--muted);line-height:1.7;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:0}
.search{display:flex;gap:10px;max-width:520px}
.search .inp{border-radius:999px;padding-left:20px}
.search .btn{border-radius:999px;flex:0 0 auto}
.hero .trust{gap:18px}
.hero .trust span{display:inline-flex;align-items:center;gap:6px}
.hero .trust svg{width:14px;height:14px}
/* ── 轮播 carousel ── */
.carousel{position:relative;overflow:hidden;border-radius:var(--r-lg);border:1px solid var(--border);background:var(--surface)}
.car-track{display:flex;transition:transform .5s cubic-bezier(.22,.61,.36,1)}
.car-slide{flex:0 0 100%;position:relative;min-height:300px}
.car-slide img{width:100%;height:300px;object-fit:cover;display:block}
.car-slide .ov{position:absolute;inset:0;background:linear-gradient(180deg,transparent 30%,color-mix(in oklab,oklch(15% 0 0),transparent 25%));display:flex;align-items:flex-end;padding:26px}
.car-slide .ov h3{color:oklch(100% 0 0);font-size:clamp(20px,2.4vw,28px);font-weight:800;letter-spacing:-.01em;max-width:640px;line-height:1.3}
.car-slide .ov .cat{display:inline-block;margin-bottom:10px;background:color-mix(in oklab,var(--accent),transparent 10%);color:oklch(100% 0 0);padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700}
.car-btn{position:absolute;top:50%;transform:translateY(-50%);width:38px;height:38px;border-radius:50%;background:color-mix(in oklab,var(--fg),transparent 70%);color:var(--fg);border:none;cursor:pointer;font-size:18px;display:grid;place-items:center;z-index:2;transition:background .2s}
.car-btn:hover{background:color-mix(in oklab,var(--fg),transparent 40%)}
.car-btn.prev{left:12px}.car-btn.next{right:12px}
.car-dots{position:absolute;bottom:12px;left:0;right:0;display:flex;justify-content:center;gap:6px;z-index:2}
.car-dots i{width:8px;height:8px;border-radius:50%;background:oklch(100% 0 0 / .5);cursor:pointer;transition:background .2s}
.car-dots i.on{background:oklch(100% 0 0)}
/* ── 专题入口 ── */
.topic-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.topic-card{display:flex;flex-direction:column;gap:8px;padding:16px;border-radius:var(--r-md);border:1px solid var(--border);background:var(--surface);transition:border-color .2s,transform .2s;text-decoration:none}
.topic-card:hover{border-color:var(--accent);transform:translateY(-2px)}
.topic-card .tc-ic{font-size:24px}
.topic-card .tc-name{font-weight:700;font-size:14.5px;color:var(--fg)}
.topic-card .tc-desc{font-size:12px;color:var(--muted);line-height:1.6}
.topic-card .tc-n{font-size:11px;color:var(--accent);font-weight:700;margin-top:auto}
/* ── 瀑布流 masonry ── */
.masonry{columns:3 300px;column-gap:16px}
.masonry .m-it{break-inside:avoid;margin-bottom:16px}
/* ── 分类聚合楼层（复用 a-card，标题行加说明） ── */
.floor-topics .sec-head .desc-l{font-size:13px;color:var(--muted);max-width:560px}
@media (max-width:760px){.masonry{columns:2 160px}.car-slide .ov h3{font-size:18px}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('articles'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏（双栏 hero：文字 + 四个入口窗） ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="academy-hero">
    <div class="hero">
      <div class="hero-copy">
        <span class="kicker">CONTENT · ACADEMY</span>
        <h1>一人公司的增长打法，<br><i class="si">都在这里</i></h1>
        <p class="lead">文章 · 资料 · 播客 · 视频，芭乐派增长方法论的完整内容库。从利润公式到 Agent 系统，把增长讲清楚、用起来。</p>
        <form class="search" action="/search" method="get" role="search">
          <input class="inp" type="search" name="q" placeholder="搜索文章、课程、资料…" aria-label="搜索">
          <button class="btn primary" type="submit">搜索</button>
        </form>
        <div class="trust">
          <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg><b><?=count($published)?></b> 篇文章</span>
          <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg><b><?=count($allDls)?></b> 份资料</span>
          <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg><b><?=count($pod['items'] ?? [])?></b> 期播客</span>
        </div>
      </div>
      <div class="hero-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">academy · 四个入口</div></div>
        <div class="win-flow">
          <a class="flow-row" href="#articles"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h13v14H4zM17 8h3v9a2 2 0 0 1-2 2h-1"/><path d="M7 9h7M7 12h7M7 15h4"/></svg></span><div><div class="ft">文章精选</div><div class="fd">增长洞察 · AI 实践 · 行业</div></div></a>
          <div class="flow-link"></div>
          <a class="flow-row" href="/downloads"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span><div><div class="ft">资料下载</div><div class="fd">白皮书 · 模板 · 报告</div></div></a>
          <div class="flow-link"></div>
          <a class="flow-row" href="/podcasts"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></span><div><div class="ft">播客视频</div><div class="fd">对谈 · 实操 · 拆解</div></div></a>
          <div class="flow-link"></div>
          <a class="flow-row" href="/tools"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4L15 12l-3-3 2.7-2.7Z"/><path d="m15 3 6 6"/></svg></span><div><div class="ft">工具箱</div><div class="fd">SEO 检查 · Meta · LTV</div></div></a>
        </div>
      </div>
    </div>
  </section>

  <!-- ══ 学习路径联动 + 分类导航 ══ -->
  <section id="nav" class="sec reveal" data-od-anchor data-od-id="academy-nav">
    <div class="strip">
      <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span>
      <div class="tx"><b>想看系统的？先走芭乐派学习路径</b><span>New-1~4 基石课免费开放 → R.B.E 训练营带你 8 周设计出增长系统</span></div>
      <a class="btn primary" href="/courses">前往课程 →</a>
    </div>
    <div class="tab-bar" role="navigation" aria-label="内容分类">
      <a class="tab-p" href="#articles" aria-selected="true"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h13v14H4zM17 8h3v9a2 2 0 0 1-2 2h-1"/><path d="M7 9h7M7 12h7M7 15h4"/></svg></span>文章</a>
      <a class="tab-p" href="#downloads"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span>资料下载</a>
      <a class="tab-p" href="#podcasts"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></span>播客</a>
      <a class="tab-p" href="#videos"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg></span>视频教程</a>
    </div>
  </section>

  <!-- ══ 精选内容 ══ -->
  <?php $docIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg>';
        $eye = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="2.8"/></svg>';
        $cover = function(array $a) use ($baseUrl) { $cv = $a['cover'] ?? ''; return $cv ? (strpos($cv,'http')===0 ? $cv : $baseUrl.'/'.ltrim($cv,'/')) : ''; };
  ?>
  <!-- ══ 轮播：置顶/最新精华 ══ -->
  <section id="featured" class="sec reveal" data-od-anchor data-od-id="academy-carousel">
    <?php if (!empty($carousel)): ?>
    <div class="carousel" id="acCarousel">
      <div class="car-track">
        <?php foreach ($carousel as $a): $cvUrl = $cover($a); ?>
        <a class="car-slide" href="/articles/<?=htmlspecialchars($a['slug'])?>">
          <?php if ($cvUrl): ?><img src="<?=htmlspecialchars($cvUrl)?>" alt="" loading="lazy"><?php else: ?><?=CoverRenderer::renderCard($a)?><?php endif; ?>
          <div class="ov"><div>
            <span class="cat"><?=htmlspecialchars($catNames[$a['category'] ?? ''] ?? '文章')?></span>
            <h3><?=htmlspecialchars($a['title'])?></h3>
          </div></div>
        </a>
        <?php endforeach; ?>
      </div>
      <button type="button" class="car-btn prev" onclick="acCarouselGo(-1)" aria-label="上一张">‹</button>
      <button type="button" class="car-btn next" onclick="acCarouselGo(1)" aria-label="下一张">›</button>
      <div class="car-dots" id="acDots"></div>
    </div>
    <?php endif; ?>
  </section>

  <!-- ══ 最新发布 → 精选 Grid ══ -->
  <?php $featured = array_slice($published, 0, 6); ?>
  <section id="articles" class="sec reveal" data-od-anchor data-od-id="academy-featured">
    <div class="sec-head row">
      <div><span class="kicker">精选内容</span><h2>最新发布的深度文章</h2></div>
      <a class="more" href="/articles">全部文章 →</a>
    </div>
    <?php if (empty($featured)): ?>
    <div class="empty">内容准备中，敬请期待</div>
    <?php else: ?>
    <div class="a-grid">
      <?php foreach ($featured as $a): $cvUrl = $cover($a); $plain = trim(strip_tags(Markdown::toHtml($a['content'] ?? ''))); $excerpt = trim((string)($a['excerpt'] ?? '')); if ($excerpt === '') $excerpt = mb_strimwidth($plain, 0, 90, '…'); $readMins = max(1, (int)ceil(mb_strlen($plain) / 400)); ?>
      <a class="a-card" href="/articles/<?=htmlspecialchars($a['slug'])?>">
        <div class="cov"><?php if ($cvUrl): ?><img src="<?=htmlspecialchars($cvUrl)?>" alt="" loading="lazy"><?php else: ?><?=CoverRenderer::renderCard($a)?><?php endif; ?></div>
        <div class="bd">
          <span class="cat"><?=htmlspecialchars($catNames[$a['category'] ?? ''] ?? '文章')?></span>
          <h3><?=htmlspecialchars($a['title'])?></h3>
          <?php if ($excerpt !== ''): ?><p class="a-excerpt"><?=htmlspecialchars($excerpt)?></p><?php endif; ?>
          <div class="meta"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?> · <?=$readMins?> 分钟 · <?=$eye?><?=$a['views'] ?? 0?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <!-- ══ 专题入口：顶级分类 → 文章列表筛选 ══ -->
  <?php if (!empty($topCats)): ?>
  <section id="topics" class="sec reveal" data-od-anchor data-od-id="academy-topics">
    <div class="sec-head row">
      <div><span class="kicker">专题</span><h2>按方向逛，更快找到想要的</h2></div>
      <a class="more" href="/articles">浏览全部 →</a>
    </div>
    <div class="topic-grid">
      <?php foreach ($topCats as $t): ?>
      <a class="topic-card" href="/articles?cat=<?=htmlspecialchars($t['top'])?>">
        <span class="tc-ic"><?=htmlspecialchars($t['icon'])?></span>
        <span class="tc-name"><?=htmlspecialchars($t['name'])?></span>
        <span class="tc-desc"><?=htmlspecialchars($t['desc'] ?: $t['name'] . '方向')?></span>
        <span class="tc-n"><?=$t['count']?> 篇</span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ══ 瀑布流：跨专题聚合 ══ -->
  <?php if (!empty($water)): ?>
  <section id="waterfall" class="sec reveal" data-od-anchor data-od-id="academy-waterfall">
    <div class="sec-head row">
      <div><span class="kicker">瀑布流</span><h2>按专题聚合，一篇接一篇</h2></div>
    </div>
    <div class="masonry">
      <?php foreach ($water as $a):
        $topa = explode('/', $a['category'] ?? '')[0];
        $topMeta = $catTree[$topa] ?? null;
        $cvUrl = $cover($a);
      ?>
      <div class="m-it">
        <a class="a-card" href="/articles/<?=htmlspecialchars($a['slug'])?>">
          <div class="cov"><?php if ($cvUrl): ?><img src="<?=htmlspecialchars($cvUrl)?>" alt="" loading="lazy"><?php else: ?><?=CoverRenderer::renderCard($a)?><?php endif; ?></div>
          <div class="bd">
            <span class="cat"><?=htmlspecialchars($catNames[$a['category'] ?? ''] ?? ($topMeta['name'] ?? '文章'))?></span>
            <h3><?=htmlspecialchars($a['title'])?></h3>
            <div class="meta"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?> · <?=$eye?><?=$a['views'] ?? 0?></div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ══ 资料 / 播客 / 视频 + 侧栏 ══ -->
  <section id="library" class="reveal" data-od-anchor data-od-id="academy-library">
  <div class="g-main-aside">
    <div>
      <div id="downloads" data-od-anchor>
        <div class="sec-head row"><div><span class="kicker">资料下载</span><h2>白皮书 · 模板 · 报告</h2></div><a class="more" href="/downloads">全部 →</a></div>
        <?php if (empty($downloads)): ?><div class="empty" style="margin-top:18px">暂无资料</div>
        <?php else: ?>
        <div class="link-grid" style="margin-top:18px;grid-template-columns:repeat(2,1fr)">
          <?php foreach ($downloads as $d): ?>
          <a class="link-it" href="/downloads"><span class="ic"><?=$docIcon?></span><span class="lt"><b><?=htmlspecialchars($d['title'])?></b><span><?=htmlspecialchars($dlCats[$d['category'] ?? ''] ?? '资料')?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div id="podcasts" data-od-anchor>
        <div class="sec-head row"><div><span class="kicker">播客</span><h2>对谈与拆解</h2></div><a class="more" href="/podcasts">全部 →</a></div>
        <?php if (empty($podItems)): ?><div class="empty" style="margin-top:18px">播客即将上线</div>
        <?php else: ?>
        <div class="link-grid" style="margin-top:18px;grid-template-columns:repeat(2,1fr)">
          <?php foreach ($podItems as $p): ?>
          <a class="link-it" href="/podcasts"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></span><span class="lt"><b><?=htmlspecialchars($p['title'] ?? '')?></b><span><?=htmlspecialchars($p['duration'] ?? ($p['pub_date'] ?? ''))?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div id="videos" data-od-anchor>
        <div class="sec-head row"><div><span class="kicker">视频教程</span><h2>跟着做一遍就会</h2></div><a class="more" href="/courses">全部 →</a></div>
        <?php if (empty($courses)): ?><div class="empty" style="margin-top:18px">暂无课程</div>
        <?php else: ?>
        <div class="a-grid" style="margin-top:18px">
          <?php foreach ($courses as $c): $ccUrl = $cover($c); ?>
          <a class="a-card" href="/course/<?=urlencode($c['slug'] ?: $c['id'])?>">
            <div class="cov"><?php if ($ccUrl): ?><img src="<?=htmlspecialchars($ccUrl)?>" alt="" loading="lazy"><?php endif; ?><div class="play"><span>▶</span></div></div>
            <div class="bd">
              <span class="cat warn"><?=htmlspecialchars($c['type'] ?? '视频教程')?></span>
              <h3><?=htmlspecialchars($c['title'])?></h3>
              <div class="meta"><?=count($c['chapters'] ?? [])?> 章 · <?=htmlspecialchars(substr($c['created_at'] ?? '', 0, 10))?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <aside>
      <div class="aside-box">
        <h3><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4.4 0 7-2.8 7-6.5 0-3.2-2-5.5-3.5-7.5C14 6 13 4 12 2c0 0-1 4-3 6-1.5 1.5-3 3.6-3 6.5C6 19.2 7.6 22 12 22Z"/></svg></span>热读榜</h3>
        <?php if (empty($hot)): ?><p>暂无数据</p>
        <?php else: ?>
        <div class="rank">
          <?php foreach ($hot as $i => $a): ?>
          <a href="/articles/<?=htmlspecialchars($a['slug'])?>"><span class="n<?=$i<3?' hot':''?>"><?=$i+1?></span><span class="t"><b><?=htmlspecialchars($a['title'])?></b><span><?=$a['views'] ?? 0?> 次阅读</span></span></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="aside-box card" style="padding:26px">
        <h3><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span>论坛</h3>
        <p>和同行一起讨论增长话题、分享经验、提问求助。真正的社区在这里。</p>
        <a class="btn ghost" href="/community">进入论坛 →</a>
      </div>

      <?php
      // 推荐偏好：GrowthEngine 已精简，推荐改为 Personalizer 默认（游客无偏好）
      $recPref = ['shape_type'=>'seedling','shape_label'=>'综合','prefs'=>['categories'=>[],'tags'=>[]]];
      $recArticles = [];
      if (class_exists('Personalizer')) {
          $pref = ['categories' => [], 'tags' => [], 'member_level' => 'guest'];
          $recArticles = Personalizer::recommendArticles($pref, 3);
      }
      if (!empty($recArticles)): ?>
      <div class="aside-box">
        <h3><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1" fill="currentColor"/></svg></span>为你推荐</h3>
        <p class="note">根据「<?=htmlspecialchars($recPref['shape_label'])?>」形态为你挑选</p>
        <div class="rank">
          <?php foreach ($recArticles as $rid => $rscore): $ra = get_article($rid); if (!$ra) continue; ?>
          <a href="/articles/<?=htmlspecialchars($ra['slug'] ?? '')?>"><span class="n">·</span><span class="t"><b><?=htmlspecialchars($ra['title'])?></b><span><?=htmlspecialchars($ra['category'] ?? '')?></span></span></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($showReport): ?>
      <div class="aside-box card" style="padding:26px">
        <h3><span class="ic"><?=$docIcon?></span>报告与白皮书</h3>
        <p>完整报告下载，掌握网站增长一手数据</p>
        <a class="btn primary" href="/downloads?cat=whitepaper">查看全部报告 →</a>
      </div>
      <?php endif; ?>
    </aside>
  </div>
  </section>

  <!-- ══ 收尾 CTA ══ -->
  <section id="next" class="reveal" data-od-anchor data-od-id="academy-cta">
    <div class="cta-band">
      <span class="kicker">芭乐派 · 学院</span>
      <h2>读到这儿了，不如直接装一个试试</h2>
      <p class="lead">方法论在学院，工具在 OpenFlow，落地在 R.B.E 训练营——三条路，最后都通向同一个地方。</p>
      <div class="cta-row">
        <a class="btn primary" href="/courses">浏览课程</a>
        <a class="btn ghost" href="/community">进入增长社区</a>
      </div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
(function(){
  var track = document.querySelector('#acCarousel .car-track');
  if (!track) return;
  var slides = track.querySelectorAll('.car-slide');
  var n = slides.length; var idx = 0;
  var dotsBox = document.getElementById('acDots');
  for (var i = 0; i < n; i++) { var d = document.createElement('i'); d.dataset.i = i; d.onclick = function(){ go(+this.dataset.i); }; dotsBox.appendChild(d); }
  function go(i){ idx = (i + n) % n; track.style.transform = 'translateX(' + (-idx*100) + '%)'; dotsBox.querySelectorAll('i').forEach(function(d, di){ d.classList.toggle('on', di===idx); }); }
  window.acCarouselGo = function(dir){ go(idx + dir); };
  var t = setInterval(function(){ go(idx + 1); }, 6000);
  var box = document.getElementById('acCarousel');
  if (box) { box.addEventListener('mouseenter', function(){ clearInterval(t); }); box.addEventListener('mouseleave', function(){ t = setInterval(function(){ go(idx+1); }, 6000); }); }
  go(0);
})();
</script>
</body>
</html>
<?php PageCache::end('academy', 1800); ?>
