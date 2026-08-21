<?php
/**
 * 分类落地页 — 统一渲染产品/能力/学院/生态/课程的分类内容
 * /category/{section}/{subkey}
 * 例：/category/academy/articles → 学院·文章
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（300 秒）
if (PageCache::begin('category', 300)) exit;

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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($title)?> | <?=site_config_get('site_name')?></title>
<meta name="description" content="<?=htmlspecialchars($desc)?>">
<?php if (function_exists('seo_head')): seo_head([
    'title' => $title . ' | ' . site_config_get('site_name'),
    'description' => $desc,
    'keywords' => implode(', ', array_column($sec['subs'], 'name')),
    'canonical' => site_config_get('site_url') . '/category/' . $section . '/' . $subkey,
]); endif; ?>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:var(--font-body);color:var(--fg)}
  .cat-hero{background:linear-gradient(135deg,var(--bg-soft),var(--accent-soft));border:1px solid var(--border);border-radius:24px}
  .cat-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;transition:.15s}
  .cat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-sm);border-color:var(--border-strong)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260822" data-cfasync="false" data-page="<?=htmlspecialchars($navPage)?>"></script>

  <div class="mx-auto px-5 py-10" style="max-width:1100px">

    <!-- 面包屑 -->
    <div style="font-size:12.5px;color:var(--faint);margin-bottom:20px">
      <a href="/" style="color:var(--faint);text-decoration:none">首页</a>
      <span> / </span><a href="<?=htmlspecialchars($sec['href'])?>" style="color:var(--faint);text-decoration:none"><?=htmlspecialchars($sec['title'])?></a>
      <span> / </span><span style="color:var(--muted)"><?=htmlspecialchars($sub['name'])?></span>
    </div>

    <!-- 头部 -->
    <div class="cat-hero p-8 mb-8">
      <div style="font-size:13px;font-weight:700;letter-spacing:.1em;color:var(--accent);margin-bottom:8px"><?=htmlspecialchars($sec['title'])?></div>
      <h1 style="font-size:30px;font-weight:800;margin-bottom:8px"><?=htmlspecialchars($sub['name'])?></h1>
      <p style="color:var(--muted);font-size:14px;max-width:640px;line-height:1.7"><?=htmlspecialchars($sub['desc'])?></p>
    </div>

    <!-- 主推内容 -->
    <h2 style="font-size:18px;font-weight:800;margin-bottom:14px">⭐ 主推内容</h2>
    <div class="grid gap-4 mb-10" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
      <?php if (!empty($realItems)): ?>
        <?php foreach ($realItems as $ri): $isArt = isset($ri['content']); $isDl = isset($ri['file']); ?>
        <a href="<?=htmlspecialchars($isArt ? '/article/' . urlencode($ri['slug'] ?? $ri['id']) : ($isDl ? '/download/' . urlencode($ri['slug'] ?? $ri['id']) : ($realLink . (strpos($realLink, '?') !== false ? '&' : '?') . 'id=' . urlencode($ri['id'] ?? ''))))?>" class="cat-card block p-5" style="text-decoration:none;color:inherit">
          <div style="width:44px;height:44px;border-radius:11px;background:linear-gradient(135deg,var(--accent),var(--ok));display:grid;place-items:center;font-size:22px;margin-bottom:12px"><?=$isArt?'📄':($isDl?'📚':'🎓')?></div>
          <div style="font-weight:700;font-size:15px;line-height:1.4;margin-bottom:6px"><?=htmlspecialchars(mb_substr($ri['title'] ?? '未命名', 0, 40))?></div>
          <div style="font-size:12.5px;color:var(--muted);line-height:1.6"><?=htmlspecialchars(mb_substr(strip_tags($ri['excerpt'] ?? $ri['description'] ?? $ri['content'] ?? ''), 0, 80))?></div>
          <?php if (!empty($ri['tags'])): ?>
          <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:8px"><?php foreach (array_slice($ri['tags'],0,3) as $tg): ?><span style="font-size:10px;padding:2px 8px;border-radius:99px;background:var(--accent-soft);color:var(--accent)">#<?=htmlspecialchars($tg)?></span><?php endforeach; ?></div>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($sec['featured'] as $i => $f): ?>
        <a href="<?=htmlspecialchars($f['href'])?>" class="cat-card block p-5" style="text-decoration:none;color:inherit">
          <div style="width:44px;height:44px;border-radius:11px;background:linear-gradient(135deg,var(--accent),var(--ok));display:grid;place-items:center;font-size:22px;margin-bottom:12px"><?=$f['icon']?></div>
          <div style="font-weight:700;font-size:15px;line-height:1.4;margin-bottom:6px"><?=htmlspecialchars($f['title'])?></div>
          <div style="font-size:12.5px;color:var(--muted);line-height:1.6"><?=htmlspecialchars($f['desc'])?></div>
        </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- 该分类下的子入口 -->
    <h2 style="font-size:18px;font-weight:800;margin-bottom:14px">🗂️ <?=htmlspecialchars($sub['name'])?> 相关</h2>
    <div class="grid gap-3 mb-8" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
      <?php foreach ($sec['subs'] as $s): ?>
      <a href="/category/<?=$section?>/<?=htmlspecialchars($s['key'])?>" class="cat-card block p-4" style="text-decoration:none;color:inherit">
        <div style="font-size:18px;margin-bottom:6px"><?=$s['icon']?></div>
        <div style="font-weight:600;font-size:13.5px"><?=htmlspecialchars($s['name'])?></div>
        <div style="font-size:11.5px;color:var(--faint);margin-top:2px"><?=htmlspecialchars($s['desc'])?></div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- 查看更多 -->
    <div style="text-align:center;padding:20px 0 10px">
      <a href="<?=htmlspecialchars($realLink)?>" class="inline-block rounded-full px-8 py-3 font-bold text-sm" style="background:var(--accent);color:var(--on-accent);text-decoration:none">查看更多 <?=htmlspecialchars($sub['name'])?> →</a>
    </div>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5" style="max-width:1100px">
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:28px;padding-bottom:22px;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-weight:800;font-size:15px;color:var(--fg)">芭乐派 · OpenFlow</div>
          <p style="font-size:12.5px;color:var(--muted);line-height:1.7;margin-top:8px;max-width:320px">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
          <p style="font-size:12px;color:var(--faint);margin-top:6px">核心能力永久开源 · 鱼与渔相结合</p>
        </div>
        <div>
          <div style="font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;margin-bottom:10px">站点导航</div>
          <div style="display:flex;flex-direction:column;gap:6px">
            <a href="/product" style="color:var(--muted);text-decoration:none;font-size:13px">产品</a>
            <a href="/capability" style="color:var(--muted);text-decoration:none;font-size:13px">能力</a>
            <a href="/courses" style="color:var(--muted);text-decoration:none;font-size:13px">课程</a>
            <a href="/academy" style="color:var(--muted);text-decoration:none;font-size:13px">学院</a>
            <a href="/community" style="color:var(--muted);text-decoration:none;font-size:13px">门派社区</a>
            <a href="/about" style="color:var(--muted);text-decoration:none;font-size:13px">关于我们</a>
          </div>
        </div>
        <div>
          <div style="font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;margin-bottom:10px">资源</div>
          <div style="display:flex;flex-direction:column;gap:6px">
            <a href="/docs" style="color:var(--muted);text-decoration:none;font-size:13px">文档中心</a>
            <a href="/downloads" style="color:var(--muted);text-decoration:none;font-size:13px">资料下载</a>
            <a href="/podcasts" style="color:var(--muted);text-decoration:none;font-size:13px">播客</a>
            <a href="/marketplace" style="color:var(--muted);text-decoration:none;font-size:13px">生态市场</a>
          </div>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding-top:16px;flex-wrap:wrap">
        <div style="font-size:12px;color:var(--muted)">© 2026 芭乐派 · OpenFlow 增长操作系统</div>
        <div style="font-size:12px;color:var(--faint)">帮一人公司设计 Agent 能跑的增长系统</div>
      </div>
    </div>
  </footer>

</body>
</html>
<?php PageCache::end('category', 300); ?>
