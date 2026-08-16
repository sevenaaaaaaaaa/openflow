<?php
/**
 * 社区帖子详情 — 内容 + 评论
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/MemberSystem.php';

$postId = req_str('id');
$posts = json_read(DATA_DIR . '/community-posts.json');
$comments = json_read(DATA_DIR . '/community-comments.json');
$topics = json_read(DATA_DIR . '/community-topics.json');
$topicNames = [];
foreach ($topics as $t) $topicNames[$t['id']] = ['name'=>$t['name'],'icon'=>$t['icon']??'💬'];

$post = null;
foreach ($posts as $p) if ($p['id'] === $postId && ($p['status'] ?? 'published') === 'published') { $post = $p; break; }
if (!$post) { http_response_code(404); die('帖子不存在'); }

$postComments = array_values(array_filter($comments, fn($c) => ($c['post_id'] ?? '') === $postId));
usort($postComments, fn($a,$b) => strcmp($a['created_at']??'', $b['created_at']??''));

$member = member_current();
$topic = $topicNames[$post['topic'] ?? ''] ?? ['name'=>'综合','icon'=>'💬'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($post['title'])?> | OpenFlow 社区</title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:-apple-system,'PingFang SC','Noto Sans SC',system-ui,sans-serif}
  .cmt{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:10px}
</style>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
</head>
<body class="min-h-screen">
  <header class="bg-white border-b border-[var(--border)]">
    <div class="mx-auto px-5 py-3 flex items-center justify-between" style="max-width:900px">
      <a href="/" class="font-bold text-lg text-gray-900">OpenFlow</a>
      <nav class="flex items-center gap-4 text-sm">
        <a href="/community" class="text-gray-600">← 返回社区</a>
        <?php if ($member): ?><a href="/member.php" class="font-semibold text-green-600"><?=htmlspecialchars($member['name'])?></a><?php endif; ?>
      </nav>
    </div>
  </header>

  <div class="mx-auto px-5 py-8" style="max-width:900px">
    <!-- 帖子 -->
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:28px">
      <div style="font-size:12px;color:var(--faint);margin-bottom:8px"><?=$topic['icon']?> <?=htmlspecialchars($topic['name'])?> · by <?=htmlspecialchars($post['author_name'])?> · <?=htmlspecialchars(substr($post['created_at']??'',0,10))?></div>
      <h1 class="text-2xl font-bold mb-4"><?=htmlspecialchars($post['title'])?></h1>
      <div class="text-[15px] leading-relaxed text-gray-600 whitespace-pre-wrap"><?=htmlspecialchars($post['content'] ?? '')?></div>
      <div style="display:flex;align-items:center;gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid var(--bg);font-size:13px;color:var(--faint)">
        <button class="vote-btn" onclick="vote('<?=htmlspecialchars($post['id'])?>',1)">▲</button>
        <span class="font-bold text-sm" style="color:var(--fg)" id="votes_<?=htmlspecialchars($post['id'])?>"><?=$post['votes']??0?></span>
        <button class="vote-btn" onclick="vote('<?=htmlspecialchars($post['id'])?>',-1)">▼</button>
        <span style="margin-left:8px">💬 <?=count($postComments)?> 评论</span>
      </div>
    </div>

    <!-- 评论 -->
    <div id="comments" class="mt-8">
      <h2 class="font-bold text-lg mb-4">评论（<?=count($postComments)?>）</h2>

      <?php if ($member): ?>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:16px">
        <textarea id="cmtContent" rows="2" placeholder="写下你的想法…" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:14px"></textarea>
        <button onclick="addComment()" class="mt-2 rounded-full px-6 py-2 font-bold" style="background:var(--accent);color:var(--on-accent)">发布评论</button>
      </div>
      <?php else: ?>
      <a href="/member.php?view=login&next=/community-post/<?=urlencode($post['id'])?>" class="block text-center py-3 rounded-xl font-semibold" style="background:var(--surface);border:1px solid var(--border);color:#2b5f7e;margin-bottom:16px">登录后参与讨论</a>
      <?php endif; ?>

      <?php if (empty($postComments)): ?>
      <div class="text-center py-10 text-gray-400">暂无评论，来抢沙发！</div>
      <?php endif; ?>
      <?php foreach ($postComments as $c): ?>
      <div class="cmt">
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--faint);margin-bottom:6px"><strong style="color:var(--fg)"><?=htmlspecialchars($c['author_name'])?></strong><span><?=htmlspecialchars(substr($c['created_at']??'',0,16))?></span></div>
        <div class="text-sm leading-relaxed text-gray-600"><?=htmlspecialchars($c['content'])?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

<script>
var MEMBER = <?=json_encode($member ? ['id'=>$member['id']] : null)?>;
var POST_ID = <?=json_encode($post['id'])?>;
function vote(postId, delta) {
  if (!MEMBER) { location.href = '/member.php?view=login'; return; }
  var fd = new FormData(); fd.append('action','vote'); fd.append('post_id', postId); fd.append('delta', delta);
  fetch('/api/community.php', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){ if (d.ok) document.getElementById('votes_' + postId).textContent = d.votes; });
}
function addComment() {
  var content = document.getElementById('cmtContent').value;
  if (!content.trim()) return alert('评论不能为空');
  var fd = new FormData(); fd.append('action','comment'); fd.append('post_id', POST_ID); fd.append('content', content);
  fetch('/api/community.php', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){ if (d.ok) location.reload(); else alert(d.error); });
}
</script>
<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)"><div class="mx-auto px-5 text-center text-sm" style="max-width:1100px"><div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div><div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div></div></footer>
</body>
</html>
