<?php
/**
 * 分类落地页 — 统一渲染产品/能力/学院/生态/课程的分类内容
 *
 * v7（2026-09-01）：迁到共享 archetype（面包屑 + hero-center + link-grid），零私有 CSS。数据与爬虫逻辑原样保留。
 * /category/{section}/{subkey}
 * 例：/category/academy/articles → 学院·文章
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（300 秒）
if (PageCache::begin('category', 1800)) exit;

$content = require __DIR__ . '/data/site-nav-content.php';

$section = req_str('section', '');
$subkey = req_str('subkey', '');
$sec = $content[$section] ?? null;
$sub = null;
if ($sec) {
    foreach ($sec['subs'] as $s) if ($s['key'] === $subkey) { $sub = $s; break; }
}
if (!$sec || !$sub) {
    header('Location: /');
    exit;
}

$title = $sub['name'] . ' · ' . $sec['title'];
$desc = $sub['desc'];
// 导航高亮映射：section → NAV id
$navPageMap = ['products' => 'product', 'capabilities' => 'capability', 'academy' => 'articles', 'marketplace' => 'marketplace', 'courses' => 'courses'];
$navPage = $navPageMap[$section] ?? 'home';

// 爬虫检测：AI/搜索爬虫直接 SSR 完整 SEO + 内容
$crawler = class_exists('CrawlerDetect') ? CrawlerDetect::detect() : ['is_crawler' => false, 'type' => null];
if ($crawler['is_crawler']) {
    header('X-Robots-Tag: index, follow');
    if (($crawler['type'] ?? '') === 'ai') {
        header('X-AI-Crawler: allowed');
    }
}

// 按 section 拉取真实内容（主推 + 列表）
$realItems = [];
$realLink = $sub['href'] ?? '#';
if ($section === 'academy') {
    $articles = get_articles();
    $published = array_values(array_filter($articles, fn($a) => ($a['status'] ?? '') === 'published'));
    if ($subkey === 'articles' && !empty($published)) {
        $realItems = array_slice($published, 0, 6);
        $realLink = '/academy';
    } elseif ($subkey === 'downloads') {
        $realItems = array_values(array_filter(json_read(DATA_DIR . '/downloads.json'), fn($d) => ($d['status'] ?? '') === 'published'));
        $realLink = '/downloads';
    }
}
if ($section === 'marketplace') {
    $assets = function_exists('mkt_assets') ? mkt_assets() : [];
    if ($subkey === 'skills') { $realItems = array_filter($assets, fn($a) => ($a['type'] ?? '') === 'skill'); $realLink = '/marketplace?type=skill'; }
    elseif ($subkey === 'plugins') { $realItems = array_filter($assets, fn($a) => ($a['type'] ?? '') === 'plugin'); $realLink = '/marketplace?type=plugin'; }
    elseif ($subkey === 'themes') { $realItems = array_filter($assets, fn($a) => ($a['type'] ?? '') === 'theme'); $realLink = '/marketplace?type=theme'; }
    $realItems = array_values(array_slice($realItems, 0, 6));
}
if ($section === 'courses') {
    $courses = json_read(DATA_DIR . '/courses/index.json');
    $realItems = array_values(array_filter($courses, fn($c) => ($c['status'] ?? '') === 'published'));
    $realLink = '/courses';
}
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($title)?> | <?=site_config_get('site_name')?></title>
<meta name="description" content="<?=htmlspecialchars($desc)?>">
<?php if (function_exists('seo_head')): seo_head([
    'title' => $title . ' | ' . site_config_get('site_name'),
    'description' => $desc,
    'keywords' => implode(', ', array_column($sec['subs'], 'name')),
    'canonical' => site_config_get('site_url') . '/category/' . $section . '/' . $subkey,
]); endif; ?>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell($navPage); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section id="top" class="reveal in" data-od-anchor data-od-id="category-hero">
    <nav class="art-meta" aria-label="面包屑" style="justify-content:center"><a href="/" style="color:var(--faint)">首页</a><span class="sep"></span><a href="<?=htmlspecialchars($sec['href'])?>" style="color:var(--faint)"><?=htmlspecialchars($sec['title'])?></a><span class="sep"></span><span><?=htmlspecialchars($sub['name'])?></span></nav>
    <div class="hero-center" style="padding-top:18px;padding-bottom:0">
      <span class="kicker"><?=htmlspecialchars($sec['title'])?></span>
      <h1><?=htmlspecialchars($sub['name'])?></h1>
      <p class="lead"><?=htmlspecialchars($sub['desc'])?></p>
    </div>
  </section>

  <section id="featured" class="sec reveal" data-od-anchor data-od-id="category-featured">
    <div class="sec-head row"><div><span class="kicker">主推内容</span><h2>先看这些</h2></div></div>
    <div class="link-grid" style="margin-top:8px">
      <?php if (!empty($realItems)): ?>
        <?php foreach ($realItems as $ri): $isArt = isset($ri['content']); $isDl = isset($ri['file']);
          $href = $isArt ? '/articles/' . urlencode($ri['slug'] ?? $ri['id']) : ($isDl ? '/downloads/' . urlencode($ri['slug'] ?? $ri['id']) : ($realLink . (strpos($realLink, '?') !== false ? '&' : '?') . 'id=' . urlencode($ri['id'] ?? ''))); ?>
        <a class="link-it top" href="<?=htmlspecialchars($href)?>">
          <span class="ic"><?php if ($isArt): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg><?php elseif ($isDl): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13m0 0-4-4m4 4 4-4M4 20h16"/></svg><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg><?php endif; ?></span>
          <span class="lt"><b><?=htmlspecialchars(mb_substr($ri['title'] ?? '未命名', 0, 40))?></b><span><?=htmlspecialchars(mb_substr(strip_tags($ri['excerpt'] ?? $ri['description'] ?? $ri['content'] ?? ''), 0, 80))?></span><?php if (!empty($ri['tags'])): ?><span class="tags" style="margin-top:6px"><?php foreach (array_slice($ri['tags'],0,3) as $tg): ?><span><?=htmlspecialchars($tg)?></span><?php endforeach; ?></span><?php endif; ?></span>
          <span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
        </a>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($sec['featured'] as $i => $f): ?>
        <a class="link-it top" href="<?=htmlspecialchars($f['href'])?>">
          <span class="ic" style="font-size:18px"><?=$f['icon']?></span>
          <span class="lt"><b><?=htmlspecialchars($f['title'])?></b><span><?=htmlspecialchars($f['desc'])?></span></span>
          <span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
        </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <section id="subs" class="sec reveal" data-od-anchor data-od-id="category-subs">
    <div class="sec-head row"><div><span class="kicker"><?=htmlspecialchars($sub['name'])?> 相关</span><h2>同一板块的其它入口</h2></div></div>
    <div class="cols n4" style="margin-top:8px">
      <?php foreach ($sec['subs'] as $s): ?>
      <a href="/category/<?=$section?>/<?=htmlspecialchars($s['key'])?>" style="<?=$s['key']===$subkey?'':''?>"><span class="ic" style="font-size:18px"><?=$s['icon']?></span><h3><?=htmlspecialchars($s['name'])?></h3><p><?=htmlspecialchars($s['desc'])?></p></a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="reveal" data-od-anchor data-od-id="category-more">
    <div class="cta-band">
      <span class="kicker"><?=htmlspecialchars($sec['title'])?></span>
      <h2>查看更多 <?=htmlspecialchars($sub['name'])?></h2>
      <div class="cta-row"><a href="<?=htmlspecialchars($realLink)?>" class="btn primary">查看更多 <?=htmlspecialchars($sub['name'])?> →</a></div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
<?php PageCache::end('category', 1800); ?>
