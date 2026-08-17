<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('follows');
admin_header('关注管理');
$follows = FollowSystem::all();
$uniqueUsers = [];
foreach ($follows as $f) {
    $uniqueUsers[$f['follower_id']] = true;
    $uniqueUsers[$f['following_id']] = true;
}
$mutualCount = 0;
foreach ($follows as $f) {
    if (isset($follows["{$f['following_id']}:{$f['follower_id']}"])) $mutualCount++;
}
?>
<div class="admin-layout">
  <?php admin_sidebar('follows'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 关注管理</h1>
      <div class="flex gap-2 ml-auto">
        <span class="badge" style="background:var(--accent);color:var(--on-accent);padding:4px 12px;border-radius:999px;font-size:13px"><?=count($follows)?> 条关注关系</span>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--accent)"><?=count($uniqueUsers)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">活跃用户</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--ok)"><?=count($follows)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">关注关系</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--warn)"><?=intval($mutualCount/2)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">互相关注</div>
      </div>
    </div>
    <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);overflow:hidden">
      <table style="width:100%;border-collapse:collapse">
        <thead><tr style="background:var(--surface-2)">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">关注者</th>
          <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--muted);font-size:13px">→</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">被关注者</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">时间</th>
        </tr></thead>
        <tbody>
        <?php if (empty($follows)): ?>
          <tr><td colspan="4" style="padding:40px;text-align:center;color:var(--muted)">暂无关注数据</td></tr>
        <?php else: foreach ($follows as $f): ?>
          <tr style="border-top:1px solid var(--border)">
            <td style="padding:12px 16px;font-size:14px"><?=h($f['follower_id'] ?? '')?></td>
            <td style="padding:12px 16px;text-align:center;color:var(--muted)">→</td>
            <td style="padding:12px 16px;font-size:14px"><?=h($f['following_id'] ?? '')?></td>
            <td style="padding:12px 16px;font-size:13px;color:var(--muted)"><?=h($f['created_at'] ?? '')?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
