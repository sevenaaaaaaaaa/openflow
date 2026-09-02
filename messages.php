<?php
/**
 * 站内信 — 会员收件箱
 *
 * v7（2026-09-01）：迁到共享 archetype（reader + 消息卡）。类型色从 hex 改为 badge 语义色。删除接口原样保留。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/MessageSystem.php';

$member = member_current();
if (!$member) {
    header('Location: /account?view=login&next=' . urlencode('/messages'));
    exit;
}
$messages = inbox_inbox($member);
inbox_mark_read($member);
$unread = inbox_unread($member);

$typeLabels = ['system' => '系统', 'order' => '订单', 'consultation' => '咨询', 'live' => '直播', 'membership' => '会员', 'marketing' => '通知'];
$typeBadge = ['system' => 'neutral', 'order' => 'ok', 'consultation' => 'hl', 'live' => 'danger', 'membership' => 'warn', 'marketing' => 'warn'];
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>站内信 | OpenFlow</title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 站内信独有：消息卡。其余全部来自 modules.css。 */
.msg{display:flex;flex-direction:column;gap:10px}
.msg .hd{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.msg .hd b{font-size:15px}
.msg .hd .when{margin-left:auto;font-family:var(--font-mono);font-size:12px;color:var(--faint)}
.msg p{font-size:14px;color:var(--muted);line-height:1.75;white-space:pre-line}
.msg .ft{display:flex;align-items:center;gap:12px}
.msg .del{font-size:12.5px;color:var(--faint);margin-left:auto}
.msg .del:hover{color:var(--danger)}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('home'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section class="reader reveal in" data-od-id="messages">
    <div class="sec-head row"><div><span class="kicker">站内信</span><h2>收件箱</h2></div><span class="sub">共 <?=count($messages)?> 条 · <?=htmlspecialchars($member['name'] ?? '')?></span></div>
    <?php if (empty($messages)): ?>
    <div class="empty" style="margin-top:18px">暂无消息</div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;margin-top:18px">
    <?php foreach ($messages as $m): $bt = $typeBadge[$m['type'] ?? 'system'] ?? 'neutral'; ?>
    <div class="card msg" data-id="<?=htmlspecialchars($m['id'])?>" style="padding:22px 24px">
      <div class="hd"><span class="ic" style="width:34px;height:34px;border-radius:10px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;flex:0 0 auto"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg></span><b><?=htmlspecialchars($m['title'] ?? '')?></b><span class="<?=$bt==='hl'||$bt==='neutral'?'pill '.$bt:'badge '.$bt?>"><?=$typeLabels[$m['type'] ?? 'system'] ?? '系统'?></span><span class="when"><?=htmlspecialchars($m['created_at'] ?? '')?></span></div>
      <p><?=htmlspecialchars($m['content'] ?? '')?></p>
      <div class="ft"><?php if (!empty($m['link'])): ?><a href="<?=htmlspecialchars($m['link'])?>" class="btn subtle" style="margin-left:-14px">查看详情 →</a><?php endif; ?><button onclick="delMsg('<?=htmlspecialchars($m['id'])?>', this)" class="del">删除</button></div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
function delMsg(id, btn) {
  if (!confirm('删除这条消息？')) return;
  var body = new FormData(); body.append('msg_id', id);
  fetch('/api/message?action=delete', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) btn.closest('.msg').remove(); });
}
</script>
</body>
</html>
