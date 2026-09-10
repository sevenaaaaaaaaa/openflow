<?php
/**
 * 幻灯片放映页 — /deck/{id}（E2 创作台产物）
 * 全屏放映：←/→/空格翻页，Esc 退出全屏，F 全屏。暗色舞台 + 设计 token。
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
$pageTitle = $deck['title'] . ' · 幻灯片';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($pageTitle)?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/tokens.css">
<style>
:root{--stage-bg:oklch(0.14 0.02 260);--stage-fg:oklch(0.96 0.01 260);--stage-accent:var(--accent,#6ea8ff)}
*{margin:0;box-sizing:border-box}
body{background:var(--stage-bg);color:var(--stage-fg);font-family:var(--font-sans,system-ui);overflow:hidden;height:100vh}
.deck{position:relative;width:100vw;height:100vh}
.slide{position:absolute;inset:0;display:none;flex-direction:column;justify-content:center;padding:8vh 10vw;opacity:0;transition:opacity .45s ease}
.slide.on{display:flex;opacity:1}
.kicker{font-family:var(--font-mono,monospace);font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:var(--stage-accent);margin-bottom:22px}
h1{font-size:clamp(34px,5.2vw,72px);line-height:1.12;font-weight:800;letter-spacing:-.01em}
h2{font-size:clamp(28px,4vw,54px);line-height:1.18;font-weight:800}
.body{font-size:clamp(17px,1.7vw,24px);line-height:1.7;opacity:.86;margin-top:26px;max-width:56ch}
.points{margin-top:28px;list-style:none;padding:0;display:grid;gap:14px}
.points li{font-size:clamp(16px,1.6vw,22px);line-height:1.55;padding-left:30px;position:relative;opacity:.9}
.points li::before{content:"";position:absolute;left:0;top:.62em;width:14px;height:2px;background:var(--stage-accent)}
.quote{font-size:clamp(24px,3.2vw,44px);line-height:1.4;font-weight:700;max-width:26ch;border-left:4px solid var(--stage-accent);padding-left:28px}
.sub{font-size:clamp(16px,1.6vw,22px);opacity:.66;margin-top:20px}
.end-cta{display:inline-block;margin-top:34px;padding:14px 34px;border-radius:999px;background:var(--stage-accent);color:oklch(0.16 0.02 260);font-weight:700;font-size:clamp(15px,1.4vw,19px);text-decoration:none}
.hud{position:fixed;bottom:22px;left:0;right:0;display:flex;justify-content:space-between;align-items:center;padding:0 28px;font-family:var(--font-mono,monospace);font-size:12px;opacity:.55;z-index:5}
.hud .bar{flex:1;height:2px;background:rgba(255,255,255,.14);margin:0 18px;border-radius:2px;overflow:hidden}
.hud .bar i{display:block;height:100%;background:var(--stage-accent);transition:width .3s}
.navbtn{position:fixed;top:50%;transform:translateY(-50%);z-index:6;width:46px;height:46px;border-radius:50%;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.06);color:#fff;font-size:18px;cursor:pointer;backdrop-filter:blur(8px)}
.navbtn:hover{background:rgba(255,255,255,.14)}
#prev{left:20px}#next{right:20px}
.brand{position:fixed;top:22px;left:28px;font-family:var(--font-mono,monospace);font-size:12px;letter-spacing:.14em;opacity:.5;z-index:5}
@media(max-width:640px){.navbtn{display:none}.slide{padding:10vh 7vw}}
</style>
</head>
<body>
<div class="brand">OPENFLOW · DECK</div>
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
<div class="hud"><span id="idx">1 / <?=count($slides)?></span><div class="bar"><i id="bar" style="width:<?=100/count($slides)?>%"></i></div><span>← → 翻页 · F 全屏</span></div>
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
  });
  var x0=null;document.addEventListener('touchstart',function(e){x0=e.touches[0].clientX},{passive:true});
  document.addEventListener('touchend',function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;
    if(Math.abs(dx)>48)go(dx<0?i+1:i-1);x0=null},{passive:true});
})();
</script>
</body>
</html>
