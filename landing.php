<?php
/**
 * Landing Page 渲染器 — /{slug}（由 .htaccess 重写）
 * 标签聚合页：按 aggregate_tags 聚合已发布文章
 */
require_once __DIR__ . '/admin/config.php';

$slug = trim(req_str('slug'));
$landing = null;
foreach (get_landing_pages() as $p) {
    if (($p['slug'] ?? '') === $slug && ($p['status'] ?? 'draft') === 'published') { $landing = $p; break; }
}

if (!$landing) {
    http_response_code(404);
    ?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>404 | OpenFlow</title><?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?></head>
    <body style="display:grid;place-items:center;min-height:100vh;text-align:center;padding:20px"><div>
    <p class="kicker" style="font-size:48px;letter-spacing:0">404</p><h1 style="margin-top:16px;font-size:28px;font-weight:700">页面不存在</h1>
    <a href="/" class="btn primary" style="margin-top:28px">返回首页</a>
    </div></body></html><?php
    exit;
}

// 聚合文章（支持 标签/分类/作者/全站 模式）
$mode = $landing['aggregate_mode'] ?? 'tag';
$tags = $landing['aggregate_tags'] ?? [];
$maxN = (int)($landing['max_articles'] ?? 20);
$items = [];
foreach (get_articles() as $a) {
    if (($a['status'] ?? 'draft') !== 'published') continue;
    $hit = false;
    if ($mode === 'all') $hit = true;
    elseif ($mode === 'tag') {
        if (count(array_intersect($tags, $a['tags'] ?? [])) > 0) $hit = true;
    } elseif ($mode === 'category') {
        $cat = trim(strtolower($landing['aggregate_category'] ?? ''));
        if ($cat && (strtolower($a['category'] ?? '') === $cat || in_array($cat, array_map('strtolower', $a['tags'] ?? [])))) $hit = true;
    } elseif ($mode === 'author') {
        $author = trim(strtolower($landing['aggregate_author'] ?? ''));
        if ($author && (strtolower($a['author'] ?? '') === $author || stripos($a['author_name'] ?? '', $author) !== false)) $hit = true;
    }
    if ($hit) $items[] = $a;
}
if (($landing['sort_by'] ?? 'newest') === 'popular') {
    usort($items, fn($x, $y) => (($y['views'] ?? 0) <=> ($x['views'] ?? 0)));
} elseif (($landing['sort_by'] ?? '') === 'personalized') {
    // 个性化排序：按访问者画像兴趣权重
    try {
        require_once __DIR__ . '/lib/Personalizer.php';
        $vid = $_COOKIE['fc_uid'] ?? '';
        $mid = $_COOKIE['member_id'] ?? '';
        $pref = Personalizer::buildProfile($vid, $mid);
        if (!empty($pref['tags']) || !empty($pref['categories'])) {
            $ranked = Personalizer::rankForProfile($items, $pref);
            $items = array_slice($ranked, 0, $maxN);
        } else {
            usort($items, fn($x, $y) => strcmp($y['created_at'] ?? '', $x['created_at'] ?? ''));
        }
    } catch (Exception $e) {
        usort($items, fn($x, $y) => strcmp($y['created_at'] ?? '', $x['created_at'] ?? ''));
    }
} else {
    usort($items, fn($x, $y) => strcmp($y['created_at'] ?? '', $x['created_at'] ?? ''));
}
$items = array_slice($items, 0, $maxN);

$modeBadge = ['tag' => '标签聚合', 'category' => '分类聚合', 'author' => '作者聚合', 'all' => '全站文章'][$mode] ?? '专题聚合';

$catNames = [];
foreach (get_categories('article') as $c) $catNames[$c['key']] = $c['name'];

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'example.com';
$baseUrl = $protocol . '://' . $host;
$pageUrl = $baseUrl . '/' . $landing['slug'];

$pageTitle = !empty($landing['seo_title']) ? $landing['seo_title'] : $landing['title'] . ' | OpenFlow';
$pageDesc = !empty($landing['seo_desc']) ? $landing['seo_desc'] : ($landing['description'] ?? '');

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $landing['title'],
    'description' => $pageDesc,
    'url' => $pageUrl,
];
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 | OpenFlow</title>
    <?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 专题聚合页：全部来自 modules.css（hero-center / a-card / cta-band）。 */
.lp-tags{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
</style>
<script src="/assets/inject.js?v=20260830b" data-cfasync="false" data-site-inject></script>
</head>
<body data-of-main>
<?php of_shell('articles'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section id="top" class="reveal in" data-od-anchor data-od-id="lp-hero">
    <nav class="art-meta" aria-label="面包屑" style="justify-content:center"><a href="/" style="color:var(--faint)">首页</a><span class="sep"></span><a href="/academy" style="color:var(--faint)">学院</a><span class="sep"></span><span><?=htmlspecialchars(mb_substr($landing['title'], 0, 30))?></span></nav>
    <div class="hero-center" style="padding-top:18px">
      <span class="kicker"><?=htmlspecialchars($modeBadge)?></span>
      <h1><?=htmlspecialchars($landing['title'])?></h1>
      <?php if (!empty($landing['show_description']) && !empty($landing['description'])): ?><p class="lead"><?=htmlspecialchars($landing['description'])?></p><?php endif; ?>
      <div class="lp-tags">
        <?php foreach ($tags as $t): ?><span class="badge ok"># <?=htmlspecialchars($t)?></span><?php endforeach; ?>
        <?php if (!empty($landing['aggregate_category'])): ?><span class="pill hl"><?=htmlspecialchars($landing['aggregate_category'])?></span><?php endif; ?>
        <?php if (!empty($landing['aggregate_author'])): ?><span class="pill neutral"><?=htmlspecialchars($landing['aggregate_author'])?></span><?php endif; ?>
      </div>
      <div class="trust"><span class="dot"></span>共 <?=count($items)?> 篇文章</div>
    </div>
  </section>

  <section id="list" class="sec reveal" data-od-anchor data-od-id="lp-list">
    <?php if (empty($items)): ?>
    <div class="empty">该专题暂无已发布的文章。</div>
    <?php else: ?>
    <div class="a-grid">
      <?php foreach ($items as $a):
        $cv = $a['cover'] ?? '';
        $cvUrl = $cv ? (strpos($cv, 'http') === 0 ? $cv : $baseUrl . '/' . ltrim($cv, '/')) : '';
        $cn = $catNames[$a['category'] ?? ''] ?? '';
      ?>
      <a href="/article/<?=htmlspecialchars($a['slug'])?>" class="a-card">
        <div class="cov"><?php if ($cvUrl): ?><img src="<?=htmlspecialchars($cvUrl)?>" alt="<?=htmlspecialchars($a['title'])?>" loading="lazy"><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg><?php endif; ?></div>
        <div class="bd">
          <?php if ($cn): ?><span class="cat"><?=htmlspecialchars($cn)?></span><?php endif; ?>
          <h3><?=htmlspecialchars($a['title'])?></h3>
          <p style="font-size:13px;color:var(--muted);line-height:1.7;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?=htmlspecialchars(mb_substr(strip_tags($a['content'] ?? ''), 0, 90))?></p>
          <div class="meta"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="reveal" data-od-id="lp-cta">
    <div class="cta-band">
      <span class="kicker">NEXT</span>
      <h2>想获取完整的网站增长方法论？</h2>
      <p class="lead">预约一次免费诊断，或订阅我们的内容更新。</p>
      <div class="cta-row"><a href="/#contact" class="btn primary">预约诊断</a><a href="/community" class="btn ghost">返回社区</a></div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
