<?php
/**
 * 文章列表页 — hero + 精选 + 排行 + 专题 + 分类楼层
 *
 * v7（2026-09-01）：从 tailwind + 行内样式迁到共享 archetype；分类的 hex 渐变色去掉，图标统一 accent-soft 底。
 * 数据逻辑原样保留；renderCard 改为输出共享 .a-card。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CoverRenderer.php';
require_once __DIR__ . '/lib/I18n.php';

$siteName = site_config_get('site_name', 'OpenFlow');
$allArticles = get_articles();
usort($allArticles, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$articles = array_values(array_filter($allArticles, fn($a) => ($a['status'] ?? '') === 'published'));
$total = count($articles);

// 分类配置
$catLabels = [
    'ai-create'    => ['icon' => '🎨', 'name' => 'AI 创作',   'desc' => '设计 · 视频 · 图片 · 写作 · 音频'],
    'content'      => ['icon' => '🎨', 'name' => 'AI 创作',   'desc' => '设计 · 视频 · 图片 · 写作 · 音频'],
    'agent'        => ['icon' => '🤖', 'name' => 'Agent 生态', 'desc' => 'Agent 工具 · Skills · MCP'],
    'ai'           => ['icon' => '🤖', 'name' => 'Agent 生态', 'desc' => 'Agent 工具 · Skills · MCP'],
    'trend'        => ['icon' => '🔮', 'name' => '行业趋势',   'desc' => '热点 · 观点 · 对比 · 入门指南'],
    'insight'      => ['icon' => '🔮', 'name' => '行业趋势',   'desc' => '热点 · 观点 · 对比 · 入门指南'],
    'ai-code'      => ['icon' => '💻', 'name' => 'AI 编程',   'desc' => 'Agent 开发 · IDE · DevOps · API'],
    'ai-marketing' => ['icon' => '📣', 'name' => 'AI 营销',   'desc' => 'SEO · 社媒 · 邮件 · 分发'],
    'ai-ops'       => ['icon' => '⚙️', 'name' => 'AI 运营',   'desc' => '自动化 · 工作流 · 效率工具'],
    'ai-sell'      => ['icon' => '💰', 'name' => 'AI 销售',   'desc' => 'CRM · 转化漏斗 · 变现'],
    'ai-data'      => ['icon' => '📊', 'name' => '数据分析',   'desc' => '分析 · 可视化 · A/B 测试'],
    'ai-user'      => ['icon' => '👤', 'name' => '用户运营',   'desc' => '画像 · 社区 · 留存 · 个性化'],
    'ai-build'     => ['icon' => '🏗️', 'name' => 'AI 建站',   'desc' => '无代码 · 落地页 · 电商 · CMS'],
];

// 合并分类
$catMerge = [];
foreach ($catLabels as $slug => $cl) { $catMerge[$slug] = $cl['name']; }
$byCat = [];
foreach ($articles as $a) {
    $cat = explode('/', $a['category'] ?? '')[0] ?: 'trend';
    $displayCat = $catMerge[$cat] ?? $cat;
    $key = $cat;
    foreach ($catMerge as $s => $name) { if ($name === $displayCat) { $key = $s; break; } }
    $byCat[$key][] = $a;
}
uksort($byCat, fn($a, $b) => count($byCat[$b]) <=> count($byCat[$a]));

$latest = array_slice($articles, 0, 12);
$featured = array_slice($articles, 0, 3); // 精选 top3
$ranking = array_slice($articles, 0, 8);  // 排行 top8

// 专题标签（去重）
$allTags = [];
foreach ($articles as $a) { foreach ($a['tags'] ?? [] as $t) { $t = trim($t); if ($t !== '') $allTags[$t] = ($allTags[$t] ?? 0) + 1; } }
arsort($allTags);
$topTags = array_slice(array_keys($allTags), 0, 12);

// 渲染卡片
function renderCard(array $a, array $catLabels, $size = 'normal'): string {
    $slug = htmlspecialchars($a['slug'] ?? '');
    $title = htmlspecialchars(mb_substr($a['title'] ?? '', 0, 60));
    $excerpt = htmlspecialchars(mb_substr(strip_tags($a['excerpt'] ?? ''), 0, $size === 'large' ? 120 : 80));
    $date = substr($a['created_at'] ?? '', 0, 10);
    $catSlug = explode('/', $a['category'] ?? '')[0] ?: 'trend';
    $cl = $catLabels[$catSlug] ?? ['icon' => '', 'name' => $catSlug];
    $coverHtml = CoverRenderer::renderCardCover($a);
    $cls = $size === 'large' ? 'a-card feat' : 'a-card';
    return '<a href="/articles/' . $slug . '" class="' . $cls . '">'
        . '<div class="cov">' . $coverHtml . '</div>'
        . '<div class="bd"><span class="cat">' . htmlspecialchars($cl['name']) . '</span>'
        . '<h3>' . $title . '</h3>'
        . ($excerpt ? '<p>' . $excerpt . '</p>' : '')
        . '<div class="meta">' . $date . '</div></div></a>';
}

?>
<!doctype html>
<html lang="<?=htmlspecialchars(i18n_current())?>" dir="<?=i18n_is_rtl()?'rtl':'ltr'?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>内容学院 · 文章精选 | <?=$siteName?></title>
<meta name="description" content="增长实践、AI 工具评测、行业洞察 — 共 <?=$total?> 篇深度文章">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260902a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260902a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260902a">
<style>
/* 文章列表独有：精选大卡横排 + 卡片摘要。其余全部来自 modules.css。 */
.feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.a-card.feat h3{font-size:18px}
.a-card p{font-size:13px;color:var(--muted);line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.floor+.floor{margin-top:clamp(40px,5vw,64px)}
.floor .a-grid{margin-top:18px}
.floor-more{margin-top:14px;text-align:right}
@media (max-width:1080px){.feat-grid{grid-template-columns:1fr 1fr}}
@media (max-width:640px){.feat-grid{grid-template-columns:1fr}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('articles'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section id="top" class="reveal in" data-od-anchor data-od-id="articles-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">全站 <?=$total?> 篇深度文章</span>
      <h1>内容<i class="si">学院</i></h1>
      <p class="lead">增长实践、AI 工具评测、行业洞察 — 一篇篇帮你把增长系统跑起来</p>
      <div class="trust"><span class="dot"></span><?=$total?> 篇文章 · <?=count($byCat)?> 个分类 · <?=count($allTags)?> 个标签</div>
    </div>
  </section>

  <section id="featured" class="sec reveal" data-od-anchor data-od-id="articles-featured">
    <div class="sec-head row"><div><span class="kicker">精选文章</span><h2>编辑推荐</h2></div></div>
    <?php if (empty($featured)): ?><div class="empty">内容准备中，敬请期待</div><?php else: ?>
    <div class="feat-grid"><?php foreach ($featured as $a): ?><?=renderCard($a, $catLabels, 'large')?><?php endforeach; ?></div>
    <?php endif; ?>
  </section>

  <section id="ranking-topics" class="sec reveal" data-od-anchor data-od-id="articles-ranking">
    <div class="g-main-aside">
      <div>
        <div class="sec-head row"><div><span class="kicker">热门排行</span><h2>TOP 8</h2></div></div>
        <div class="rank" style="margin-top:10px">
          <?php foreach ($ranking as $i => $a): $catSlug = explode('/', $a['category'] ?? '')[0] ?: 'trend'; $cl = $catLabels[$catSlug] ?? ['icon' => '', 'name' => $catSlug]; ?>
          <a href="/articles/<?=htmlspecialchars($a['slug'] ?? '')?>"><span class="n<?=$i<3?' hot':''?>"><?=$i+1?></span><span class="t"><b><?=htmlspecialchars(mb_substr($a['title'] ?? '', 0, 50))?></b><span><?=$cl['icon']?> <?=htmlspecialchars($cl['name'])?></span></span></a>
          <?php endforeach; ?>
        </div>
      </div>
      <aside>
        <div class="aside-box">
          <h3>专题入口</h3>
          <p class="note"><?=count($topTags)?> 个热门话题</p>
          <div class="tags"><?php foreach ($topTags as $t): ?><a href="?tag=<?=urlencode($t)?>" style="display:inline-flex;gap:4px"><span># <?=htmlspecialchars($t)?></span><span style="opacity:.6"><?=$allTags[$t]?></span></a><?php endforeach; ?></div>
        </div>
        <div class="aside-box">
          <h3>分类</h3>
          <nav class="rank" aria-label="分类">
            <?php $seenNames = []; foreach ($catLabels as $cat => $cl): if (!isset($byCat[$cat]) || in_array($cl['name'], $seenNames)) continue; $seenNames[] = $cl['name']; ?>
            <a href="#floor-<?=$cat?>"><span class="n" style="font-size:16px"><?=$cl['icon']?></span><span class="t"><b><?=htmlspecialchars($cl['name'])?></b><span><?=count($byCat[$cat])?> 篇</span></span></a>
            <?php endforeach; ?>
          </nav>
        </div>
      </aside>
    </div>
  </section>

  <section id="floors" class="sec reveal" data-od-anchor data-od-id="articles-floors">
    <?php foreach ($byCat as $cat => $catArticles):
      $cl = $catLabels[$cat] ?? ['icon' => '', 'name' => $cat, 'desc' => ''];
      $show = array_slice($catArticles, 0, 8); $hasMore = count($catArticles) > 8; ?>
    <div class="floor" id="floor-<?=$cat?>" data-od-anchor>
      <div class="sec-head row">
        <div><span class="kicker"><?=$cl['icon']?> <?=htmlspecialchars($cl['name'])?> · <?=count($catArticles)?> 篇</span><h2><?=htmlspecialchars($cl['desc'] ?? '')?: htmlspecialchars($cl['name'])?></h2></div>
        <?php if ($hasMore): ?><a class="more" href="?cat=<?=urlencode($cat)?>">查看全部 <?=htmlspecialchars($cl['name'])?> →</a><?php endif; ?>
      </div>
      <div class="a-grid"><?php foreach ($show as $a): ?><?=renderCard($a, $catLabels)?><?php endforeach; ?></div>
    </div>
    <?php endforeach; ?>
  </section>

  <footer class="foot" data-od-id="site-footer">
    <div class="fb">
      <div class="brand"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>芭乐派 · OpenFlow</div>
      <p class="f-about">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
      <p class="note">核心能力永久开源 · 鱼与渔相结合</p>
    </div>
    <div class="fb"><h4>站点导航</h4><a href="/product">产品</a><a href="/capability">能力</a><a href="/courses">课程</a><a href="/academy">学院</a><a href="/community">门派社区</a><a href="/about">关于我们</a></div>
    <div class="fb"><h4>资源</h4><a href="/docs">文档中心</a><a href="/downloads">资料下载</a><a href="/podcasts">播客</a><a href="/marketplace">生态市场</a></div>
    <div class="fb"><h4>联系</h4><a href="mailto:hello@openflow.dev">hello@openflow.dev</a><a href="/community">门派社区</a></div>
    <div class="f-bottom"><span>© 2026 芭乐派 · OpenFlow 增长操作系统</span><?php if (function_exists('i18n_enabled') && i18n_enabled()): ?><?=i18n_switcher()?><?php endif; ?><span>帮一人公司设计 Agent 能跑的增长系统</span></div>
  </footer>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
