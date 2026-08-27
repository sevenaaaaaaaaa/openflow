<?php
/**
 * 文章列表页 — 丰富视觉版（hero + 精选 + 排行 + 楼层）
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
    'ai-create'    => ['icon' => '🎨', 'name' => 'AI 创作',   'desc' => '设计 · 视频 · 图片 · 写作 · 音频', 'gradient' => 'linear-gradient(135deg,#7c3aed,#a78bfa)', 'color' => '#7c3aed'],
    'content'      => ['icon' => '🎨', 'name' => 'AI 创作',   'desc' => '设计 · 视频 · 图片 · 写作 · 音频', 'gradient' => 'linear-gradient(135deg,#7c3aed,#a78bfa)', 'color' => '#7c3aed'],
    'agent'        => ['icon' => '🤖', 'name' => 'Agent 生态', 'desc' => 'Agent 工具 · Skills · MCP',       'gradient' => 'linear-gradient(135deg,#4f46e5,#818cf8)', 'color' => '#4f46e5'],
    'ai'           => ['icon' => '🤖', 'name' => 'Agent 生态', 'desc' => 'Agent 工具 · Skills · MCP',       'gradient' => 'linear-gradient(135deg,#4f46e5,#818cf8)', 'color' => '#4f46e5'],
    'trend'        => ['icon' => '🔮', 'name' => '行业趋势',   'desc' => '热点 · 观点 · 对比 · 入门指南', 'gradient' => 'linear-gradient(135deg,#ea580c,#fb923c)', 'color' => '#ea580c'],
    'insight'      => ['icon' => '🔮', 'name' => '行业趋势',   'desc' => '热点 · 观点 · 对比 · 入门指南', 'gradient' => 'linear-gradient(135deg,#ea580c,#fb923c)', 'color' => '#ea580c'],
    'ai-code'      => ['icon' => '💻', 'name' => 'AI 编程',   'desc' => 'Agent 开发 · IDE · DevOps · API', 'gradient' => 'linear-gradient(135deg,#2563eb,#60a5fa)', 'color' => '#2563eb'],
    'ai-marketing' => ['icon' => '📣', 'name' => 'AI 营销',   'desc' => 'SEO · 社媒 · 邮件 · 分发',       'gradient' => 'linear-gradient(135deg,#059669,#34d399)', 'color' => '#059669'],
    'ai-ops'       => ['icon' => '⚙️', 'name' => 'AI 运营',   'desc' => '自动化 · 工作流 · 效率工具',    'gradient' => 'linear-gradient(135deg,#7c3aed,#c084fc)', 'color' => '#7c3aed'],
    'ai-sell'      => ['icon' => '💰', 'name' => 'AI 销售',   'desc' => 'CRM · 转化漏斗 · 变现',          'gradient' => 'linear-gradient(135deg,#d97706,#fbbf24)', 'color' => '#d97706'],
    'ai-data'      => ['icon' => '📊', 'name' => '数据分析',   'desc' => '分析 · 可视化 · A/B 测试',       'gradient' => 'linear-gradient(135deg,#0d9488,#5eead4)', 'color' => '#0d9488'],
    'ai-user'      => ['icon' => '👤', 'name' => '用户运营',   'desc' => '画像 · 社区 · 留存 · 个性化',    'gradient' => 'linear-gradient(135deg,#e11d48,#fb7185)', 'color' => '#e11d48'],
    'ai-build'     => ['icon' => '🏗️', 'name' => 'AI 建站',   'desc' => '无代码 · 落地页 · 电商 · CMS',   'gradient' => 'linear-gradient(135deg,#0891b2,#67e8f9)', 'color' => '#0891b2'],
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
    $cl = $catLabels[$catSlug] ?? ['icon' => '📦', 'name' => $catSlug, 'color' => 'var(--accent)'];
    $coverHtml = CoverRenderer::renderCardCover($a);

    if ($size === 'large') {
        return '<a href="/articles/' . $slug . '" style="display:grid;grid-template-columns:1fr 1fr;border-radius:16px;overflow:hidden;background:var(--surface);border:1px solid var(--border);text-decoration:none;color:inherit;transition:.2s" onmouseover="this.style.boxShadow=\'0 12px 40px rgba(0,0,0,.12)\';this.style.transform=\'translateY(-2px)\'" onmouseout="this.style.boxShadow=\'none\';this.style.transform=\'none\'">'
            . '<div style="aspect-ratio:4/3;overflow:hidden">' . $coverHtml . '</div>'
            . '<div style="padding:24px;display:flex;flex-direction:column;gap:8px;justify-content:center">'
            . '<div style="font-size:11px;font-weight:700;color:' . $cl['color'] . ';text-transform:uppercase;letter-spacing:.06em">' . $cl['icon'] . ' ' . $cl['name'] . '</div>'
            . '<div style="font-size:20px;font-weight:800;line-height:1.3;letter-spacing:-.02em">' . $title . '</div>'
            . '<div style="font-size:13px;color:var(--muted);line-height:1.6">' . $excerpt . '</div>'
            . '<div style="font-size:12px;color:var(--faint);margin-top:auto">' . $date . ' · Gana</div>'
            . '</div></a>';
    }
    return '<a href="/articles/' . $slug . '" class="card-hover" style="text-decoration:none;color:inherit">'
        . $coverHtml
        . '<div style="padding:14px 16px;display:flex;flex-direction:column;gap:6px;flex:1">'
        . '<div style="font-size:11px;font-weight:600;color:' . $cl['color'] . '">' . $cl['icon'] . ' ' . $cl['name'] . '</div>'
        . '<div style="font-weight:700;font-size:15px;line-height:1.35">' . $title . '</div>'
        . ($excerpt ? '<div style="font-size:12.5px;color:var(--muted);line-height:1.5">' . $excerpt . '</div>' : '')
        . '<div style="font-size:11px;color:var(--faint);margin-top:auto;padding-top:8px">' . $date . ' · Gana</div>'
        . '</div></a>';
}

?>
<!DOCTYPE html>
<html lang="<?=htmlspecialchars(i18n_current())?>" dir="<?=i18n_is_rtl()?'rtl':'ltr'?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>内容学院 · 文章精选 | <?=$siteName?></title>
<meta name="description" content="增长实践、AI 工具评测、行业洞察 — 共 <?=$total?> 篇深度文章">
<link rel="stylesheet" href="/assets/tailwind-build.css">
<script src="/assets/inject.js" defer></script>
<script src="/assets/site-shell.js?v=20260826b" defer></script>
<style>
  .hero{position:relative;overflow:hidden;border-radius:24px;padding:52px 36px 44px;background:linear-gradient(150deg,var(--surface) 0%,rgba(221,255,14,.06) 45%,rgba(56,189,248,.10) 100%);border:1px solid var(--border);margin-bottom:28px}
  .hero .glow-r{position:absolute;top:-80px;right:-60px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(56,189,248,.18),transparent 70%)}
  .hero .glow-l{position:absolute;bottom:-100px;left:-40px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(221,255,14,.15),transparent 70%)}
  .hero .grid-pattern{position:absolute;inset:0;background-image:radial-gradient(rgba(30,30,30,.04) 1px,transparent 1px);background-size:22px 22px;pointer-events:none}
  .hero-inner{position:relative;text-align:center}
  .hero-badge{display:inline-flex;align-items:center;gap:8px;padding:6px 16px;border-radius:999px;background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.2);font-size:12.5px;color:var(--accent);font-weight:600}
  .hero-badge .dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--ok)}
  .hero h1{font-size:clamp(32px,5vw,42px);font-weight:800;letter-spacing:-.02em;margin:20px 0 12px;line-height:1.15;background:linear-gradient(135deg,var(--fg),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
  .hero p{font-size:15px;color:var(--muted);max-width:560px;margin:0 auto}
  .hero-stats{display:flex;gap:24px;margin-top:18px;justify-content:center}
  .hero-stat{display:flex;flex-direction:column;gap:2px;align-items:center}
  .hero-stat .val{font-size:28px;font-weight:800;font-family:var(--font-mono)}
  .hero-stat .lbl{font-size:12px;color:var(--faint);text-transform:uppercase;letter-spacing:.06em}
  .section-title{display:flex;align-items:center;gap:10px;margin-bottom:18px;padding-bottom:12px;border-bottom:2px solid var(--border);position:relative}
  .section-title h2{font-size:18px;font-weight:800;letter-spacing:-.01em}
  .section-title .count{font-size:12px;color:var(--faint);margin-left:auto;font-family:var(--font-mono)}
  .featured-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:32px}
  .ranking-list{display:flex;flex-direction:column;gap:1px;background:var(--border);border-radius:14px;overflow:hidden;margin-bottom:32px}
  .ranking-item{display:flex;align-items:center;gap:14px;padding:12px 16px;background:var(--surface);text-decoration:none;color:inherit;transition:.15s}
  .ranking-item:hover{background:var(--hover)}
  .ranking-num{width:28px;height:28px;border-radius:8px;display:grid;place-items:center;font-size:12px;font-weight:800;font-family:var(--font-mono);background:var(--surface-2);color:var(--faint)}
  .ranking-item:nth-child(1) .ranking-num{background:#f59e0b;color:#fff}
  .ranking-item:nth-child(2) .ranking-num{background:#94a3b8;color:#fff}
  .ranking-item:nth-child(3) .ranking-num{background:#cd7c2f;color:#fff}
  .topic-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;margin-bottom:32px}
  .topic-tag{display:flex;align-items:center;gap:10px;padding:14px 16px;border-radius:12px;background:var(--surface);border:1px solid var(--border);text-decoration:none;color:inherit;font-size:13px;font-weight:600;transition:.15s}
  .topic-tag:hover{border-color:var(--accent);background:var(--accent-soft);color:var(--accent)}
  .cat-nav{position:sticky;top:72px;z-index:10;display:flex;gap:8px;flex-wrap:wrap;padding:12px 0;margin-bottom:8px}
  .cat-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:999px;font-size:13px;font-weight:600;border:1.5px solid var(--border);background:var(--surface);color:var(--muted);text-decoration:none;transition:.15s;cursor:pointer}
  .cat-chip:hover{border-color:var(--accent);color:var(--accent)}
  .cat-chip.active{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
  .floor{margin-bottom:36px}
  .floor-head{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border)}
  .floor-icon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;font-size:18px;flex:0 0 auto}
  .floor-title{font-size:18px;font-weight:800;letter-spacing:-.01em}
  .floor-desc{font-size:12.5px;color:var(--muted);margin-left:4px}
  .floor-count{font-size:12px;color:var(--faint);margin-left:auto;font-family:var(--font-mono)}
  .floor-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
  .floor-more{display:flex;justify-content:center;margin-top:16px}
  .floor-more a{padding:8px 20px;border-radius:999px;font-size:13px;font-weight:600;background:var(--surface);border:1px solid var(--border);color:var(--muted);text-decoration:none;transition:.15s}
  .floor-more a:hover{border-color:var(--accent);color:var(--accent)}
</style>
</head>
<body>

<div style="padding:20px clamp(16px,4vw,40px) 64px;padding-top:calc(var(--chrome-h,56px) + 20px)">

  <!-- Hero Banner（导航页风格：浅渐变 + 光晕 + 网格 + 渐变文字） -->
  <div class="hero">
    <div class="glow-r"></div>
    <div class="glow-l"></div>
    <div class="grid-pattern"></div>
    <div class="hero-inner">
      <div class="hero-badge"><span class="dot"></span> 全站 <?=$total?> 篇深度文章</div>
      <h1>内容学院</h1>
      <p>增长实践、AI 工具评测、行业洞察 — 一篇篇帮你把增长系统跑起来</p>
      <div class="hero-stats">
        <div class="hero-stat"><span class="val"><?=$total?></span><span class="lbl">文章</span></div>
        <div class="hero-stat"><span class="val"><?=count($byCat)?></span><span class="lbl">分类</span></div>
        <div class="hero-stat"><span class="val"><?=count($allTags)?></span><span class="lbl">标签</span></div>
      </div>
    </div>
  </div>

  <!-- 精选文章（top 3 大卡）-->
  <div class="section-title">
    <span style="font-size:20px">⭐</span>
    <h2>精选文章</h2>
    <span class="count">编辑推荐</span>
  </div>
  <div class="featured-grid">
    <?php foreach ($featured as $a): ?>
    <?=renderCard($a, $catLabels, 'large')?>
    <?php endforeach; ?>
  </div>

  <!-- 排行榜（top 8）-->
  <div class="section-title">
    <span style="font-size:20px">🏆</span>
    <h2>热门排行</h2>
    <span class="count">TOP 8</span>
  </div>
  <div class="ranking-list">
    <?php foreach ($ranking as $i => $a):
      $slug = htmlspecialchars($a['slug'] ?? '');
      $title = htmlspecialchars(mb_substr($a['title'] ?? '', 0, 50));
      $catSlug = explode('/', $a['category'] ?? '')[0] ?: 'trend';
      $cl = $catLabels[$catSlug] ?? ['icon' => '📦', 'name' => $catSlug, 'color' => 'var(--accent)'];
    ?>
    <a href="/articles/<?=$slug?>" class="ranking-item">
      <div class="ranking-num"><?=$i+1?></div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:14px;line-height:1.35"><?=$title?></div>
        <div style="font-size:11px;color:var(--faint);margin-top:2px"><?=$cl['icon']?> <?=$cl['name']?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- 专题入口 -->
  <div class="section-title">
    <span style="font-size:20px">🔖</span>
    <h2>专题入口</h2>
    <span class="count"><?=count($topTags)?> 个热门话题</span>
  </div>
  <div class="topic-grid">
    <?php foreach ($topTags as $t): ?>
    <a href="?tag=<?=urlencode($t)?>" class="topic-tag">
      <span style="color:var(--accent)">#</span> <?=htmlspecialchars($t)?> <span style="margin-left:auto;font-size:11px;color:var(--faint);font-family:var(--font-mono)"><?=$allTags[$t]?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- 分类 chips -->
  <div class="cat-nav" id="cat-nav">
    <span style="font-size:13px;color:var(--faint);font-weight:600;margin-right:4px">分类：</span>
    <?php $seenNames = []; foreach ($catLabels as $cat => $cl): if (!isset($byCat[$cat]) || in_array($cl['name'], $seenNames)) continue; $seenNames[] = $cl['name']; ?>
    <a href="#floor-<?=$cat?>" class="cat-chip"><?=$cl['icon']?> <?=$cl['name']?> <span style="opacity:.6"><?=count($byCat[$cat])?></span></a>
    <?php endforeach; ?>
  </div>

  <!-- 分类楼层 -->
  <?php foreach ($byCat as $cat => $catArticles):
    $cl = $catLabels[$cat] ?? ['icon' => '📦', 'name' => $cat, 'desc' => '', 'gradient' => 'var(--accent)', 'color' => 'var(--accent)'];
    $show = array_slice($catArticles, 0, 8);
    $hasMore = count($catArticles) > 8;
  ?>
  <div class="floor" id="floor-<?=$cat?>">
    <div class="floor-head">
      <div class="floor-icon" style="background:<?=$cl['gradient']?>;color:#fff"><?=$cl['icon']?></div>
      <div>
        <div class="floor-title"><?=$cl['name']?></div>
        <?php if ($cl['desc']): ?><div class="floor-desc"><?=$cl['desc']?></div><?php endif; ?>
      </div>
      <div class="floor-count"><?=count($catArticles)?> 篇</div>
    </div>
    <div class="floor-grid">
      <?php foreach ($show as $a): ?><?=renderCard($a, $catLabels)?><?php endforeach; ?>
    </div>
    <?php if ($hasMore): ?>
    <div class="floor-more">
      <a href="?cat=<?=urlencode($cat)?>">查看全部 <?=$cl['name']?> →</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

</div>

</body>
</html>
