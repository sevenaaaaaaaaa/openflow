<?php
/**
 * 播客/视频 — 前台展示 + 播放 + RSS 订阅
 *
 * v7（2026-09-01）：迁到共享 archetype（双栏 hero + tab 筛选 + 播放器 .g-main-aside + .rank 播放列表）。RSS 与播放逻辑原样保留。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（300 秒）
if (PageCache::begin('podcasts', 1800)) exit;

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
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>播客与视频 | <?=site_config_get("site_name")?></title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 播客独有：播放器头、播放列表项。其余全部来自 modules.css。 */
.player-hd{display:flex;align-items:center;gap:16px;margin-bottom:18px}
.player-hd .em{width:64px;height:64px;border-radius:16px;display:grid;place-items:center;background:var(--accent-soft);color:var(--accent);flex:0 0 auto}
.player-hd .em svg{width:26px;height:26px}
.player-hd h2{font-size:20px;font-weight:800;letter-spacing:-.01em;line-height:1.35}
.player-hd .sub{font-family:var(--font-mono);font-size:12px;color:var(--faint);margin-top:4px}
.player video,.player audio{width:100%;border-radius:12px}
.player video{background:var(--fg)}
.player p{font-size:14.5px;color:var(--muted);line-height:1.8;margin-top:16px}
.rank a.playing{background:var(--accent-soft)}
.rank a.playing .t b{color:var(--accent-strong)}
.rank .n svg{width:16px;height:16px;margin:0 auto}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('podcasts'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section id="top" class="reveal in" data-od-anchor data-od-id="podcasts-hero">
    <div class="hero">
      <div class="hero-copy">
        <span class="kicker">PODCAST · 播客视频</span>
        <h1>用耳朵<i class="si">学增长</i></h1>
        <p class="lead">网站增长、AI 运营与内容营销的音频与视频内容。通勤路上，也能追上增长前沿。</p>
        <div class="cta-row"><a class="btn ghost" href="?rss=1">RSS 订阅</a></div>
      </div>
      <div class="hero-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">podcasts · <?=count($items)?> 期</div></div>
        <div class="win-flow">
          <?php $pcats = array_slice($categories, 0, 4); foreach ($pcats as $k => $pc): if ($k) echo '<div class="flow-link"></div>'; ?>
          <a class="flow-row" href="?cat=<?=urlencode($pc)?>"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></span><div><div class="ft"><?=htmlspecialchars($pc)?></div><div class="fd">查看该分类内容</div></div></a>
          <?php endforeach; ?>
          <?php if (empty($pcats)): ?><div class="empty" style="margin:18px">分类整理中</div><?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section id="list" class="sec reveal" data-od-anchor data-od-id="podcasts-list">
    <?php if ($categories): ?>
    <div class="tab-bar" role="navigation" aria-label="分类">
      <a class="tab-p" href="podcasts" aria-selected="<?=!$cat?'true':'false'?>">全部</a>
      <?php foreach ($categories as $c): ?><a class="tab-p" href="podcasts?cat=<?=urlencode($c)?>" aria-selected="<?=$cat===$c?'true':'false'?>"><?=htmlspecialchars($c)?></a><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (empty($items)): ?>
    <div class="empty">暂无内容，敬请期待。</div>
    <?php else: ?>
    <div class="g-main-aside">
      <div class="card player">
        <?php if ($playing): $src = strpos($playing['file'],'http')===0?$playing['file']:$siteBase.'/'.ltrim($playing['file'],'/'); ?>
        <div class="player-hd">
          <span class="em"><?php if ($playing['type']==='video'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg><?php endif; ?></span>
          <div><h2><?=htmlspecialchars($playing['title'])?></h2><div class="sub"><?=htmlspecialchars($playing['category'] ?? '')?> · <?=$playing['type']==='video'?'视频':'音频'?></div></div>
        </div>
        <?php if ($playing['type'] === 'video'): ?><video controls src="<?=htmlspecialchars($src)?>"></video><?php else: ?><audio controls src="<?=htmlspecialchars($src)?>"></audio><?php endif; ?>
        <p><?=htmlspecialchars($playing['description'] ?? '')?></p>
        <?php endif; ?>
      </div>
      <aside>
        <div class="aside-box">
          <h3>播放列表</h3>
          <div class="rank">
            <?php foreach ($items as $i => $p): $on = $playing && $playing['id']===$p['id']; ?>
            <a class="<?=$on?'playing':''?>" href="/podcasts<?=htmlspecialchars($cat?'?cat='.urlencode($cat).'&': '?')?>play=<?=urlencode($p['id'])?>"><span class="n"><?=$i+1?></span><span class="t"><b><?=htmlspecialchars($p['title'])?></b><span><?=htmlspecialchars($p['category'] ?? '')?> · <?=htmlspecialchars(substr($p['created_at']??'',0,10))?></span></span></a>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>
    </div>
    <?php endif; ?>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
<?php PageCache::end('podcasts', 1800); ?>
