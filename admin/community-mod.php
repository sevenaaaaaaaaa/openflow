<?php
/**
 * 社区管理 — 帖子/评论/话题
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$postsFile = DATA_DIR . '/community-posts.json';
$commentsFile = DATA_DIR . '/community-comments.json';
$topicsFile = DATA_DIR . '/community-topics.json';
$posts = json_read($postsFile);
$comments = json_read($commentsFile);
$topics = json_read($topicsFile);

// 隐藏/显示帖子
if (isset($_GET['toggle_post'])) {
    foreach ($posts as &$p) if ($p['id'] === $_GET['toggle_post']) $p['status'] = ($p['status'] ?? 'published') === 'published' ? 'hidden' : 'published';
    unset($p);
    json_write($postsFile, $posts);
    flash('success', '帖子状态已更新');
    header('Location: /xmp/community-mod');
    exit;
}
// 删除帖子
if (isset($_GET['delete_post'])) {
    $posts = array_values(array_filter($posts, fn($p) => $p['id'] !== $_GET['delete_post']));
    $comments = array_values(array_filter($comments, fn($c) => ($c['post_id'] ?? '') !== $_GET['delete_post']));
    json_write($postsFile, $posts);
    json_write($commentsFile, $comments);
    flash('success', '帖子已删除（含评论）');
    header('Location: /xmp/community-mod');
    exit;
}
// 删除评论
if (isset($_GET['delete_comment'])) {
    $comments = array_values(array_filter($comments, fn($c) => $c['id'] !== $_GET['delete_comment']));
    json_write($commentsFile, $comments);
    flash('success', '评论已删除');
    header('Location: /xmp/community-mod?tab=comments');
    exit;
}
// 保存话题
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_topics'])) {
    csrf_verify();
    $topics = [];
    foreach (($_POST['topic_id'] ?? []) as $i => $tid) {
        if (empty(trim($_POST['topic_name'][$i] ?? ''))) continue;
        $topics[] = [
            'id' => $tid ?: 't_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'name' => trim($_POST['topic_name'][$i]),
            'icon' => $_POST['topic_icon'][$i] ?? '💬',
            'desc' => $_POST['topic_desc'][$i] ?? '',
        ];
    }
    json_write($topicsFile, $topics);
    flash('success', '话题已保存');
    header('Location: /xmp/community-mod?tab=topics');
    exit;
}

$tab = $_GET['tab'] ?? 'posts';
usort($posts, fn($a,$b) => strcmp($b['created_at']??'', $a['created_at']??''));
usort($comments, fn($a,$b) => strcmp($b['created_at']??'', $a['created_at']??''));

admin_header('社区管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('community-mod'); ?>
  <div class="main">
    <h1>💬 社区管理</h1>
    <p class="sub">管理话题、帖子与评论 · 前台展示于 /community.php</p>

    <div class="flex gap-2 mb-4">
      <a href="community-mod.php?tab=posts" class="btn btn-sm <?=$tab==='posts'?'btn-primary':'btn-ghost'?>">📝 帖子 (<?=count($posts)?>)</a>
      <a href="community-mod.php?tab=comments" class="btn btn-sm <?=$tab==='comments'?'btn-primary':'btn-ghost'?>">💬 评论 (<?=count($comments)?>)</a>
      <a href="community-mod.php?tab=topics" class="btn btn-sm <?=$tab==='topics'?'btn-primary':'btn-ghost'?>">🗂️ 话题 (<?=count($topics)?>)</a>
    </div>

    <?php if ($tab === 'posts'): ?>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>标题</th><th>作者</th><th>话题</th><th>投票</th><th>评论</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($posts)): ?><tr><td colspan="7" class="empty">暂无帖子</td></tr><?php endif; ?>
          <?php foreach ($posts as $p): ?>
          <tr>
            <td style="max-width:220px"><strong><?=htmlspecialchars($p['title'])?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($p['author_name'])?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($p['topic'] ?? '')?></td>
            <td><?=$p['votes']??0?></td>
            <td><?=$p['comments']??0?></td>
            <td><span class="badge <?=($p['status']??'published')==='published'?'badge-green':'badge-gray'?>"><?=$p['status']??'published'?></span></td>
            <td style="white-space:nowrap">
              <a href="../community-post.php?id=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm" target="_blank">👁</a>
              <a href="?toggle_post=<?=urlencode($p['id'])?>" class="btn btn-ghost btn-sm"><?=($p['status']??'published')==='published'?'隐藏':'显示'?></a>
              <a href="?delete_post=<?=urlencode($p['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除该帖及评论?')">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php elseif ($tab === 'comments'): ?>
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>评论</th><th>作者</th><th>时间</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($comments)): ?><tr><td colspan="4" class="empty">暂无评论</td></tr><?php endif; ?>
          <?php foreach (array_slice($comments, 0, 50) as $c): ?>
          <tr>
            <td style="max-width:400px"><?=htmlspecialchars($c['content'])?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($c['author_name'])?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($c['created_at']??'',0,16))?></td>
            <td><a href="?delete_comment=<?=urlencode($c['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除该评论?')">删除</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (count($comments) > 50): ?><p class="text-sm text-muted" style="padding:12px 20px">仅显示最近 50 条，共 <?=count($comments)?> 条</p><?php endif; ?>
    </div>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>🗂️ 话题管理</h2>
        <div id="topicList">
          <?php foreach ($topics as $ti => $t): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <input type="hidden" name="topic_id[]" value="<?=htmlspecialchars($t['id'])?>">
            <input type="text" name="topic_icon[]" value="<?=htmlspecialchars($t['icon'] ?? '💬')?>" style="width:50px;padding:7px;border:1.5px solid var(--border);border-radius:8px;text-align:center">
            <input type="text" name="topic_name[]" value="<?=htmlspecialchars($t['name'])?>" placeholder="话题名称" style="width:160px;padding:8px;border:1.5px solid var(--border);border-radius:8px">
            <input type="text" name="topic_desc[]" value="<?=htmlspecialchars($t['desc'] ?? '')?>" placeholder="描述" style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:8px">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div').remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addTopic()">+ 添加话题</button>
        <div style="margin-top:12px"><button type="submit" name="save_topics" class="btn btn-primary">保存话题</button></div>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
<script>
function addTopic() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
  d.innerHTML = '<input type="hidden" name="topic_id[]" value="t_' + Date.now() + '"><input type="text" name="topic_icon[]" value="💬" style="width:50px;padding:7px;border:1.5px solid var(--border);border-radius:8px;text-align:center"><input type="text" name="topic_name[]" placeholder="话题名称" style="width:160px;padding:8px;border:1.5px solid var(--border);border-radius:8px"><input type="text" name="topic_desc[]" placeholder="描述" style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:8px"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  document.getElementById('topicList').appendChild(d);
}
</script>
<?php admin_footer(); ?>
