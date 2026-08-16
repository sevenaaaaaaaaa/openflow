<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('bookmarks');

admin_header('收藏管理');
$bookmarks = BookmarkSystem::all();
$articleCount = $courseCount = $postCount = 0;
foreach ($bookmarks as $b) {
    if (($b['target_type'] ?? '') === 'article') $articleCount++;
    elseif (($b['target_type'] ?? '') === 'course') $courseCount++;
    elseif (($b['target_type'] ?? '') === 'post') $postCount++;
}
?>
<div class="admin-layout">
  <?php admin_sidebar('bookmarks'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">📚 收藏管理</h1>
      <div class="flex gap-2 ml-auto">
        <span class="badge" style="background:var(--accent);color:var(--on-accent);padding:4px 12px;border-radius:999px;font-size:13px">共 <?=count($bookmarks)?> 条</span>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--accent)"><?=$articleCount?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">文章收藏</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--ok)"><?=$courseCount?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">课程收藏</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--warn)"><?=$postCount?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">帖子收藏</div>
      </div>
    </div>

    <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);overflow:hidden">
      <table style="width:100%;border-collapse:collapse">
        <thead><tr style="background:var(--surface-2)">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">用户</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">类型</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">内容标题</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--muted);font-size:13px">时间</th>
          <th style="padding:12px 16px;text-align:right;font-weight:600;color:var(--muted);font-size:13px">操作</th>
        </tr></thead>
        <tbody>
        <?php if (empty($bookmarks)): ?>
          <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--muted)">暂无收藏数据</td></tr>
        <?php else: foreach ($bookmarks as $b): ?>
          <tr style="border-top:1px solid var(--border)">
            <td style="padding:12px 16px;font-size:14px"><?=h($b['user_id'] ?? '')?></td>
            <td style="padding:12px 16px"><span style="padding:3px 10px;border-radius:12px;font-size:12px;background:var(--accent);color:var(--on-accent)"><?=h($b['target_type'] ?? '')?></span></td>
            <td style="padding:12px 16px;font-size:14px"><?=h($b['title'] ?? $b['target_id'] ?? '')?></td>
            <td style="padding:12px 16px;font-size:13px;color:var(--muted)"><?=h($b['created_at'] ?? '')?></td>
            <td style="padding:12px 16px;text-align:right"><button onclick="removeBookmark('<?=h($b['user_id'])?>','<?=h($b['target_type'])?>','<?=h($b['target_id'])?>')" style="padding:4px 10px;border-radius:6px;border:1px solid var(--danger);color:var(--danger);background:none;cursor:pointer;font-size:12px">删除</button></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
function removeBookmark(userId, type, id) {
  if (!confirm('确定删除？')) return;
  fetch('../api/bookmark.php',{method:'DELETE',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'user_id='+encodeURIComponent(userId)+'&target_type='+encodeURIComponent(type)+'&target_id='+encodeURIComponent(id)+'&csrf_token=<?=csrf_token()?>'}).then(r=>r.json()).then(d=>{if(d.ok)location.reload();else alert(d.error||'删除失败')});
}
</script>
<?php admin_footer(); ?>
