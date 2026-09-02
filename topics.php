<?php
/**
 * 专题聚合页 — 浏览所有专题及其下文章
 *
 * v7（2026-09-01）：迁到共享 archetype；顺手修掉 <body> 标签被 site-shell 脚本截断的错误。数据逻辑原样保留。
 * /topics/{slug}  单个专题详情
 * /topics.php    专题列表
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$topics = json_read(DATA_DIR . '/topics.json');
$articles = get_articles();
$articleMap = [];
foreach ($articles as $a) $articleMap[$a['id']] = $a;
$catNames = [];
foreach (get_categories('article') as $c) $catNames[$c['key']] = $c['name'];

$topics = array_values(array_filter($topics, fn($t) => ($t['status'] ?? '') === 'published'));
usort($topics, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));

// 单个专题详情模式
$topicSlug = trim(req_str('slug'));
$currentTopic = null;
if ($topicSlug) {
    foreach ($topics as $t) {
        if (($t['slug'] ?? '') === $topicSlug) { $currentTopic = $t; break; }
    }
}
if ($currentTopic) {
    $topicArticles = [];
    foreach (($currentTopic['article_ids'] ?? $currentTopic['articles'] ?? []) as $aid) {
        if (isset($articleMap[$aid]) && ($articleMap[$aid]['status'] ?? 'draft') === 'published') {
            $topicArticles[] = $articleMap[$aid];
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $currentTopic ? (htmlspecialchars($currentTopic['title'] ?? '专题') . ' | ' . site_config_get('site_name')) : ('专题合集 | ' . site_config_get('site_name')) ?></title>
<link rel="stylesheet" href="/assets/tokens.css?v=20260903a">
<link rel="stylesheet" href="/assets/modules.css?v=20260903a">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 专题页独有：专题卡。其余全部来自 modules.css。 */
.tp-card{display:flex;flex-direction:column;gap:14px}
.tp-card .hd{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
.tp-card .cov{width:140px;height:90px;object-fit:cover;border-radius:12px;flex:0 0 auto}
.tp-card .hd>div{flex:1;min-width:240px}
.tp-card h2{font-size:20px;font-weight:800;letter-spacing:-.01em;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.tp-card p{font-size:14px;color:var(--muted);line-height:1.75;margin-top:6px}
.tp-card .more{font-size:12.5px;color:var(--accent);font-weight:600}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('topics'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <?php if ($currentTopic): ?>
  <section id="top" class="reveal in" data-od-anchor data-od-id="topic-head">
    <nav class="art-meta" aria-label="面包屑" style="justify-content:center"><a href="/topics" style="color:var(--faint)">← 全部专题</a></nav>
    <div class="hero-center" style="padding-top:18px;padding-bottom:0">
      <span class="kicker">专题</span>
      <h1><?=htmlspecialchars($currentTopic['title'] ?? '')?></h1>
      <p class="lead"><?=htmlspecialchars($currentTopic['description'] ?? '')?></p>
      <?php if (!empty($currentTopic['category'])): ?><span class="badge ok"><?=htmlspecialchars($catNames[$currentTopic['category']] ?? $currentTopic['category'])?></span><?php endif; ?>
    </div>
  </section>
  <section class="sec reveal reader" data-od-anchor data-od-id="topic-articles">
    <div class="sec-head row"><div><span class="kicker">收录文章 · <?=count($topicArticles)?> 篇</span><h2>按顺序读</h2></div></div>
    <?php if (empty($topicArticles)): ?>
    <div class="empty">该专题下暂无文章</div>
    <?php else: ?>
    <div class="rank">
      <?php foreach ($topicArticles as $i => $a): ?>
      <a href="/articles/<?=htmlspecialchars($a['slug'])?>"><span class="n"><?=$i+1?></span><span class="t"><b><?=htmlspecialchars($a['title'])?></b><span><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?> · <?=htmlspecialchars($catNames[$a['category'] ?? ''] ?? '')?></span></span></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php else: ?>
  <section id="top" class="reveal in" data-od-anchor data-od-id="topics-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">专题</span>
      <h1>一次读透<i class="si">一个主题</i></h1>
      <p class="lead">围绕核心议题的深度内容聚合，一次读透一个主题</p>
    </div>
  </section>
  <section class="sec reveal" data-od-anchor data-od-id="topics-list">
    <?php if (empty($topics)): ?>
    <div class="empty">专题筹备中</div>
    <?php else: foreach ($topics as $t): $tArts = array_values(array_filter(array_map(fn($id) => $articleMap[$id] ?? null, $t['article_ids'] ?? []))); ?>
    <div class="card tp-card">
      <div class="hd">
        <?php if (!empty($t['cover'])): ?><img class="cov" src="<?=htmlspecialchars(strpos($t['cover'],'http')===0?$t['cover']:'/'.ltrim($t['cover'],'/'))?>" alt="" onerror="this.remove()"><?php endif; ?>
        <div>
          <h2><a href="/topics/<?=htmlspecialchars($t['slug'] ?? '')?>"><?=htmlspecialchars($t['title'] ?? '')?></a><?php if (!empty($t['category'])): ?><span class="badge ok"><?=htmlspecialchars($catNames[$t['category']] ?? $t['category'])?></span><?php endif; ?><span class="note"><?=count($tArts)?> 篇文章</span></h2>
          <p><?=htmlspecialchars($t['description'] ?? '')?></p>
        </div>
      </div>
      <div class="rank">
        <?php foreach (array_slice($tArts, 0, 5) as $i => $a): ?>
        <a href="/articles/<?=htmlspecialchars($a['slug'])?>"><span class="n"><?=$i+1?></span><span class="t"><b><?=htmlspecialchars($a['title'])?></b><span><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?></span></span></a>
        <?php endforeach; ?>
      </div>
      <?php if (count($tArts) > 5): ?><a class="more" href="/topics/<?=htmlspecialchars($t['slug'] ?? '')?>">+ <?=count($tArts)-5?> 篇更多 →</a><?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
  </section>
  <?php endif; ?>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
