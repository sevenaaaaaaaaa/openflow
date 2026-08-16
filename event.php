<?php
/**
 * 活动详情页
 * /event/{slug}
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$slug = trim(req_str('slug'));
$event = null;
foreach (json_read(DATA_DIR . '/events/index.json') as $e) {
    if (($e['slug'] ?? '') === $slug && ($e['status'] ?? 'draft') === 'published') { $event = $e; break; }
}
if (!$event) { http_response_code(404); header('Location: /'); exit; }

$cover = $event['cover'] ?? '';
$coverUrl = $cover ? (strpos($cover, 'http') === 0 ? $cover : '/' . ltrim($cover, '/')) : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($event['seo_title'] ?? $event['title'])?> | <?=site_config_get('site_name')?></title>
<meta name="description" content="<?=htmlspecialchars($event['seo_desc'] ?? $event['description'] ?? '')?>">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260813ad" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-10" style="max-width:1000px">
    <a href="/academy" class="text-sm text-[#2b5f7e]">← 返回社区</a>
    <?php if ($coverUrl): ?>
    <img src="<?=htmlspecialchars($coverUrl)?>" alt="<?=htmlspecialchars($event['title'])?>" class="w-full rounded-3xl mt-4 object-cover" style="max-height:380px">
    <?php endif; ?>

    <div class="rounded-3xl p-8 mt-6" style="background:var(--surface);border:1px solid var(--border)">
      <span class="text-xs px-3 py-1 rounded-full" style="background:var(--ok-soft);color:var(--ok)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h18l-2 12H5L3 7Z"/><path d="M5 7l1.5-3h11L19 7M12 7v12"/></svg></span> 活动</span>
      <h1 class="text-3xl font-extrabold text-gray-900 mt-3"><?=htmlspecialchars($event['title'])?></h1>
      <p class="text-gray-600 mt-3 leading-relaxed"><?=htmlspecialchars($event['description'] ?? '')?></p>

      <div class="grid gap-4 mt-6 sm:grid-cols-3">
        <div class="rounded-2xl px-5 py-4" style="background:var(--bg)">
          <div class="text-xs text-gray-400">时间</div>
          <div class="font-semibold mt-1"><?=htmlspecialchars($event['start_date'] ?? '')?></div>
        </div>
        <div class="rounded-2xl px-5 py-4" style="background:var(--bg)">
          <div class="text-xs text-gray-400">地点</div>
          <div class="font-semibold mt-1"><?=htmlspecialchars($event['location'] ?? '')?></div>
        </div>
        <div class="rounded-2xl px-5 py-4 flex items-center justify-center" style="background:var(--ok-soft)">
          <a href="<?=htmlspecialchars($event['registration_url'] ?? '/index.html#contact')?>" class="inline-block rounded-full bg-[var(--accent)] text-white px-8 py-3 font-semibold">立即报名 →</a>
        </div>
      </div>

      <?php if (!empty($event['content'])): ?>
      <div class="prose mt-8" style="line-height:1.9;color:var(--muted)"><?= $event['content'] ?></div>
      <?php endif; ?>

      <?php if (!empty($event['speakers'])): ?>
      <h2 class="text-lg font-bold mt-8 mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.2 2.7-5 6-5s6 1.8 6 5"/><path d="M16 4.5a3.2 3.2 0 0 1 0 6.5M18 15.5c2 .8 3 2.3 3 4.5"/></svg></span> 嘉宾</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <?php foreach ($event['speakers'] as $sp): ?>
        <div class="flex items-center gap-4 rounded-2xl px-5 py-4" style="background:var(--bg)">
          <?php if (!empty($sp['avatar'])): ?><img src="<?=htmlspecialchars(strpos($sp['avatar'],'http')===0?$sp['avatar']:'/'.ltrim($sp['avatar'],'/'))?>" class="w-14 h-14 rounded-full object-cover" alt="" onerror="this.style.display='none'"><?php endif; ?>
          <div>
            <div class="font-bold text-gray-900"><?=htmlspecialchars($sp['name'] ?? '')?></div>
            <div class="text-xs text-gray-400 mt-1"><?=htmlspecialchars($sp['title'] ?? '')?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:1000px">
      <div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
    </div>
  </footer>
</body>
</html>
