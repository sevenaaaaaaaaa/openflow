<?php
/**
 * 活动列表页 /events
 * 展示线上/线下活动，可报名，含筛选
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$now = time();
$events = array_values(array_filter(json_read(DATA_DIR . '/events/index.json'), fn($e) => ($e['status'] ?? '') === 'published'));
usort($events, fn($a, $b) => strcmp($a['start_date'] ?? '', $b['start_date'] ?? ''));

$typeFilter = $_GET['type'] ?? '';
if ($typeFilter === 'upcoming') $events = array_values(array_filter($events, fn($e) => strtotime($e['end_date'] ?? $e['start_date'] ?? '2000-01-01') >= $now));
if ($typeFilter === 'past') $events = array_values(array_filter($events, fn($e) => strtotime($e['end_date'] ?? $e['start_date'] ?? '2000-01-01') < $now));
if ($typeFilter === 'online') $events = array_values(array_filter($events, fn($e) => ($e['event_type'] ?? '') === 'online'));
if ($typeFilter === 'offline') $events = array_values(array_filter($events, fn($e) => ($e['event_type'] ?? '') === 'offline'));

$siteName = site_config_get('site_name', 'OpenFlow');
$siteSlogan = site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>活动 · <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="<?=htmlspecialchars($siteSlogan)?> 的线上直播与线下活动，报名参加获得一手增长打法。">
<link rel="stylesheet" href="/assets/site.css">
<link rel="stylesheet" href="/assets/tailwind-build.css">
<script src="/assets/inject.js?v=20260830b" defer></script>
<script src="/assets/site-shell.js?v=20260826b" defer></script>
<style>
  body{background:var(--bg);color:var(--fg)}
  .ev-card{display:flex;gap:18px;padding:22px;border-radius:18px;border:1px solid var(--border);background:var(--surface);transition:.15s}
  .ev-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm)}
  .ev-date{min-width:76px;text-align:center;padding:12px;border-radius:14px;background:var(--accent-soft);color:var(--accent)}
</style>
</head>
<body>
<main class="mx-auto px-5 py-12" style="max-width:1080px">
  <div class="mb-10 text-center">
    <h1 class="text-3xl font-extrabold">🎪 活动</h1>
    <p class="text-muted mt-2">线上直播 / 线下聚会 · 报名即获增长打法</p>
    <div class="flex justify-center gap-2 mt-5" style="flex-wrap:wrap">
      <?php $tabs = [''=>'全部','upcoming'=>'即将开始','online'=>'线上','offline'=>'线下','past'=>'往期']; foreach ($tabs as $k=>$v): ?>
      <a href="?type=<?=$k?>" style="padding:7px 18px;border-radius:999px;font-size:13px;border:1.5px solid var(--border);<?=$typeFilter===$k?'background:var(--accent);color:var(--on-accent);border-color:var(--accent)':''?>"><?=$v?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($events)): ?><div class="text-center py-20 text-muted">暂无活动，敬请期待</div><?php endif; ?>
  <div class="grid gap-5">
    <?php foreach ($events as $e): $sd = strtotime($e['start_date'] ?? ''); $ed = strtotime($e['end_date'] ?? ''); ?>
    <a href="/events/<?=urlencode($e['slug'])?>" class="ev-card">
      <div class="ev-date">
        <div style="font-size:24px;font-weight:800"><?=$sd ? date('d', $sd) : '--'?></div>
        <div style="font-size:11px"><?=$sd ? date('M', $sd) : ''?></div>
      </div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <h2 style="font-size:17px;font-weight:700;margin:0"><?=htmlspecialchars($e['title'])?></h2>
          <span style="font-size:11px;padding:2px 8px;border-radius:999px;<?=($e['event_type']??'')==='online'?'background:var(--accent-soft);color:var(--accent)':'background:var(--ok-soft);color:var(--ok)'?>"><?=($e['event_type']??'')==='online'?'线上直播':'线下活动'?></span>
          <?php if ($ed < $now): ?><span style="font-size:11px;color:var(--faint)">已结束</span><?php elseif ($sd <= $now): ?><span style="font-size:11px;color:var(--ok)">进行中</span><?php endif; ?>
        </div>
        <p class="text-muted text-sm mt-2" style="margin:6px 0 0"><?=htmlspecialchars(mb_substr($e['description'] ?? '', 0, 120))?></p>
        <div style="font-size:12px;color:var(--faint);margin-top:10px">🕒 <?=htmlspecialchars(substr($e['start_date'] ?? '', 0, 16))?> · 📍 <?=htmlspecialchars($e['location'] ?? '')?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</main>
<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
  <div class="mx-auto px-5 text-center text-sm" style="max-width:1100px">
    <div class="mb-2"><?=htmlspecialchars($siteName)?> · <?=htmlspecialchars($siteSlogan)?></div>
    <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
  </div>
</footer>
</body>
</html>
