<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('moderation');
admin_header('举报管理');
$all = ReportSystem::all();
$pending = array_filter($all, fn($r) => $r['status'] === 'pending');
$resolved = array_filter($all, fn($r) => $r['status'] === 'resolved');
$dismissed = array_filter($all, fn($r) => $r['status'] === 'dismissed');
$reasons = ReportSystem::reasons();
?>
<div class="admin-layout">
  <?php admin_sidebar('reports'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 举报管理</h1>
      <div class="flex gap-2 ml-auto">
        <span class="badge" style="background:var(--danger);color:#fff;padding:4px 12px;border-radius:999px;font-size:13px"><?=count($pending)?> 待处理</span>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--danger)"><?=count($all)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">全部举报</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--warn)"><?=count($pending)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">待处理</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--ok)"><?=count($resolved)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">已处理</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--muted)"><?=count($dismissed)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">已驳回</div>
      </div>
    </div>
    <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);overflow:hidden">
      <table style="width:100%;border-collapse:collapse">
        <thead><tr style="background:var(--surface-2)">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">举报者</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">类型</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">内容</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">原因</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">状态</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">时间</th>
          <th style="padding:12px 16px;text-align:right;font-weight:600;color:var(--muted);font-size:13px">操作</th>
        </tr></thead>
        <tbody>
        <?php if (empty($all)): ?>
          <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--muted)">暂无举报数据</td></tr>
        <?php else: foreach ($all as $r): ?>
          <tr style="border-top:1px solid var(--border)" id="report-<?=h($r['id'])?>">
            <td style="padding:12px 16px;font-size:14px"><?=h($r['user_name'] ?? $r['user_id'] ?? '')?></td>
            <td style="padding:12px 16px;font-size:13px"><?=h($r['target_type'] ?? '')?></td>
            <td style="padding:12px 16px;font-size:14px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=h($r['target_title'] ?? $r['target_id'] ?? '')?></td>
            <td style="padding:12px 16px"><span style="font-size:12px;padding:3px 8px;border-radius:8px;background:var(--surface-2);color:var(--text)"><?=h($reasons[$r['category']] ?? $r['category'] ?? '')?></span></td>
            <td style="padding:12px 16px">
              <?php if ($r['status']==='pending'): ?>
                <span style="padding:3px 10px;border-radius:12px;font-size:12px;background:#fef3c7;color:#92400e">待处理</span>
              <?php elseif ($r['status']==='resolved'): ?>
                <span style="padding:3px 10px;border-radius:12px;font-size:12px;background:#d1fae5;color:#065f46">已处理</span>
              <?php else: ?>
                <span style="padding:3px 10px;border-radius:12px;font-size:12px;background:var(--surface-2);color:var(--muted)">已驳回</span>
              <?php endif; ?>
            </td>
            <td style="padding:12px 16px;font-size:13px;color:var(--muted)"><?=h($r['created_at'] ?? '')?></td>
            <td style="padding:12px 16px;text-align:right;white-space:nowrap">
              <?php if ($r['status']==='pending'): ?>
                <button onclick="resolveReport('<?=h($r['id'])?>','resolved')" style="padding:4px 10px;border-radius:6px;border:1px solid var(--ok);color:var(--ok);background:none;cursor:pointer;font-size:12px;margin-right:4px">处理</button>
                <button onclick="resolveReport('<?=h($r['id'])?>','dismissed')" style="padding:4px 10px;border-radius:6px;border:1px solid var(--border);color:var(--muted);background:none;cursor:pointer;font-size:12px">驳回</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
function resolveReport(id, status) {
  const note = prompt('处理备注（可选）：') || '';
  fetch('../api/report-manage.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+encodeURIComponent(id)+'&status='+status+'&note='+encodeURIComponent(note)+'&csrf_token=<?=csrf_token()?>'}).then(r=>r.json()).then(d=>{if(d.ok)location.reload();else alert(d.error||'操作失败')});
}
</script>
<?php admin_footer(); ?>
