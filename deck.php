<?php
/**
 * 幻灯片放映页 — /deck/{id}（E2 创作台产物 · F1 主题系统）
 * 主题包 + design.md 品牌契约 + 多版式（封面/观点/列表/金句/大数字/分栏/议程/图片/图表/结尾）
 * ←/→/空格翻页，F 全屏，P 打印导出 PDF。暗色舞台 + 设计 token。
 */
require_once __DIR__ . '/admin/config.php';

$id = preg_replace('/[^a-z0-9_]/i', '', (string)($_GET['id'] ?? ''));
$decks = json_read(DATA_DIR . '/slide-decks.json');
$deck = $decks[$id] ?? null;

if (!$deck) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>幻灯片不存在</title>'
       . '<style>body{font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0f1220;color:#eef}a{color:#8ab4ff}</style></head>'
       . '<body><div style="text-align:center"><h1>幻灯片不存在</h1><p><a href="/">返回首页</a></p></div></body></html>';
    exit;
}

$slides = array_values((array)$deck['slides']);
$themeId = (string)($deck['theme'] ?? 'stage');
$brandMd = (string)($deck['brand_md'] ?? '') ?: DeckThemes::globalBrand();
$cssVars = DeckThemes::cssVars($themeId, $brandMd);
$brandLogo = DeckThemes::brandLogo($brandMd);
$pageTitle = ($deck['title'] ?? '幻灯片') . ' · 幻灯片';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($pageTitle)?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/tokens.css">
<style>
*{margin:0;box-sizing:border-box}
body{background:var(--stage-bg);color:var(--stage-fg);font-family:var(--stage-body-font);overflow:hidden;height:100vh;transition:background .3s}
.deck{position:relative;width:100vw;height:100vh}
.slide{position:absolute;inset:0;display:none;flex-direction:column;justify-content:center;padding:8vh 10vw;opacity:0;transition:opacity .45s ease}
.slide.on{display:flex;opacity:1}
.kicker{font-family:var(--font-mono,monospace);font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:var(--stage-accent);margin-bottom:22px}
h1{font-family:var(--stage-heading-font);font-size:clamp(34px,5.2vw,72px);line-height:1.12;font-weight:var(--stage-heading-weight);letter-spacing:-.01em}
h2{font-family:var(--stage-heading-font);font-size:clamp(28px,4vw,54px);line-height:1.18;font-weight:var(--stage-heading-weight)}
.body{font-size:clamp(17px,1.7vw,24px);line-height:1.7;opacity:.86;margin-top:26px;max-width:56ch}
.points{margin-top:28px;list-style:none;padding:0;display:grid;gap:14px}
.points li{font-size:clamp(16px,1.6vw,22px);line-height:1.55;padding-left:30px;position:relative;opacity:.9}
.points li::before{content:"";position:absolute;left:0;top:.62em;width:14px;height:2px;background:var(--stage-accent)}
.quote{font-family:var(--stage-heading-font);font-size:clamp(24px,3.2vw,44px);line-height:1.4;font-weight:700;max-width:26ch;border-left:4px solid var(--stage-accent);padding-left:28px}
.sub{font-size:clamp(16px,1.6vw,22px);opacity:.66;margin-top:20px}
.end-cta{display:inline-block;margin-top:34px;padding:14px 34px;border-radius:999px;background:var(--stage-accent);color:var(--stage-bg);font-weight:700;font-size:clamp(15px,1.4vw,19px);text-decoration:none}
/* ── 大数字 ── */
.stat-num{font-family:var(--stage-heading-font);font-size:clamp(72px,14vw,200px);font-weight:var(--stage-heading-weight);line-height:1;color:var(--stage-accent);letter-spacing:-.03em}
.stat-label{font-size:clamp(17px,1.8vw,24px);opacity:.8;margin-top:18px;max-width:44ch}
/* ── 分栏 ── */
.split{display:grid;grid-template-columns:1fr 1fr;gap:5vw;align-items:center}
@media(max-width:760px){.split{grid-template-columns:1fr;gap:24px}}
/* ── 议程 ── */
.agenda{list-style:none;padding:0;margin-top:24px;display:grid;gap:4px;counter-reset:ag}
.agenda li{counter-increment:ag;font-family:var(--stage-heading-font);font-size:clamp(19px,2.2vw,30px);font-weight:700;padding:14px 0;border-bottom:1px solid var(--stage-soft);display:flex;gap:20px;align-items:baseline}
.agenda li::before{content:counter(ag,decimal-leading-zero);font-family:var(--font-mono,monospace);font-size:.6em;color:var(--stage-accent);flex:none}
/* ── 图片页 ── */
.slide[data-kind="image"]{padding:0}
.imgbg{position:absolute;inset:0;background-size:cover;background-position:center}
.imgbg::after{content:"";position:absolute;inset:0;background:linear-gradient(15deg,var(--stage-bg) 8%,transparent 65%)}
.imgcap{position:relative;z-index:2;margin-top:auto;padding:0 8vw 10vh}
/* ── 图表页（纯 CSS 条形图）── */
.chart{display:grid;gap:16px;margin-top:34px;max-width:720px}
.chart .row{display:grid;grid-template-columns:minmax(90px,180px) 1fr auto;gap:14px;align-items:center;font-size:clamp(14px,1.4vw,18px)}
.chart .track{height:26px;background:var(--stage-soft);border-radius:6px;overflow:hidden}
.chart .fill{height:100%;background:var(--stage-accent);border-radius:6px;min-width:2px;transition:width .8s cubic-bezier(.32,.72,0,1)}
.chart .val{font-family:var(--font-mono,monospace);color:var(--stage-accent);font-weight:700}
/* ── HUD ── */
.hud{position:fixed;bottom:22px;left:0;right:0;display:flex;justify-content:space-between;align-items:center;padding:0 28px;font-family:var(--font-mono,monospace);font-size:12px;opacity:.55;z-index:5}
.hud .bar{flex:1;height:2px;background:var(--stage-soft);margin:0 18px;border-radius:2px;overflow:hidden}
.hud .bar i{display:block;height:100%;background:var(--stage-accent);transition:width .3s}
.navbtn{position:fixed;top:50%;transform:translateY(-50%);z-index:6;width:46px;height:46px;border-radius:50%;border:1px solid var(--stage-soft);background:var(--stage-soft);color:var(--stage-fg);font-size:18px;cursor:pointer;backdrop-filter:blur(8px)}
.navbtn:hover{opacity:.8}
#prev{left:20px}#next{right:20px}
.brand{position:fixed;top:22px;left:28px;z-index:5;display:flex;align-items:center;gap:10px;font-family:var(--font-mono,monospace);font-size:12px;letter-spacing:.14em;opacity:.5}
.brand img{height:22px;width:auto;opacity:.9}
@media(max-width:640px){.navbtn{display:none}.slide{padding:10vh 7vw}}
/* ── 打印导出 PDF（P 键或浏览器打印，横版一页一屏）── */
@page{size:landscape;margin:0}
@media print{
  body{overflow:visible;height:auto}
  .slide{position:relative;display:flex!important;opacity:1!important;width:100vw;height:100vh;page-break-after:always;break-after:page}
  .hud,.navbtn,.brand .hint{display:none}
}
</style>
</head>
<body style="<?=$cssVars?>">
<div class="brand"><?php if ($brandLogo): ?><img src="<?=htmlspecialchars($brandLogo)?>" alt=""><?php endif; ?><span>OPENFLOW · DECK</span></div>
<div class="deck" id="deck">
<?php foreach ($slides as $i => $s): $kind = $s['kind'] ?? 'point'; ?>
  <section class="slide<?=$i===0?' on':''?>" data-kind="<?=htmlspecialchars($kind)?>">
    <?php if ($kind === 'cover'): ?>
      <div class="kicker"><?=htmlspecialchars($deck['topic'] ?? '')?></div>
      <h1><?=htmlspecialchars($s['heading'] ?? $deck['title'])?></h1>
      <?php if (!empty($deck['subtitle'])): ?><p class="sub"><?=htmlspecialchars($deck['subtitle'])?></p><?php endif; ?>
    <?php elseif ($kind === 'quote'): ?>
      <div class="kicker">金句</div>
      <div class="quote"><?=htmlspecialchars($s['quote'] ?? $s['heading'] ?? '')?></div>
    <?php elseif ($kind === 'list'): ?>
      <div class="kicker"><?=sprintf('%02d / %02d', $i+1, count($slides))?></div>
      <h2><?=htmlspecialchars($s['heading'] ?? '')?></h2>
      <ul class="points"><?php foreach ((array)($s['points'] ?? []) as $p): ?><li><?=htmlspecialchars((string)$p)?></li><?php endforeach; ?></ul>
    <?php elseif ($kind === 'stat'): ?>
      <div class="kicker"><?=htmlspecialchars($s['heading'] ?? '')?></div>
      <div class="stat-num"><?=htmlspecialchars($s['num'] ?? '')?></div>
      <p class="stat-label"><?=htmlspecialchars($s['body'] ?? '')?></p>
    <?php elseif ($kind === 'split'): ?>
      <div class="split">
        <div><div class="kicker"><?=sprintf('%02d / %02d', $i+1, count($slides))?></div><h2><?=htmlspecialchars($s['heading'] ?? '')?></h2></div>
        <div><?php if (!empty($s['body'])): ?><p class="body" style="margin-top:0"><?=htmlspecialchars($s['body'])?></p><?php endif; ?>
        <?php if (!empty($s['points'])): ?><ul class="points"><?php foreach ((array)$s['points'] as $p): ?><li><?=htmlspecialchars((string)$p)?></li><?php endforeach; ?></ul><?php endif; ?></div>
      </div>
    <?php elseif ($kind === 'agenda'): ?>
      <div class="kicker">议程</div>
      <h2><?=htmlspecialchars($s['heading'] ?? '今天讲这些')?></h2>
      <ol class="agenda"><?php foreach ((array)($s['points'] ?? []) as $p): ?><li><?=htmlspecialchars((string)$p)?></li><?php endforeach; ?></ol>
    <?php elseif ($kind === 'image'): ?>
      <div class="imgbg" style="background-image:url('<?=htmlspecialchars($s['image'] ?? '')?>')"></div>
      <div class="imgcap"><h2><?=htmlspecialchars($s['heading'] ?? '')?></h2>
      <?php if (!empty($s['body'])): ?><p class="body"><?=htmlspecialchars($s['body'])?></p><?php endif; ?></div>
    <?php elseif ($kind === 'chart'): ?>
      <div class="kicker"><?=sprintf('%02d / %02d', $i+1, count($slides))?></div>
      <h2><?=htmlspecialchars($s['heading'] ?? '')?></h2>
      <div class="chart">
        <?php
        $rows = [];
        foreach ((array)($s['points'] ?? []) as $p) { $kv = array_map('trim', explode('|', (string)$p)); if (($kv[0] ?? '') !== '' && is_numeric($kv[1] ?? null)) $rows[] = $kv; }
        $max = max(1, ...array_map(fn($r) => (float)$r[1], $rows ?: [['', 1]]));
        foreach ($rows as $r): ?>
        <div class="row"><span><?=htmlspecialchars($r[0])?></span><div class="track"><div class="fill" style="width:<?=round((float)$r[1] / $max * 100)?>%"></div></div><span class="val"><?=htmlspecialchars($r[1])?></span></div>
        <?php endforeach; ?>
      </div>
    <?php elseif ($kind === 'end'): ?>
      <div class="kicker">最后</div>
      <h2><?=htmlspecialchars($s['heading'] ?? '谢谢')?></h2>
      <?php if (!empty($s['body'])): ?><p class="body"><?=htmlspecialchars($s['body'])?></p><?php endif; ?>
    <?php else: ?>
      <div class="kicker"><?=sprintf('%02d / %02d', $i+1, count($slides))?></div>
      <h2><?=htmlspecialchars($s['heading'] ?? '')?></h2>
      <?php if (!empty($s['body'])): ?><p class="body"><?=htmlspecialchars($s['body'])?></p><?php endif; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
<button class="navbtn" id="prev" aria-label="上一页">←</button>
<button class="navbtn" id="next" aria-label="下一页">→</button>
<div class="hud"><span id="idx">1 / <?=count($slides)?></span><div class="bar"><i id="bar" style="width:<?=100/count($slides)?>%"></i></div><span>← → 翻页 · F 全屏 · P 打印PDF</span></div>
<script>
(function(){
  var slides=document.querySelectorAll('.slide'),n=slides.length,i=0;
  var idx=document.getElementById('idx'),bar=document.getElementById('bar');
  function go(k){i=Math.max(0,Math.min(n-1,k));slides.forEach(function(s,j){s.classList.toggle('on',j===i)});
    idx.textContent=(i+1)+' / '+n;bar.style.width=((i+1)/n*100)+'%'}
  document.getElementById('next').onclick=function(){go(i+1)};
  document.getElementById('prev').onclick=function(){go(i-1)};
  document.addEventListener('keydown',function(e){
    if(e.key==='ArrowRight'||e.key===' '||e.key==='PageDown'){e.preventDefault();go(i+1)}
    else if(e.key==='ArrowLeft'||e.key==='PageUp'){e.preventDefault();go(i-1)}
    else if(e.key==='Home'){go(0)}else if(e.key==='End'){go(n-1)}
    else if(e.key==='f'||e.key==='F'){document.fullscreenElement?document.exitFullscreen():document.documentElement.requestFullscreen()}
    else if(e.key==='p'||e.key==='P'){window.print()}
  });
  var x0=null;document.addEventListener('touchstart',function(e){x0=e.touches[0].clientX},{passive:true});
  document.addEventListener('touchend',function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;
    if(Math.abs(dx)>48)go(dx<0?i+1:i-1);x0=null},{passive:true});
})();
</script>
</body>
</html>
