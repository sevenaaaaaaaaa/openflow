<?php
/**
 * 作者/讲师主页
 *
 * v7（2026-09-01）：迁到共享 archetype（作者头 + stats + 分组 link-grid）。数据逻辑原样保留。
 * /authors/{name} — 显示该作者的简介、文章、课程、Skills/插件
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/MemberSystem.php';

require_once __DIR__ . '/lib/AuthorSystem.php';

// 支持 /authors/{slug} 或 ?name= / ?slug=；优先按已建档作者解析
$slug = trim(urldecode($_GET['slug'] ?? ''));
$authorName = trim(urldecode($_GET['name'] ?? ''));
$entity = null;
if ($slug !== '')            $entity = author_by_slug($slug);
if (!$entity && $authorName !== '') $entity = author_by_name($authorName);
if ($entity) $authorName = $entity['name'];   // 归一到规范名，别名也能进来
if (!$authorName) {
    header('Location: /');
    exit;
}

// 该作者名下所有可署名（含别名）
$names = $entity ? array_merge([$entity['name']], (array)($entity['aliases'] ?? [])) : [$authorName];
$byAuthor = fn($x) => in_array(trim((string)($x['author'] ?? '')), $names, true);

// 聚合该作者的内容
$articles = array_values(array_filter(get_articles(), fn($a) => ($a['status'] ?? 'draft') === 'published' && $byAuthor($a)));
$skills = array_values(array_filter(json_read(DATA_DIR . '/skills/index.json'), $byAuthor));
$courses = array_values(array_filter(json_read(DATA_DIR . '/courses/index.json'), $byAuthor));
$plugins = array_values(array_filter(json_read(DATA_DIR . '/plugins.json'), $byAuthor));

// 档案优先：已建档就用档案；否则退回"碰巧同名的会员"
$authorProfile = null;
foreach (json_read(DATA_DIR . '/members/index.json') as $m) {
    if (($m['name'] ?? '') === $authorName || ($m['nickname'] ?? '') === $authorName) { $authorProfile = $m; break; }
    if ($entity && !empty($entity['member_id']) && ($m['id'] ?? '') === $entity['member_id']) { $authorProfile = $m; break; }
}
$avatar = ($entity['avatar'] ?? '') ?: ($authorProfile['avatar'] ?? '');
$bio    = ($entity['bio'] ?? '') ?: ($authorProfile['bio'] ?? '');
$authorTitle = $entity['title'] ?? '';
$authorLinks = (array)($entity['links'] ?? []);

$catNames = [];
foreach (get_categories('article') as $c) $catNames[$c['key']] = $c['name'];

$pageTitle = $authorName . ' 的主页 | ' . site_config_get('site_name');
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($pageTitle)?></title>
<meta name="description" content="<?=htmlspecialchars($authorName)?> 在 <?=site_config_get('site_name')?> 发布的文章、课程与技能">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 作者页独有：作者头。其余全部来自 modules.css。 */
.au-head{display:flex;align-items:center;gap:24px;flex-wrap:wrap}
.au-head .av{width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--surface-strong);box-shadow:var(--shadow-sm);flex:0 0 auto}
.au-head .av.txt{display:grid;place-items:center;background:var(--accent-soft);color:var(--accent-strong);font-family:var(--font-display);font-size:34px;font-weight:700}
.au-head .bd{flex:1;min-width:240px;display:flex;flex-direction:column;gap:8px}
.au-head h1{font-size:clamp(26px,3.5vw,36px);font-weight:800;letter-spacing:-.02em}
.au-head .ttl{color:var(--accent);font-weight:600;font-size:14px}
.au-head p{color:var(--muted);font-size:15px;line-height:1.8;max-width:640px}
.au-links{display:flex;gap:8px;flex-wrap:wrap}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('author'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section id="top" class="reveal in" data-od-anchor data-od-id="author-head">
    <div class="card au-head">
      <?php if ($avatar): ?>
      <img class="av" src="<?=htmlspecialchars(strpos($avatar,'http')===0?$avatar:'/'.ltrim($avatar,'/'))?>" alt="<?=htmlspecialchars($authorName)?>">
      <?php else: ?>
      <div class="av txt"><?=htmlspecialchars(mb_substr($authorName, 0, 1))?></div>
      <?php endif; ?>
      <div class="bd">
        <span class="kicker">作者 · AUTHOR</span>
        <h1><?=htmlspecialchars($authorName)?></h1>
        <?php if (!empty($authorTitle)): ?><div class="ttl"><?=htmlspecialchars($authorTitle)?></div><?php endif; ?>
        <p><?=$bio ? htmlspecialchars($bio) : ('专注内容创作与分享，在 ' . site_config_get('site_name') . ' 发布 ' . count($articles) . ' 篇文章、' . count($skills) . ' 个技能。')?></p>
        <?php if (!empty($authorLinks)): ?>
        <div class="au-links"><?php foreach ($authorLinks as $l): if (empty($l['url'])) continue; ?><a class="pill neutral" href="<?=htmlspecialchars($l['url'])?>" target="_blank" rel="nofollow noopener"><?=htmlspecialchars($l['label'] ?? $l['url'])?></a><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="sec reveal" data-od-anchor data-od-id="author-stats">
    <div class="stats">
      <div class="st"><div class="st-n"><?=count($articles)?></div><span class="st-en">Articles</span><span class="st-t">篇文章</span></div>
      <div class="st"><div class="st-n"><?=count($courses)?></div><span class="st-en">Courses</span><span class="st-t">门课程</span></div>
      <div class="st"><div class="st-n"><?=count($skills)?></div><span class="st-en">Skills</span><span class="st-t">个技能</span></div>
      <div class="st"><div class="st-n"><?=count($plugins)?></div><span class="st-en">Plugins</span><span class="st-t">个插件</span></div>
    </div>
  </section>

  <section class="sec reveal" data-od-anchor data-od-id="author-works">
    <?php if ($articles): ?>
    <div class="sec-head row"><div><span class="kicker">文章</span><h2>TA 写的</h2></div></div>
    <div class="link-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:28px">
      <?php foreach (array_slice($articles, 0, 12) as $a): ?>
      <a class="link-it" href="/articles/<?=htmlspecialchars($a['slug'])?>"><span class="lt"><b><?=htmlspecialchars($a['title'])?></b><span><?=htmlspecialchars($catNames[$a['category'] ?? ''] ?? '')?> · <?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($courses): ?>
    <div class="sec-head row"><div><span class="kicker">课程</span><h2>TA 讲的</h2></div></div>
    <div class="link-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:28px">
      <?php foreach ($courses as $c): ?>
      <a class="link-it" href="/courses/<?=htmlspecialchars($c['id'])?>"><span class="lt"><b><?=htmlspecialchars($c['title'] ?? '')?></b><span><?=($c['price'] ?? 0) ? '¥'.$c['price'] : '免费'?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($skills): ?>
    <div class="sec-head row"><div><span class="kicker">技能</span><h2>TA 做的 Skill</h2></div></div>
    <div class="link-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:28px">
      <?php foreach ($skills as $sk): ?>
      <a class="link-it" href="/marketplace?view=skill&id=<?=htmlspecialchars($sk['id'])?>"><span class="lt"><b><?=htmlspecialchars($sk['title'] ?? '')?></b><span><?=htmlspecialchars($sk['type'] ?? '')?> · <?=htmlspecialchars(mb_substr($sk['desc'] ?? '', 0, 60))?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($plugins): ?>
    <div class="sec-head row"><div><span class="kicker">插件</span><h2>TA 做的插件</h2></div></div>
    <div class="link-grid" style="grid-template-columns:repeat(2,1fr)">
      <?php foreach ($plugins as $p): ?>
      <a class="link-it" href="/marketplace?view=plugin&id=<?=htmlspecialchars($p['id'] ?? '')?>"><span class="lt"><b><?=htmlspecialchars($p['name'] ?? $p['title'] ?? '')?></b><span>插件</span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!$articles && !$courses && !$skills && !$plugins): ?><div class="empty">这位作者还没有发布内容</div><?php endif; ?>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
