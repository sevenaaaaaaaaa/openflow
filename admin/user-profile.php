<?php
require_once __DIR__ . '/config.php';
require_login();

$userId = $_GET['id'] ?? $_COOKIE['member_id'] ?? $_SESSION['member_id'] ?? '';
if (!$userId) { header('Location: /xmp/login'); exit; }

$followingCount = FollowSystem::followingCount($userId);
$followersCount = FollowSystem::followersCount($userId);
$bookmarks = BookmarkSystem::getUserBookmarks($userId);

$currentUserId = $_COOKIE['member_id'] ?? $_SESSION['member_id'] ?? '';
$isSelf = ($userId === $currentUserId);
$isFollowing = $isSelf ? false : FollowSystem::isFollowing($currentUserId, $userId);

admin_header("用户主页 - " . $userId);
?>
<div class="admin-layout">
  <?php admin_sidebar(''); ?>
  <div class="main">
    <div style="max-width:800px;margin:0 auto">
      <div style="background:var(--surface);border-radius:16px;border:1px solid var(--border);overflow:hidden">
        <div style="height:120px;background:linear-gradient(135deg,var(--accent),#8b5cf6)"></div>
        <div style="padding:24px;position:relative">
          <div style="width:80px;height:80px;border-radius:50%;background:var(--surface-2);border:4px solid var(--surface);position:absolute;top:-40px;left:24px;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:var(--accent)"><?=mb_substr($userId, 0, 1)?></div>
          <div style="padding-left:100px;margin-bottom:16px">
            <div style="font-size:20px;font-weight:700;color:var(--text)"><?=h($userId)?></div>
          </div>
          <div style="display:flex;gap:24px;margin-bottom:20px;padding-left:100px">
            <div style="text-align:center">
              <div style="font-size:20px;font-weight:700;color:var(--accent)"><?=$followingCount?></div>
              <div style="font-size:12px;color:var(--muted)">关注</div>
            </div>
            <div style="text-align:center">
              <div style="font-size:20px;font-weight:700;color:var(--ok)"><?=$followersCount?></div>
              <div style="font-size:12px;color:var(--muted)">粉丝</div>
            </div>
            <div style="text-align:center">
              <div style="font-size:20px;font-weight:700;color:var(--warn)"><?=count($bookmarks)?></div>
              <div style="font-size:12px;color:var(--muted)">收藏</div>
            </div>
          </div>
          <?php if (!$isSelf): ?>
            <div style="padding-left:100px">
              <button id="followBtn" onclick="toggleFollow()" style="padding:8px 24px;border-radius:8px;border:1px solid <?= $isFollowing ? 'var(--border)' : 'var(--accent)' ?>;background:<?= $isFollowing ? 'var(--surface-2)' : 'var(--accent)' ?>;color:<?= $isFollowing ? 'var(--text)' : 'var(--on-accent)' ?>;cursor:pointer;font-weight:600">
                <?= $isFollowing ? '已关注' : '+ 关注' ?>
              </button>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div style="margin-top:24px;background:var(--surface);border-radius:12px;border:1px solid var(--border);padding:20px">
        <h3 style="margin:0 0 16px;font-size:16px">📚 收藏内容</h3>
        <?php if (empty($bookmarks)): ?>
          <div style="padding:30px;text-align:center;color:var(--muted);font-size:14px">暂无收藏</div>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach (array_slice($bookmarks, 0, 20) as $b): ?>
              <div style="padding:12px 16px;background:var(--surface-2);border-radius:8px;display:flex;justify-content:space-between;align-items:center">
                <div>
                  <span style="padding:2px 8px;border-radius:6px;font-size:11px;background:var(--accent);color:var(--on-accent);margin-right:8px"><?=h($b['target_type'])?></span>
                  <span style="font-size:14px;color:var(--text)"><?=h($b['title'] ?: $b['target_id'])?></span>
                </div>
                <span style="font-size:12px;color:var(--muted)"><?=h($b['created_at'])?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
function toggleFollow() {
  const targetUserId = '<?=h($userId)?>';
  fetch('../api/follow.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'target_user_id=' + encodeURIComponent(targetUserId)
  }).then(r => r.json()).then(d => {
    if (d.ok) location.reload();
    else ofAlert(d.error || '操作失败');
  });
}
</script>
<?php admin_footer(); ?>
