<?php
/**
 * 统一会话收件箱 —— 表单/评论/站内信/咨询汇一处（BACKLOG T1-10）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/UnifiedInbox.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_login();
require_perm('messages');

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $uid = (string)($_POST['uid'] ?? '');
    if ($act === 'mark') {
        uinbox_set_state($uid, (string)($_POST['status'] ?? 'done'));
        header('Location: /xmp/inbox?ok=1'); exit;
    } elseif ($act === 'to_lead') {
        $items = uinbox_all();
        $hit = null;
        foreach ($items as $i) if ($i['uid'] === $uid) { $hit = $i; break; }
        $r = $hit ? uinbox_to_lead($hit) : ['ok' => false, 'error' => '条目不存在'];
        if ($r['ok']) { audit('收件箱转线索 ' . $uid, 'crm'); $msg = '已转为 CRM 线索：' . $r['email']; }
        else $err = $r['error'];
    }
}

$all = uinbox_all();
$counts = uinbox_counts($all);
$fSource = (string)($_GET['source'] ?? '');
$fStatus = (string)($_GET['status'] ?? 'open');
$items = uinbox_filter($all, $fSource, $fStatus);

admin_header('统一收件箱');
?>
<div style="max-width:1000px">
  <h1 style="margin:0 0 4px">📥 统一收件箱</h1>
  <p class="v-sub" style="margin:0 0 14px">表单、评论、站内信、咨询预约汇到一处——访客问的每一句都不再散落。有邮箱的可一键转成 CRM 线索，客服→售前接上。</p>
  <?php if ($msg): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;border-left:3px solid #16a34a"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#dc2626;border-left:3px solid #dc2626"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;align-items:center">
    <span style="font-size:13px;color:var(--faint)">待处理 <strong style="color:var(--text)"><?=$counts['open']?></strong> · 已处理 <?=$counts['done']?> · 已忽略 <?=$counts['ignored']?></span>
    <span style="margin-left:auto"></span>
    <?php foreach (['open'=>'待处理','done'=>'已处理','ignored'=>'已忽略',''=>'全部'] as $sk=>$sl): ?>
    <a href="/xmp/inbox?status=<?=$sk?>&source=<?=urlencode($fSource)?>" class="btn btn-sm <?=$fStatus===$sk?'btn-primary':'btn-ghost'?>"><?=$sl?></a>
    <?php endforeach; ?>
    <select onchange="location.href='/xmp/inbox?status=<?=urlencode($fStatus)?>&source='+this.value" style="margin-left:6px">
      <option value="">全部来源</option>
      <?php foreach (uinbox_sources() as $sk=>$sl): ?><option value="<?=$sk?>" <?=$fSource===$sk?'selected':''?>><?=$sl?></option><?php endforeach; ?>
    </select>
  </div>

  <?php if (!$items): ?>
    <div class="card" style="padding:36px;text-align:center;color:var(--faint)">这个筛选下没有会话。</div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach (array_slice($items, 0, 100) as $i): ?>
    <div class="card" style="padding:12px 14px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="font-size:11px;padding:1px 8px;border-radius:999px;background:var(--accent-soft,#eef2ff);color:var(--accent,#4f46e5)"><?=htmlspecialchars(uinbox_sources()[$i['source']] ?? $i['source'])?></span>
        <strong style="font-size:14px"><?=htmlspecialchars($i['name'] ?: ($i['email'] ?: '匿名'))?></strong>
        <?php if ($i['email']): ?><span style="font-size:12px;color:var(--faint)"><?=htmlspecialchars($i['email'])?></span><?php endif; ?>
        <span style="font-size:12px;color:var(--faint);margin-left:auto"><?=htmlspecialchars($i['at'])?></span>
      </div>
      <div style="font-size:13px;margin:6px 0;color:var(--text-soft,#475569);white-space:pre-wrap"><?=htmlspecialchars(mb_substr($i['content'], 0, 300))?></div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php if ($i['link']): ?><a href="<?=htmlspecialchars($i['link'])?>" target="_blank" class="btn btn-ghost btn-sm">查看出处</a><?php endif; ?>
        <?php if ($i['email'] && $i['status'] !== 'done'): ?>
        <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="to_lead"><input type="hidden" name="uid" value="<?=htmlspecialchars($i['uid'])?>"><button class="btn btn-primary btn-sm">转为线索 →</button></form>
        <?php endif; ?>
        <?php if ($i['status'] !== 'done'): ?>
        <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="mark"><input type="hidden" name="uid" value="<?=htmlspecialchars($i['uid'])?>"><input type="hidden" name="status" value="done"><button class="btn btn-ghost btn-sm">标记已处理</button></form>
        <?php endif; ?>
        <?php if ($i['status'] === 'open'): ?>
        <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="mark"><input type="hidden" name="uid" value="<?=htmlspecialchars($i['uid'])?>"><input type="hidden" name="status" value="ignored"><button class="btn btn-ghost btn-sm" style="color:var(--faint)">忽略</button></form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
