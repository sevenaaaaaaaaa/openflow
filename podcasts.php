<?php
/**
 * 播客/视频 — 前台展示 + 播放 + RSS 订阅
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（300 秒）
if (PageCache::begin('podcasts', 300)) exit;

$pods = json_read(DATA_DIR . '/podcasts.json');
$items = array_values(array_filter($pods['items'] ?? [], fn($p) => ($p['status'] ?? 'published') === 'published'));
$categories = $pods['categories'] ?? [];
$cat = req_str('cat');
$playId = req_str('play');
$siteBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');

// ─── RSS 订阅源 ───
if (req_str('rss') !== '') {
    header('Content-Type: application/rss+xml; charset=utf-8');
    $siteName = site_config_get('site_name');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    ?>
    <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
      <title><?=htmlspecialchars($siteName)?> 播客</title>
      <link><?=htmlspecialchars($siteBase)?>/podcasts.php</link>
      <description><?=htmlspecialchars(site_config_get('site_slogan', '网站增长与 AI 运营'))?></description>
      <language>zh-cn</language>
      <atom:link href="<?=htmlspecialchars($siteBase)?>/podcasts?rss=1" rel="self" type="application/rss+xml"/>
      <?php foreach ($items as $p):
        $mediaUrl = htmlspecialchars(strpos($p['file']??'','http')===0?$p['file']:($siteBase.'/'.$p['file']));
        $guid = htmlspecialchars($p['id']);
      ?>
      <item>
        <title><?=htmlspecialchars($p['title'] ?? '')?></title>
        <link><?=htmlspecialchars($siteBase)?>/podcasts?play=<?=urlencode($p['id'])?></link>
        <guid isPermaLink="false"><?=$guid?></guid>
        <pubDate><?=htmlspecialchars(date('r', strtotime($p['created_at'] ?? 'now')))?></pubDate>
        <description><?=htmlspecialchars(mb_substr($p['description'] ?? '', 0, 500))?></description>
        <enclosure url="<?=$mediaUrl?>" type="<?=$p['type']==='video'?'video/mp4':'audio/mpeg'?>" length="0"/>
        <?php if (!empty($p['duration'])): ?><itunes:duration><?=htmlspecialchars($p['duration'])?></itunes:duration><?php endif; ?>
        <?php if (!empty($p['cover'])): ?><itunes:image href="<?=htmlspecialchars(strpos($p['cover'],'http')===0?$p['cover']:($siteBase.'/'.$p['cover']))?>"/><?php endif; ?>
      </item>
      <?php endforeach; ?>
    </channel>
    </rss>
    <?php
    exit;
}

if ($cat) $items = array_values(array_filter($items, fn($p) => ($p['category'] ?? '') === $cat));
usort($items, fn($a,$b) => strcmp($b['created_at']??'', $a['created_at']??''));

$playing = null;
if ($playId) foreach ($items as $p) if ($p['id'] === $playId) { $playing = $p; break; }
if (!$playing && !empty($items)) $playing = $items[0];

$featured = array_values(array_filter($items, fn($p) => !empty($p['featured'])));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>播客与视频 | <?=site_config_get("site_name")?></title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .pod-item{display:flex;gap:14px;align-items:center;padding:14px;border-radius:12px;cursor:pointer;transition:.12s;border:1px solid transparent}
  .pod-item:hover{background:var(--surface);border-color:var(--border)}
  .pod-item.active{background:var(--fg);color:var(--bg)}
  .pod-thumb{width:64px;height:64px;border-radius:12px;display:grid;place-items:center;font-size:26px;flex-shrink:0;background:var(--bg)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260813ad" data-cfasync="false" data-page="articles"></script>

  <div class="mx-auto px-5 py-8" style="max-width:1200px">
    <div style="display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(24px,4vw,48px);align-items:center;margin-bottom:24px">
      <div style="display:flex;flex-direction:column;gap:14px">
        <span class="kicker" style="font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;color:var(--accent);text-transform:uppercase">PODCAST · 播客视频</span>
        <h1 style="font-size:clamp(28px,4.5vw,44px);font-weight:800;letter-spacing:-.035em;line-height:1.1;color:var(--fg)">用耳朵<span style="font-family:var(--font-display);font-style:italic">学增长</span></h1>
        <p style="color:var(--muted);font-size:15px;line-height:1.8;max-width:520px">网站增长、AI 运营与内容营销的音频与视频内容。通勤路上，也能追上增长前沿。</p>
        <div style="display:flex;gap:18px;margin-top:6px;color:var(--faint);font-size:12.5px;flex-wrap:wrap">
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14v-2a9 9 0 0 1 18 0v2"/><rect x="3" y="14" width="4" height="6" rx="2"/><rect x="17" y="14" width="4" height="6" rx="2"/></svg></span> <b style="color:var(--fg)"><?=count($items)?></b> 期内容</span>
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg></span> 视频 · <span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg></span> 音频</span>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <?php $pcats = array_slice($categories, 0, 4); $picons = ['🎙️', '🎬', '📢', '💡']; $pi = 0; foreach ($pcats as $pc): ?>
        <a href="?cat=<?=urlencode($pc)?>" style="display:flex;flex-direction:column;gap:10px;padding:18px;border-radius:var(--r-md);background:var(--surface);border:1px solid var(--border);backdrop-filter:blur(16px) saturate(150%);text-decoration:none;transition:transform .25s var(--ease-spring),box-shadow .25s,border-color .25s" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-sm)';this.style.borderColor='var(--border-strong)'" onmouseout="this.style.transform='none';this.style.boxShadow='none';this.style.borderColor='var(--border)'">
          <span style="width:38px;height:38px;border-radius:12px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:18px"><?=$picons[$pi] ?? '🎙️'?></span>
          <b style="font-size:14.5px;color:var(--fg)"><?=htmlspecialchars($pc)?></b>
          <span style="font-size:12px;color:var(--muted)">查看该分类内容</span>
        </a>
        <?php $pi++; endforeach; ?>
      </div>
    </div>

    <!-- 分类筛选 -->
    <?php if ($categories): ?>
    <div class="flex gap-2 mb-6 flex-wrap justify-center">
      <a href="podcasts" class="cat-pill <?=!$cat?'active':''?>" style="padding:6px 14px;border-radius:999px;border:1px solid var(--border);background:var(--surface);font-size:13px;text-decoration:none;color:var(--muted)">全部</a>
      <?php foreach ($categories as $c): ?>
      <a href="podcasts?cat=<?=urlencode($c)?>" class="cat-pill <?=$cat===$c?'active':''?>" style="padding:6px 14px;border-radius:999px;border:1px solid var(--border);background:<?=$cat===$c?'var(--accent)':'var(--surface)'?>;font-size:13px;text-decoration:none;color:var(--muted)"><?=htmlspecialchars($c)?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
    <div class="text-center py-16 text-gray-600">暂无内容，敬请期待。</div>
    <?php else: ?>
    <div class="grid gap-6" style="grid-template-columns:1fr 360px;align-items:start">
      <!-- 播放器 -->
      <div class="card p-6" style="background:var(--surface);border:1px solid var(--border);border-radius:16px">
        <?php if ($playing): ?>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
          <div style="width:72px;height:72px;border-radius:16px;display:grid;place-items:center;font-size:30px;background:linear-gradient(135deg,var(--accent),var(--ok))"><?=$playing['type']==='video'?'🎬':'🎵'?></div>
          <div>
            <h2 class="text-xl font-bold"><?=htmlspecialchars($playing['title'])?></h2>
            <div class="text-sm text-gray-600 mt-1"><?=htmlspecialchars($playing['category'] ?? '')?> · <?=$playing['type']==='video'?'视频':'音频'?></div>
          </div>
        </div>
        <?php if ($playing['type'] === 'video'): ?>
        <video controls style="width:100%;border-radius:12px;background:#000" src="<?=htmlspecialchars(strpos($playing['file'],'http')===0?$playing['file']:$siteBase.'/'.ltrim($playing['file'],'/'))?>"></video>
        <?php else: ?>
        <audio controls style="width:100%" src="<?=htmlspecialchars(strpos($playing['file'],'http')===0?$playing['file']:$siteBase.'/'.ltrim($playing['file'],'/'))?>"></audio>
        <?php endif; ?>
        <p class="text-sm text-gray-600 mt-4 leading-relaxed"><?=htmlspecialchars($playing['description'] ?? '')?></p>
        <?php endif; ?>
      </div>

      <!-- 列表 -->
      <div class="card p-3" style="background:var(--surface);border:1px solid var(--border);border-radius:16px">
        <div class="font-bold text-sm px-2 py-2">播放列表</div>
        <?php foreach ($items as $p): ?>
        <div class="pod-item <?=$playing && $playing['id']===$p['id']?'active':''?>" onclick="location.href='/podcasts<?=htmlspecialchars($cat?'?cat='.urlencode($cat).'&': '?')?>play=<?=urlencode($p['id'])?>'">
          <div class="pod-thumb"><?=$p['type']==='video'?'🎬':'🎵'?></div>
          <div class="min-w-0">
            <div class="font-semibold text-sm truncate"><?=htmlspecialchars($p['title'])?></div>
            <div class="text-xs opacity-60"><?=htmlspecialchars($p['category'] ?? '')?> · <?=htmlspecialchars(substr($p['created_at']??'',0,10))?></div>
            <?php if (!empty($p['tags'])): ?><div class="flex gap-1 mt-1 flex-wrap"><?php foreach (array_slice($p['tags'],0,3) as $t): ?><span class="px-1.5 py-px rounded text-[10px] font-semibold" style="background:var(--accent-soft);color:var(--accent)">#<?=htmlspecialchars($t)?></span><?php endforeach; ?></div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)"><div class="mx-auto px-5 text-center text-sm" style="max-width:1100px"><div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div><div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div></div></footer>
</body>
</html>
<?php PageCache::end('podcasts', 300); ?>
