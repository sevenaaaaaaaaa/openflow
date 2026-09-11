<?php
/**
 * 帮助中心首页 — 即时搜索 + 分类导航 + 热门文章
 * 交互：输入即搜（防抖）· 键盘 ↑↓/Enter 导航 · 匹配高亮 · 分类过滤
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/HelpCenter.php';

if (PageCache::begin('help', 300)) exit;

$cats = HelpCenter::categories();
$articles = HelpCenter::articles();
$hot = HelpCenter::hot(6);
$searchIndex = HelpCenter::searchIndex();
$catMap = [];
foreach ($cats as $c) $catMap[$c['id']] = $c;

$meta = ['title' => '帮助中心', 'desc' => 'OpenFlow 使用指南：快速上手、内容创作、增长营销、直播带货、AI 能力与账户设置。'];
require_once __DIR__ . '/includes/site-head.php';
require_once __DIR__ . '/includes/site-nav.php';
require_once __DIR__ . '/lib/SeoHead.php';
?><!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($meta['title'])?> | <?=site_config_get('site_name')?></title>
<meta name="description" content="<?=htmlspecialchars($meta['desc'])?>">
<?php seo_head(['title' => $meta['title'] . ' | ' . site_config_get('site_name'), 'description' => $meta['desc'], 'canonical' => rtrim(site_config_get('site_url', ''), '/') . '/help']); ?>
<?php of_head_assets(); ?>
</head>
<body>
<a class="skip" href="#top">跳到主要内容</a>
<?php of_shell('help'); ?>

<style>
.help-hero{text-align:center;padding:clamp(48px,8vw,84px) 0 clamp(30px,4vw,44px);position:relative}
.help-hero::before{content:'';position:absolute;inset:-20px 0 auto;height:340px;background:radial-gradient(60% 80% at 50% 0%,oklch(from var(--accent) l c h / 0.1),transparent 70%);pointer-events:none}
.help-hero h1{font-size:clamp(28px,4.5vw,42px);font-weight:800;letter-spacing:-0.02em;margin:0 0 10px;position:relative}
.help-hero .sub{color:var(--text-2);font-size:clamp(14px,1.6vw,16px);margin-bottom:30px;position:relative}
/* 搜索 */
.help-search{position:relative;max-width:620px;margin:0 auto}
.help-search input{width:100%;height:58px;padding:0 24px 0 54px;border-radius:99px;border:1.5px solid var(--border);background:var(--surface);font-size:16px;color:var(--fg);outline:none;transition:all .25s;box-shadow:0 2px 12px oklch(0.2 0.02 262 / 0.05)}
.help-search input:focus{border-color:var(--accent);box-shadow:0 0 0 4px oklch(from var(--accent) l c h / 0.14),0 8px 30px oklch(0.2 0.02 262 / 0.1)}
.help-search .hs-ic{position:absolute;left:20px;top:50%;transform:translateY(-50%);font-size:18px;color:var(--faint);pointer-events:none}
.help-search .hs-kbd{position:absolute;right:16px;top:50%;transform:translateY(-50%);font-family:var(--font-mono);font-size:11px;color:var(--faint);border:1px solid var(--border);border-radius:6px;padding:2px 7px;pointer-events:none}
/* 搜索结果浮层 */
.hs-results{position:absolute;top:calc(100% + 10px);left:0;right:0;background:var(--surface-strong);border:1px solid var(--border);border-radius:18px;box-shadow:0 18px 50px oklch(0.2 0.02 262 / 0.16);overflow:hidden;z-index:50;display:none;text-align:left}
.hs-results.on{display:block;animation:hsIn .18s cubic-bezier(.22,.9,.3,1)}
@keyframes hsIn{from{opacity:0;transform:translateY(-6px) scale(.98)}to{opacity:1;transform:none}}
.hs-item{display:flex;align-items:center;gap:12px;padding:13px 18px;cursor:pointer;border-bottom:1px solid var(--border-soft);transition:background .12s}
.hs-item:last-child{border-bottom:0}
.hs-item:hover,.hs-item.sel{background:var(--accent-soft,oklch(0.95 0.03 262))}
.hs-item .hs-cat{flex:none;font-size:10.5px;font-weight:700;color:var(--accent);background:oklch(from var(--accent) l c h / 0.1);border-radius:6px;padding:2px 8px}
.hs-item .hs-t{font-size:14px;font-weight:600;color:var(--fg)}
.hs-item .hs-e{font-size:12px;color:var(--faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.hs-item mark{background:oklch(0.8 0.15 85 / 0.5);color:inherit;border-radius:3px;padding:0 1px}
.hs-empty{padding:22px;text-align:center;font-size:13px;color:var(--faint)}
/* 分类卡 */
.help-cats{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin:44px 0 12px}
.help-cat{display:block;background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:22px;text-decoration:none;color:var(--fg);transition:all .22s cubic-bezier(.22,.9,.3,1)}
.help-cat:hover{transform:translateY(-3px);border-color:var(--accent);box-shadow:0 12px 34px oklch(0.2 0.02 262 / 0.1)}
.help-cat .hc-ic{font-size:26px;margin-bottom:10px}
.help-cat h3{margin:0 0 5px;font-size:16px;font-weight:700}
.help-cat p{margin:0;font-size:12.5px;color:var(--text-2);line-height:1.6}
.help-cat .hc-n{display:inline-block;margin-top:10px;font-size:11px;font-weight:700;color:var(--accent);font-family:var(--font-mono)}
/* 文章行 */
.help-list{display:flex;flex-direction:column;gap:2px}
.help-row{display:flex;align-items:center;gap:12px;padding:13px 16px;border-radius:12px;text-decoration:none;color:var(--fg);transition:background .14s}
.help-row:hover{background:var(--surface)}
.help-row .hr-t{font-size:14.5px;font-weight:600}
.help-row .hr-e{display:block;font-size:12.5px;color:var(--faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.help-row .hr-arrow{margin-left:auto;color:var(--faint);transition:transform .15s,color .15s;flex:none}
.help-row:hover .hr-arrow{transform:translateX(3px);color:var(--accent)}
.help-sec-t{display:flex;align-items:baseline;gap:10px;margin:40px 0 12px}
.help-sec-t h2{margin:0;font-size:20px;font-weight:800;letter-spacing:-0.01em}
.help-sec-t .hint{font-size:12.5px;color:var(--faint)}
.help-foot{margin:56px 0 20px;text-align:center;padding:34px 20px;border:1px solid var(--border);border-radius:20px;background:linear-gradient(135deg,oklch(from var(--accent) l c h / 0.06),transparent 60%)}
.help-foot h3{margin:0 0 8px;font-size:18px}
.help-foot p{color:var(--text-2);font-size:13.5px;margin:0 0 16px}
@media (max-width:640px){.help-search input{height:52px;font-size:15px}.help-cats{grid-template-columns:1fr 1fr}}
@media (prefers-reduced-motion: reduce){.hs-results.on{animation:none}.help-cat,.help-row,.help-row .hr-arrow{transition:none}}
</style>

<section class="help-hero" id="top">
  <h1>有什么可以帮你？</h1>
  <p class="sub">搜索指南，或按分类浏览 — 共 <?=count($articles)?> 篇指南</p>
  <div class="help-search">
    <span class="hs-ic">🔍</span>
    <input type="text" id="helpSearch" placeholder="搜索：如「直播」「邮件」「自动化」…" autocomplete="off" spellcheck="false">
    <span class="hs-kbd">↑↓ 选择</span>
    <div class="hs-results" id="hsResults"></div>
  </div>
</section>

<section class="help-cats">
  <?php foreach ($cats as $c): $n = count(HelpCenter::articles($c['id'])); ?>
  <a class="help-cat" href="#cat-<?=$c['id']?>">
    <div class="hc-ic"><?=$c['icon']?></div>
    <h3><?=htmlspecialchars($c['name'])?></h3>
    <p><?=htmlspecialchars($c['desc'])?></p>
    <span class="hc-n"><?=$n?> 篇 →</span>
  </a>
  <?php endforeach; ?>
</section>

<?php if ($hot): ?>
<section>
  <div class="help-sec-t"><h2>🔥 热门指南</h2><span class="hint">最常查阅</span></div>
  <div class="help-list">
    <?php foreach ($hot as $a): ?>
    <a class="help-row" href="/help/<?=urlencode($a['slug'])?>">
      <span><?=$catMap[$a['cat']]['icon'] ?? '📄'?></span>
      <span style="min-width:0"><span class="hr-t"><?=htmlspecialchars($a['title'])?></span><br><span class="hr-e"><?=htmlspecialchars($a['excerpt'])?></span></span>
      <span class="hr-arrow">→</span>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php foreach ($cats as $c): $list = HelpCenter::articles($c['id']); if (!$list) continue; ?>
<section id="cat-<?=$c['id']?>">
  <div class="help-sec-t"><h2><?=$c['icon']?> <?=htmlspecialchars($c['name'])?></h2><span class="hint"><?=htmlspecialchars($c['desc'])?></span></div>
  <div class="help-list">
    <?php foreach ($list as $a): ?>
    <a class="help-row" href="/help/<?=urlencode($a['slug'])?>">
      <span style="min-width:0"><span class="hr-t"><?=htmlspecialchars($a['title'])?></span><br><span class="hr-e"><?=htmlspecialchars($a['excerpt'])?></span></span>
      <span class="hr-arrow">→</span>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>

<section class="help-foot">
  <h3>没找到答案？</h3>
  <p>到社区发帖提问，或给我们写邮件——通常 24 小时内回复。</p>
  <a href="/community" class="btn btn-primary" style="display:inline-block;padding:10px 26px;border-radius:99px;background:var(--accent);color:#fff;text-decoration:none;font-weight:700;font-size:14px">去社区提问 →</a>
</section>

<script>
(function () {
  var INDEX = <?=json_encode($searchIndex, JSON_UNESCAPED_UNICODE)?>;
  var CATS = <?=json_encode(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name'], 'icon' => $c['icon']], $cats), JSON_UNESCAPED_UNICODE)?>;
  var input = document.getElementById('helpSearch');
  var box = document.getElementById('hsResults');
  var sel = -1, results = [];

  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function hi(text, q) {
    var t = esc(text);
    var e = esc(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    if (!e) return t;
    return t.replace(new RegExp('(' + e + ')', 'ig'), '<mark>$1</mark>');
  }
  function search(q) {
    q = q.trim().toLowerCase();
    if (!q) return [];
    return INDEX.filter(function (a) {
      return a.title.toLowerCase().indexOf(q) >= 0 || (a.excerpt || '').toLowerCase().indexOf(q) >= 0;
    }).slice(0, 7);
  }
  function render(q) {
    results = search(input.value);
    sel = -1;
    if (!input.value.trim()) { box.classList.remove('on'); return; }
    if (!results.length) {
      box.innerHTML = '<div class="hs-empty">没有找到「' + esc(input.value) + '」相关的指南<br><span style="font-size:11.5px">换个关键词，或到 <a href="/community" style="color:var(--accent)">社区</a> 提问</span></div>';
    } else {
      box.innerHTML = results.map(function (a, i) {
        var cat = CATS.find(function (c) { return c.id === a.cat; }) || {};
        return '<div class="hs-item" data-i="' + i + '">' +
          '<span class="hs-cat">' + (cat.icon || '') + ' ' + esc(cat.name || '') + '</span>' +
          '<span style="min-width:0"><div class="hs-t">' + hi(a.title, input.value.trim()) + '</div>' +
          '<div class="hs-e">' + hi(a.excerpt || '', input.value.trim()) + '</div></span></div>';
      }).join('');
    }
    box.classList.add('on');
  }
  function go(i) { if (results[i]) location.href = '/help/' + encodeURIComponent(results[i].slug); }
  var timer = 0;
  input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(render, 120); });
  input.addEventListener('keydown', function (e) {
    if (!box.classList.contains('on')) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      sel = e.key === 'ArrowDown' ? Math.min(sel + 1, results.length - 1) : Math.max(sel - 1, 0);
      box.querySelectorAll('.hs-item').forEach(function (el, i) { el.classList.toggle('sel', i === sel); });
    } else if (e.key === 'Enter') { e.preventDefault(); go(sel >= 0 ? sel : 0); }
    else if (e.key === 'Escape') { box.classList.remove('on'); input.blur(); }
  });
  box.addEventListener('click', function (e) { var it = e.target.closest('.hs-item'); if (it) go(+it.dataset.i); });
  document.addEventListener('click', function (e) { if (!e.target.closest('.help-search')) box.classList.remove('on'); });
})();
</script>
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</body>
</html>
