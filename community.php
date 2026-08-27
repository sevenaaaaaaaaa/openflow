<?php
/**
 * 社区 — Reddit 式话题+帖子流
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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>门派社区 | <?=site_config_get('site_name')?> · 讨论</title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" defer></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .topic-btn{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--r-sm);font-size:14px;color:var(--muted);cursor:pointer;transition:.12s;text-decoration:none}
  .topic-btn:hover{background:var(--surface)}
  .topic-btn.active{background:var(--accent);color:var(--on-accent);font-weight:600}
  .post-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;margin-bottom:12px;transition:.12s}
  .post-card:hover{box-shadow:var(--shadow-sm)}
  .vote-btn{width:34px;height:34px;border-radius:var(--r-sm);display:grid;place-items:center;cursor:pointer;transition:.12s;font-size:16px}
  .vote-btn:hover{background:var(--bg)}
  .vote-btn.on{background:var(--accent)}

  /* 设计语言统一：token 语义工具类（终版契约） */
  .text-faint{color:var(--faint)}.text-muted{color:var(--muted)}.text-fg{color:var(--fg)}
  .text-ok{color:var(--ok)}.text-accent{color:var(--accent)}.text-danger{color:var(--danger)}
  .bg-surface{background:var(--surface)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260826b" data-cfasync="false" data-page="community"></script>

  <div style="padding:clamp(16px,3vw,32px) 0 8px">
    <div class="mx-auto px-5" style="max-width:1120px">
      <div style="display:flex;flex-direction:column;gap:12px">
        <span class="kicker" style="font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;color:var(--accent);text-transform:uppercase">COMMUNITY · 门派</span>
        <h1 style="font-size:clamp(28px,4vw,40px);font-weight:800;letter-spacing:-.035em;line-height:1.1;color:var(--fg)">一人公司增长门派<br><span style="font-family:var(--font-display);font-style:italic">在这里切磋、打卡、长本事</span></h1>
        <p style="color:var(--muted);font-size:15px;line-height:1.8;max-width:560px">提问、分享、讨论。学完课程的同学在这里交作业、晒增长数据、互相诊断——把增长系统这门功夫，练到身上。</p>
        <div style="display:flex;gap:18px;margin-top:4px;color:var(--faint);font-size:12.5px;flex-wrap:wrap">
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span> <b style="color:var(--fg)"><?=count($posts)?></b> 个帖子</span>
          <span style="display:inline-flex;align-items:center;gap:4px"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:3px"><path d="M3 7a2 2 0 0 1 2-2h4l2 3h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg><b style="color:var(--fg)"><?=count($topics)?></b> 个话题</span>
        </div>
      </div>
    </div>
  </div>

  <div class="mx-auto px-5 py-6" style="max-width:1120px">
    <div class="grid gap-6" style="grid-template-columns:240px 1fr;align-items:start">
      <!-- 话题侧栏 -->
      <div class="card p-3" style="background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);position:sticky;top:20px">
        <div class="px-2 py-2 font-bold text-sm">话题</div>
        <a class="topic-btn <?=!$topicFilter?'active':''?>" href="community?sort=<?=$sort?>"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg></span> 全部</a>
        <?php foreach ($topics as $t): ?>
        <a class="topic-btn <?=$topicFilter===$t['id']?'active':''?>" href="community?topic=<?=urlencode($t['id'])?>&sort=<?=$sort?>">
          <?=$t['icon']??'💬'?> <?=htmlspecialchars($t['name'])?>
        </a>
        <?php endforeach; ?>
        <div style="border-top:1px solid var(--bg);margin-top:12px;padding-top:12px">
          <button onclick="showNewPost()" class="w-full rounded-full py-2.5 font-bold" style="background:var(--accent);color:var(--on-accent)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg></span> 发帖</button>
        </div>
      </div>

      <!-- 帖子流 -->
      <div>
        <!-- 排序 -->
        <div class="flex gap-2 mb-4">
          <a href="community?topic=<?=$topicFilter?>&sort=hot" class="cat-pill" style="padding:6px 16px;border-radius:999px;border:1px solid var(--border);background:<?=$sort==='hot'?'var(--accent)':'var(--surface)'?>;font-size:13px;text-decoration:none;color:var(--muted)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4.4 0 7-2.8 7-6.5 0-3.2-2-5.5-3.5-7.5C14 6 13 4 12 2c0 0-1 4-3 6-1.5 1.5-3 3.6-3 6.5C6 19.2 7.6 22 12 22Z"/></svg></span> 热门</a>
          <a href="community?topic=<?=$topicFilter?>&sort=new" class="cat-pill" style="padding:6px 16px;border-radius:999px;border:1px solid var(--border);background:<?=$sort==='new'?'var(--accent)':'var(--surface)'?>;font-size:13px;text-decoration:none;color:var(--muted)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span> 最新</a>
          <span class="text-sm text-faint self-center ml-auto"><?=count($display)?> 帖</span>
        </div>

        <!-- 发帖框 -->
        <div id="newPostBox" style="display:none;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;margin-bottom:14px">
          <input id="np_title" placeholder="标题" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:14px;margin-bottom:10px">
          <textarea id="np_content" rows="3" placeholder="内容" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:14px;margin-bottom:10px"></textarea>
          <div style="display:flex;gap:8px;align-items:center">
            <select id="np_topic" style="padding:8px;border:1.5px solid var(--border);border-radius:var(--r-sm)">
              <?php foreach ($topics as $t): ?><option value="<?=htmlspecialchars($t['id'])?>" <?=$topicFilter===$t['id']?'selected':''?>><?=$t['icon']??''?> <?=htmlspecialchars($t['name'])?></option><?php endforeach; ?>
            </select>
            <button onclick="createPost()" class="rounded-full px-6 py-2 font-bold" style="background:var(--accent);color:var(--on-accent);margin-left:auto">发布</button>
          </div>
        </div>

        <?php if (empty($display)): ?>
        <div class="text-center py-16 text-faint">暂无帖子，来发第一帖吧！</div>
        <?php endif; ?>
        <?php foreach ($display as $p): $topic = !empty($p['topic']) ? ($topicNames[$p['topic']] ?? ['name'=>'综合','icon'=>'💬']) : ['name'=>'综合','icon'=>'💬']; ?>
        <div class="post-card">
          <div style="display:flex;gap:12px">
            <!-- 投票 -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
              <button class="vote-btn <?php $mid = is_array($member) ? ($member['id'] ?? '') : ''; $voted = is_array($p['voted'] ?? null) ? ($p['voted'][$mid] ?? 0) : 0; echo $voted > 0 ? 'on' : ''; ?>" onclick="vote('<?=htmlspecialchars($p['id'])?>',1)">▲</button>
              <div class="font-bold text-sm" id="votes_<?=htmlspecialchars($p['id'])?>"><?=$p['votes']??0?></div>
              <button class="vote-btn" onclick="vote('<?=htmlspecialchars($p['id'])?>',-1)">▼</button>
            </div>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--faint);margin-bottom:4px">
                <span><?=$topic['icon']?> <?=htmlspecialchars($topic['name'])?></span>
                <span>·</span>
                <span>by <?=htmlspecialchars($p['author_name'])?></span>
                <span>·</span>
                <span><?=htmlspecialchars(substr($p['created_at']??'',0,10))?></span>
              </div>
              <a href="community-post/<?=urlencode($p['id'])?>" style="text-decoration:none;color:inherit">
                <div class="font-bold text-lg"><?=!empty($p['pinned'])?'<span class="pin-ic"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="m9 4 6 2-2.5 5.5L15 15l-3 1-1 4-3-6-2 .5L8 9.5 5 7l4-3Z"/></svg></span>':''?><?=htmlspecialchars($p['title'])?></div>
              </a>
              <div class="text-sm text-muted mt-1 line-clamp-2"><?=htmlspecialchars(mb_substr(strip_tags($p['content']??''),0,120))?></div>
              <div style="display:flex;align-items:center;gap:14px;margin-top:8px;font-size:13px;color:var(--faint)">
                <a href="community-post/<?=urlencode($p['id'])?>#comments" style="color:var(--faint);text-decoration:none"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span> <?=$p['comments']??0?> 评论</a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

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
<div style="padding:clamp(16px,3vw,32px) 0 8px">
  <div class="mx-auto px-5" style="max-width:1120px">
    <div style="background:linear-gradient(135deg,var(--accent),oklch(58% .16 295));border-radius:var(--r-lg);padding:clamp(28px,4vw,48px);color:#fff;text-align:center">
      <div style="font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;opacity:.75">芭乐派 · 门派</div>
      <h2 style="font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em;margin:10px 0 8px">还没进门派？从 New-1 开始练功</h2>
      <p style="opacity:.85;font-size:14.5px;line-height:1.7;max-width:560px;margin:0 auto 22px">地基在 New-1~4 基石课，招式在 R.B.E 训练营，切磋在这里。先学再用，再回来交作业。</p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="/courses" style="padding:12px 26px;border-radius:999px;font-weight:700;font-size:14px;background:#fff;color:var(--accent);text-decoration:none">浏览课程</a>
        <a href="/academy" style="padding:12px 26px;border-radius:999px;font-weight:700;font-size:14px;background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,.6);text-decoration:none">去学院读文章</a>
      </div>
    </div>
  </div>
</div>

<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
  <div class="mx-auto px-5" style="max-width:1120px">
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:28px;padding-bottom:22px;border-bottom:1px solid var(--border)">
      <div>
        <div style="font-weight:800;font-size:15px;color:var(--fg)">芭乐派 · OpenFlow</div>
        <p style="font-size:12.5px;color:var(--muted);line-height:1.7;margin-top:8px;max-width:320px">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
        <p style="font-size:12px;color:var(--faint);margin-top:6px">核心能力永久开源 · 鱼与渔相结合</p>
      </div>
      <div>
        <div style="font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;margin-bottom:10px">站点导航</div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <a href="/product" style="color:var(--muted);text-decoration:none;font-size:13px">产品</a>
          <a href="/capability" style="color:var(--muted);text-decoration:none;font-size:13px">能力</a>
          <a href="/courses" style="color:var(--muted);text-decoration:none;font-size:13px">课程</a>
          <a href="/academy" style="color:var(--muted);text-decoration:none;font-size:13px">学院</a>
          <a href="/about" style="color:var(--muted);text-decoration:none;font-size:13px">关于我们</a>
        </div>
      </div>
      <div>
        <div style="font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;margin-bottom:10px">资源</div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <a href="/docs" style="color:var(--muted);text-decoration:none;font-size:13px">文档中心</a>
          <a href="/downloads" style="color:var(--muted);text-decoration:none;font-size:13px">资料下载</a>
          <a href="/podcasts" style="color:var(--muted);text-decoration:none;font-size:13px">播客</a>
          <a href="/marketplace" style="color:var(--muted);text-decoration:none;font-size:13px">生态市场</a>
        </div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding-top:16px;flex-wrap:wrap">
      <div style="font-size:12px;color:var(--muted)">© 2026 芭乐派 · OpenFlow 增长操作系统</div>
      <div style="font-size:12px;color:var(--faint)">帮一人公司设计 Agent 能跑的增长系统</div>
    </div>
  </div>
</footer>
</body>
</html>
<?php PageCache::end('community', 900); ?>
