<?php
/**
 * Academy · 内容学院首页 — 统一 文章 / 资料下载 / 播客 / 视频教程
 *
 * v7（2026-09-01）：从 tailwind + 行内样式迁到共享 archetype（tokens.css + modules.css）。
 * 数据逻辑（楼层 / 文章 / 资料 / 播客 / 课程 / 热读 / 推荐）原样保留，只换渲染层。文案逐字相同。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（300 秒）
if (PageCache::begin('academy', 1800)) exit;

$cfg = json_read(DATA_DIR . '/community.json');
$floors = $cfg['floors'] ?? [];
$hotReadCount = (int)($cfg['hot_read_count'] ?? 5);
$showReport = $cfg['show_report_section'] ?? true;

// 文章（已发布 + 有标题的草稿作为预告）
$allArticles = get_articles_list();
$published = array_values(array_filter($allArticles, fn($a) => ($a['status'] ?? '') === 'published'));
// 有标题的草稿（预告内容，无正文）
$drafts = array_values(array_filter($allArticles, fn($a) => ($a['status'] ?? '') !== 'published' && !empty(trim($a['title'] ?? ''))));
$catNames = [];
foreach (get_categories('article') as $c) $catNames[$c['key']] = $c['name'];

// 楼层定义（分类聚合）
$floorDefs = [
    'insight'    => ['label' => '增长洞察', 'desc' => '增长方法论 · 案例拆解 · 数据驱动', 'cats' => ['insight', 'report']],
    'ai'         => ['label' => 'AI 工具与实践', 'desc' => 'AI 工具推荐 · 设计 · 视频 · Agent · 副业', 'cats' => ['ai-tools', 'ai-design', 'ai-video', 'ai-image', 'ai-agent', 'ai-business', 'ai-trend']],
    'content'    => ['label' => '内容与 SEO 实践', 'desc' => '内容策略 · SEO · 分发 · 自动化', 'cats' => ['content', 'productivity']],
    'industry'   => ['label' => '行业实践', 'desc' => '行业深度案例 · 组织与领导力', 'cats' => ['industry', 'news']],
];
// 合并后台配置楼层
foreach ($floors as $fk => $fv) {
    if (!isset($floorDefs[$fk]) || empty($fv['categories'])) continue;
    if (isset($fv['enabled']) && !$fv['enabled']) continue;
    $floorDefs[$fk]['title'] = $fv['title'] ?? $floorDefs[$fk]['label'];
    $floorDefs[$fk]['desc'] = $fv['desc'] ?? $floorDefs[$fk]['desc'];
    $floorDefs[$fk]['cats'] = $fv['categories'];
    $floorDefs[$fk]['label'] = $floorDefs[$fk]['title'];
}

function floor_articles(array $articles, array $cats, int $limit = 4): array {
    $out = [];
    foreach ($articles as $a) {
        $aCats = array_map('strtolower', $a['tags'] ?? []);
        $aCat = strtolower($a['category'] ?? '');
        $hit = in_array($aCat, $cats) || count(array_intersect($cats, $aCats)) > 0;
        if ($hit) $out[] = $a;
    }
    return array_slice($out, 0, $limit);
}

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

$siteName = site_config_get('site_name', 'OpenFlow');
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$baseUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>学院 · 门派知识库 | <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="芭乐派增长方法论内容库：文章 · 资料下载 · 播客 · 视频教程，从利润公式到 Agent 系统，把增长讲清楚、用起来">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260901a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260901a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260901a">
<style>
/* 学院页独有：首屏搜索框与统计行。其余全部来自 modules.css。 */
.search{display:flex;gap:10px;max-width:520px}
.search .inp{border-radius:999px;padding-left:20px}
.search .btn{border-radius:999px;flex:0 0 auto}
.hero .trust{gap:18px}
.hero .trust span{display:inline-flex;align-items:center;gap:6px}
.hero .trust svg{width:14px;height:14px}
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
          <a class="flow-row" href="/category/academy/tools"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4L15 12l-3-3 2.7-2.7Z"/><path d="m15 3 6 6"/></svg></span><div><div class="ft">工具箱</div><div class="fd">SEO 检查 · Meta · LTV</div></div></a>
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
  <?php $featured = array_slice($published, 0, 3);
        $docIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg>';
        $eye = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="2.8"/></svg>';
        $cover = function(array $a) use ($baseUrl) { $cv = $a['cover'] ?? ''; return $cv ? (strpos($cv,'http')===0 ? $cv : $baseUrl.'/'.ltrim($cv,'/')) : ''; };
  ?>
  <section id="articles" class="sec reveal" data-od-anchor data-od-id="academy-featured">
    <div class="sec-head row">
      <div><span class="kicker">精选内容</span><h2>最新发布的深度文章</h2></div>
    </div>
    <?php if (empty($featured)): ?>
    <div class="empty">内容准备中，敬请期待</div>
    <?php else: ?>
    <div class="a-grid">
      <?php foreach ($featured as $a): $cvUrl = $cover($a); ?>
      <a class="a-card" href="/articles/<?=htmlspecialchars($a['slug'])?>">
        <div class="cov"><?php if ($cvUrl): ?><img src="<?=htmlspecialchars($cvUrl)?>" alt="" loading="lazy"><?php else: ?><?=$docIcon?><?php endif; ?></div>
        <div class="bd">
          <span class="cat"><?=htmlspecialchars($catNames[$a['category'] ?? ''] ?? '文章')?></span>
          <h3><?=htmlspecialchars($a['title'])?></h3>
          <div class="meta"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?> · <?=$eye?><?=$a['views'] ?? 0?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <!-- ══ 四个楼层 ══ -->
  <?php foreach ($floorDefs as $fk => $fd):
    $arts = floor_articles($published, $fd['cats']);
    $previews = floor_articles($drafts, $fd['cats'], 3);
    $hasContent = !empty($arts) || !empty($previews);
  ?>
  <section id="floor-<?=htmlspecialchars($fk)?>" class="sec reveal" data-od-anchor data-od-id="academy-floor-<?=htmlspecialchars($fk)?>">
    <div class="sec-head row">
      <div><span class="kicker"><?=htmlspecialchars($fd['label'])?></span><h2><?=htmlspecialchars($fd['desc'])?></h2></div>
      <?php if (!$hasContent): ?><span class="sub">即将上线</span><?php endif; ?>
    </div>
    <?php if (!$hasContent): ?>
    <div class="link-grid">
      <a class="link-it dashed" href="/articles/ai-bonus-opc-cold-start"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2 0-2.8-.8-.7-2-.7-3 0Z"/><path d="M12 15l-3-3c2-5.5 5-9 9-9s3 6-1 11l-5 1Z"/><path d="M9 12c-2.5 1-4 3-4.5 5"/></svg></span><span class="lt"><b>从 New-1 开始</b><span>一人公司冷启动，免费课稿</span></span></a>
      <a class="link-it dashed" href="/courses"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1" fill="currentColor"/></svg></span><span class="lt"><b>学方法论</b><span>利润公式 + 四引擎，边学边用</span></span></a>
      <a class="link-it dashed" href="/community"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span><span class="lt"><b>进门派聊聊</b><span>提问、交作业、晒增长数据</span></span></a>
    </div>
    <?php else: ?>
    <div class="a-grid">
      <?php foreach (array_merge($arts, $previews) as $a):
        $isPreview = ($a['status'] ?? '') !== 'published'; $cvUrl = $cover($a);
        $link = $isPreview ? '/academy' : '/articles/'.htmlspecialchars($a['slug']); ?>
      <a class="a-card" href="<?=$link?>">
        <div class="cov"><?php if ($cvUrl): ?><img src="<?=htmlspecialchars($cvUrl)?>" alt="" loading="lazy"><?php else: ?><?=$isPreview?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>':$docIcon?><?php endif; ?></div>
        <div class="bd">
          <span class="cat<?=$isPreview?' dim':''?>"><?=$isPreview?'即将发布':htmlspecialchars($catNames[$a['category'] ?? ''] ?? '文章')?></span>
          <h3><?=htmlspecialchars($a['title'])?></h3>
          <div class="meta"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?> · <?=$eye?><?=$a['views'] ?? 0?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

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
          <a class="a-card" href="/courses/<?=urlencode($c['slug'])?>">
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
      $recPref = class_exists('GrowthEngine') ? GrowthEngine::recommendPreferences() : ['shape_type'=>'seedling','shape_label'=>'综合','prefs'=>['categories'=>[],'tags'=>[]]];
      $recArticles = [];
      if (class_exists('Personalizer')) {
          $pref = ['categories' => array_fill_keys($recPref['prefs']['categories'] ?? [], 2), 'tags' => array_fill_keys($recPref['prefs']['tags'] ?? [], 1), 'member_level' => 'guest'];
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
        <a class="btn ghost" href="/community">进入门派社区</a>
      </div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
  <footer class="foot" data-od-id="site-footer">
    <div class="fb">
      <div class="brand"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>芭乐派 · OpenFlow</div>
      <p class="f-about">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
      <p class="note">核心能力永久开源 · 鱼与渔相结合</p>
    </div>
    <div class="fb">
      <h4>站点导航</h4>
      <a href="/product">产品</a><a href="/capability">能力</a><a href="/courses">课程</a><a href="/community">门派社区</a><a href="/about">关于我们</a>
    </div>
    <div class="fb">
      <h4>资源</h4>
      <a href="/academy">学院</a><a href="/docs">文档中心</a><a href="/downloads">资料下载</a><a href="/podcasts">播客</a><a href="/marketplace">生态市场</a>
    </div>
    <div class="fb">
      <h4>联系</h4>
      <a href="mailto:hello@openflow.dev">hello@openflow.dev</a><a href="/community">门派社区</a>
    </div>
    <div class="f-bottom"><span>© 2026 芭乐派 · OpenFlow 增长操作系统</span><?php if (function_exists('i18n_enabled') && i18n_enabled()): ?><?=i18n_switcher()?><?php endif; ?><span>帮一人公司设计 Agent 能跑的增长系统</span></div>
  </footer>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
<?php PageCache::end('academy', 1800); ?>
