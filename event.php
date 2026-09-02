<?php
/**
 * 活动详情页
 *
 * v7（2026-09-01）：迁到共享 archetype（reader + art-head + cols 信息格 + prose）。报名逻辑与接口原样保留。
 * /events/{slug}
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

// 报名状态 + 名额
require_once __DIR__ . '/lib/MemberSystem.php';
$member = member_current();
$regsFile = DATA_DIR . '/event-registrations.json';
$allRegs = json_read($regsFile);
$regList = $allRegs[$event['id']] ?? [];
$myReg = null;
if ($member) { foreach ($regList as $r) { if (($r['member_id'] ?? '') === $member['id']) { $myReg = $r; break; } } }
$capacity = (int)($event['capacity'] ?? 0);
$joinedCount = count(array_filter($regList, fn($r) => ($r['status'] ?? '') !== 'rejected'));
$full = $capacity > 0 && $joinedCount >= $capacity;
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($event['seo_title'] ?? $event['title'])?> | <?=site_config_get('site_name')?></title>
<meta name="description" content="<?=htmlspecialchars($event['seo_desc'] ?? $event['description'] ?? '')?>">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 活动详情页零私有 CSS */

</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('events'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <article class="reader reveal in" data-od-id="event">
    <nav class="art-meta" aria-label="面包屑" style="margin-bottom:18px"><a href="/events" style="color:var(--faint)">← 全部活动</a></nav>
    <?php if ($coverUrl): ?><img class="art-cover" src="<?=htmlspecialchars($coverUrl)?>" alt="<?=htmlspecialchars($event['title'])?>"><?php endif; ?>
    <div class="art-head">
      <div class="art-meta">
        <span class="badge <?=($event['event_type']??'')==='online'?'ok':'warn'?>"><?=($event['event_type']??'')==='online'?'线上':'线下'?></span>
        <span><?=htmlspecialchars(substr($event['start_date'] ?? '', 0, 16))?></span><span class="sep"></span><span><?=htmlspecialchars($event['location'] ?? '')?></span>
      </div>
      <h1><?=htmlspecialchars($event['title'])?></h1>
      <p class="lead" style="color:var(--muted);font-size:16px;line-height:1.85"><?=htmlspecialchars($event['description'] ?? '')?></p>
    </div>

    <div class="cols">
      <div><span class="w-tag">时间</span><h3><?=htmlspecialchars($event['start_date'] ?? '')?></h3></div>
      <div><span class="w-tag">地点</span><h3><?=htmlspecialchars($event['location'] ?? '')?></h3></div>
      <div><span class="w-tag">报名</span>
        <?php if ($myReg): ?>
        <h3 style="color:var(--ok)"><?=['pending'=>'报名审核中','approved'=>'已报名','rejected'=>'报名未通过'][$myReg['status'] ?? 'approved'] ?? '已报名'?></h3>
        <button onclick="cancelReg()" class="btn subtle" style="align-self:flex-start;margin-left:-14px;color:var(--danger)">取消报名</button>
        <?php elseif ($full): ?>
        <h3 style="color:var(--danger)">名额已满</h3>
        <?php elseif ($event['registration_url'] ?? ''): ?>
        <a href="<?=htmlspecialchars($event['registration_url'])?>" class="btn primary" style="align-self:flex-start">立即报名 →</a>
        <?php else: ?>
        <button onclick="doRegister()" class="btn primary" style="align-self:flex-start">立即报名<?=$capacity > 0 ? '（剩 ' . max(0, $capacity - $joinedCount) . ' 席）' : ''?></button>
        <div id="regMsg" class="note"></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($event['replay_url']) || !empty($event['live_room'])): ?>
    <div class="sp-win" style="margin-top:32px">
      <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url"><?=!empty($event['replay_url'])?'replay':'live'?></div></div>
      <?php if (!empty($event['replay_url'])): ?>
      <video controls style="width:100%;aspect-ratio:16/9;display:block;background:var(--fg)" src="<?=htmlspecialchars($event['replay_url'])?>" poster="<?=htmlspecialchars($coverUrl)?>"></video>
      <?php else: ?>
      <div class="empty" style="margin:18px;border:none"><span class="badge danger"><span class="dot"></span>直播进行中</span><div style="margin-top:8px"><?=htmlspecialchars($event['location'] ?? '')?></div></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($event['content'])): ?><div class="prose" style="margin-top:32px"><?= $event['content'] ?></div><?php endif; ?>

    <?php if (!empty($event['speakers'])): ?>
    <div class="sec-head row" style="margin-top:36px"><div><span class="kicker">嘉宾</span></div></div>
    <div class="link-grid" style="grid-template-columns:repeat(2,1fr);margin-top:8px">
      <?php foreach ($event['speakers'] as $sp): ?>
      <div class="link-it"><?php if (!empty($sp['avatar'])): ?><img src="<?=htmlspecialchars(strpos($sp['avatar'],'http')===0?$sp['avatar']:'/'.ltrim($sp['avatar'],'/'))?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover" alt="" onerror="this.remove()"><?php else: ?><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg></span><?php endif; ?><span class="lt"><b><?=htmlspecialchars($sp['name'] ?? '')?></b><span><?=htmlspecialchars($sp['title'] ?? '')?></span></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </article>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
  var EVENT_ID = <?=json_encode($event['id'])?>;
  var IS_LOGGED = <?=$member ? 'true' : 'false'?>;
  function regFetch(action) {
    var fd = new FormData(); fd.append('action', action); fd.append('event_id', EVENT_ID);
    return fetch('/api/event-register', { method:'POST', body: fd }).then(function(r){ return r.json(); });
  }
  function doRegister() {
    var msg = document.getElementById('regMsg');
    if (!IS_LOGGED) { location.href = '/account?view=login&next=/events/' + <?=json_encode($slug)?>; return; }
    if (!confirm('确认报名该活动？')) return;
    msg.textContent = '提交中…'; msg.style.color = 'var(--muted)';
    regFetch('register').then(function(d){ msg.textContent = d.message || d.error; msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)'; if (d.ok) setTimeout(function(){ location.reload(); }, 1000); });
  }
  function cancelReg() {
    if (!confirm('确认取消报名？')) return;
    regFetch('cancel').then(function(d){ if (d.ok) location.reload(); else alert(d.error); });
  }
  </script>
</body>
</html>
