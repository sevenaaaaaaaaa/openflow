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
    ?><!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>404 | OpenFlow</title>
    <link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<link rel="stylesheet" href="/assets/site-arc-betterup.css"><script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
</head>
    <body class="bg-bg text-ink"><div class="mx-auto max-w-site px-5 py-[180px] text-center">
    <p class="text-[64px] font-bold text-accent">404</p><h1 class="mt-4 text-[28px] font-bold">页面不存在</h1>
    <a href="/" class="mt-8 inline-flex rounded-full bg-jade px-7 py-3 font-semibold text-white">返回首页</a>
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
$host = $_SERVER['HTTP_HOST'] ?? 'nownexts.com';
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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($pageTitle)?></title>
<meta name="description" content="<?=htmlspecialchars($pageDesc)?>">
<link rel="canonical" href="<?=htmlspecialchars($pageUrl)?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="OpenFlow XMP">
<meta property="og:title" content="<?=htmlspecialchars($landing['title'])?>">
<meta property="og:description" content="<?=htmlspecialchars($pageDesc)?>">
<meta property="og:url" content="<?=htmlspecialchars($pageUrl)?>">
<meta property="og:locale" content="zh_CN">
<script type="application/ld+json"><?=json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?></script>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/site-betterup.js"></script>
<link rel="stylesheet" href="/assets/site-arc-betterup.css">
<style>
  .lp-card{display:flex;flex-direction:column;overflow:hidden;background:var(--surface);border:1px solid var(--border);border-radius:20px;box-shadow:0 4px 16px oklch(0.35 0.05 295 / .07);transition:transform .2s,box-shadow .2s;text-decoration:none}
  .lp-card:hover{transform:translateY(-3px);box-shadow:0 16px 40px oklch(0.35 0.07 295 / .12)}
  .lp-media{aspect-ratio:16/9;overflow:hidden;background:linear-gradient(135deg,var(--ok-soft) 0%,var(--bg) 50%,var(--accent-soft) 100%)}
  .lp-media img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
  .lp-card:hover .lp-media img{transform:scale(1.04)}
  .lp-body{padding:18px 20px 20px;display:flex;flex-direction:column;flex:1;gap:9px}
  .lp-body h3{font-size:17px;font-weight:700;line-height:1.45;color:var(--fg)}
  .lp-card:hover .lp-body h3{color:var(--ok)}
  .lp-body p{font-size:13.5px;line-height:1.7;color:var(--muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
</style>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
</head>
<body class="bg-bg text-ink antialiased">

<header class="fixed inset-x-0 top-0 z-50">
  <div class="border-b border-line bg-surface/85 backdrop-blur-xl">
    <div class="mx-auto flex h-[68px] max-w-site items-center justify-between px-5 sm:px-8">
      <a href="/" class="flex items-center gap-2.5" aria-label="OpenFlow 首页">
        <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true"><defs><linearGradient id="lp-lg-1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="var(--accent)"/><stop offset=".52" stop-color="#86efac"/><stop offset="1" stop-color="#7dd3fc"/></linearGradient></defs><rect x="1.6" y="1.6" width="28.8" height="28.8" rx="8.5" fill="url(#lp-lg-1)"/><path d="M7.5 19c2.8-5.2 4.6 2.6 7.4-2.2s4.8 2.6 7.6-2.2" fill="none" stroke="#1e1e1e" stroke-width="2.3" stroke-linecap="round"/><circle cx="23.2" cy="9.6" r="1.9" fill="#1e1e1e"/></svg>
        <span class="brand-text text-[19px] font-bold tracking-tight leading-none">OpenFlow</span>
      </a>
      <nav class="hidden lg:flex items-center gap-5 text-[14.5px]">
        <a href="/" class="nav-link">首页</a>
        <a href="/capability" class="nav-link">产品</a>
        <a href="/courses" class="nav-link">解决方案</a>
        <a href="/academy" class="nav-link">学院</a>
        <a href="/about" class="nav-link">关于我们</a>
      </nav>
      <div class="hidden lg:flex items-center gap-4">
        <a href="/index.html#contact" class="rounded-full bg-jade px-5 py-2.5 text-[14.5px] font-semibold text-white hover:bg-flow transition">预约诊断</a>
      </div>
      <button id="burger" class="burger lg:hidden p-2 -mr-2" aria-label="菜单" aria-expanded="false">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>
  <div id="mobile-menu" class="lg:hidden hidden border-t border-line bg-surface/95 backdrop-blur-xl">
    <nav class="mx-auto max-w-site px-5 py-5 flex flex-col gap-1 text-ink">
      <a href="/" class="py-3 border-b border-line">首页</a>
      <a href="/capability" class="py-3 border-b border-line">产品</a>
      <a href="/courses" class="py-3 border-b border-line">解决方案</a>
      <a href="/academy" class="py-3 border-b border-line">学院</a>
      <a href="/about" class="py-3 border-b border-line">关于我们</a>
    </nav>
  </div>
</header>

<section class="relative overflow-hidden bg-bg pt-[128px] pb-10 lg:pt-[150px]">
  <div class="absolute inset-0 grid-bg opacity-70"></div>
    <div class="relative mx-auto max-w-site px-5 sm:px-8">
      <nav class="text-[13px] text-muted flex flex-wrap items-center gap-2 mb-6" aria-label="面包屑">
        <a href="/" class="hover:text-ink transition">首页</a><span>/</span>
        <a href="/academy" class="hover:text-ink transition">学院</a><span>/</span>
        <span class="text-ink"><?=htmlspecialchars(mb_substr($landing['title'], 0, 30))?></span>
      </nav>
    <div class="max-w-3xl">
      <p class="eyebrow"><?=htmlspecialchars($modeBadge)?></p>
      <h1 class="mt-4 text-[34px] font-bold leading-[1.2] tracking-tight sm:text-[44px]"><?=htmlspecialchars($landing['title'])?></h1>
      <?php if (!empty($landing['show_description']) && !empty($landing['description'])): ?>
      <p class="mt-5 max-w-[640px] text-[16px] leading-[1.85] text-muted"><?=htmlspecialchars($landing['description'])?></p>
      <?php endif; ?>
      <?php if (!empty($tags)): ?>
      <div class="mt-6 flex flex-wrap gap-2">
        <?php foreach ($tags as $t): ?>
        <span class="inline-flex items-center rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold" style="background:var(--ok-soft);color:var(--ok)"># <?=htmlspecialchars($t)?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($landing['aggregate_category'])): ?>
      <div class="mt-6"><span class="inline-flex items-center rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold" style="background:var(--accent-soft);color:#1a5c8a">📂 <?=htmlspecialchars($landing['aggregate_category'])?></span></div>
      <?php endif; ?>
      <?php if (!empty($landing['aggregate_author'])): ?>
      <div class="mt-6"><span class="inline-flex items-center rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold" style="background:var(--bg);color:#6b5d1e"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg></span> <?=htmlspecialchars($landing['aggregate_author'])?></span></div>
      <?php endif; ?>
      <p class="mt-5 text-[13px] text-muted">共 <?=count($items)?> 篇文章</p>
    </div>
  </div>
</section>

<section class="bg-bg pb-20">
  <div class="mx-auto max-w-site px-5 sm:px-8">
    <?php if (empty($items)): ?>
    <div class="rounded-2xl border border-line bg-white p-14 text-center text-muted">该专题暂无已发布的文章。</div>
    <?php else: ?>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($items as $a):
        $cv = $a['cover'] ?? '';
        $cvUrl = $cv ? (strpos($cv, 'http') === 0 ? $cv : $baseUrl . '/' . ltrim($cv, '/')) : '';
        $cn = $catNames[$a['category'] ?? ''] ?? '';
      ?>
      <a href="/article/<?=htmlspecialchars($a['slug'])?>" class="lp-card">
        <div class="lp-media">
          <?php if ($cvUrl): ?><img src="<?=htmlspecialchars($cvUrl)?>" alt="<?=htmlspecialchars($a['title'])?>" loading="lazy">
          <?php else: ?><div class="flex h-full items-center justify-center text-[40px]"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span></div><?php endif; ?>
        </div>
        <div class="lp-body">
          <?php if ($cn): ?><span class="text-[11.5px] font-semibold" style="color:var(--ok)"><?=htmlspecialchars($cn)?></span><?php endif; ?>
          <h3><?=htmlspecialchars($a['title'])?></h3>
          <p><?=htmlspecialchars(mb_substr(strip_tags($a['content'] ?? ''), 0, 90))?></p>
          <span class="mt-auto text-[12px] text-muted"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="mt-14 rounded-3xl border border-line p-8 text-center sm:p-12" style="background:linear-gradient(135deg,var(--ok-soft) 0%,var(--bg) 55%,var(--accent-soft) 100%)">
      <h2 class="text-[24px] font-bold tracking-tight sm:text-[28px]">想获取完整的网站增长方法论？</h2>
      <p class="mx-auto mt-3 max-w-xl text-[15px] leading-relaxed text-muted">预约一次免费诊断，或订阅我们的内容更新。</p>
      <div class="mt-7 flex flex-wrap justify-center gap-3">
        <a href="/index.html#contact" class="rounded-full bg-jade px-7 py-3 font-semibold text-white hover:bg-flow transition">预约诊断</a>
        <a href="/community" class="rounded-full border border-line bg-white px-7 py-3 font-semibold text-ink hover:border-accent transition">返回社区</a>
      </div>
    </div>
  </div>
</section>

<footer class="pt-16 lg:pt-20" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
  <div class="mx-auto max-w-site px-5 sm:px-8">
    <div class="grid gap-12 pb-14 lg:grid-cols-[1.25fr_2.75fr]">
      <div>
        <div class="flex items-center gap-2.5">
          <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true"><defs><linearGradient id="lp-lg-2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="var(--accent)"/><stop offset=".52" stop-color="#86efac"/><stop offset="1" stop-color="#7dd3fc"/></linearGradient></defs><rect x="1.6" y="1.6" width="28.8" height="28.8" rx="8.5" fill="url(#lp-lg-2)"/><path d="M7.5 19c2.8-5.2 4.6 2.6 7.4-2.2s4.8 2.6 7.6-2.2" fill="none" stroke="#1e1e1e" stroke-width="2.3" stroke-linecap="round"/><circle cx="23.2" cy="9.6" r="1.9" fill="#1e1e1e"/></svg>
          <span class="text-[19px] font-bold tracking-tight">芭乐派 · OpenFlow</span>
        </div>
        <p class="mt-5 text-[15px] font-medium text-white/80">帮一人公司设计 Agent 能跑的增长系统</p>
        <p class="mt-2.5 text-[13.5px] leading-relaxed text-white/45">芭乐派（OpenFlow 科技有限公司）<br>成立于 2026 年 · 上海</p>
      </div>
      <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <p class="foot-h">产品</p>
          <ul class="foot-l">
            <li><a href="/product">产品总览</a></li>
            <li><a href="/capability">TIPS 能力</a></li>
            <li><a href="/courses">New-1~4 + R.B.E</a></li>
            <li><a href="/community">门派社区</a></li>
          </ul>
        </div>
        <div>
          <p class="foot-h">解决方案</p>
          <ul class="foot-l">
            <li><a href="/courses">内容增长</a></li>
            <li><a href="/courses">线索转化</a></li>
            <li><a href="/courses">自动化培育</a></li>
            <li><a href="/academy">数据洞察</a></li>
          </ul>
        </div>
        <div>
          <p class="foot-h">联系我们</p>
          <ul class="foot-l">
            <li><a href="tel:13166373667">13166373667</a></li>
            <li><a href="mailto:admin@nownexts.com">admin@nownexts.com</a></li>
            <li><a href="https://nownexts.com">nownexts.com</a></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="flex flex-col gap-3 border-t border-white/10 py-7 text-[13px] text-white/40 sm:flex-row sm:items-center sm:justify-between">
      <p>© 2026 OpenFlow 科技有限公司　<a href="https://beian.miit.gov.cn" target="_blank" rel="noopener" style="color:inherit;text-decoration:none"></a></p>
      <div class="flex gap-6">
        <a href="#" class="hover:text-white/70 transition">隐私政策</a>
        <a href="#" class="hover:text-white/70 transition">服务条款</a>
      </div>
    </div>
  </div>
</footer>

<script>
(function() {
  var b = document.getElementById('burger'), m = document.getElementById('mobile-menu');
  if (b && m) b.addEventListener('click', function() {
    var open = m.classList.toggle('hidden') === false;
    b.setAttribute('aria-expanded', open);
  });
})();
</script>
</body>
</html>
