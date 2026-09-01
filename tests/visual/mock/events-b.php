<?php require_once __DIR__ . '/../../../admin/config.php'; require_once __DIR__ . '/_shell.php';
$events = json_decode(file_get_contents(DATA_DIR . '/events/index.json'), true);
$now = time(); usort($events, fn($a,$b)=>strcmp($a['start_date'],$b['start_date']));
$up = array_values(array_filter($events, fn($e)=>strtotime($e['end_date'])>=$now)); $past = array_values(array_filter($events, fn($e)=>strtotime($e['end_date'])<$now));
$next = array_shift($up);
mock_head('活动 · 方案 B', '.ev-big-date{display:flex;align-items:baseline;gap:8px;font-family:var(--font-display)}.ev-big-date b{font-size:56px;font-weight:700;line-height:1;letter-spacing:-.03em;color:var(--accent-strong)}.ev-big-date span{font-family:var(--font-mono);font-size:13px;letter-spacing:.1em;color:var(--faint);text-transform:uppercase}.scn-row .when{font-family:var(--font-mono);font-size:11.5px;color:var(--accent);margin-bottom:4px;display:block}.cols .when{font-family:var(--font-mono);font-size:12px;color:var(--faint)}');
of_shell('events'); ?>
<main id="main">
  <section id="top" class="reveal in">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">芭乐派 · 活动</span>
      <h1>和<i class="si">同类人</i>碰个面</h1>
      <p class="lead">线上直播 / 线下聚会 · 报名即获增长打法</p>
    </div>
  </section>
  <section class="sec reveal in">
    <div class="sec-head center"><span class="kicker">下一场</span><h2>最近的一场，先看这个</h2></div>
    <div class="scn">
      <div class="scn-f">
        <span class="f-tag"><?=$next['event_type']==='online'?'线上直播':'线下聚会'?> · <?=htmlspecialchars($next['location'])?></span>
        <div class="ev-big-date"><b><?=date('d',strtotime($next['start_date']))?></b><span><?=date('M · l',strtotime($next['start_date']))?> · <?=substr($next['start_date'],11,5)?></span></div>
        <h3><?=htmlspecialchars($next['title'])?></h3>
        <p><?=htmlspecialchars($next['description'])?></p>
        <div class="cta-row"><a class="btn primary" href="#">查看并报名</a><a class="btn subtle" href="#">加到日历 →</a></div>
      </div>
      <div class="scn-s">
        <?php foreach ($up as $e): ?>
        <a class="scn-row" href="#"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/></svg></span><div><span class="when"><?=substr($e['start_date'],5,11)?> · <?=$e['event_type']==='online'?'线上':htmlspecialchars($e['location'])?></span><h3><?=htmlspecialchars($e['title'])?></h3><p><?=htmlspecialchars(mb_substr($e['description'],0,60))?>…</p></div></a>
        <?php endforeach; ?>
        <a class="scn-row" href="#"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span><div><span class="when">随时</span><h3>想办一场？</h3><p>门派成员可以发起线下聚会，我们帮你张罗。</p></div></a>
      </div>
    </div>
  </section>
  <section class="sec reveal in">
    <div class="sec-head center"><span class="kicker">往期</span><h2>错过的，看回顾</h2></div>
    <div class="cols">
      <?php foreach ($past as $e): ?>
      <div><span class="when"><?=substr($e['start_date'],0,10)?> · <?=$e['event_type']==='online'?'线上':htmlspecialchars($e['location'])?></span><h3><?=htmlspecialchars($e['title'])?></h3><p><?=htmlspecialchars($e['description'])?></p><a class="btn subtle" style="align-self:flex-start;margin-left:-14px" href="#">看回顾 →</a></div>
      <?php endforeach; ?>
      <div><span class="when">—</span><h3>更多回顾整理中</h3><p>直播录像与线下纪要会陆续放到学院。</p></div>
      <div><span class="when">—</span><h3>订阅活动通知</h3><p>进门派社区，新活动第一时间通知。</p><a class="btn subtle" style="align-self:flex-start;margin-left:-14px" href="#">进入社区 →</a></div>
    </div>
  </section>
<?php mock_foot();
