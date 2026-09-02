<?php
/**
 * 社区 — Reddit 式话题+帖子流
 *
 * v7（2026-09-01）：从 tailwind + 行内样式迁到共享 archetype（方案 A：话题侧栏 + 投票帖子流）。
 * 排序 / 筛选 / 发帖 / 投票逻辑与接口调用原样保留。文案逐字相同；调色板外的紫色渐变横幅换成 cta-band。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（60 秒）
if (PageCache::begin('community', 900)) exit;
require_once __DIR__ . '/lib/MemberSystem.php';

$topics = json_read(DATA_DIR . '/community-topics.json');
$posts = json_read(DATA_DIR . '/community-posts.json');
$comments = json_read(DATA_DIR . '/community-comments.json');

// 筛选：话题 / 排序
$topicFilter = req_str('topic');
$sort = req_str('sort', 'hot');
$display = $posts;
if ($topicFilter) $display = array_values(array_filter($display, fn($p) => ($p['topic'] ?? '') === $topicFilter));
$display = array_values(array_filter($display, fn($p) => ($p['status'] ?? 'published') === 'published'));

// 置顶优先（📌 帖子始终排最前）
usort($display, function($a, $b) {
    $pa = !empty($a['pinned']) ? 1 : 0;
    $pb = !empty($b['pinned']) ? 1 : 0;
    if ($pa !== $pb) return $pb - $pa;
    return 0;
});

if ($sort === 'new') {
    // 置顶在前，其余按时间
    usort($display, function($a, $b) {
        if (!empty($a['pinned']) !== !empty($b['pinned'])) return !empty($a['pinned']) ? -1 : 1;
        return strcmp($b['created_at']??'', $a['created_at']??'');
    });
} else { // hot = 置顶优先 + 投票+评论加权
    usort($display, function($a, $b) {
        if (!empty($a['pinned']) !== !empty($b['pinned'])) return !empty($a['pinned']) ? -1 : 1;
        $scoreA = ($a['votes']??0) + ($a['comments']??0) * 2 + (strtotime($a['created_at']??'') / 86400);
        $scoreB = ($b['votes']??0) + ($b['comments']??0) * 2 + (strtotime($b['created_at']??'') / 86400);
        return $scoreB <=> $scoreA;
    });
}

$member = member_current();
$topicNames = [];
foreach ($topics as $t) $topicNames[$t['id']] = ['name'=>$t['name'],'icon'=>$t['icon']??'💬'];
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>门派社区 | <?=site_config_get('site_name')?> · 讨论</title>
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260902b">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260902b">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260902b">
<style>
/* 社区页独有：话题侧栏项、帖子卡与投票列、发帖框。其余全部来自 modules.css。 */
.g-main-aside.aside-left{grid-template-columns:minmax(0,240px) minmax(0,1fr)}
.g-main-aside.aside-left>aside{position:sticky;top:calc(var(--chrome-h) + 24px)}
.topic-nav{display:flex;flex-direction:column;gap:2px}
.topic-nav a{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:10px;font-size:14px;color:var(--muted);transition:background .15s,color .15s}
.topic-nav a:hover{background:var(--hover);color:var(--fg)}
.topic-nav a.active{background:var(--accent-soft);color:var(--accent-strong);font-weight:600}
.topic-nav .em{width:20px;text-align:center;flex:0 0 auto}
.stream{display:flex;flex-direction:column;gap:14px}
.stream-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.stream-bar .count{margin-left:auto;font-family:var(--font-mono);font-size:12.5px;color:var(--faint)}
.post{display:grid;grid-template-columns:44px minmax(0,1fr);gap:16px;padding:22px 24px}
.vote{display:flex;flex-direction:column;align-items:center;gap:2px}
.vote-btn{width:34px;height:30px;border-radius:9px;display:grid;place-items:center;color:var(--faint);transition:background .15s,color .15s}
.vote-btn:hover{background:var(--hover);color:var(--fg)}
.vote-btn.on{background:var(--accent-soft);color:var(--accent-strong)}
.vote-btn svg{width:16px;height:16px}
.vote b{font-family:var(--font-display);font-size:15px;font-weight:700}
.post-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-family:var(--font-mono);font-size:12px;color:var(--faint)}
.post h3{font-size:17.5px;font-weight:700;letter-spacing:-.01em;line-height:1.45;margin-top:6px;display:flex;align-items:center;gap:6px}
.post h3 a:hover{color:var(--accent)}
.post h3 .pin{color:var(--accent);display:inline-grid;place-items:center}
.post h3 .pin svg{width:14px;height:14px}
.post p{font-size:14px;color:var(--muted);line-height:1.75;margin-top:6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.post-act{display:flex;align-items:center;gap:14px;margin-top:12px;font-size:13px;color:var(--faint)}
.post-act a{display:inline-flex;align-items:center;gap:5px;color:var(--faint);transition:color .2s}
.post-act a:hover{color:var(--accent)}
.post-act svg{width:14px;height:14px}
.newpost{display:flex;flex-direction:column;gap:12px}
.newpost .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.newpost .row .btn{margin-left:auto}
@media (max-width:1080px){.g-main-aside.aside-left{grid-template-columns:1fr}.g-main-aside.aside-left>aside{position:static}.topic-nav{flex-direction:row;flex-wrap:wrap}}
@media (max-width:640px){.post{grid-template-columns:1fr}.vote{flex-direction:row;gap:8px}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('community'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="community-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">COMMUNITY · 门派</span>
      <h1>一个人做公司，<br><i class="si">不该一个人扛</i></h1>
      <p class="lead">提问、分享、讨论。学完课程的同学在这里交作业、晒增长数据、互相诊断——把增长系统这门功夫，练到身上。</p>
      <div class="trust"><span class="dot"></span><?=count($posts)?> 个帖子 · <?=count($topics)?> 个话题</div>
    </div>
  </section>

  <!-- ══ 话题侧栏 + 帖子流 ══ -->
  <section id="stream" class="sec reveal" data-od-anchor data-od-id="community-stream">
    <div class="g-main-aside aside-left">
      <aside>
        <div class="aside-box">
          <h3>话题</h3>
          <nav class="topic-nav" aria-label="话题">
            <a class="<?=!$topicFilter?'active':''?>" href="community?sort=<?=$sort?>"><span class="em"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>全部</a>
            <?php foreach ($topics as $t): ?>
            <a class="<?=$topicFilter===$t['id']?'active':''?>" href="community?topic=<?=urlencode($t['id'])?>&sort=<?=$sort?>"><span class="em"><?=$t['icon']??'💬'?></span><?=htmlspecialchars($t['name'])?></a>
            <?php endforeach; ?>
          </nav>
        </div>
        <button class="btn primary" onclick="showNewPost()" style="width:100%"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg>发帖</button>
      </aside>

      <div class="stream">
        <div class="stream-bar">
          <div class="tab-bar" style="border-bottom:none;padding-bottom:0;justify-content:flex-start">
            <a class="tab-p" href="community?topic=<?=$topicFilter?>&sort=hot" aria-selected="<?=$sort==='hot'?'true':'false'?>"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4.4 0 7-2.8 7-6.5 0-3.2-2-5.5-3.5-7.5C14 6 13 4 12 2c0 0-1 4-3 6-1.5 1.5-3 3.6-3 6.5C6 19.2 7.6 22 12 22Z"/></svg></span>热门</a>
            <a class="tab-p" href="community?topic=<?=$topicFilter?>&sort=new" aria-selected="<?=$sort==='new'?'true':'false'?>"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></span>最新</a>
          </div>
          <span class="count"><?=count($display)?> 帖</span>
        </div>

        <div id="newPostBox" class="card newpost" style="display:none">
          <input id="np_title" class="inp" placeholder="标题">
          <textarea id="np_content" class="inp" rows="3" placeholder="内容"></textarea>
          <div class="row">
            <select id="np_topic" class="inp" style="width:auto;min-height:44px">
              <?php foreach ($topics as $t): ?><option value="<?=htmlspecialchars($t['id'])?>" <?=$topicFilter===$t['id']?'selected':''?>><?=$t['icon']??''?> <?=htmlspecialchars($t['name'])?></option><?php endforeach; ?>
            </select>
            <button onclick="createPost()" class="btn primary">发布</button>
          </div>
        </div>

        <?php if (empty($display)): ?>
        <div class="empty">暂无帖子，来发第一帖吧！</div>
        <?php endif; ?>
        <?php foreach ($display as $p): $topic = !empty($p['topic']) ? ($topicNames[$p['topic']] ?? ['name'=>'综合','icon'=>'💬']) : ['name'=>'综合','icon'=>'💬']; $mid = is_array($member) ? ($member['id'] ?? '') : ''; $voted = is_array($p['voted'] ?? null) ? ($p['voted'][$mid] ?? 0) : 0; $pid = htmlspecialchars($p['id']); ?>
        <article class="card post" data-od-id="post-<?=$pid?>">
          <div class="vote">
            <button class="vote-btn <?=$voted > 0 ? 'on' : ''?>" onclick="vote('<?=$pid?>',1)" aria-label="赞同"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 15 6-6 6 6"/></svg></button>
            <b id="votes_<?=$pid?>"><?=$p['votes']??0?></b>
            <button class="vote-btn" onclick="vote('<?=$pid?>',-1)" aria-label="反对"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></button>
          </div>
          <div>
            <div class="post-meta"><span><?=$topic['icon']?> <?=htmlspecialchars($topic['name'])?></span><span>·</span><span>by <?=htmlspecialchars($p['author_name'])?></span><span>·</span><span><?=htmlspecialchars(substr($p['created_at']??'',0,10))?></span></div>
            <h3><?php if (!empty($p['pinned'])): ?><span class="pin" title="置顶"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 4 6 2-2.5 5.5L15 15l-3 1-1 4-3-6-2 .5L8 9.5 5 7l4-3Z"/></svg></span><?php endif; ?><a href="community-post/<?=urlencode($p['id'])?>"><?=htmlspecialchars($p['title'])?></a></h3>
            <p><?=htmlspecialchars(mb_substr(strip_tags($p['content']??''),0,120))?></p>
            <div class="post-act"><a href="community-post/<?=urlencode($p['id'])?>#comments"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg><?=$p['comments']??0?> 评论</a></div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ══ 收尾 CTA ══ -->
  <section id="next" class="reveal" data-od-anchor data-od-id="community-cta">
    <div class="cta-band">
      <span class="kicker">芭乐派 · 门派</span>
      <h2>还没进门派？从 New-1 开始练功</h2>
      <p class="lead">地基在 New-1~4 基石课，招式在 R.B.E 训练营，切磋在这里。先学再用，再回来交作业。</p>
      <div class="cta-row"><a class="btn primary" href="/courses">浏览课程</a><a class="btn ghost" href="/academy">去学院读文章</a></div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
var MEMBER = <?=json_encode(is_array($member) && isset($member['id']) ? ['id'=>$member['id']] : null)?>;
function showNewPost() {
  if (!MEMBER) { location.href = '/account?view=login&next=/community'; return; }
  var b = document.getElementById('newPostBox');
  b.style.display = b.style.display === 'none' ? 'block' : 'none';
}
function createPost() {
  var fd = new FormData();
  fd.append('action','create_post');
  fd.append('title', document.getElementById('np_title').value);
  fd.append('content', document.getElementById('np_content').value);
  fd.append('topic', document.getElementById('np_topic').value);
  fetch('/api/community', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){ if (d.ok) location.reload(); else alert(d.error); });
}
function vote(postId, delta) {
  if (!MEMBER) { location.href = '/account?view=login'; return; }
  var fd = new FormData();
  fd.append('action','vote'); fd.append('post_id', postId); fd.append('delta', delta);
  fetch('/api/community', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){ if (d.ok) document.getElementById('votes_' + postId).textContent = d.votes; });
}
</script>
</body>
</html>
<?php PageCache::end('community', 900); ?>
