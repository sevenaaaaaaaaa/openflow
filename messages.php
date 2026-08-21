<?php
/**
 * 站内信 — 会员收件箱
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/MessageSystem.php';

$member = member_current();
if (!$member) {
    header('Location: member.php?view=login&next=' . urlencode('/messages.php'));
    exit;
}
$messages = inbox_inbox($member);
inbox_mark_read($member);
$unread = inbox_unread($member);

$typeLabels = ['system' => '系统', 'order' => '订单', 'consultation' => '咨询', 'live' => '直播', 'membership' => '会员', 'marketing' => '通知'];
$typeColors = ['system' => '#2b5f7e', 'order' => 'var(--ok)', 'consultation' => '#7c3aed', 'live' => '#dc2626', 'membership' => '#b45309', 'marketing' => '#d97706'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>站内信 | OpenFlow</title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:-apple-system,'PingFang SC','Noto Sans SC',system-ui,sans-serif}
  .msg-item{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:12px;transition:.15s}
  .msg-item:hover{border-color:var(--accent)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260821" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-8" style="max-width:900px">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">🔔 站内信</h1>
      <span class="text-sm text-gray-600">共 <?=count($messages)?> 条 · <?=htmlspecialchars($member['name'] ?? '')?></span>
    </div>

    <?php if (empty($messages)): ?>
    <div class="rounded-3xl p-12 text-center" style="background:var(--surface);border:1px solid var(--border);color:var(--muted)">暂无消息</div>
    <?php else: foreach ($messages as $m): ?>
    <div class="msg-item" data-id="<?=htmlspecialchars($m['id'])?>">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="font-size:20px"><?=htmlspecialchars($m['icon'] ?? '🔔')?></span>
        <strong style="font-size:15px"><?=htmlspecialchars($m['title'] ?? '')?></strong>
        <span style="font-size:11px;padding:2px 8px;border-radius:999px;color:var(--surface);background:<?=$typeColors[$m['type'] ?? 'system'] ?? '#2b5f7e'?>"><?=$typeLabels[$m['type'] ?? 'system'] ?? '系统'?></span>
        <span class="text-sm text-gray-400" style="margin-left:auto"><?=htmlspecialchars($m['created_at'] ?? '')?></span>
      </div>
      <p class="text-sm text-gray-600 leading-relaxed mt-3" style="white-space:pre-line"><?=htmlspecialchars($m['content'] ?? '')?></p>
      <?php if (!empty($m['link'])): ?>
      <a href="<?=htmlspecialchars($m['link'])?>" class="inline-block mt-3 text-xs font-bold px-5 py-2 rounded-full" style="background:var(--accent);color:var(--on-accent)">查看详情 →</a>
      <?php endif; ?>
      <button onclick="delMsg('<?=htmlspecialchars($m['id'])?>', this)" style="margin-left:10px" class="mt-3 text-xs text-gray-400 underline">删除</button>
    </div>
    <?php endforeach; endif; ?>
  </div>

<script>
function delMsg(id, btn) {
  if (!confirm('删除这条消息？')) return;
  var body = new FormData(); body.append('msg_id', id);
  fetch('/api/message.php?action=delete', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) btn.closest('.msg-item').remove(); });
}
</script>
<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)"><div class="mx-auto px-5 text-center text-sm" style="max-width:1100px"><div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div><div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div></div></footer>
</body>
</html>
