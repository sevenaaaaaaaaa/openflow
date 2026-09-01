<?php require_once __DIR__ . '/../../../admin/config.php'; require_once __DIR__ . '/_shell.php';
$topics = json_decode(file_get_contents(DATA_DIR . '/community-topics.json'), true);
$posts = json_decode(file_get_contents(DATA_DIR . '/community-posts.json'), true);
$tn=[]; foreach($topics as $t) $tn[$t['id']]=$t;
mock_head('社区 · 方案 B', '.post-row{display:grid;grid-template-columns:minmax(150px,190px) minmax(0,1fr) auto;gap:22px;align-items:center;padding:22px 6px;border-bottom:1px solid var(--border-soft);border-radius:12px;transition:background .2s}.post-row:hover{background:var(--hover)}.post-row .m{display:flex;flex-direction:column;gap:6px;font-family:var(--font-mono);font-size:11.5px;color:var(--faint)}.post-row .m .tp{color:var(--accent);font-weight:700}.post-row h3{font-size:16.5px;font-weight:700;line-height:1.45;letter-spacing:-.01em}.post-row:hover h3{color:var(--accent)}.post-row p{font-size:13.5px;color:var(--muted);line-height:1.7;margin-top:5px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.post-row .st{display:flex;gap:14px;font-family:var(--font-mono);font-size:12px;color:var(--faint);white-space:nowrap}.post-row .st b{color:var(--fg);font-weight:700}.post-list{border-top:1px solid var(--border-soft)}.tab-bar .pin-b{margin-left:auto}');
of_shell('community'); ?>
<main id="main">
  <section id="top" class="reveal in">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">COMMUNITY · 门派</span>
      <h1>一个人做公司，<br><i class="si">不该一个人扛</i></h1>
      <p class="lead">提问、分享、讨论。学完课程的同学在这里交作业、晒增长数据、互相诊断——把增长系统这门功夫，练到身上。</p>
      <div class="cta-row"><a class="btn primary" href="#">发帖</a><a class="btn ghost" href="#">先逛逛</a></div>
      <div class="trust"><span class="dot"></span><?=count($posts)?> 个帖子 · <?=count($topics)?> 个话题</div>
    </div>
  </section>
  <section class="sec reveal in">
    <div class="tab-bar" style="justify-content:flex-start">
      <a class="tab-p" href="#" aria-selected="true">全部</a>
      <?php foreach ($topics as $t): ?><a class="tab-p" href="#"><?=$t['icon']?> <?=htmlspecialchars($t['name'])?></a><?php endforeach; ?>
      <span class="pin-b tab-bar" style="border:none;padding:0"><a class="tab-p" href="#" aria-selected="true">热门</a><a class="tab-p" href="#">最新</a></span>
    </div>
    <div class="post-list">
      <?php foreach ($posts as $p): $t=$tn[$p['topic']]; ?>
      <a class="post-row" href="#">
        <div class="m"><span class="tp"><?=$t['icon']?> <?=htmlspecialchars($t['name'])?><?=!empty($p['pinned'])?' · 置顶':''?></span><span>by <?=htmlspecialchars($p['author_name'])?></span><span><?=substr($p['created_at'],0,10)?></span></div>
        <div><h3><?=htmlspecialchars($p['title'])?></h3><p><?=htmlspecialchars($p['content'])?></p></div>
        <div class="st"><span><b><?=$p['votes']?></b> 赞</span><span><b><?=$p['comments']?></b> 评论</span></div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="reveal in">
    <div class="cta-band"><span class="kicker">芭乐派 · 门派</span><h2>还没进门派？从 New-1 开始练功</h2><p class="lead">地基在 New-1~4 基石课，招式在 R.B.E 训练营，切磋在这里。先学再用，再回来交作业。</p><div class="cta-row"><a class="btn primary" href="#">浏览课程</a><a class="btn ghost" href="#">去学院读文章</a></div></div>
  </section>
<?php mock_foot();
