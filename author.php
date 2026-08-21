<?php
/**
 * 作者/讲师主页
 * /author/{name} — 显示该作者的简介、文章、课程、Skills/插件
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/MemberSystem.php';

$authorName = trim(urldecode($_GET['name'] ?? ''));
if (!$authorName) {
    header('Location: /');
    exit;
}

// 聚合该作者的内容
$articles = array_values(array_filter(get_articles(), fn($a) =>
    ($a['status'] ?? 'draft') === 'published' && ($a['author'] ?? '') === $authorName
));
$skills = array_values(array_filter(json_read(DATA_DIR . '/skills/index.json'), fn($s) => ($s['author'] ?? '') === $authorName));
$courses = array_values(array_filter(json_read(DATA_DIR . '/courses/index.json'), fn($c) => ($c['author'] ?? '') === $authorName));
$plugins = array_values(array_filter(json_read(DATA_DIR . '/plugins.json'), fn($p) => ($p['author'] ?? '') === $authorName));

// 会员资料（若该名称对应某会员）
$authorProfile = null;
foreach (json_read(DATA_DIR . '/members/index.json') as $m) {
    if (($m['name'] ?? '') === $authorName || ($m['nickname'] ?? '') === $authorName) { $authorProfile = $m; break; }
}
$avatar = $authorProfile['avatar'] ?? '';
$bio = $authorProfile['bio'] ?? '';

$catNames = [];
foreach (get_categories('article') as $c) $catNames[$c['key']] = $c['name'];

$pageTitle = $authorName . ' 的主页 | ' . site_config_get('site_name');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($pageTitle)?></title>
<meta name="description" content="<?=htmlspecialchars($authorName)?> 在 <?=site_config_get('site_name')?> 发布的文章、课程与技能">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .acard{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:18px;transition:.15s;display:block;text-decoration:none;color:inherit}
  .acard:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(30,30,30,.08);border-color:var(--accent)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260821" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-10" style="max-width:1100px">
    <!-- 作者信息 -->
    <div class="rounded-3xl p-8 mb-10 flex items-center gap-6 flex-wrap" style="background:linear-gradient(135deg,var(--ok-soft) 0%,var(--accent-soft) 100%)">
      <?php if ($avatar): ?>
      <img src="<?=htmlspecialchars(strpos($avatar,'http')===0?$avatar:'/'.ltrim($avatar,'/'))?>" class="w-24 h-24 rounded-full object-cover border-4 border-white" style="box-shadow:0 8px 24px rgba(0,0,0,.1)" alt="<?=htmlspecialchars($authorName)?>">
      <?php else: ?>
      <div class="w-24 h-24 rounded-full grid place-items-center text-3xl font-bold text-white" style="background:linear-gradient(135deg,var(--accent-strong),var(--accent))"><?=htmlspecialchars(mb_substr($authorName, 0, 1))?></div>
      <?php endif; ?>
      <div class="flex-1 min-w-[240px]">
        <h1 class="text-3xl font-extrabold text-gray-900"><?=htmlspecialchars($authorName)?></h1>
        <p class="text-gray-600 mt-2 leading-relaxed max-w-xl">
          <?=$bio ? htmlspecialchars($bio) : ('专注内容创作与分享，在 ' . site_config_get('site_name') . ' 发布 ' . count($articles) . ' 篇文章、' . count($skills) . ' 个技能。')?>
        </p>
        <div class="flex gap-6 mt-4 text-sm text-[#2b5f7e]">
          <span><strong><?=count($articles)?></strong> 篇文章</span>
          <span><strong><?=count($courses)?></strong> 门课程</span>
          <span><strong><?=count($skills)?></strong> 个技能</span>
          <span><strong><?=count($plugins)?></strong> 个插件</span>
        </div>
      </div>
    </div>

    <?php if ($articles): ?>
    <h2 class="text-lg font-bold mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span> 专栏文章 (<?=count($articles)?>)</h2>
    <div class="grid gap-3 mb-10 md:grid-cols-2">
      <?php foreach (array_slice($articles, 0, 12) as $a): ?>
      <a href="/article/<?=htmlspecialchars($a['slug'])?>" class="acard">
        <div class="font-semibold text-gray-900"><?=htmlspecialchars($a['title'])?></div>
        <div class="text-xs text-gray-400 mt-1"><?=htmlspecialchars($catNames[$a['category'] ?? ''] ?? '')?> · <?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($courses): ?>
    <h2 class="text-lg font-bold mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span> 课程</h2>
    <div class="grid gap-3 mb-10 md:grid-cols-2">
      <?php foreach ($courses as $c): ?>
      <a href="/course/<?=htmlspecialchars($c['id'])?>" class="acard">
        <div class="flex items-center justify-between">
          <div class="font-semibold text-gray-900"><?=htmlspecialchars($c['title'] ?? '')?></div>
          <span class="text-xs font-bold text-green-600"><?=($c['price'] ?? 0) ? '¥'.$c['price'] : '免费'?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($skills): ?>
    <h2 class="text-lg font-bold mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span> 分享的技能</h2>
    <div class="grid gap-3 mb-10 md:grid-cols-2">
      <?php foreach ($skills as $s): ?>
      <a href="/marketplace?view=skill&id=<?=htmlspecialchars($s['id'])?>" class="acard">
        <div class="font-semibold text-gray-900"><?=htmlspecialchars($s['title'] ?? '')?></div>
        <div class="text-xs text-gray-400 mt-1"><?=htmlspecialchars($s['type'] ?? '')?> · <?=htmlspecialchars(mb_substr($s['desc'] ?? '', 0, 60))?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($plugins): ?>
    <h2 class="text-lg font-bold mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg></span> 发布的插件</h2>
    <div class="grid gap-3 mb-10 md:grid-cols-2">
      <?php foreach ($plugins as $p): ?>
      <a href="/marketplace?view=plugin&id=<?=htmlspecialchars($p['id'] ?? '')?>" class="acard">
        <div class="font-semibold text-gray-900"><?=htmlspecialchars($p['name'] ?? $p['title'] ?? '')?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$articles && !$courses && !$skills && !$plugins): ?>
    <div class="text-center py-16 text-gray-400">该作者还没有发布内容</div>
    <?php endif; ?>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:1100px">
      <div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
    </div>
  </footer>
</body>
</html>
