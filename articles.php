<?php
/**
 * 文章列表页 — 学院 › 文章
 *
 * v8（2026-09-03）：重排。之前是「精选 + TOP8 + 按分类分楼层」，文章少时每层只有一张卡、同一篇出现三次；
 * ?cat= / ?tag= 链接到处都是却没人处理。现在：一条筛选栏（分类 tab + 标签 chips + 搜索）+ 最新一篇大卡 + 全部文章网格，
 * 服务端认 ?cat / ?tag / ?q（可分享、无 JS 也对），前端只做即时过滤与「加载更多」。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CoverRenderer.php';
require_once __DIR__ . '/lib/I18n.php';

$siteName = site_config_get('site_name', 'OpenFlow');
$allArticles = get_articles();
usort($allArticles, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$articles = array_values(array_filter($allArticles, fn($a) => ($a['status'] ?? '') === 'published'));
$total = count($articles);

// 分类：后台配置优先，退化为内置映射（旧 slug 合并到同名）
$catLabels = [
    'ai-create' => 'AI 创作', 'content' => 'AI 创作', 'agent' => 'Agent 生态', 'ai' => 'Agent 生态', 'ai-agent' => 'Agent 生态',
    'trend' => '行业趋势', 'insight' => '行业趋势', 'ai-code' => 'AI 编程', 'ai-marketing' => 'AI 营销', 'ai-ops' => 'AI 运营',
    'ai-sell' => 'AI 销售', 'ai-data' => '数据分析', 'ai-user' => '用户运营', 'ai-build' => 'AI 建站',
];
foreach (get_categories('article') ?: [] as $c) { if (!empty($c['key']) && !empty($c['name'])) $catLabels[$c['key']] = $c['name']; }
$catOf = function (array $a) use ($catLabels): array {
    $slug = explode('/', $a['category'] ?? '')[0] ?: 'trend';
    return [$slug, $catLabels[$slug] ?? $slug];
};
// 分类计数（按显示名合并）
$catCounts = []; $catKeyByName = [];
foreach ($articles as $a) { [$slug, $name] = $catOf($a); $catCounts[$name] = ($catCounts[$name] ?? 0) + 1; $catKeyByName[$name] = $catKeyByName[$name] ?? $slug; }
arsort($catCounts);
// 标签
$allTags = [];
foreach ($articles as $a) { foreach ($a['tags'] ?? [] as $t) { $t = trim((string)$t); if ($t !== '') $allTags[$t] = ($allTags[$t] ?? 0) + 1; } }
arsort($allTags);
$topTags = array_slice(array_keys($allTags), 0, 14);

// 服务端筛选（可分享的 URL）
$fCat = trim(req_str('cat', '')); $fTag = trim(req_str('tag', '')); $fQ = trim(req_str('q', ''));
$fCatName = $fCat !== '' ? ($catLabels[$fCat] ?? $fCat) : '';
$list = array_values(array_filter($articles, function ($a) use ($fCatName, $fTag, $fQ, $catOf) {
    if ($fCatName !== '' && $catOf($a)[1] !== $fCatName) return false;
    if ($fTag !== '' && !in_array($fTag, array_map('trim', $a['tags'] ?? []), true)) return false;
    if ($fQ !== '' && mb_stripos(($a['title'] ?? '') . ' ' . ($a['excerpt'] ?? '') . ' ' . implode(' ', $a['tags'] ?? []), $fQ) === false) return false;
    return true;
}));
$filtering = $fCat !== '' || $fTag !== '' || $fQ !== '';
$lead = $filtering ? null : ($list[0] ?? null);   // 无筛选时最新一篇做大卡
$rest = $filtering ? $list : array_slice($list, 1);
$pageTitle = $filtering ? ('文章 · ' . ($fCatName ?: ($fTag !== '' ? '#' . $fTag : '搜索 ' . $fQ))) : '文章';

function card_meta(array $a): string {
    $d = substr($a['created_at'] ?? '', 0, 10);
    $words = mb_strlen(strip_tags($a['content'] ?? ''));
    $min = max(1, (int)ceil($words / 450));
    return htmlspecialchars($d) . ' · ' . $min . ' 分钟';
}
function render_card(array $a, callable $catOf): string {
    [$slug, $cat] = $catOf($a);
    $tags = implode(' ', array_map(fn($t) => mb_strtolower(trim((string)$t)), $a['tags'] ?? []));
    return '<a href="/articles/' . htmlspecialchars($a['slug'] ?? '') . '" class="a-card" data-cat="' . htmlspecialchars($cat) . '" data-tags="' . htmlspecialchars($tags) . '" data-q="' . htmlspecialchars(mb_strtolower(($a['title'] ?? '') . ' ' . strip_tags($a['excerpt'] ?? ''))) . '">'
        . '<div class="cov">' . CoverRenderer::renderCardCover($a) . '</div>'
        . '<div class="bd"><span class="cat">' . htmlspecialchars($cat) . '</span>'
        . '<h3>' . htmlspecialchars(mb_substr($a['title'] ?? '', 0, 60)) . '</h3>'
        . ($a['excerpt'] ?? '' ? '<p>' . htmlspecialchars(mb_substr(strip_tags($a['excerpt']), 0, 84)) . '</p>' : '')
        . '<div class="meta">' . card_meta($a) . '</div></div></a>';
}
?>
<!doctype html>
<html lang="<?=htmlspecialchars(i18n_current())?>" dir="<?=i18n_is_rtl()?'rtl':'ltr'?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($pageTitle)?> · 学院 | <?=$siteName?></title>
<meta name="description" content="芭乐派学院 · 文章：增长实践、AI 工具评测、行业洞察，共 <?=$total?> 篇。">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260903a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260903a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260903a">
<style>
/* 文章列表独有：筛选栏、最新大卡、卡片摘要、加载更多。其余全部来自 modules.css。 */
.crumbs{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--faint);justify-content:center}
.crumbs a{color:var(--muted)}.crumbs a:hover{color:var(--accent)}
.crumbs svg{width:12px;height:12px}
.filter{display:flex;flex-direction:column;gap:14px;padding:16px 18px;border-radius:var(--r-md);background:var(--surface);border:1px solid var(--border);backdrop-filter:blur(16px) saturate(150%)}
.filter .row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.filter .tab-bar{border:0;padding:0;justify-content:flex-start;gap:4px;flex:1 1 320px;min-width:0;flex-wrap:wrap}
.filter .tab-p{padding:8px 14px;font-size:13.5px;white-space:nowrap;flex:0 0 auto}
.filter .tab-p b{font-family:var(--font-mono);font-weight:600;font-size:11px;color:var(--faint);margin-left:2px}
.filter .q{position:relative;flex:1 1 240px;min-width:0}
.filter .q .inp{min-height:40px;padding:8px 14px 8px 38px;font-size:14px;border-radius:999px}
.filter .q svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--faint)}
.filter .tags{margin:0;gap:6px}
.filter .tags a{font-size:12px;font-weight:600;color:var(--muted);background:var(--bg-soft);padding:5px 11px;border-radius:999px;transition:background .15s,color .15s}
.filter .tags a:hover{color:var(--fg);background:var(--hover-strong)}
.filter .tags a[aria-current="true"]{color:var(--accent);background:var(--accent-soft)}
.lead-card{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:0;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;color:inherit;transition:transform .3s var(--ease-spring),box-shadow .3s,border-color .3s}
.lead-card:hover{transform:translateY(-3px);border-color:var(--border-strong);box-shadow:var(--shadow)}
.lead-card .cov{aspect-ratio:16/10;background:linear-gradient(135deg,var(--bg-soft),var(--accent-soft));position:relative;overflow:hidden;display:grid;place-items:center}
.lead-card .cov img,.lead-card .cov .gcov{width:100%;height:100%;object-fit:cover}
.lead-card .bd{padding:clamp(22px,3vw,40px);display:flex;flex-direction:column;gap:12px;justify-content:center}
.lead-card .cat{font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--ok);text-transform:uppercase}
.lead-card h2{font-size:clamp(22px,2.4vw,30px);font-weight:700;letter-spacing:-.015em;line-height:1.3}
.lead-card p{font-size:15px;color:var(--muted);line-height:1.8}
.lead-card .meta{font-family:var(--font-mono);font-size:12px;color:var(--faint);margin-top:auto;padding-top:8px}
.a-card p{font-size:13px;color:var(--muted);line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.a-card.hide{display:none}
.more-row{display:flex;justify-content:center;margin-top:22px}
.res{font-size:13px;color:var(--faint);font-family:var(--font-mono)}
@media (max-width:900px){.lead-card{grid-template-columns:1fr}.lead-card .cov{aspect-ratio:16/9}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('articles'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section id="top" class="reveal in" data-od-anchor data-od-id="articles-hero">
    <div class="hero-center" style="padding-bottom:0;gap:16px">
      <nav class="crumbs" aria-label="位置"><a href="/academy">学院</a><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg><span>文章</span></nav>
      <h1><?=$filtering ? htmlspecialchars($fCatName ?: ($fTag !== '' ? '#' . $fTag : '搜索：' . $fQ)) : '文<i class="si">章</i>'?></h1>
      <p class="lead"><?=$filtering ? '共 ' . count($list) . ' 篇 · <a href="/articles" style="color:var(--accent)">看全部文章</a>' : '增长实践、AI 工具评测、行业洞察。每一篇都从一个真实问题出发，写到能照着做为止。'?></p>
      <?php if (!$filtering): ?><div class="trust"><span class="dot"></span><?=$total?> 篇 · <?=count($catCounts)?> 个分类 · <?=count($allTags)?> 个标签</div><?php endif; ?>
    </div>
  </section>

  <section id="filter" class="sec reveal" data-od-anchor data-od-id="articles-filter" style="padding-top:8px">
    <form class="filter" method="get" action="/articles" id="artFilter" data-no-guard>
      <div class="row">
        <div class="tab-bar" role="tablist" aria-label="分类">
          <a class="tab-p" href="/articles<?=$fTag !== '' ? '?tag=' . urlencode($fTag) : ''?>" data-cat="" aria-selected="<?=$fCat === '' ? 'true' : 'false'?>">全部 <b><?=$total?></b></a>
          <?php foreach ($catCounts as $name => $n): $key = $catKeyByName[$name]; ?>
          <a class="tab-p" href="/articles?cat=<?=urlencode($key)?>" data-cat="<?=htmlspecialchars($name)?>" aria-selected="<?=$fCatName === $name ? 'true' : 'false'?>"><?=htmlspecialchars($name)?> <b><?=$n?></b></a>
          <?php endforeach; ?>
        </div>
        <label class="q"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg><input class="inp" type="search" name="q" value="<?=htmlspecialchars($fQ)?>" placeholder="搜标题、摘要、标签…" aria-label="搜索文章" autocomplete="off"></label>
      </div>
      <?php if ($topTags): ?>
      <div class="row"><div class="tags" aria-label="标签">
        <?php foreach ($topTags as $t): ?><a href="/articles?tag=<?=urlencode($t)?>" data-tag="<?=htmlspecialchars(mb_strtolower($t))?>" aria-current="<?=$fTag === $t ? 'true' : 'false'?>"># <?=htmlspecialchars($t)?></a><?php endforeach; ?>
      </div></div>
      <?php endif; ?>
    </form>
  </section>

  <?php if ($lead): [$ls, $lc] = $catOf($lead); ?>
  <section id="latest" class="sec reveal" data-od-anchor data-od-id="articles-latest" style="padding-top:8px">
    <div class="sec-head row"><div><span class="kicker">最新</span><h2>刚写完的一篇</h2></div><span class="sub"><?=card_meta($lead)?></span></div>
    <a class="lead-card" href="/articles/<?=htmlspecialchars($lead['slug'] ?? '')?>" style="margin-top:14px">
      <div class="cov"><?=CoverRenderer::renderCardCover($lead)?></div>
      <div class="bd">
        <span class="cat"><?=htmlspecialchars($lc)?></span>
        <h2><?=htmlspecialchars($lead['title'] ?? '')?></h2>
        <?php if (!empty($lead['excerpt'])): ?><p><?=htmlspecialchars(mb_substr(strip_tags($lead['excerpt']), 0, 160))?></p><?php endif; ?>
        <span class="meta"><?=card_meta($lead)?><?php if (!empty($lead['author'])): ?> · <?=htmlspecialchars($lead['author'])?><?php endif; ?></span>
      </div>
    </a>
  </section>
  <?php endif; ?>

  <section id="all" class="sec reveal" data-od-anchor data-od-id="articles-all" style="padding-top:8px">
    <div class="sec-head row"><div><span class="kicker"><?=$lead ? '全部' : '结果'?></span><h2><?=$lead ? '按时间倒序' : ($list ? '匹配的文章' : '没有匹配的文章')?></h2></div><span class="res" id="artCount"><?=count($rest)?> 篇</span></div>
    <?php if (!$rest && !$lead): ?>
      <div class="empty">没有找到「<?=htmlspecialchars($fCatName ?: ($fTag ?: $fQ))?>」相关的文章。<a href="/articles" style="color:var(--accent)">看全部</a>，或者去<a href="/search?q=<?=urlencode($fQ ?: $fTag ?: $fCatName)?>" style="color:var(--accent)">全站搜索</a>。</div>
    <?php elseif ($rest): ?>
      <div class="a-grid" id="artGrid" style="margin-top:14px"><?php foreach ($rest as $a) echo render_card($a, $catOf); ?></div>
      <div class="more-row" id="moreRow" hidden><button type="button" class="btn ghost" id="moreBtn">再看 12 篇</button></div>
      <div class="empty" id="artEmpty" hidden>没有匹配的文章，换个词试试。</div>
    <?php endif; ?>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<script>
(function(){
  var grid=document.getElementById('artGrid'); if(!grid) return;
  var cards=Array.prototype.slice.call(grid.querySelectorAll('.a-card')), PAGE=12, shown=PAGE, q='';
  var more=document.getElementById('moreRow'), btn=document.getElementById('moreBtn'), cnt=document.getElementById('artCount'), emp=document.getElementById('artEmpty');
  var inp=document.querySelector('#artFilter input[name=q]');
  function apply(){
    var hit=cards.filter(function(c){return !q || c.dataset.q.indexOf(q)>-1 || c.dataset.tags.indexOf(q)>-1});
    cards.forEach(function(c){c.classList.add('hide')});
    hit.slice(0,shown).forEach(function(c){c.classList.remove('hide')});
    if(more) more.hidden = hit.length<=shown;
    if(cnt) cnt.textContent = (q?('匹配 '+hit.length):hit.length)+' 篇';
    if(emp) emp.hidden = hit.length>0;
  }
  if(btn) btn.addEventListener('click',function(){shown+=PAGE;apply()});
  if(inp){ var t; inp.addEventListener('input',function(){clearTimeout(t);t=setTimeout(function(){q=inp.value.trim().toLowerCase();shown=PAGE;apply()},120)}); }
  apply();
})();
</script>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
