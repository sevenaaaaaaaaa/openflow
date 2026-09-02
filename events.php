<?php
/**
 * 活动列表页 /events
 * 展示线上/线下活动，可报名，含筛选
 *
 * v7（2026-09-01）：从 tailwind 80 行空壳迁到共享 archetype（方案 A：日期块 + 杂志行）。
 * 数据与筛选逻辑原样保留；空状态给出去向（社区 / 课程），不再只是一句「敬请期待」。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$now = time();
$events = array_values(array_filter(json_read(DATA_DIR . '/events/index.json'), fn($e) => ($e['status'] ?? '') === 'published'));
// 排序：未结束的活动按开始时间升序排在前面（最近要开的最先），已结束的按时间倒序排在后面
$evEnd = fn($e) => strtotime($e['end_date'] ?? $e['start_date'] ?? '2000-01-01');
usort($events, function ($a, $b) use ($now, $evEnd) {
    $ua = $evEnd($a) >= $now ? 0 : 1; $ub = $evEnd($b) >= $now ? 0 : 1;
    if ($ua !== $ub) return $ua <=> $ub;
    return $ua === 0 ? strcmp($a['start_date'] ?? '', $b['start_date'] ?? '') : strcmp($b['start_date'] ?? '', $a['start_date'] ?? '');
});

$typeFilter = $_GET['type'] ?? '';
if ($typeFilter === 'upcoming') $events = array_values(array_filter($events, fn($e) => strtotime($e['end_date'] ?? $e['start_date'] ?? '2000-01-01') >= $now));
if ($typeFilter === 'past') $events = array_values(array_filter($events, fn($e) => strtotime($e['end_date'] ?? $e['start_date'] ?? '2000-01-01') < $now));
if ($typeFilter === 'online') $events = array_values(array_filter($events, fn($e) => ($e['event_type'] ?? '') === 'online'));
if ($typeFilter === 'offline') $events = array_values(array_filter($events, fn($e) => ($e['event_type'] ?? '') === 'offline'));

$siteName = site_config_get('site_name', 'OpenFlow');
$siteSlogan = site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统');
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>活动 · 线上直播 / 线下聚会 | <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="芭乐派活动：线上直播、线下聚会。和同类人碰个面，报名即获增长打法。">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260902b">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260902b">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260902b">
<style>
/* 活动页独有：活动行（日期块 + 标题 + 元信息）。其余全部来自 modules.css。 */
.ev-list{display:flex;flex-direction:column;border-top:1px solid var(--border-soft)}
.ev{display:grid;grid-template-columns:96px minmax(0,1fr) auto;gap:clamp(16px,3vw,32px);align-items:center;padding:24px 8px;border-bottom:1px solid var(--border-soft);transition:background .2s;border-radius:12px}
.ev:hover{background:var(--hover)}
.ev-date{text-align:center;display:flex;flex-direction:column;align-items:center;gap:2px;padding:12px 0;border-radius:var(--r-sm);background:var(--accent-soft);color:var(--accent-strong)}
.ev-date b{font-family:var(--font-display);font-size:30px;font-weight:700;line-height:1;letter-spacing:-.02em}
.ev-date span{font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
.ev.past .ev-date{background:var(--hover);color:var(--faint)}
.ev-body{display:flex;flex-direction:column;gap:8px;min-width:0}
.ev-body .row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.ev-body h3{font-size:18px;font-weight:700;letter-spacing:-.01em;line-height:1.4;transition:color .2s}
.ev:hover .ev-body h3{color:var(--accent)}
.ev-body p{font-size:14px;color:var(--muted);line-height:1.75}
.ev-meta{display:flex;gap:14px;flex-wrap:wrap;font-family:var(--font-mono);font-size:12px;color:var(--faint)}
.ev-meta span{display:inline-flex;align-items:center;gap:5px}
.ev-meta svg{width:13px;height:13px}
.ev-go{display:inline-flex;align-items:center;gap:6px;font-size:13.5px;font-weight:600;color:var(--accent);white-space:nowrap}
.ev-go svg{width:15px;height:15px}
@media (max-width:860px){.ev{grid-template-columns:72px 1fr}.ev-go{grid-column:2;justify-self:start}.ev-date b{font-size:24px}}
</style>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('events'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="events-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">芭乐派 · 活动</span>
      <h1>和<i class="si">同类人</i>碰个面</h1>
      <p class="lead">线上直播 / 线下聚会 · 报名即获增长打法</p>
      <div class="tab-bar" role="navigation" aria-label="活动筛选">
        <?php $tabs = [''=>'全部','upcoming'=>'即将开始','online'=>'线上','offline'=>'线下','past'=>'往期']; foreach ($tabs as $k=>$v): ?>
        <a class="tab-p" href="?type=<?=$k?>" aria-selected="<?=$typeFilter===$k?'true':'false'?>"><?=$v?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ══ 活动列表 ══ -->
  <section id="list" class="sec reveal" data-od-anchor data-od-id="events-list">
    <?php if (empty($events)): ?>
    <div class="cta-band">
      <span class="kicker">暂无活动</span>
      <h2>暂无活动，敬请期待</h2>
      <p class="lead">下一场活动开放报名时，门派社区会第一时间通知。在此之前，先把地基打好。</p>
      <div class="cta-row"><a class="btn primary" href="/community">进入门派社区</a><a class="btn ghost" href="/courses">先看课程</a></div>
    </div>
    <?php else: ?>
    <div class="ev-list">
      <?php foreach ($events as $e): $sd = strtotime($e['start_date'] ?? ''); $ed = strtotime($e['end_date'] ?? ''); $past = $ed && $ed < $now; $online = ($e['event_type']??'')==='online'; ?>
      <a href="/events/<?=urlencode($e['slug'])?>" class="ev<?=$past?' past':''?>">
        <div class="ev-date"><b><?=$sd ? date('d', $sd) : '--'?></b><span><?=$sd ? date('M', $sd) : ''?></span></div>
        <div class="ev-body">
          <div class="row">
            <span class="badge <?=$online?'ok':'warn'?>"><?=$online?'线上':'线下'?></span>
            <?php if ($past): ?><span class="badge" style="background:var(--hover);color:var(--faint)">已结束</span><?php elseif ($sd && $sd <= $now): ?><span class="badge ok"><span class="dot"></span>进行中</span><?php endif; ?>
          </div>
          <h3><?=htmlspecialchars($e['title'])?></h3>
          <p><?=htmlspecialchars(mb_substr($e['description'] ?? '', 0, 120))?></p>
          <div class="ev-meta">
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg><?=htmlspecialchars(substr($e['start_date'] ?? '', 0, 16))?></span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11.5a7 7 0 1 0-14 0C5 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg><?=htmlspecialchars($e['location'] ?? '')?></span>
          </div>
        </div>
        <span class="ev-go"><?=$past?'看回顾':'查看并报名'?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
