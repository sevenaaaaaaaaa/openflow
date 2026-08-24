<?php
/**
 * 文章列表页 — 动态渲染，支持 CSS 渐变封面
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CoverRenderer.php';
require_once __DIR__ . '/lib/I18n.php';

$siteName = site_config_get('site_name', 'OpenFlow');
$allArticles = get_articles();

// 按创建时间倒序
usort($allArticles, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

// 只显示已发布
$articles = array_values(array_filter($allArticles, fn($a) => ($a['status'] ?? '') === 'published'));

// 分页
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$total = count($articles);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pageArticles = array_slice($articles, $offset, $perPage);

// 分类筛选
$filterCat = trim($_GET['cat'] ?? '');
if ($filterCat) {
    $pageArticles = array_values(array_filter($pageArticles, fn($a) => strpos($a['category'] ?? '', $filterCat) === 0));
}

// 分类统计
$catCounts = [];
foreach ($articles as $a) {
    $cat = explode('/', $a['category'] ?? '')[0] ?: 'trend';
    $catCounts[$cat] = ($catCounts[$cat] ?? 0) + 1;
}

$catLabels = [
    'ai-create' => ['icon' => '🎨', 'name' => 'AI 创作'],
    'ai-marketing' => ['icon' => '📣', 'name' => 'AI 营销'],
    'ai-build' => ['icon' => '🏗️', 'name' => 'AI 建站'],
    'ai-code' => ['icon' => '💻', 'name' => 'AI 编程'],
    'ai-ops' => ['icon' => '⚙️', 'name' => 'AI 运营'],
    'ai-sell' => ['icon' => '💰', 'name' => 'AI 销售'],
    'ai-data' => ['icon' => '📈', 'name' => '数据分析'],
    'ai-user' => ['icon' => '👤', 'name' => '用户运营'],
    'agent' => ['icon' => '🤖', 'name' => 'Agent 生态'],
    'trend' => ['icon' => '🔮', 'name' => '行业趋势'],
];

admin_header('文章');
?>
<div class="mx-auto px-5 py-10" style="max-width:1200px">

  <!-- 页面标题 -->
  <div style="margin-bottom:28px">
    <h1 style="font-size:28px;font-weight:800;letter-spacing:-.02em">📚 内容学院</h1>
    <p style="font-size:14px;color:var(--muted);margin-top:6px">增长实践、AI 工具评测、行业洞察 — 共 <?=count($articles)?> 篇</p>
  </div>

  <!-- 分类标签 -->
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px">
    <a href="/articles" class="rounded-full px-4 py-2 text-[13px] font-semibold" style="<?=empty($filterCat)?'background:var(--accent);color:var(--on-accent)':'background:var(--surface);border:1px solid var(--border);color:var(--muted)'?>;text-decoration:none">全部 (<?=count($articles)?>)</a>
    <?php foreach ($catCounts as $cat => $cnt): $cl = $catLabels[$cat] ?? ['icon'=>'📦','name'=>$cat]; ?>
    <a href="/articles?cat=<?=urlencode($cat)?>" class="rounded-full px-4 py-2 text-[13px] font-semibold" style="<?=$filterCat===$cat?'background:var(--accent);color:var(--on-accent)':'background:var(--surface);border:1px solid var(--border);color:var(--muted)'?>;text-decoration:none"><?=$cl['icon']?> <?=$cl['name']?> (<?=$cnt?>)</a>
    <?php endforeach; ?>
  </div>

  <!-- 文章网格 -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
    <?php foreach ($pageArticles as $a):
      $slug = htmlspecialchars($a['slug'] ?? '');
      $title = htmlspecialchars($a['title'] ?? '');
      $excerpt = htmlspecialchars(mb_substr(strip_tags($a['excerpt'] ?? ''), 0, 80));
      $date = substr($a['created_at'] ?? '', 0, 10);
      $catSlug = explode('/', $a['category'] ?? '')[0] ?: 'trend';
      $cl = $catLabels[$catSlug] ?? ['icon' => '📦', 'name' => $catSlug];
      $coverHtml = CoverRenderer::renderCardCover($a);
    ?>
    <a href="/article/<?=$slug?>" class="card-hover" style="text-decoration:none;color:inherit">
      <?=$coverHtml?>
      <div style="padding:14px 16px;display:flex;flex-direction:column;gap:6px;flex:1">
        <div style="font-size:11px;font-weight:600;color:var(--accent)"><?=$cl['icon']?> <?=$cl['name']?></div>
        <div style="font-weight:700;font-size:15px;line-height:1.35"><?=$title?></div>
        <?php if ($excerpt): ?><div style="font-size:12.5px;color:var(--muted);line-height:1.5"><?=$excerpt?></div><?php endif; ?>
        <div style="font-size:11px;color:var(--faint);margin-top:auto;padding-top:8px"><?=$date?> · Gana</div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- 分页 -->
  <?php if ($totalPages > 1): ?>
  <div style="display:flex;justify-content:center;gap:8px;margin-top:32px">
    <?php if ($page > 1): ?>
    <a href="?page=<?=$page-1?><?= $filterCat ? '&cat='.urlencode($filterCat) : '' ?>" style="padding:8px 16px;border-radius:999px;font-size:13px;background:var(--surface);border:1px solid var(--border);color:var(--muted);text-decoration:none">← 上一页</a>
    <?php endif; ?>
    <span style="padding:8px 16px;font-size:13px;color:var(--muted)">第 <?=$page?> / <?=$totalPages?> 页</span>
    <?php if ($page < $totalPages): ?>
    <a href="?page=<?=$page+1?><?= $filterCat ? '&cat='.urlencode($filterCat) : '' ?>" style="padding:8px 16px;border-radius:999px;font-size:13px;background:var(--accent);color:var(--on-accent);text-decoration:none">下一页 →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
<?php admin_footer(); ?>
