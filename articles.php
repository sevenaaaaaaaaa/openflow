<?php
/**
 * 文章列表页 — 楼层式布局（分类楼层 + 最新文章 + 筛选）
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CoverRenderer.php';
require_once __DIR__ . '/lib/I18n.php';

$siteName = site_config_get('site_name', 'OpenFlow');
$allArticles = get_articles();
usort($allArticles, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$articles = array_values(array_filter($allArticles, fn($a) => ($a['status'] ?? '') === 'published'));

// 分类配置
$catLabels = [
    'ai-create'    => ['icon' => '🎨', 'name' => 'AI 创作',   'desc' => '设计 · 视频 · 图片 · 写作 · 音频 · 3D', 'gradient' => 'linear-gradient(135deg,#7c3aed,#a78bfa)'],
    'content'      => ['icon' => '🎨', 'name' => 'AI 创作',   'desc' => '设计 · 视频 · 图片 · 写作 · 音频 · 3D', 'gradient' => 'linear-gradient(135deg,#7c3aed,#a78bfa)'],
    'agent'        => ['icon' => '🤖', 'name' => 'Agent 生态', 'desc' => 'Agent 工具 · Skills · MCP · 编排',       'gradient' => 'linear-gradient(135deg,#4f46e5,#818cf8)'],
    'trend'        => ['icon' => '🔮', 'name' => '行业趋势',   'desc' => '热点新闻 · 观点 · 对比评测 · 入门指南', 'gradient' => 'linear-gradient(135deg,#ea580c,#fb923c)'],
    'insight'      => ['icon' => '🔮', 'name' => '行业趋势',   'desc' => '热点新闻 · 观点 · 对比评测 · 入门指南', 'gradient' => 'linear-gradient(135deg,#ea580c,#fb923c)'],
    'ai'           => ['icon' => '🤖', 'name' => 'Agent 生态', 'desc' => 'Agent 工具 · Skills · MCP · 编排',       'gradient' => 'linear-gradient(135deg,#4f46e5,#818cf8)'],
    'ai-code'      => ['icon' => '💻', 'name' => 'AI 编程',   'desc' => 'Agent 开发 · AI IDE · DevOps · API',    'gradient' => 'linear-gradient(135deg,#2563eb,#60a5fa)'],
    'ai-marketing' => ['icon' => '📣', 'name' => 'AI 营销',   'desc' => 'SEO · 社媒 · 邮件 · 内容分发',          'gradient' => 'linear-gradient(135deg,#059669,#34d399)'],
    'ai-ops'       => ['icon' => '⚙️', 'name' => 'AI 运营',   'desc' => '自动化 · 工作流 · 效率工具',            'gradient' => 'linear-gradient(135deg,#7c3aed,#c084fc)'],
    'ai-sell'      => ['icon' => '💰', 'name' => 'AI 销售',   'desc' => 'CRM · 转化漏斗 · 变现方法',             'gradient' => 'linear-gradient(135deg,#d97706,#fbbf24)'],
    'ai-data'      => ['icon' => '📊', 'name' => '数据分析',   'desc' => '数据分析 · 可视化 · A/B 测试',         'gradient' => 'linear-gradient(135deg,#0d9488,#5eead4)'],
    'ai-user'      => ['icon' => '👤', 'name' => '用户运营',   'desc' => '用户画像 · 社区 · 留存 · 个性化',       'gradient' => 'linear-gradient(135deg,#e11d48,#fb7185)'],
    'ai-build'     => ['icon' => '🏗️', 'name' => 'AI 建站',   'desc' => '无代码 · 落地页 · 电商 · CMS',          'gradient' => 'linear-gradient(135deg,#0891b2,#67e8f9)'],
];

// 按分类分组（合并映射到同一显示名的分类）
$byCat = [];
$catMerge = [];
foreach ($catLabels as $slug => $cl) {
    $catMerge[$slug] = $cl['name'];
}
foreach ($articles as $a) {
    $cat = explode('/', $a['category'] ?? '')[0] ?: 'trend';
    $displayCat = $catMerge[$cat] ?? $cat;
    // 找到该显示名对应的第一个 slug 作为 key
    $key = $cat;
    foreach ($catMerge as $s => $name) {
        if ($name === $displayCat) { $key = $s; break; }
    }
    $byCat[$key][] = $a;
}
// 分类排序：按文章数降序
uksort($byCat, fn($a, $b) => count($byCat[$b]) <=> count($byCat[$a]));

// 最新文章（全站前 12 篇）
$latest = array_slice($articles, 0, 12);

// 分类筛选
$filterCat = trim($_GET['cat'] ?? '');

// 渲染卡片
function renderCard(array $a, array $catLabels): string {
    $slug = htmlspecialchars($a['slug'] ?? '');
    $title = htmlspecialchars($a['title'] ?? '');
    $excerpt = htmlspecialchars(mb_substr(strip_tags($a['excerpt'] ?? ''), 0, 80));
    $date = substr($a['created_at'] ?? '', 0, 10);
    $catSlug = explode('/', $a['category'] ?? '')[0] ?: 'trend';
    $cl = $catLabels[$catSlug] ?? ['icon' => '📦', 'name' => $catSlug];
    $coverHtml = CoverRenderer::renderCardCover($a);
    return '<a href="/article/' . $slug . '" class="card-hover" style="text-decoration:none;color:inherit">'
        . $coverHtml
        . '<div style="padding:14px 16px;display:flex;flex-direction:column;gap:6px;flex:1">'
        . '<div style="font-size:11px;font-weight:600;color:var(--accent)">' . $cl['icon'] . ' ' . $cl['name'] . '</div>'
        . '<div style="font-weight:700;font-size:15px;line-height:1.35">' . $title . '</div>'
        . ($excerpt ? '<div style="font-size:12.5px;color:var(--muted);line-height:1.5">' . $excerpt . '</div>' : '')
        . '<div style="font-size:11px;color:var(--faint);margin-top:auto;padding-top:8px">' . $date . ' · Gana</div>'
        . '</div></a>';
}

admin_header('文章');
?>
<style>
  .floor{margin-bottom:40px}
  .floor-head{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border)}
  .floor-icon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;font-size:18px;flex:0 0 auto}
  .floor-title{font-size:20px;font-weight:800;letter-spacing:-.01em}
  .floor-desc{font-size:12.5px;color:var(--muted);margin-left:4px}
  .floor-count{font-size:12px;color:var(--faint);margin-left:auto;font-family:var(--font-mono)}
  .floor-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
  .floor-more{display:flex;justify-content:center;margin-top:16px}
  .floor-more a{padding:8px 20px;border-radius:999px;font-size:13px;font-weight:600;background:var(--surface);border:1px solid var(--border);color:var(--muted);text-decoration:none;transition:.15s}
  .floor-more a:hover{border-color:var(--accent);color:var(--accent)}
  .cat-nav{position:sticky;top:72px;z-index:10;display:flex;gap:8px;flex-wrap:wrap;padding:12px 0;background:var(--bg)}
  .cat-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:999px;font-size:13px;font-weight:600;border:1.5px solid var(--border);background:var(--surface);color:var(--muted);text-decoration:none;transition:.15s;cursor:pointer}
  .cat-chip:hover{border-color:var(--accent);color:var(--accent)}
  .cat-chip.active{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
</style>

<div style="padding:0 clamp(16px,4vw,40px);padding-top:24px;padding-bottom:64px">

  <!-- 页面标题 -->
  <div style="margin-bottom:24px">
    <h1 style="font-size:32px;font-weight:800;letter-spacing:-.02em">📚 内容学院</h1>
    <p style="font-size:15px;color:var(--muted);margin-top:8px">增长实践、AI 工具评测、行业洞察 — 共 <b><?=$total = count($articles)?></b> 篇</p>
  </div>

  <!-- 分类导航 chips -->
  <div class="cat-nav">
    <a href="/articles" class="cat-chip <?=empty($filterCat)?'active':''?>">📦 全部 (<?=$total?>)</a>
    <?php
    $seenNames = [];
    foreach ($catLabels as $cat => $cl):
      if (!isset($byCat[$cat]) || in_array($cl['name'], $seenNames)) continue;
      $seenNames[] = $cl['name'];
    ?>
    <a href="/articles?cat=<?=urlencode($cat)?>#floor-<?=$cat?>" class="cat-chip"><?=$cl['icon']?> <?=$cl['name']?> (<?=count($byCat[$cat])?>)</a>
    <?php endforeach; ?>
  </div>

  <?php if ($filterCat): ?>
    <!-- 单分类筛选模式 -->
    <?php $cl = $catLabels[$filterCat] ?? ['icon'=>'📦','name'=>$filterCat,'desc'=>'','gradient'=>'']; $catArticles = $byCat[$filterCat] ?? []; ?>
    <div style="margin-top:24px;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div class="floor-icon" style="background:<?=$cl['gradient']?>;color:#fff;font-size:20px"><?=$cl['icon']?></div>
        <div>
          <div style="font-size:22px;font-weight:800"><?=$cl['name']?></div>
          <?php if ($cl['desc']): ?><div style="font-size:13px;color:var(--muted);margin-top:2px"><?=$cl['desc']?></div><?php endif; ?>
        </div>
        <span style="margin-left:auto;font-size:13px;color:var(--faint)"><?=count($catArticles)?> 篇</span>
      </div>
      <div class="floor-grid">
        <?php foreach ($catArticles as $a): ?><?=renderCard($a, $catLabels)?><?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>
    <!-- 最新文章 -->
    <div class="floor" style="margin-bottom:36px">
      <div class="floor-head">
        <div class="floor-icon" style="background:linear-gradient(135deg,var(--accent),oklch(58% .16 285));color:#fff">✨</div>
        <div>
          <div class="floor-title">最新发布</div>
          <div class="floor-desc">全站最新 <?=$perFloor = 12?> 篇文章</div>
        </div>
        <div class="floor-count"><?=$total?> 篇</div>
      </div>
      <div class="floor-grid">
        <?php foreach ($latest as $a): ?><?=renderCard($a, $catLabels)?><?php endforeach; ?>
      </div>
    </div>

    <!-- 分类楼层 -->
    <?php foreach ($byCat as $cat => $catArticles):
      $cl = $catLabels[$cat] ?? ['icon' => '📦', 'name' => $cat, 'desc' => '', 'gradient' => 'linear-gradient(135deg,var(--accent),var(--accent-strong))'];
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
        <a href="/articles?cat=<?=urlencode($cat)?>">查看全部 <?=$cl['name']?> →</a>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
<?php admin_footer(); ?>
