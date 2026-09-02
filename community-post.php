<?php
/**
 * 社区帖子详情 — 内容 + 评论
 *
 * v7（2026-09-01）：迁到共享 archetype（reader + prose + actions + 评论列表）。投票 / 评论接口原样保留；自绘顶栏换成共享外壳。
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
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($post['title'])?> | OpenFlow 社区</title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 帖子页独有：评论列表。其余全部来自 modules.css。 */
.cmts{display:flex;flex-direction:column}
.cmt{padding:18px 4px;border-bottom:1px solid var(--border-soft)}
.cmt .hd{display:flex;justify-content:space-between;gap:10px;font-family:var(--font-mono);font-size:12px;color:var(--faint);margin-bottom:6px}
.cmt .hd b{color:var(--fg);font-family:var(--font-body);font-size:13.5px}
.cmt p{font-size:14.5px;color:var(--muted);line-height:1.75}
.vote-inline{display:inline-flex;align-items:center;gap:6px}
.vote-inline .act{height:34px;padding:0 12px}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('community'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <article class="reader reveal in" data-od-id="post">
    <nav class="art-meta" aria-label="面包屑" style="margin-bottom:18px"><a href="/community" style="color:var(--faint)">← 返回社区</a></nav>
    <div class="art-head">
      <div class="art-meta"><span><?=$topic['icon']?> <?=htmlspecialchars($topic['name'])?></span><span class="sep"></span><span>by <?=htmlspecialchars($post['author_name'])?></span><span class="sep"></span><span><?=htmlspecialchars(substr($post['created_at']??'',0,10))?></span></div>
      <h1><?=htmlspecialchars($post['title'])?></h1>
    </div>
    <div class="prose" style="white-space:pre-wrap"><?=htmlspecialchars($post['content'] ?? '')?></div>
    <div class="actions">
      <span class="vote-inline">
        <button class="act" onclick="vote('<?=htmlspecialchars($post['id'])?>',1)" aria-label="赞同"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 15 6-6 6 6"/></svg></button>
        <b id="votes_<?=htmlspecialchars($post['id'])?>"><?=$post['votes']??0?></b>
        <button class="act" onclick="vote('<?=htmlspecialchars($post['id'])?>',-1)" aria-label="反对"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></button>
      </span>
      <a class="act" href="#comments"><?=count($postComments)?> 评论</a>
    </div>
  </article>

  <section id="comments" class="reader reveal" data-od-id="post-comments">
    <div class="sec-head row"><div><span class="kicker">评论 · <?=count($postComments)?></span><h2>大家怎么说</h2></div></div>
    <?php if ($member): ?>
    <div class="card" style="margin-top:14px;display:flex;flex-direction:column;gap:12px">
      <textarea id="cmtContent" class="inp" rows="3" placeholder="写下你的想法…"></textarea>
      <div class="cta-row"><button onclick="addComment()" class="btn primary">发布评论</button></div>
    </div>
    <?php else: ?>
    <a href="/member.php?view=login&next=/community-post/<?=urlencode($post['id'])?>" class="btn ghost" style="margin-top:14px;align-self:flex-start">登录后评论</a>
    <?php endif; ?>
    <?php if (empty($postComments)): ?><div class="empty" style="margin-top:18px">暂无评论，来抢沙发！</div><?php endif; ?>
    <div class="cmts" style="margin-top:10px">
      <?php foreach ($postComments as $c): ?>
      <div class="cmt"><div class="hd"><b><?=htmlspecialchars($c['author_name'])?></b><span><?=htmlspecialchars(substr($c['created_at']??'',0,16))?></span></div><p><?=htmlspecialchars($c['content'])?></p></div>
      <?php endforeach; ?>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
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
</body>
</html>
