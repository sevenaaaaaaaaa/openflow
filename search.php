<?php
/**
 * 前台搜索结果页 — 相关话题/课程/文章/资料/技能
 * /search?q=关键词
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$q = trim(req_str('q'));
$results = ['ok' => false];
if ($q) {
    // 直接本地调用搜索逻辑，避免依赖 site_url 的 HTTP 自调用（本地/线上均可）
    require_once __DIR__ . '/lib/SearchEngine.php';
    $results = SearchEngine::search($q);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>搜索「<?=htmlspecialchars($q)?>」 | <?=site_config_get('site_name')?></title>
<meta name="description" content="搜索 <?=htmlspecialchars($q)?> 相关文章、专题、课程、资料与技能">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .res-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:16px 18px;transition:.15s;display:block;text-decoration:none;color:inherit}
  .res-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(30,30,30,.08);border-color:var(--accent)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260816" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-10" style="max-width:1100px">
    <?php if (!$q): ?>
    <div class="text-center py-20 text-gray-400">请输入关键词搜索</div>
    <?php elseif (empty($results['ok'])): ?>
    <div class="text-center py-20 text-gray-400">搜索服务暂不可用，请稍后再试</div>
    <?php else: ?>
    <h1 class="text-2xl font-bold mb-2">「<?=htmlspecialchars($q)?>」的搜索结果</h1>
    <p class="text-sm text-gray-600 mb-8">
      文章 <?=count($results['articles'])?> · 专题 <?=count($results['topics'])?> · 课程 <?=count($results['courses'])?> · 资料 <?=count($results['downloads'])?> · 技能 <?=count($results['skills'])?>
    </p>

    <?php if ($results['articles']): ?>
    <h2 class="text-lg font-bold mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span> 相关文章</h2>
    <div class="grid gap-3 mb-10 md:grid-cols-2">
      <?php foreach ($results['articles'] as $a): ?>
      <a href="/article/<?=htmlspecialchars($a['slug'])?>" class="res-card">
        <div class="font-semibold text-gray-900"><?=htmlspecialchars($a['title'])?></div>
        <div class="text-xs text-gray-400 mt-1"><?=htmlspecialchars($a['category'] ?? '')?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($results['topics']): ?>
    <h2 class="text-lg font-bold mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span> 相关专题</h2>
    <div class="grid gap-3 mb-10 md:grid-cols-2">
      <?php foreach ($results['topics'] as $t): ?>
      <a href="/topic/<?=htmlspecialchars($t['slug'])?>" class="res-card">
        <div class="font-semibold text-gray-900"><?=htmlspecialchars($t['title'])?></div>
        <div class="text-xs text-gray-600 mt-1 line-clamp-2"><?=htmlspecialchars($t['description'] ?? '')?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($results['courses']): ?>
    <h2 class="text-lg font-bold mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span> 相关课程</h2>
    <div class="grid gap-3 mb-10 md:grid-cols-2">
      <?php foreach ($results['courses'] as $c): ?>
      <a href="/course/<?=htmlspecialchars($c['id'])?>" class="res-card">
        <div class="flex items-center justify-between">
          <div class="font-semibold text-gray-900"><?=htmlspecialchars($c['title'])?></div>
          <span class="text-xs font-bold text-green-600"><?=$c['price'] ? '¥'.$c['price'] : '免费'?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($results['downloads']): ?>
    <h2 class="text-lg font-bold mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg></span> 相关资料</h2>
    <div class="grid gap-3 mb-10 md:grid-cols-2">
      <?php foreach ($results['downloads'] as $d): ?>
      <a href="/downloads" class="res-card">
        <div class="font-semibold text-gray-900"><?=htmlspecialchars($d['title'])?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($results['skills']): ?>
    <h2 class="text-lg font-bold mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span> 相关技能</h2>
    <div class="grid gap-3 mb-10 md:grid-cols-2">
      <?php foreach ($results['skills'] as $s): ?>
      <a href="/marketplace?view=skill&id=<?=htmlspecialchars($s['id'])?>" class="res-card">
        <div class="font-semibold text-gray-900"><?=htmlspecialchars($s['title'])?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$results['articles'] && !$results['topics'] && !$results['courses'] && !$results['downloads'] && !$results['skills']): ?>
    <div class="text-center py-16 text-gray-400">没有找到与「<?=htmlspecialchars($q)?>」相关的内容，换个关键词试试。</div>
    <?php endif; ?>
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
