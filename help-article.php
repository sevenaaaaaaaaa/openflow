<?php
/**
 * 帮助中心 · 指南详情 — Markdown 渲染 + TOC 滚动监听 + 有用反馈 + 相关指南
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/HelpCenter.php';
require_once __DIR__ . '/lib/Markdown.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$article = $slug ? HelpCenter::get($slug) : null;

if (!$article) {
    http_response_code(404);
    require_once __DIR__ . '/includes/site-head.php';
    require_once __DIR__ . '/includes/site-nav.php';
    require_once __DIR__ . '/lib/SeoHead.php';
    ?><!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>指南不存在 | <?=site_config_get('site_name')?> 帮助中心</title>
<meta name="robots" content="noindex">
<?php of_head_assets(); ?>
</head>
<body>
<?php of_shell('help'); ?>
<div style="text-align:center;padding:90px 20px"><div style="font-size:44px;margin-bottom:14px">📭</div><h1 style="font-size:22px;margin:0 0 8px">这篇指南不存在或已下线</h1><p style="color:var(--text-2);margin-bottom:22px">去帮助中心首页搜索试试</p><a href="/help" style="color:var(--accent);font-weight:700">← 返回帮助中心</a></div>
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</body>
</html><?php
    exit;
}

$cat = HelpCenter::category((string)($article['cat'] ?? ''));
$related = HelpCenter::related($article);
$fb = HelpCenter::feedbackGet($article['slug']);
$html = Markdown::toHtml((string)($article['content'] ?? ''));

// 提取 h2 做 TOC
preg_match_all('/<h2[^>]*>(.*?)<\/h2>/s', $html, $m);
$toc = [];
foreach ($m[1] as $i => $t) {
    $plain = trim(strip_tags($t));
    $toc[] = ['id' => 'h2-' . $i, 'text' => $plain];
    $html = preg_replace('/<h2([^>]*)>' . preg_quote($t, '/') . '<\/h2>/s', '<h2$1 id="h2-' . $i . '">' . $t . '</h2>', $html, 1);
}

$meta = ['title' => $article['title'] . ' · 帮助中心', 'desc' => (string)($article['excerpt'] ?? '')];
require_once __DIR__ . '/includes/site-head.php';
require_once __DIR__ . '/includes/site-nav.php';
require_once __DIR__ . '/lib/SeoHead.php';
$helpUrl = rtrim(site_config_get('site_url', ''), '/') . '/help/' . $article['slug'];
?><!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($meta['title'])?> | <?=site_config_get('site_name')?></title>
<meta name="description" content="<?=htmlspecialchars($meta['desc'])?>">
<?php seo_head(['title' => $meta['title'] . ' | ' . site_config_get('site_name'), 'description' => $meta['desc'], 'canonical' => $helpUrl, 'type' => 'article', 'json_ld' => [
    '@context' => 'https://schema.org', '@type' => 'TechArticle',
    'headline' => $article['title'], 'description' => $meta['desc'],
    'url' => $helpUrl, 'dateModified' => $article['updated_at'] ?? '',
]]); ?>
<?php of_head_assets(); ?>
</head>
<body>
<a class="skip" href="#ha-body">跳到主要内容</a>
<?php of_shell('help'); ?>

<style>
.ha-wrap{display:grid;grid-template-columns:1fr 240px;gap:44px;padding:36px 0 60px}
.ha-crumb{font-size:12.5px;color:var(--faint);margin-bottom:18px}
.ha-crumb a{color:var(--faint);text-decoration:none}
.ha-crumb a:hover{color:var(--accent)}
.ha-title{font-size:clamp(24px,3.4vw,34px);font-weight:800;letter-spacing:-0.02em;margin:0 0 10px;line-height:1.3}
.ha-meta{font-size:12.5px;color:var(--faint);margin-bottom:30px;display:flex;gap:14px;align-items:center}
.ha-meta .ha-cat{font-weight:700;color:var(--accent);background:oklch(from var(--accent) l c h / 0.1);border-radius:6px;padding:3px 10px}
.ha-body{font-size:15.5px;line-height:1.85;color:var(--fg)}
.ha-body h2{font-size:21px;font-weight:800;margin:38px 0 14px;letter-spacing:-0.01em;scroll-margin-top:90px}
.ha-body p{margin:0 0 16px}
.ha-body ul,.ha-body ol{margin:0 0 16px;padding-left:22px}
.ha-body li{margin-bottom:7px}
.ha-body code{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:1px 7px;font-size:13px;font-family:var(--font-mono)}
.ha-body table{width:100%;border-collapse:collapse;margin:0 0 18px;font-size:13.5px}
.ha-body th{text-align:left;padding:9px 12px;border-bottom:2px solid var(--border);font-weight:700}
.ha-body td{padding:9px 12px;border-bottom:1px solid var(--border-soft);color:var(--text-2)}
.ha-body a{color:var(--accent)}
.ha-body img{max-width:100%;border-radius:14px;border:1px solid var(--border);box-shadow:0 8px 30px rgba(0,0,0,.08);margin:8px 0 22px;display:block}
.ha-body blockquote{margin:0 0 18px;padding:14px 18px;border-left:3px solid var(--accent);background:var(--surface);border-radius:0 12px 12px 0;color:var(--text-2);font-size:14.5px}
.ha-body blockquote p{margin:0}
.ha-body strong{font-weight:700}
/* TOC */
.ha-toc{position:sticky;top:90px;align-self:start}
.ha-toc .tt{font-size:11px;font-weight:700;letter-spacing:.08em;color:var(--faint);text-transform:uppercase;margin-bottom:10px}
.ha-toc a{display:block;font-size:13px;color:var(--text-2);text-decoration:none;padding:6px 0 6px 14px;border-left:2px solid var(--border);transition:all .15s;line-height:1.5}
.ha-toc a:hover{color:var(--fg)}
.ha-toc a.on{color:var(--accent);border-left-color:var(--accent);font-weight:700}
/* 反馈 */
.ha-fb{margin:44px 0 0;padding:26px;border:1px solid var(--border);border-radius:18px;text-align:center;background:var(--surface)}
.ha-fb .q{font-size:15px;font-weight:700;margin-bottom:14px}
.ha-fb button{border:1.5px solid var(--border);background:var(--surface-strong);border-radius:99px;padding:9px 22px;font-size:14px;font-weight:700;cursor:pointer;margin:0 5px;transition:all .18s;color:var(--fg)}
.ha-fb button:hover{border-color:var(--accent);transform:translateY(-2px)}
.ha-fb button.voted-up{background:var(--ok,#22c55e);border-color:var(--ok,#22c55e);color:#fff}
.ha-fb .fb-stat{font-size:12px;color:var(--faint);margin-top:12px}
/* 相关 */
.ha-rel{margin-top:36px}
.ha-rel h3{font-size:16px;font-weight:800;margin:0 0 12px}
.ha-rel a{display:flex;align-items:center;gap:10px;padding:12px 16px;border:1px solid var(--border);border-radius:12px;text-decoration:none;color:var(--fg);font-size:14px;font-weight:600;margin-bottom:8px;transition:all .15s}
.ha-rel a:hover{border-color:var(--accent);transform:translateX(3px)}
@media (max-width:900px){.ha-wrap{grid-template-columns:1fr}.ha-toc{display:none}}
</style>

<div class="ha-wrap">
  <div>
    <nav class="ha-crumb"><a href="/help">帮助中心</a> / <a href="/help#cat-<?=$cat['id'] ?? ''?>"><?=htmlspecialchars($cat['name'] ?? '指南')?></a> / <span style="color:var(--text-2)"><?=htmlspecialchars($article['title'])?></span></nav>
    <h1 class="ha-title"><?=htmlspecialchars($article['title'])?></h1>
    <div class="ha-meta">
      <span class="ha-cat"><?=$cat['icon'] ?? '📄'?> <?=htmlspecialchars($cat['name'] ?? '')?></span>
      <span>更新于 <?=htmlspecialchars($article['updated_at'] ?? '')?></span>
    </div>
    <div class="ha-body" id="ha-body"><?=$html?></div>

    <div class="ha-fb" id="haFb">
      <div class="q">这篇指南对你有用吗？</div>
      <button onclick="haVote('up', this)">👍 有用</button>
      <button onclick="haVote('down', this)">👎 没解决</button>
      <div class="fb-stat" id="haFbStat"><?=$fb['up'] + $fb['down'] > 0 ? $fb['up'] . ' 人觉得有用' : '成为第一个反馈的人'?></div>
    </div>

    <?php if ($related): ?>
    <div class="ha-rel">
      <h3>📚 相关指南</h3>
      <?php foreach ($related as $r): ?>
      <a href="/help/<?=urlencode($r['slug'])?>"><span>→</span><span><?=htmlspecialchars($r['title'])?></span></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if (count($toc) >= 2): ?>
  <aside class="ha-toc">
    <div class="tt">本页内容</div>
    <?php foreach ($toc as $t): ?>
    <a href="#<?=$t['id']?>" data-toc="<?=$t['id']?>"><?=htmlspecialchars($t['text'])?></a>
    <?php endforeach; ?>
  </aside>
  <?php endif; ?>
</div>

<script>
(function () {
  /* TOC 滚动监听 */
  var links = document.querySelectorAll('[data-toc]');
  if (links.length) {
    var heads = [];
    links.forEach(function (a) { var el = document.getElementById(a.dataset.toc); if (el) heads.push({ a: a, el: el }); });
    function spy() {
      var cur = null;
      heads.forEach(function (h) { if (h.el.getBoundingClientRect().top < 110) cur = h; });
      heads.forEach(function (h) { h.a.classList.toggle('on', h === cur); });
    }
    document.addEventListener('scroll', spy, { passive: true });
    spy();
  }
  /* 反馈 */
  window.haVote = function (vote, btn) {
    if (localStorage.getItem('of_help_fb_<?=$article['slug']?>')) return;
    fetch('/api/help-feedback.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'slug=<?=urlencode($article['slug'])?>&vote=' + vote })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) return;
        localStorage.setItem('of_help_fb_<?=$article['slug']?>', vote);
        btn.classList.add('voted-up');
        document.getElementById('haFbStat').textContent = vote === 'up' ? '感谢！已记录 👍' : '收到，我们会补充完善这篇指南';
      });
  };
  if (localStorage.getItem('of_help_fb_<?=$article['slug']?>')) {
    document.getElementById('haFbStat').textContent = '你已反馈过，感谢！';
  }
})();
</script>
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</body>
</html>
