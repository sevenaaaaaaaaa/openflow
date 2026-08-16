<?php
/**
 * 产品 | OpenFlow（动态版）
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
$siteName = site_config_get('site_name', 'OpenFlow');
header('Cache-Control: no-cache, max-age=0');
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (function_exists('seo_head')): seo_head(['title' => '产品 | OpenFlow', 'canonical' => site_config_get('site_url') . '/product']); endif; ?>
<title>产品 · 芭乐派 · OpenFlow 增长操作系统</title>
<meta name="description" content="Open Flow 产品介绍：连接、编排、执行三步原理，可视化画布、AI 步骤、开放连接器与可运行演示。">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<style>
:root{
  --bg:oklch(96.5% .016 85); --bg-soft:oklch(94% .02 85);
  --surface:oklch(100% 0 0 / .62); --surface-strong:oklch(100% 0 0 / .88);
   --fg:oklch(22% .02 70); --muted:oklch(46% .016 70); --faint:oklch(51% .014 75);
  --border:oklch(86% .014 80); --border-strong:oklch(76% .02 80);
  --hover:oklch(22% .02 70 / .055); --hover-strong:oklch(22% .02 70 / .11);
  --accent:oklch(52% .17 258); --accent-strong:oklch(46% .17 258); --accent-soft:oklch(52% .17 258 / .12); --on-accent:oklch(100% 0 0);
  --ok:oklch(58% .17 152); --ok-soft:oklch(58% .17 152 / .12);
  --warn:oklch(66% .15 75); --warn-soft:oklch(66% .15 75 / .14);
  --danger:oklch(55% .2 25); --danger-soft:oklch(55% .2 25 / .12);
  --glass:oklch(100% 0 0 / .5); --glass-bright:oklch(100% 0 0 / .66); --glass-border:oklch(100% 0 0 / .68);
  --shadow:0 24px 60px -24px oklch(30% .04 80 / .28); --shadow-sm:0 10px 28px -14px oklch(30% .04 80 / .22);
  --blob-a:oklch(72% .12 262 / .30); --blob-b:oklch(70% .13 305 / .24); --blob-c:oklch(74% .11 200 / .22);
  --ease-spring:cubic-bezier(.32,.72,0,1); --ease-out:cubic-bezier(.22,1,.36,1);
  --font-display:"Space Grotesk","PingFang SC","HarmonyOS Sans SC","MiSans","Segoe UI",system-ui,sans-serif;
  --font-body:"Space Grotesk",-apple-system,BlinkMacSystemFont,"PingFang SC","HarmonyOS Sans SC","MiSans","Segoe UI",system-ui,sans-serif;
  --font-mono:ui-monospace,'SF Mono','JetBrains Mono',Menlo,monospace;
  --r-lg:26px; --r-md:18px; --r-sm:12px;
  --container:1120px;

  --chrome-h:56px; --sb-w:248px; --tab-w:150px;
  color-scheme:light;
}
[data-theme="dark"]{
  --bg:oklch(19% .014 70); --bg-soft:oklch(22.5% .014 72);
  --surface:oklch(27% .016 75 / .55); --surface-strong:oklch(30% .016 75 / .82);
   --fg:oklch(93% .008 85); --muted:oklch(70% .014 80); --faint:oklch(64% .014 80);
  --border:oklch(100% 0 0 / .1); --border-strong:oklch(100% 0 0 / .2);
  --hover:oklch(93% .008 85 / .07); --hover-strong:oklch(93% .008 85 / .13);
  --accent:oklch(74% .13 258); --accent-strong:oklch(80% .12 258); --accent-soft:oklch(74% .13 258 / .15); --on-accent:oklch(16% .03 260);
  --ok:oklch(74% .15 152); --ok-soft:oklch(74% .15 152 / .15);
  --warn:oklch(76% .13 75); --warn-soft:oklch(76% .13 75 / .16);
  --danger:oklch(72% .16 25); --danger-soft:oklch(72% .16 25 / .14);
  --glass:oklch(30% .014 75 / .5); --glass-bright:oklch(34% .014 75 / .62); --glass-border:oklch(100% 0 0 / .15);
  --shadow:0 24px 60px -24px oklch(0% 0 0 / .55); --shadow-sm:0 10px 28px -14px oklch(0% 0 0 / .5);
  --blob-a:oklch(62% .13 262 / .18); --blob-b:oklch(58% .14 305 / .15); --blob-c:oklch(60% .12 200 / .13);
  color-scheme:dark;
}
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0; font-family:var(--font-body); color:var(--fg); background:var(--bg); overflow-x:clip; -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility}
::selection{background:var(--accent-soft)}
:focus-visible{outline:2px solid var(--accent); outline-offset:2px; border-radius:8px}
button{font:inherit; color:inherit; background:none; border:0; cursor:pointer; -webkit-tap-highlight-color:transparent}
a{color:inherit}
input,textarea,select{font:inherit; color:inherit}
h1,h2,h3,h4,p{margin:0}
svg{display:block}
em{font-style:normal}
button:disabled{opacity:.45; cursor:default}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-thumb{background:var(--border-strong); border-radius:99px; border:3px solid transparent; background-clip:padding-box}
::-webkit-scrollbar-track{background:transparent}
.si{font-family:var(--font-display); font-style:italic; font-weight:700; letter-spacing:-.01em}
.ic{width:16px;height:16px; flex:0 0 16px}
.ic svg{width:100%;height:100%}
.kicker{font-family:var(--font-mono); font-size:11px; font-weight:700; letter-spacing:.18em; color:var(--accent); text-transform:uppercase}
.note{font-family:var(--font-mono); font-size:11px; color:var(--faint); letter-spacing:.02em}
.sec-head{display:flex; flex-direction:column; gap:10px; margin-bottom:34px}
.pg .sec-head{margin-top:46px}
.pg .band{margin-top:30px}
.sec-head h2{font-size:clamp(26px,3vw,36px); font-weight:800; letter-spacing:-.02em}
.sec-head p{color:var(--muted); font-size:15px; line-height:1.7; max-width:640px}

/* ── ambient ── */
.ambient{position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden}
.blob{position:absolute; border-radius:50%; filter:blur(70px); will-change:transform}
.blob-a{width:52vw;height:52vw; left:-9vw; top:-13vh; background:radial-gradient(circle,var(--blob-a),transparent 65%); animation:driftA 38s ease-in-out infinite alternate}
.blob-b{width:44vw;height:44vw; right:-7vw; top:16vh; background:radial-gradient(circle,var(--blob-b),transparent 65%); animation:driftB 44s ease-in-out infinite alternate}
.blob-c{width:40vw;height:40vw; left:26vw; bottom:-20vh; background:radial-gradient(circle,var(--blob-c),transparent 65%); animation:driftC 50s ease-in-out infinite alternate}
@keyframes driftA{to{transform:translate3d(6vw,4vh,0) scale(1.08)}}
@keyframes driftB{to{transform:translate3d(-5vw,6vh,0) scale(1.12)}}
@keyframes driftC{to{transform:translate3d(4vw,-5vh,0) scale(.94)}}

/* ── chrome / liquid glass ── */
#chrome{position:fixed; inset:0 0 auto 0; z-index:60; padding:8px 14px; transition:padding .5s var(--ease-spring)}
.bar{position:relative; height:var(--chrome-h); display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:10px; padding:0 10px; border-radius:18px;
  background:var(--glass); -webkit-backdrop-filter:blur(22px) saturate(170%); backdrop-filter:blur(22px) saturate(170%);
  border:1px solid var(--border); transition:border-radius .5s var(--ease-spring), box-shadow .5s var(--ease-spring), background .4s, border-color .4s}
.bar::after{content:''; position:absolute; inset:0 0 auto 0; height:42%; border-radius:inherit; background:linear-gradient(180deg,oklch(100% 0 0/.42),transparent); opacity:.65; pointer-events:none}
#chrome.scrolled{padding:10px 14px}
#chrome.scrolled .bar{border-radius:999px; border-color:var(--glass-border); background:var(--glass-bright); box-shadow:var(--shadow-sm), inset 0 1px 0 oklch(100% 0 0/.35)}
.lights{display:flex; gap:8px; padding:0 4px; flex:0 0 auto; justify-self:start}
.light{width:12px;height:12px;border-radius:50%; box-shadow:inset 0 0 2px oklch(0% 0 0/.25)}
.light-r{background:oklch(64% .23 25)} .light-y{background:oklch(82% .17 85)} .light-g{background:oklch(70% .2 150)}
.tabs{min-width:0; max-width:100%; justify-self:center; display:flex; gap:6px; overflow-x:auto; scrollbar-width:none; padding:3px 2px}
.tabs::-webkit-scrollbar{display:none}
.tab{flex:1 1 0; min-width:104px; max-width:168px; height:44px; display:flex; align-items:center; gap:8px; padding:0 12px; border-radius:14px; text-decoration:none;
  color:var(--muted); border:1px solid transparent; transition:background .22s, color .22s, border-color .22s; white-space:nowrap; cursor:pointer}
.tab:hover{background:var(--hover); color:var(--fg)}
.tab.active{background:var(--surface-strong); color:var(--fg); border-color:var(--border); box-shadow:var(--shadow-sm)}
.tab .ic{width:15px;height:15px; flex-basis:15px; color:var(--faint)}
.tab.active .ic{color:var(--accent)}
.t-label{font-size:13px; font-weight:600; overflow:hidden; text-overflow:ellipsis}
.tab{position:relative}
.mega{position:fixed; top:72px; left:50%; transform:translateX(-50%); width:min(720px,calc(100vw - 32px)); background:var(--surface-strong);
  -webkit-backdrop-filter:blur(40px) saturate(200%); backdrop-filter:blur(40px) saturate(200%); border:1px solid var(--border); border-radius:20px;
  box-shadow:var(--shadow); padding:20px; opacity:0; pointer-events:none; transition:opacity .2s,transform .25s var(--ease-spring); z-index:80}
.tab:hover .mega,.tab.mega-open .mega{opacity:1; pointer-events:auto; transform:translateX(-50%) translateY(0)}
.mega-top{display:flex; align-items:baseline; gap:12px; padding-bottom:14px; border-bottom:1px solid var(--border); margin-bottom:14px}
.mega-top h4{font-size:15px; font-weight:800}
.mega-top p{font-size:12px; color:var(--faint)}
.mega-cols{display:grid; grid-template-columns:1fr 1fr; gap:18px}
.mega-col-head{font-family:var(--font-mono); font-size:10.5px; font-weight:700; letter-spacing:.12em; color:var(--faint); text-transform:uppercase; margin-bottom:8px}
.mega-item{display:flex; flex-direction:column; gap:2px; padding:8px 10px; border-radius:10px; text-decoration:none; color:var(--fg); transition:background .14s}
.mega-item:hover{background:var(--hover)}
.mega-item b{font-size:13px; font-weight:600}
.mega-item span{font-size:11.5px; color:var(--faint)}
.mega-foot{display:flex; gap:8px; margin-top:14px; padding-top:12px; border-top:1px solid var(--border)}
.mega-foot a{padding:6px 14px; border-radius:999px; font-size:12px; font-weight:600; text-decoration:none; background:var(--hover); color:var(--fg); transition:.15s}
.mega-foot a:hover{background:var(--accent); color:var(--on-accent)}
.controls{flex:0 0 auto; display:flex; align-items:center; gap:6px; position:relative; justify-self:end}
.controls{flex:0 0 auto; display:flex; align-items:center; gap:6px; position:relative}
.cbtn{width:40px;height:44px; border-radius:12px; display:grid; place-items:center; color:var(--muted); transition:background .22s, color .22s}
.cbtn:hover{background:var(--hover); color:var(--fg)}
.cbtn svg{width:18px;height:18px}
.kbd-chip{display:flex; align-items:center; gap:8px; height:44px; padding:0 12px; border-radius:12px; color:var(--muted); background:var(--hover); border:1px solid var(--border); font-size:13px; font-weight:600; transition:background .22s, color .22s}
.kbd-chip:hover{color:var(--fg); background:var(--hover-strong)}
.kbd-chip .kbd{font-family:var(--font-mono); font-size:11px; padding:2px 6px; border-radius:6px; background:var(--surface-strong); border:1px solid var(--border)}
.avatar{width:36px;height:36px; border-radius:50%; background:linear-gradient(135deg,var(--accent),oklch(60% .18 300)); display:grid; place-items:center; color:var(--on-accent); font-size:13px; font-weight:800; flex:0 0 auto; transition:transform .25s var(--ease-spring)}
.avatar:hover{transform:scale(1.06)}
.avatar.anon{background:var(--hover); color:var(--muted); border:1px solid var(--border)}
#btn-drawer{display:none}

/* ── dropdown ── */
.drop{position:absolute; top:calc(100% + 10px); right:0; width:248px; background:var(--surface-strong); -webkit-backdrop-filter:blur(30px) saturate(170%); backdrop-filter:blur(30px) saturate(170%);
  border:1px solid var(--border); border-radius:18px; box-shadow:var(--shadow); padding:8px; opacity:0; pointer-events:none; transform:translateY(-6px) scale(.98); transition:opacity .22s, transform .3s var(--ease-spring); z-index:75}
.drop.open{opacity:1; pointer-events:auto; transform:none}
.drop-head{display:flex; align-items:center; gap:12px; padding:10px 10px 12px; border-bottom:1px solid var(--border); margin-bottom:6px}
.drop-av{width:38px;height:38px; border-radius:50%; background:linear-gradient(135deg,var(--accent),oklch(60% .18 300)); color:var(--on-accent); display:grid; place-items:center; font-weight:800; font-size:14px; flex:0 0 auto}
.drop-name{font-size:14px; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
.drop-mail{font-size:12px; color:var(--faint); font-family:var(--font-mono); overflow:hidden; text-overflow:ellipsis}
.drop-item{display:flex; align-items:center; gap:10px; width:100%; height:44px; padding:0 12px; border-radius:12px; color:var(--fg); font-size:13.5px; font-weight:600; text-align:left; text-decoration:none; transition:background .18s}
.drop-item .ic{color:var(--muted)}
.drop-item:hover{background:var(--hover)}
.drop-item.danger{color:var(--danger)}
.drop-item.danger .ic{color:var(--danger)}

/* ── sidebar ── */
#sidebar{position:fixed; top:76px; left:14px; bottom:14px; width:var(--sb-w); z-index:50; display:flex; flex-direction:column; gap:2px;
  padding:12px 10px; border-radius:var(--r-lg); background:var(--glass); -webkit-backdrop-filter:blur(24px) saturate(170%); backdrop-filter:blur(24px) saturate(170%);
  border:1px solid var(--border); overflow:hidden; transition:width .45s var(--ease-spring), transform .45s var(--ease-spring), opacity .3s}
.ws{display:flex; align-items:center; gap:10px; height:44px; padding:0 10px; border-radius:12px; color:var(--fg); font-size:13.5px; font-weight:700; cursor:pointer; transition:background .2s; position:relative; text-decoration:none}
.ws:hover{background:var(--hover)}
.ws .ic{color:var(--accent)}
.ws .chev{margin-left:auto; color:var(--faint); width:14px;height:14px; transition:transform .3s var(--ease-spring)}
.sec{padding-top:14px}
.sec-title{display:flex; align-items:center; height:22px; padding:0 10px; font-family:var(--font-mono); font-size:10.5px; font-weight:700; letter-spacing:.1em; color:var(--faint); white-space:nowrap; overflow:hidden}
.s-item{position:relative; display:flex; align-items:center; gap:10px; width:100%; height:44px; padding:0 10px; border-radius:12px; color:var(--muted); font-size:13.5px; font-weight:500; text-decoration:none;
  white-space:nowrap; overflow:hidden; text-align:left; transition:background .18s, color .18s; cursor:pointer}
.s-item:hover{background:var(--hover); color:var(--fg)}
.s-item.active{background:var(--accent-soft); color:var(--accent)}
.s-item .ic{color:var(--faint)}
.s-item.active .ic{color:var(--accent)}
.s-count{margin-left:auto; font-family:var(--font-mono); font-size:11px; color:var(--faint)}
.s-label{overflow:hidden; text-overflow:ellipsis}
.pin-dot{width:8px;height:8px; border-radius:50%; background:var(--faint); flex:0 0 8px}
.pin-item.active .pin-dot{background:var(--accent); box-shadow:0 0 0 3px var(--accent-soft)}
.sb-foot{margin-top:auto; display:flex; justify-content:flex-end; gap:6px; padding-top:10px}
#sb-toggle{width:38px;height:38px; border-radius:12px; display:grid; place-items:center; color:var(--muted); transition:background .2s, color .2s}
#sb-toggle:hover{background:var(--hover); color:var(--fg)}
#sb-toggle svg{width:16px;height:16px; transition:transform .35s var(--ease-spring)}
body[data-sb="rail"] #sb-toggle svg{transform:rotate(180deg)}
body[data-sb="rail"]{--sb-w:76px}
body[data-sb="rail"] .s-label, body[data-sb="rail"] .s-count, body[data-sb="rail"] .sec-title span, body[data-sb="rail"] .ws-name, body[data-sb="rail"] .ws .chev{display:none}
body[data-sb="rail"] .s-item, body[data-sb="rail"] .ws{justify-content:center; padding:0}
body[data-sb="closed"]{--sb-w:0px}
body[data-sb="closed"] #sidebar{transform:translateX(calc(-100% - 30px)); opacity:0; pointer-events:none}
body[data-sb="closed"] #btn-drawer{display:grid}
body[data-sb="drawer"] #sidebar{transform:translateX(0); left:10px; width:min(300px,calc(100vw - 40px)); opacity:1; pointer-events:auto}
body[data-sb="drawer"] .s-label, body[data-sb="drawer"] .s-count, body[data-sb="drawer"] .sec-title span{display:inline}
body[data-sb="drawer"] .s-item, body[data-sb="drawer"] .ws{justify-content:flex-start; padding:0 10px}
.scrim{position:fixed; inset:0; z-index:45; background:oklch(0% 0 0/.35); opacity:0; pointer-events:none; transition:opacity .3s}
body[data-sb="drawer"] .scrim{opacity:1; pointer-events:auto}
#sb-prev{position:fixed; z-index:55; width:232px; padding:14px; border-radius:16px; background:var(--surface-strong); -webkit-backdrop-filter:blur(30px) saturate(170%); backdrop-filter:blur(30px) saturate(170%);
  border:1px solid var(--border); box-shadow:var(--shadow); opacity:0; pointer-events:none; transform:translateY(4px) scale(.98); transition:opacity .18s, transform .25s var(--ease-spring)}
#sb-prev.open{opacity:1; transform:none}
#sb-prev .p-k{font-family:var(--font-mono); font-size:10px; letter-spacing:.14em; color:var(--accent); margin-bottom:6px}
#sb-prev .p-t{font-size:14px; font-weight:700; margin-bottom:4px}
#sb-prev .p-d{font-size:12px; color:var(--muted); line-height:1.6}

/* ── layout ── */
main{margin-left:calc(var(--sb-w) + 26px); margin-right:14px; padding-top:96px; padding-bottom:64px; position:relative; z-index:10; min-width:0; transition:margin-left .45s var(--ease-spring)}
.page{display:block; max-width:1120px; margin:0 auto; animation:pageIn .5s var(--ease-spring)}
@keyframes pageIn{from{opacity:0; transform:translateY(14px) scale(.992)} to{opacity:1; transform:none}}
.pg{display:flex; flex-direction:column; gap:18px; padding:clamp(24px,5vw,56px) 0 clamp(40px,6vw,72px)}
.pg-h{display:flex; flex-direction:column; gap:14px; max-width:760px}
.pg-h h1{font-size:clamp(32px,4.6vw,54px); font-weight:800; letter-spacing:-.03em; line-height:1.12}
.pg-h .lead{color:var(--muted); font-size:clamp(15px,1.6vw,17px); line-height:1.75; max-width:640px}
.pg-h .cta-row{display:flex; gap:10px; flex-wrap:wrap; margin-top:6px}
.btn{height:46px; padding:0 20px; border-radius:14px; font-size:14.5px; font-weight:700; display:inline-flex; align-items:center; justify-content:center; gap:8px; text-decoration:none; transition:transform .22s var(--ease-spring), background .2s, border-color .2s, box-shadow .2s}
.btn:active{transform:scale(.97)}
.btn.primary{background:var(--accent); color:var(--on-accent); box-shadow:0 10px 26px -12px var(--accent)}
.btn.primary:hover{background:var(--accent-strong); transform:translateY(-1px)}
.btn.ghost{background:var(--surface); color:var(--fg); border:1px solid var(--border)}
.btn.ghost:hover{background:var(--hover); border-color:var(--border-strong)}
.btn.sm{height:38px; padding:0 14px; border-radius:11px; font-size:13px}
.card{background:var(--surface); border:1px solid var(--border); border-radius:var(--r-md); padding:24px; -webkit-backdrop-filter:blur(16px) saturate(150%); backdrop-filter:blur(16px) saturate(150%); transition:transform .25s var(--ease-spring), box-shadow .25s, border-color .25s}
.card.hov:hover{transform:translateY(-3px); box-shadow:var(--shadow-sm); border-color:var(--border-strong)}
.grid{display:grid; gap:16px}
.g2{grid-template-columns:repeat(2,1fr)} .g3{grid-template-columns:repeat(3,1fr)} .g4{grid-template-columns:repeat(4,1fr)} .g6{grid-template-columns:repeat(3,1fr)}
.pill{display:inline-flex; align-items:center; gap:5px; height:24px; padding:0 9px; border-radius:99px; font-size:11px; font-weight:700; font-family:var(--font-mono)}
.pill.ok{background:var(--ok-soft); color:var(--ok)}
.pill.warn{background:var(--warn-soft); color:var(--warn)}
.pill.danger{background:var(--danger-soft); color:var(--danger)}
.pill.neu{background:var(--hover); color:var(--muted)}
.band{display:flex; flex-direction:column; gap:16px; padding:clamp(32px,5vw,56px); border-radius:var(--r-lg); background:linear-gradient(135deg,var(--accent),oklch(58% .16 295)); color:var(--on-accent); position:relative; overflow:hidden}
.band::before{content:''; position:absolute; inset:0 0 auto 0; height:46%; background:linear-gradient(180deg,oklch(100% 0 0/.16),transparent); pointer-events:none}
.band h2{font-size:clamp(24px,3vw,34px); font-weight:800; letter-spacing:-.02em; position:relative}
.band p{position:relative; opacity:.92; line-height:1.7; max-width:560px; font-size:15px}
.band .btn.primary{background:var(--on-accent); color:var(--accent)}
.band .btn.ghost{background:oklch(100% 0 0/.14); border-color:oklch(100% 0 0/.3); color:var(--on-accent)}
.band .btn.ghost:hover{background:oklch(100% 0 0/.24)}
.divider{height:1px; background:var(--border); border:0; margin:0}

/* product */
.pain-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:16px}
.pain{display:flex; flex-direction:column; gap:10px; padding:26px 24px}
.pain .pi{width:42px;height:42px; border-radius:13px; background:var(--danger-soft); color:var(--danger); display:grid; place-items:center}
.pain .pi svg{width:20px;height:20px}
.pain h3{font-size:16.5px; font-weight:800}
.pain p{font-size:13.5px; color:var(--muted); line-height:1.7}
.how{display:grid; grid-template-columns:repeat(3,1fr); gap:16px}
.how .card{text-align:center; padding:30px 24px}
.how .hn{width:52px;height:52px; margin:0 auto 14px; border-radius:16px; background:var(--accent-soft); color:var(--accent); display:grid; place-items:center; font-family:var(--font-mono); font-weight:800; font-size:17px}
.how h3{font-size:16.5px; font-weight:800; margin-bottom:8px}
.how p{font-size:13.5px; color:var(--muted); line-height:1.7}
.dd{display:grid; grid-template-columns:1fr 1fr; gap:clamp(20px,4vw,48px); align-items:center; padding:clamp(20px,3.5vw,44px) 0}
.dd.rev .dd-vis{order:2}
.dd-copy{display:flex; flex-direction:column; gap:12px}
.dd-copy h3{font-size:clamp(20px,2.4vw,26px); font-weight:800; letter-spacing:-.015em}
.dd-copy p{color:var(--muted); font-size:14px; line-height:1.75}
.dd-copy ul{list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px}
.dd-copy li{display:flex; gap:9px; font-size:13.5px; color:var(--fg); line-height:1.6}
.dd-copy li::before{content:''; flex:0 0 6px; width:6px; height:6px; border-radius:50%; background:var(--accent); margin-top:7px}
.dd-vis{min-width:0}
.dd-frame{background:var(--glass); border:1px solid var(--border); border-radius:var(--r-md); box-shadow:var(--shadow-sm); padding:14px; -webkit-backdrop-filter:blur(16px) saturate(150%); backdrop-filter:blur(16px) saturate(150%)}
.mock-canvas{position:relative; border-radius:14px; background:var(--bg-soft); border:1px solid var(--border); height:150px; overflow:hidden}
.mnode{position:absolute; background:var(--surface-strong); border:1px solid var(--border-strong); border-radius:11px; padding:8px 12px; font-size:11.5px; font-weight:700; box-shadow:var(--shadow-sm)}
.mnode .ic{display:none}
.mchat{display:flex; flex-direction:column; gap:10px}
.mchat .bub{max-width:88%; border-radius:14px; padding:11px 14px; font-size:13px; line-height:1.6}
.mchat .bub.u{align-self:flex-start; background:var(--surface-strong); border:1px solid var(--border)}
.mchat .bub.a{align-self:flex-end; background:var(--accent); color:var(--on-accent)}
.mchat .gen{display:flex; gap:8px; flex-wrap:wrap; margin-top:6px}
.mchat .gen span{font-family:var(--font-mono); font-size:11px; padding:6px 10px; border-radius:9px; background:var(--ok-soft); color:var(--ok); font-weight:700}
.conn-chips{display:flex; flex-wrap:wrap; gap:8px}
.conn-chips .cc{display:inline-flex; align-items:center; gap:8px; height:40px; padding:0 14px; border-radius:12px; background:var(--surface); border:1px solid var(--border); font-size:13px; font-weight:600}
.conn-chips .cc .cd{width:8px;height:8px;border-radius:50%; background:var(--accent); box-shadow:0 0 0 3px var(--accent-soft)}
.run-list{display:flex; flex-direction:column; gap:8px}
.run-row{display:flex; align-items:center; gap:10px; padding:11px 13px; border-radius:12px; background:var(--surface); border:1px solid var(--border); font-size:12.5px}
.run-row .rn{font-family:var(--font-mono); color:var(--faint); flex:0 0 44px}
.run-row .rt{font-weight:600; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
.run-row .rs{margin-left:auto}
/* demo */
.demo-wrap{display:grid; grid-template-columns:1.2fr .8fr; gap:16px; align-items:stretch; scroll-margin-top:120px}
.demo-fig{display:flex; flex-direction:column; gap:12px}
.demo-svg{width:100%; height:auto}
.demo-svg .nd rect{fill:var(--surface); stroke:var(--border-strong); stroke-width:1.4; transition:fill .35s,stroke .35s}
.demo-svg .nd .nt{fill:var(--fg); font-size:13px; font-weight:700}
.demo-svg .nd .nd2{fill:var(--faint); font-size:10.5px; font-family:var(--font-mono)}
.demo-svg .ln{stroke:var(--border-strong); stroke-width:1.6}
.demo-svg .fd{fill:var(--accent); opacity:0; transition:opacity .3s}
.demo-svg.r1 .fd1,.demo-svg.r2 .fd1,.demo-svg.r3 .fd1,.demo-svg.r4 .fd1{opacity:1}
.demo-svg.r2 .fd2,.demo-svg.r3 .fd2,.demo-svg.r4 .fd2{opacity:1}
.demo-svg.r3 .fd3,.demo-svg.r4 .fd3{opacity:1}
.demo-svg.r4 .fd4{opacity:1}
.demo-svg .nd0 rect{fill:var(--accent); stroke:var(--accent);}
.demo-svg .nd0 .nt,.demo-svg .nd0 .nd2{fill:var(--on-accent)}
.demo-log{background:oklch(0% 0 0 / .72); border-radius:var(--r-md); padding:16px; font-family:var(--font-mono); font-size:12px; line-height:1.9; color:oklch(85% .01 140); min-height:236px; overflow:hidden; position:relative}
.demo-log .ln2{white-space:pre-wrap; word-break:break-all}
.demo-log .t-ok{color:oklch(80% .15 152)}
.demo-log .t-warn{color:oklch(82% .13 75)}
.demo-log .t-dim{color:oklch(60% .01 140)}
.demo-log::before{content:'执行日志 · 演示环境'; position:absolute; top:10px; right:14px; font-size:9.5px; letter-spacing:.14em; color:oklch(55% .01 140)}
.demo-ctrl{display:flex; align-items:center; gap:12px; flex-wrap:wrap}
/* stats */
.stats{display:grid; grid-template-columns:repeat(4,1fr); gap:14px}
.stat{text-align:center; padding:26px 18px}
.stat .sv{font-size:clamp(26px,3vw,38px); font-weight:800; letter-spacing:-.03em; background:linear-gradient(120deg,var(--accent),oklch(58% .16 295)); -webkit-background-clip:text; background-clip:text; color:transparent}
.stat .sl{font-size:12.5px; color:var(--muted); margin-top:6px; line-height:1.6}
/* faq */
.faq{display:flex; flex-direction:column; gap:10px}
.fq{border:1px solid var(--border); border-radius:16px; background:var(--surface); overflow:hidden; transition:border-color .25s}
.fq.open{border-color:var(--border-strong)}
.fq-q{width:100%; display:flex; align-items:center; gap:12px; padding:18px 20px; text-align:left; font-size:14.5px; font-weight:700}
.fq-q .fx{margin-left:auto; width:26px;height:26px; flex:0 0 26px; border-radius:9px; background:var(--hover); display:grid; place-items:center; color:var(--muted); transition:transform .35s var(--ease-spring), background .2s}
.fq-q .fx svg{width:13px;height:13px}
.fq.open .fq-q .fx{transform:rotate(45deg); background:var(--accent-soft); color:var(--accent)}
.fq-a{display:grid; grid-template-rows:0fr; transition:grid-template-rows .4s var(--ease-out)}
.fq.open .fq-a{grid-template-rows:1fr}
.fq-a>div{overflow:hidden}
.fq-a p{padding:0 20px 18px; color:var(--muted); font-size:13.5px; line-height:1.75}

/* footer */
.foot{margin-top:clamp(32px,5vw,64px); border-top:1px solid var(--border); padding-top:44px; display:grid; grid-template-columns:1.4fr 1fr 1fr 1fr; gap:clamp(20px,3vw,40px)}
.foot .fb{display:flex; flex-direction:column; gap:10px}
.foot .fb h4{font-family:var(--font-mono); font-size:10.5px; letter-spacing:.14em; color:var(--faint); font-weight:700; margin-bottom:4px}
.foot .fb a{color:var(--muted); font-size:13.5px; text-decoration:none; width:fit-content; transition:color .18s}
.foot .fb a:hover{color:var(--fg)}
.foot .brand{display:flex; align-items:center; gap:9px; font-size:15px; font-weight:800}
.foot .brand .ic{color:var(--accent); width:18px;height:18px}
.foot .f-about{font-size:12.5px; color:var(--muted); line-height:1.7}
.foot .f-bottom{grid-column:1/-1; border-top:1px solid var(--border); padding-top:18px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; font-family:var(--font-mono); font-size:11px; color:var(--faint)}

/* ── palette ── */
.overlay{position:fixed; inset:0; z-index:90; background:oklch(0% 0 0/.4); -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px); opacity:0; pointer-events:none; transition:opacity .25s}
.overlay.open{opacity:1; pointer-events:auto}
.palette{position:fixed; top:12vh; left:50%; transform:translateX(-50%); width:min(560px,calc(100vw - 32px)); z-index:95; background:var(--surface-strong); -webkit-backdrop-filter:blur(40px) saturate(180%); backdrop-filter:blur(40px) saturate(180%);
  border:1px solid var(--border); border-radius:22px; box-shadow:var(--shadow); opacity:0; pointer-events:none; transform:translate(-50%,-6px) scale(.98); transition:opacity .22s, transform .3s var(--ease-spring)}
.palette.open{opacity:1; pointer-events:auto; transform:translate(-50%,0) scale(1)}
.palette input{width:100%; height:60px; padding:0 20px; border:0; background:transparent; font-size:15.5px; outline:none}
.palette .p-list{max-height:320px; overflow-y:auto; padding:8px; border-top:1px solid var(--border)}
.p-item{display:flex; align-items:center; gap:12px; width:100%; height:48px; padding:0 14px; border-radius:13px; font-size:13.5px; font-weight:600; text-align:left; text-decoration:none; transition:background .15s}
.p-item .ic{color:var(--faint)}
.p-item .p-hint{margin-left:auto; font-family:var(--font-mono); font-size:10.5px; color:var(--faint)}
.p-item.sel{background:var(--accent-soft); color:var(--accent)}
.p-item.sel .ic{color:var(--accent)}
.p-empty{padding:26px; text-align:center; color:var(--faint); font-size:13px}

/* ── modal ── */
.modal{position:fixed; inset:0; z-index:95; display:grid; place-items:center; padding:20px; opacity:0; pointer-events:none; transition:opacity .25s}
.modal.open{opacity:1; pointer-events:auto}
.modal .mbox{width:min(420px,100%); background:var(--surface-strong); -webkit-backdrop-filter:blur(40px) saturate(180%); backdrop-filter:blur(40px) saturate(180%); border:1px solid var(--border); border-radius:24px; box-shadow:var(--shadow); transform:translateY(12px) scale(.97); transition:transform .35s var(--ease-spring); max-height:min(640px,calc(100vh - 40px)); overflow-y:auto}
.modal.open .mbox{transform:none}
.mhead{display:flex; align-items:center; gap:10px; padding:20px 22px 4px}
.mhead h3{font-size:17px; font-weight:800; flex:1}
.mx{width:34px;height:34px; border-radius:11px; display:grid; place-items:center; color:var(--muted); transition:background .18s,color .18s}
.mx:hover{background:var(--hover); color:var(--fg)}
.mx svg{width:15px;height:15px}
.mbody{padding:16px 22px 22px}
.auth-tabs{display:flex; gap:4px; background:var(--hover); border-radius:12px; padding:4px; margin-bottom:18px}
.auth-tab{flex:1; height:38px; border-radius:9px; font-size:13.5px; font-weight:700; color:var(--muted); transition:background .2s,color .2s}
.auth-tab.on{background:var(--surface-strong); color:var(--fg); box-shadow:var(--shadow-sm)}
.field{display:flex; flex-direction:column; gap:7px; margin-bottom:14px}
.field label{font-size:12px; font-weight:700; color:var(--muted)}
.field input{height:44px; padding:0 14px; border-radius:12px; border:1px solid var(--border); background:var(--surface); transition:border-color .2s, box-shadow .2s}
.field input:focus{outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft)}
.err{font-size:12px; color:var(--danger); min-height:16px; margin:-6px 0 10px; font-weight:600}
.auth-foot{font-size:11.5px; color:var(--faint); line-height:1.6; margin-top:14px; font-family:var(--font-mono)}
.p-stat{display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin:14px 0}
.p-stat .ps{background:var(--hover); border-radius:13px; padding:14px 10px; text-align:center}
.p-stat .pv{font-size:19px; font-weight:800; letter-spacing:-.02em}
.p-stat .pl{font-size:10.5px; color:var(--faint); margin-top:3px; font-family:var(--font-mono)}
.set-row{display:flex; align-items:center; justify-content:space-between; gap:12px; padding:13px 0; border-top:1px solid var(--border)}
.set-row .st2{font-size:13.5px; font-weight:700}
.set-row .sd{font-size:11.5px; color:var(--faint); margin-top:2px}
.switch{position:relative; width:44px; height:26px; flex:0 0 44px; border-radius:99px; background:var(--border-strong); transition:background .25s; cursor:pointer}
.switch::after{content:''; position:absolute; left:3px; top:3px; width:20px; height:20px; border-radius:50%; background:var(--surface-strong); box-shadow:var(--shadow-sm); transition:transform .3s var(--ease-spring)}
.switch.on{background:var(--accent)}
.switch.on::after{transform:translateX(18px)}

/* ── toast / backtop ── */
#toasts{position:fixed; bottom:24px; left:50%; transform:translateX(-50%); z-index:120; display:flex; flex-direction:column; gap:8px; align-items:center; pointer-events:none}
.toast{display:flex; align-items:center; gap:9px; background:var(--surface-strong); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); padding:12px 18px; font-size:13px; font-weight:600; animation:tIn .4s var(--ease-spring); max-width:min(420px,calc(100vw - 40px))}
.toast .tic{width:18px;height:18px; color:var(--ok); flex:0 0 18px}
.toast .tic svg{width:100%;height:100%}
@keyframes tIn{from{opacity:0; transform:translateY(14px) scale(.95)} to{opacity:1; transform:none}}
#backtop{position:fixed; right:20px; bottom:22px; z-index:70; width:44px; height:44px; border-radius:14px; background:var(--surface-strong); border:1px solid var(--border); box-shadow:var(--shadow-sm); display:grid; place-items:center; color:var(--muted); opacity:0; pointer-events:none; transform:translateY(10px); transition:opacity .3s, transform .3s var(--ease-spring), background .2s, color .2s}
#backtop.show{opacity:1; pointer-events:auto; transform:none}
#backtop:hover{background:var(--hover); color:var(--fg)}
#backtop svg{width:16px;height:16px}

/* ── responsive ── */
@media (max-width:1080px){
  .kbd-chip{display:none}
  .g4,.res-grid,.prin-grid,.eco-grid{grid-template-columns:repeat(2,1fr)}
  .stats{grid-template-columns:repeat(2,1fr)}
  .hero{grid-template-columns:1fr}
  .hero-win{max-width:560px}
  .dd{grid-template-columns:1fr}
  .dd.rev .dd-vis{order:0}
  .demo-wrap{grid-template-columns:1fr}
}
@media (max-width:860px){
  #sidebar{top:auto; bottom:0; left:0; right:0; width:auto; transform:translateX(-110%); border-radius:0; border:0; border-top:1px solid var(--border); max-height:76vh; overflow-y:auto; z-index:80}
  body[data-sb="drawer"] #sidebar{width:auto; transform:translateX(0); left:0; border-radius:26px 26px 0 0; border:1px solid var(--border); border-bottom:0; top:auto; bottom:0; margin:0 10px}
  body[data-sb="full"] #sidebar, body[data-sb="rail"] #sidebar{transform:translateX(-110%)}
  body[data-sb="closed"] #sidebar, body[data-sb="drawer"] #sidebar{transform:translateX(0)}
  #sb-prev{display:none}
  .tabs{display:none}
  #btn-drawer{display:grid}
  main{margin-left:14px; margin-right:14px; padding-top:86px}
  .g2,.g3,.g6,.q-grid,.steps,.pain-grid,.how,.cap-grid,.path,.course-grid,.art-feat,.art-grid,.ch-grid,.deploy-grid{grid-template-columns:1fr}
  .cap-detail .grid{grid-template-columns:1fr}
  .foot{grid-template-columns:1fr 1fr}
  .win-chip{right:8px; top:-10px}
}
@media (max-width:520px){
  #chrome{padding:6px 8px}
  .bar{gap:6px; padding:0 6px}
  .lights{gap:5px}
  .light{width:9px;height:9px}
  .foot{grid-template-columns:1fr}
  .stats{grid-template-columns:1fr 1fr}
  .pg-h h1{font-size:30px}
  .btn{height:44px}
  .band{padding:28px 22px}
}
@media (prefers-reduced-motion: reduce), html.rm{
  *,*::before,*::after{animation-duration:.01ms!important; animation-iteration-count:1!important; transition-duration:.01ms!important}
  html{scroll-behavior:auto}
}
</style>
<script src="/assets/seo-inject.js?v=20260813ad" data-page="product"></script>
</head>
<body>

<div class="ambient" aria-hidden="true"><div class="blob blob-a"></div><div class="blob blob-b"></div><div class="blob blob-c"></div></div>

<!-- ══ chrome ══ -->
<header id="chrome">
  <div class="bar">
    <div class="lights" aria-hidden="true"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span></div>
    <nav class="tabs" id="tabs" role="navigation" aria-label="站点导航"></nav>
    <div class="controls">
      <button class="cbtn" id="btn-drawer" data-od-id="nav-drawer" aria-label="打开导航菜单"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
      <button class="kbd-chip" id="btn-cmd" data-od-id="cmd-open" aria-label="打开命令面板">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg></span>
        <span>搜索与命令</span><span class="kbd">⌘ K</span>
      </button>
      <button class="cbtn" id="btn-theme" data-od-id="theme-toggle" aria-label="切换主题"></button>
      <button class="avatar anon" id="btn-av" data-od-id="account" aria-label="账户"></button>
    </div>
    <div class="drop" id="drop">
      <div class="drop-head"><div class="drop-av" id="dropAv">?</div><div style="min-width:0"><div class="drop-name" id="dropName"></div><div class="drop-mail" id="dropMail"></div></div></div>
      <button class="drop-item" id="dropProfile" data-od-id="profile-entry"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.5" r="3.6"/><path d="M4.5 20c1.4-3.5 4.2-5.2 7.5-5.2s6.1 1.7 7.5 5.2"/></svg></span>个人中心</button>
      <button class="drop-item danger" id="dropLogout"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="m10 8-4 4 4 4M6 12h11"/></svg></span>退出登录</button>
    </div>
  </div>
</header>

<!-- ══ sidebar ══ -->
<aside id="sidebar" data-od-id="sidebar">
  <div class="ws" id="ws" role="button" tabindex="0" aria-label="空间：Open Flow 官网">
    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10l2 2h6.5A1.5 1.5 0 0 1 20 7.5v11a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13Z"/></svg></span>
    <span class="ws-name">Open Flow 官网</span>
    <span class="chev"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></span>
  </div>
  <div class="sec"><div class="sec-title"><span>导航</span></div><div id="sbNav"></div></div>
  <div class="sec"><div class="sec-title"><span>快捷操作</span></div><div id="sbFav"></div></div>
  <div class="sec"><div class="sec-title"><span>置顶标签</span></div><div id="sbPin"></div></div>
  <div class="sb-foot"><button id="sb-toggle" data-od-id="sidebar-toggle" aria-label="折叠侧栏" title="折叠侧栏"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M9 4v16"/></svg></button></div>
</aside>
<div class="scrim" id="scrim"></div>
<div id="sb-prev"></div>

<main id="main" data-od-id="main">

<!-- ════════════ 产品 ════════════ -->
<section class="page" id="page-product" data-od-id="page-product">
  <div class="pg">
    <div class="pg-h">
      <span class="kicker">产品 · 芭乐派 OpenFlow</span>
      <h1>一个平台，跑通你的整条增长链路</h1>
      <p class="lead">一人公司最缺的，不是一个工具，而是一套系统。OpenFlow 把内容、数据、自动化、触达连成一套增长引擎——让 Agent 跑流程，你只做判断。不是 All in one，而是 Everything。</p>
      <div class="cta-row"><button class="btn primary" data-act="start">免费开始</button><a class="btn ghost" href="#demo">运行演示</a></div>
    </div>

    <div class="sec-head"><span class="kicker">痛点</span><h2>一人公司最缺的，不是一个工具，而是一套系统</h2></div>
    <div class="pain-grid">
      <div class="card pain"><div class="pi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div><h3>增长靠「手动堆」</h3><p>爬热点、写文章、发触达、盯数据——每件事都亲力亲为，时间被重复动作吃掉，策略没人做。</p></div>
      <div class="card pain"><div class="pi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5h14v6H5zM5 13h14v6H5zM9 8h.01M9 16h.01"/></svg></div><h3>工具之间互相割裂</h3><p>CMS、CDP、MA、CRM 各自为政。数据散落各处，触达和转化接不上，洞察变不成动作。</p></div>
      <div class="card pain"><div class="pi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 19V5m0 14h16M7 15l3-4 3 2 4-6"/></svg></div><h3>增长黑盒不可见</h3><p>不知道访客从哪来、什么内容有效、哪个环节漏单。没有洞察，增长就是撞运气。</p></div>
    </div>

    <div class="sec-head"><span class="kicker">框架</span><h2>TIPS 四力：触达 · 洞察 · 个性化 · 销售</h2><p>OpenFlow 的一切都围绕这四个力组织。理解 TIPS，你就理解了整个平台——也是芭乐派增长操作系统的方法论底座。</p></div>
    <div class="how">
      <div class="card"><div class="hn">T</div><h3>触达 Touch</h3><p>内容引擎、分发渠道、触达体系。正确的时间、渠道、内容，把信息递到用户面前。</p></div>
      <div class="card"><div class="hn">I</div><h3>洞察 Insight</h3><p>数据、CDP、舆情、分析。从几百个指标捞出该看的那 3-5 个，把数据变成判断。</p></div>
      <div class="card"><div class="hn">P</div><h3>个性化 Personality</h3><p>画像、分群、自动化。给对的人，在对的时刻，说对的话。</p></div>
      <div class="card"><div class="hn">S</div><h3>销售 Sales</h3><p>CRM、转化、商城、订阅。从触达到成交，让支付能力流向你。</p></div>
    </div>

    <div class="sec-head"><span class="kicker">能力</span><h2>不是 All in one，而是 Everything</h2></div>

    <div class="dd">
      <div class="dd-copy">
        <h3>可视化编排画布</h3>
        <p>节点即逻辑。拖拽触发器、条件、动作与人工确认步骤，连线即成流程——零代码上手，也不挡专业用户的路。</p>
        <ul><li>分支、循环、并行与等待结构</li><li>实时预览与一键回滚历史版本</li><li>模板库一键复用成熟流程</li></ul>
      </div>
      <div class="dd-vis"><div class="dd-frame"><div class="mock-canvas">
        <div class="mnode" style="left:14px;top:22px">触发器</div>
        <div class="mnode" style="left:52px;top:78px">条件判断</div>
        <div class="mnode" style="right:14px;top:22px">动作 A</div>
        <div class="mnode" style="right:14px;bottom:14px">动作 B</div>
        <svg viewBox="0 0 320 150" style="position:absolute;inset:0;width:100%;height:100%"><g stroke="var(--border-strong)" stroke-width="1.6" fill="none" stroke-dasharray="4 5"><path d="M78 36 C 120 36, 120 92, 150 92"/><path d="M150 92 C 180 92, 180 36, 210 36"/><path d="M150 92 C 180 92, 180 120, 210 120"/></g><circle r="3.5" fill="var(--accent)"><animateMotion dur="2.4s" repeatCount="indefinite" path="M78 36 C 120 36, 120 92, 150 92"/></circle></svg>
      </div></div></div>
    </div>

    <div class="dd rev">
      <div class="dd-copy">
        <h3>AI 步骤：给流程装上判断力</h3>
        <p>用自然语言描述需求，AI 自动生成流程步骤与字段映射。摘要、分类、改写、抽取——大模型能力以步骤的形式进入你的工作流。</p>
        <ul><li>自然语言生成流程草稿</li><li>字段智能映射，少填一次表</li><li>异常自动降级与重试</li></ul>
      </div>
      <div class="dd-vis"><div class="dd-frame"><div class="mchat">
        <div class="bub u">每天 9 点汇总销售日报，提取 Top 3 要点，发到企业微信销售群</div>
        <div class="bub a">已生成 4 步流程：定时触发 → 读取日报 → AI 摘要 → 推送通知</div>
        <div class="gen"><span>✓ 定时触发</span><span>✓ 读取数据</span><span>✓ AI 摘要</span><span>✓ 推送</span></div>
      </div></div></div>
    </div>

    <div class="dd">
      <div class="dd-copy">
        <h3>开放连接器生态</h3>
        <p>不是封闭的私有集成，而是开放的连接标准。核心能力永久开源，常用系统开箱即用，私有系统用 OpenAPI 或 Webhook 自定义接入。</p>
        <ul><li>400+ 内置连接器，持续更新</li><li>核心能力永久开源 · 鱼与渔结合</li><li>Webhook 双向触发与回调</li></ul>
      </div>
      <div class="dd-vis"><div class="dd-frame"><div class="conn-chips" id="connChips"></div></div></div>
    </div>

    <div class="dd rev">
      <div class="dd-copy">
        <h3>自生长 AI Engine，从 Marketing 到 Sales</h3>
        <p>OpenFlow 不是被动工具，而是主动驱动增长的引擎：每 6 小时自动爬取信号、AI 洞察、生成内容、主动触达转化。装完即用，每个人都能改造成专属自己的增长引擎。</p>
        <ul><li>主动爬取舆情与行业热点</li><li>AI 撰写草稿（人工审核后发布）</li><li>洞察→优化→转化全闭环</li></ul>
      </div>
      <div class="dd-vis"><div class="dd-frame"><div class="run-list">
        <div class="run-row"><span class="rn">Loop</span><span class="rt">爬取行业信号 · 自动</span><span class="pill ok rs">进行中</span></div>
        <div class="run-row"><span class="rn">Insight</span><span class="rt">AI 总结热点洞察</span><span class="pill ok rs">完成</span></div>
        <div class="run-row"><span class="rn">Write</span><span class="rt">生成文章草稿（待审）</span><span class="pill warn rs">待确认</span></div>
        <div class="run-row"><span class="rn">Convert</span><span class="rt">主动触达转化</span><span class="pill ok rs">完成</span></div>
      </div></div></div>
    </div>

    <div class="sec-head"><span class="kicker">增长闭环</span><h2>点一下，看增长引擎跑起来</h2><p>下面的增长闭环每 6 小时自动执行：爬取信号 → AI 洞察 → 生成草稿 → 主动触达。点击「运行一轮」观察完整过程。</p></div>
    <div class="demo-wrap" id="demo">
      <div class="demo-fig">
        <div class="dd-frame"><svg class="demo-svg" viewBox="0 0 740 150" aria-hidden="true">
          <g class="nd nd0"><rect x="10" y="42" width="150" height="66" rx="16"/><text class="nt" x="85" y="70" text-anchor="middle">爬取信号</text><text class="nd2" x="85" y="90" text-anchor="middle">舆情 · RSS 热点</text></g>
          <g class="nd nd1"><rect x="200" y="42" width="150" height="66" rx="16"/><text class="nt" x="275" y="70" text-anchor="middle">AI 洞察</text><text class="nd2" x="275" y="90" text-anchor="middle">总结增长机会</text></g>
          <g class="nd nd2"><rect x="390" y="42" width="150" height="66" rx="16"/><text class="nt" x="465" y="70" text-anchor="middle">AI 撰写</text><text class="nd2" x="465" y="90" text-anchor="middle">生成草稿 · 待审</text></g>
          <g class="nd nd3"><rect x="580" y="42" width="150" height="66" rx="16"/><text class="nt" x="655" y="70" text-anchor="middle">主动触达</text><text class="nd2" x="655" y="90" text-anchor="middle">转化 · 销售闭环</text></g>
          <g stroke="var(--border-strong)" stroke-width="1.8" fill="none"><path class="ln" d="M160 75 H200"/><path class="ln" d="M350 75 H390"/><path class="ln" d="M540 75 H580"/></g>
          <circle class="fd fd1" r="4"><animateMotion dur="1s" repeatCount="indefinite" path="M160 75 H200"/></circle>
          <circle class="fd fd2" r="4"><animateMotion dur="1s" repeatCount="indefinite" path="M350 75 H390"/></circle>
          <circle class="fd fd3" r="4"><animateMotion dur="1s" repeatCount="indefinite" path="M540 75 H580"/></circle>
          <circle class="fd fd4" r="4"><animateMotion dur="1s" repeatCount="indefinite" path="M160 75 H580"/></circle>
        </svg></div>
        <div class="demo-ctrl">
          <button class="btn primary sm" id="demoRun" data-od-id="demo-run"><span class="ic"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l11-6.5-11-6.5Z"/></svg></span><span>运行一轮</span></button>
          <span class="note" id="demoState">就绪 · 点击运行</span>
        </div>
      </div>
      <div class="demo-log"><div class="ln2" id="demoLog">$ openflow growth-loop<br><span class="t-dim"># 等待触发……</span></div></div>
    </div>

    <div class="sec-head"><span class="kicker">价值</span><h2>增长引擎正在产生实实在在的价值</h2><p class="note">以下为演示示例数据，正式版将替换为真实统计。</p></div>
    <div class="stats">
      <div class="card stat"><div class="sv">8/8</div><div class="sl">增长闭环环节正常</div></div>
      <div class="card stat"><div class="sv">24/7</div><div class="sl">自生长引擎主动运行</div></div>
      <div class="card stat"><div class="sv">100%</div><div class="sl">核心能力永久开源</div></div>
      <div class="card stat"><div class="sv">1人</div><div class="sl">即可驱动整套增长系统</div></div>
    </div>

    <div class="sec-head"><span class="kicker">常见问题</span><h2>你可能会关心</h2></div>
    <div class="faq" id="faq"></div>

    <div class="sec-head"><span class="kicker">客户评价</span><h2>他们已经在用 OpenFlow 跑增长</h2></div>
    <div class="stats" style="grid-template-columns:repeat(3,1fr)">
      <div class="card stat" style="text-align:left;padding:24px"><div style="color:var(--warn);font-size:14px;letter-spacing:2px;margin-bottom:10px">★★★★★</div><div style="font-size:13.5px;line-height:1.7;color:var(--fg);text-align:left">「以前每天 3 小时找选题改文章，现在 OpenFlow 爬完信号直接给草稿，我只管把关。效率翻了三倍。」</div><div style="margin-top:12px;font-size:12px;color:var(--muted)">陈默 · 内容工作室</div></div>
      <div class="card stat" style="text-align:left;padding:24px"><div style="color:var(--warn);font-size:14px;letter-spacing:2px;margin-bottom:10px">★★★★★</div><div style="font-size:13.5px;line-height:1.7;color:var(--fg);text-align:left">「销转率从 2.1% 提到 3.8%，靠的不是更多流量，是把转化每一环都拆出来让 Agent 盯。」</div><div style="margin-top:12px;font-size:12px;color:var(--muted)">林晓 · 知识付费</div></div>
      <div class="card stat" style="text-align:left;padding:24px"><div style="color:var(--warn);font-size:14px;letter-spacing:2px;margin-bottom:10px">★★★★★</div><div style="font-size:13.5px;line-height:1.7;color:var(--fg);text-align:left">「4 个人的团队，周报、监控、跨群通知全交给工作流，省出的时间够多做一个客户。」</div><div style="margin-top:12px;font-size:12px;color:var(--muted)">王珩 · SaaS 服务商</div></div>
    </div>

    <div class="band" data-od-id="product-cta">
      <span class="kicker" style="color:inherit;opacity:.75">立即开始</span>
      <h2>装完即用，今天就能长出你的增长引擎</h2>
      <p>免费开始，无需信用卡。安装后 OpenFlow 自动开始爬取信号、主动洞察、主动转化——每个人都能改造成专属自己的增长系统。</p>
       <div class="cta-row"><button class="btn primary" data-act="start">免费开始</button><a class="btn ghost" href="/capability">了解 TIPS 能力</a></div>
    </div>
  </div>
</section>

<!-- ══ footer ══ -->
<footer class="foot" data-od-id="site-footer">
  <div class="fb">
    <div class="brand"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>芭乐派 · OpenFlow</div>
    <p class="f-about">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
    <p class="note">核心能力永久开源 · 鱼与渔相结合</p>
  </div>
  <div class="fb">
    <h4>站点导航</h4>
    <a href="/product">产品</a><a href="/capability">能力</a><a href="/courses">课程</a><a href="/academy">学院</a><a href="/community">论坛</a><a href="/about">关于我们</a>
  </div>
  <div class="fb">
    <h4>资源</h4>
    <a href="/courses">芭乐派课程</a><a href="#" data-act="mail">文档中心</a><a href="#" data-act="mail">模板库</a><a href="#" data-act="mail">开放 API</a>
  </div>
  <div class="fb">
    <h4>联系</h4>
    <a href="#" data-act="mail">hello@openflow.dev</a><a href="#" data-act="mail">商务合作</a><a href="#" data-act="mail">加入团队</a><a href="/community">门派社区</a>
  </div>
  <div class="f-bottom"><span>© 2026 芭乐派 · OpenFlow 增长操作系统</span><span>帮一人公司设计 Agent 能跑的增长系统</span></div>
</footer>
</main>

<!-- ══ command palette ══ -->
<div class="overlay" id="palOverlay"></div>
<div class="palette" id="palette" role="dialog" aria-label="命令面板">
  <input id="palInput" placeholder="搜索页面与命令…" autocomplete="off">
  <div class="p-list" id="palList"></div>
</div>

<!-- ══ auth modal ══ -->
<div class="modal" id="authModal" data-od-id="auth-modal">
  <div class="mbox">
    <div class="mhead"><h3 id="authTitle">登录 Open Flow</h3><button class="mx" data-close="authModal" aria-label="关闭"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>
    <div class="mbody">
      <div class="auth-tabs">
        <button class="auth-tab on" id="tabLogin">登录</button>
        <button class="auth-tab" id="tabReg">注册</button>
      </div>
      <div id="regFields" style="display:none">
        <div class="field"><label for="fNick">昵称</label><input id="fNick" placeholder="2-20 个字符" autocomplete="nickname"></div>
      </div>
      <div class="field"><label for="fMail">邮箱</label><input id="fMail" placeholder="you@example.com" type="email" autocomplete="email"></div>
      <div class="field"><label for="fPwd">密码</label><input id="fPwd" placeholder="至少 6 位" type="password" autocomplete="current-password"></div>
      <div class="err" id="authErr"></div>
      <button class="btn primary" id="authSubmit" style="width:100%">登录</button>
      <p class="auth-foot">演示环境：账号仅保存在本地浏览器，用于体验登录与个人中心流程。</p>
    </div>
  </div>
</div>

<!-- ══ profile modal ══ -->
<div class="modal" id="profileModal" data-od-id="profile-modal">
  <div class="mbox">
    <div class="mhead"><h3>个人中心</h3><button class="mx" data-close="profileModal" aria-label="关闭"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>
    <div class="mbody">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:6px">
        <div class="drop-av" id="pfAv">?</div>
        <div style="min-width:0"><div class="drop-name" id="pfName"></div><div class="drop-mail" id="pfMail"></div></div>
      </div>
      <div class="p-stat">
        <div class="ps"><div class="pv" id="pfC1">0</div><div class="pl">已加入课程</div></div>
        <div class="ps"><div class="pv" id="pfC2">0</div><div class="pl">点赞帖子</div></div>
        <div class="ps"><div class="pv" id="pfC3">0</div><div class="pl">收藏文章</div></div>
      </div>
      <div class="set-row"><div><div class="st2">深色主题</div><div class="sd">跟随你的偏好</div></div><div class="switch" id="setTheme" role="switch" aria-checked="false"></div></div>
      <div class="set-row"><div><div class="st2">减少动效</div><div class="sd">关闭动画与过渡</div></div><div class="switch" id="setRM" role="switch" aria-checked="false"></div></div>
      <button class="btn ghost" id="pfLogout" style="width:100%;margin-top:14px;color:var(--danger);border-color:var(--danger)">退出登录</button>
    </div>
  </div>
</div>

<div id="toasts" aria-live="polite"></div>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>

<script>
(function(){
'use strict';
var $=function(s){return document.querySelector(s)};
var $$=function(s){return Array.prototype.slice.call(document.querySelectorAll(s))};
var RM=matchMedia('(prefers-reduced-motion: reduce)').matches;
var LS='openflow-site-v3', UK='of_users_v3', SK='of_session_v3';
var EASE='cubic-bezier(.34,1.56,.64,1)';
var PAGE='product';

var I={
home:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9v10.5h13V9"/></svg>',
box:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 8v8l9 5 9-5V8"/></svg>',
bolt:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg>',
book:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14v18H7a2 2 0 0 1-2-2V3Z"/><path d="M5 17a2 2 0 0 1 2-2h12"/></svg>',
doc:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h10l4 4v14H7V3Z"/><path d="M17 3v4h4"/></svg>',
users:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.5c2.8.3 5 2.6 5 5.5"/></svg>',
info:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="8" r=".7" fill="currentColor" stroke="none"/></svg>',
check:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.5 5 5 10-11"/></svg>',
x:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>',
plus:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
refresh:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12a8 8 0 1 1-2.3-5.6M20 4v4h-4"/></svg>',
arrow:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>',
sun:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/></svg>',
moon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5Z"/></svg>',
play:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l11-6.5-11-6.5Z"/></svg>'
};
function ic(n){return '<span class="ic">'+(I[n]||'')+'</span>'}

var NAV=[
{id:'home',label:'首页',href:'/',icon:'home',blurb:'芭乐派 · 帮一人公司设计 Agent 能跑的增长系统'},
{id:'product',label:'产品',href:'/product',icon:'box',blurb:'痛点、原理、能力深挖与可运行演示',mega:{title:'产品与平台',blurb:'芭乐派增长操作系统 · 帮一人公司设计 Agent 能跑的增长系统',cols:[{head:'核心产品',items:[{t:'内容引擎 CMS',d:'文章 · 页面 · 发布',href:'/category/products/cms'},{t:'营销自动化 MA',d:'可视化工作流引擎',href:'/category/products/ma'},{t:'客户数据 CDP',d:'画像 · 分群 · 洞察',href:'/category/products/cdp'},{t:'SEO / GEO 引擎',d:'搜索与 AI 优化',href:'/category/products/seo'}]},{head:'增长与商业',items:[{t:'CRM 与线索',d:'线索池与转化',href:'/category/products/crm'},{t:'商业与订阅',d:'商城 · 会员 · 付费',href:'/category/products/commerce'},{t:'社区与内容',d:'论坛 · 评论 · 积分',href:'/category/products/community'},{t:'数据分析',d:'归因 · A/B · 洞察',href:'/category/products/data'}]}],foot:[{t:'产品总览',href:'/product'},{t:'能力矩阵',href:'/capability'},{t:'课程入口',href:'/courses'}]}},
{id:'capability',label:'能力',href:'/capability',icon:'bolt',blurb:'TIPS 框架 · 触达/洞察/个性化/销售四力合一',mega:{title:'六大核心能力',blurb:'TIPS 框架 · 触达/洞察/个性化/销售四力合一',cols:[{head:'内容与增长',items:[{t:'内容引擎',d:'CMS · 课程 · 资料 · 播客',href:'/category/capabilities/content'},{t:'增长与获客',d:'落地页 · 表单 · SEO · 工具',href:'/category/capabilities/growth'},{t:'转化与留存',d:'MA 自动化 · 会员 · 订阅',href:'/category/capabilities/conversion'},{t:'数据与洞察',d:'CDP · 分析 · 归因 · A/B',href:'/category/capabilities/data'}]},{head:'商业与运营',items:[{t:'商业闭环',d:'商城 · 生态 · 分销',href:'/category/capabilities/commerce'},{t:'社区运营',d:'论坛 · 积分 · 直播 · 咨询',href:'/category/capabilities/community'},{t:'内容学院',d:'文章 · 案例 · 方法论',href:'/category/academy/articles'},{t:'生态市场',d:'Skill · 插件 · 主题',href:'/category/marketplace/skills'}]}],foot:[{t:'全部能力',href:'/capability'},{t:'进入学院',href:'/category/academy/articles'},{t:'社区讨论',href:'/community'}]}},
{id:'courses',label:'课程',href:'/courses',icon:'book',blurb:'New-1~4 基石课 + R.B.E 训练营',mega:{title:'芭乐派 · 学习路径',blurb:'New-1~4 课程 + R.B.E 训练营 · 以 OpenFlow 为工具',cols:[{head:'课程类型',items:[{t:'基石课',d:'New-1~4 免费入门课',href:'/courses'},{t:'训练营',d:'R.B.E 八周系统设计营',href:'/courses#courseGrid'},{t:'方法论',d:'利润公式 · 四引擎',href:'/courses#coursePath'},{t:'一对一咨询',d:'O.L.B 增长诊断',href:'/consultation'}]},{head:'相关资源',items:[{t:'免费资源',d:'入门免费内容',href:'/category/courses/free'},{t:'资料下载',d:'白皮书 · 模板',href:'/downloads'},{t:'播客视频',d:'干货音视频',href:'/podcasts'},{t:'内容学院',d:'增长实践文章',href:'/category/academy/articles'}]}],foot:[{t:'浏览全部课程',href:'/courses'},{t:'报名训练营',href:'/courses#courseGrid'}]}},
{id:'articles',label:'学院',href:'/academy',icon:'doc',blurb:'增长系统 · Agent · 一人公司方法论',mega:{title:'内容学院',blurb:'增长系统 · Agent · 一人公司方法论',cols:[{head:'内容专区',items:[{t:'文章',d:'增长实践文章',href:'/category/academy/articles'},{t:'资料',d:'白皮书 · 模板 · 报告',href:'/category/academy/downloads'},{t:'播客视频',d:'干货音视频',href:'/category/academy/podcasts'},{t:'专题合集',d:'主题系列文章',href:'/category/academy/topics'}]},{head:'文档与工具',items:[{t:'文档中心',d:'产品文档 · 使用指南',href:'/category/academy/docs'},{t:'工具箱',d:'SEO 检查 · Meta · LTV',href:'/category/academy/tools'},{t:'社区问答',d:'提问与讨论',href:'/community'}]}],foot:[{t:'进入学院',href:'/academy'},{t:'浏览工具',href:'/category/academy/tools'}]}},
{id:'about',label:'关于我们',href:'/about',icon:'info',blurb:'芭乐派故事 · 创始人刘泽军 · 加入门派'}
];
var byId={};NAV.forEach(function(n){byId[n.id]=n});

var defaults={theme:'light',sb:'full',enrolled:[],liked:[],rm:false};
var S;
try{S=Object.assign({},defaults,JSON.parse(localStorage.getItem(LS)||'{}'));}catch(e){S=Object.assign({},defaults);}
function save(){try{localStorage.setItem(LS,JSON.stringify({theme:S.theme,sb:S.sb,enrolled:S.enrolled,liked:S.liked,rm:S.rm}));}catch(e){}}

var users=[];try{users=JSON.parse(localStorage.getItem(UK)||'[]')}catch(e){}
var sess=null;try{sess=localStorage.getItem(SK)}catch(e){}
function curUser(){return sess?{email:sess,nick:sess.split('@')[0]}:null}

function applyTheme(){document.documentElement.dataset.theme=S.theme;$('#btn-theme').innerHTML=S.theme==='dark'?I.sun:I.moon;var sw=$('#setTheme');if(sw){sw.classList.toggle('on',S.theme==='dark');sw.setAttribute('aria-checked',S.theme==='dark');}}
function toggleTheme(){S.theme=S.theme==='dark'?'light':'dark';applyTheme();save();toast(S.theme==='dark'?'已切换到深色主题':'已切换到浅色主题');}
applyTheme();
if(S.rm)document.documentElement.classList.add('rm');

function toast(msg){var t=document.createElement('div');t.className='toast';t.innerHTML='<span class="tic">'+I.check+'</span>'+msg;$('#toasts').appendChild(t);setTimeout(function(){t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(function(){t.remove()},320)},2600);}

function renderAvatar(){var u=curUser(),b=$('#btn-av'),d=$('#drop');
b.classList.toggle('anon',!u);
b.textContent=u?(u.nick||u.email)[0].toUpperCase():'?';
var nm=$('#dropName'),ml=$('#dropMail'),av=$('#dropAv');
if(u){nm.textContent=u.nick||u.email;ml.textContent=u.email;av.textContent=(u.nick||u.email)[0].toUpperCase();}
else{nm.textContent='未登录';ml.textContent='点击登录 / 注册';av.textContent='?';}
$('#btn-av').setAttribute('aria-label',u?'账户：'+(u.nick||u.email):'登录 / 注册');
var pv=$('#pfAv'),pn=$('#pfName'),pm=$('#pfMail');
if(u){pv.textContent=(u.nick||u.email)[0].toUpperCase();pn.textContent=u.nick||u.email;pm.textContent=u.email;}
else{pv.textContent='?';pn.textContent='未登录';pm.textContent='—';}
$('#pfC1').textContent=S.enrolled.length;$('#pfC2').textContent=S.liked.length;$('#pfC3').textContent=0;
}
function closeDrop(){$('#drop').classList.remove('open')}
$('#btn-av').addEventListener('click',function(e){e.stopPropagation();var u=curUser();if(u){$('#drop').classList.toggle('open')}else{openAuth('login')}});
document.addEventListener('click',function(e){if(!e.target.closest('.controls'))closeDrop();});
$('#dropProfile').addEventListener('click',function(){closeDrop();openProfile()});
$('#dropLogout').addEventListener('click',function(){closeDrop();logout()});

function goFile(href){closeDrawer();closePalette();closeDrop();window.location.href=href;}

/* tabs */
var tabsEl=$('#tabs');
function renderTabs(){
  tabsEl.innerHTML='';
  NAV.forEach(function(n){
    var el=document.createElement('a');
    el.className='tab'+(n.id===PAGE?' active':'');el.href=n.href;
    el.innerHTML=ic(n.icon)+'<span class="t-label">'+n.label+'</span>';
    if(n.mega){
      var mm=document.createElement('div');mm.className='mega';
      var h='<div class="mega-top"><h4>'+n.mega.title+'</h4><p>'+n.mega.blurb+'</p></div><div class="mega-cols">';
      n.mega.cols.forEach(function(col){
        h+='<div><div class="mega-col-head">'+col.head+'</div>';
        col.items.forEach(function(it){h+='<a class="mega-item" href="'+it.href+'"><b>'+it.t+'</b><span>'+it.d+'</span></a>';});
        h+='</div>';
      });
      h+='</div>';
      if(n.mega.foot){h+='<div class="mega-foot">';n.mega.foot.forEach(function(f){h+='<a href="'+f.href+'">'+f.t+'</a>';});h+='</div>';}
      // mega 渲染到 body（fixed 定位，避免 .tabs overflow 裁剪）
      mm.innerHTML=h;
      el.appendChild(mm);
      // hover 时计算 fixed 位置（基于 tab 相对视口）
      el.addEventListener('mouseenter', function(){
        var r=el.getBoundingClientRect();
        var mw=Math.min(720, window.innerWidth-24);
        mm.style.width=mw+'px';
        mm.style.top=(r.bottom+10)+'px';
        var cx=Math.max(mw/2+12, Math.min(r.left+r.width/2, window.innerWidth-mw/2-12));
        mm.style.left=cx+'px';
        mm.style.transform='translateX(-50%)';
        mm.style.opacity='1';
        mm.style.pointerEvents='auto';
      });
      var hideTimer=null;
      el.addEventListener('mouseleave', function(){
        clearTimeout(hideTimer);
        hideTimer=setTimeout(function(){ mm.style.opacity='0'; mm.style.pointerEvents='none'; }, 220);
      });
      mm.addEventListener('mouseenter', function(){ clearTimeout(hideTimer); mm.style.opacity='1'; mm.style.pointerEvents='auto'; });
      mm.addEventListener('mouseleave', function(){
        clearTimeout(hideTimer);
        hideTimer=setTimeout(function(){ mm.style.opacity='0'; mm.style.pointerEvents='none'; }, 220);
      });
    }
    tabsEl.appendChild(el);
  });
}

/* sidebar */
var sbNav=$('#sbNav');
function renderSidebar(){
  sbNav.innerHTML='';
  NAV.forEach(function(n){
    var el=document.createElement('a');
    el.className='s-item'+(n.id===PAGE?' active':'');el.href=n.href;
    el.innerHTML=ic(n.icon)+'<span class="s-label">'+n.label+'</span>';
    el.addEventListener('mouseenter',function(){showPrev(el,n)});
    el.addEventListener('mouseleave',hidePrev);
    el.addEventListener('focus',function(){showPrev(el,n)});
    el.addEventListener('blur',hidePrev);
    sbNav.appendChild(el);
  });
  var fav=[{act:'top',label:'回到顶部',icon:'arrow'},{act:'theme',label:'切换主题',icon:'bolt'},{act:'demo',label:'运行演示',icon:'refresh'}];
  $('#sbFav').innerHTML='';
  fav.forEach(function(f){var el=document.createElement('button');el.className='s-item';el.innerHTML=ic(f.icon)+'<span class="s-label">'+f.label+'</span>';
    el.addEventListener('click',function(){
      if(f.act==='top')window.scrollTo({top:0,behavior:RM?'auto':'smooth'});
      else if(f.act==='theme')toggleTheme();
      else if(f.act==='demo'){var d=document.getElementById('demo');if(d)window.scrollTo({top:d.getBoundingClientRect().top+window.scrollY-120,behavior:RM?'auto':'smooth'});else goFile('/product#demo');}
    });$('#sbFav').appendChild(el);});
  var pin=['home','product','courses'];
  $('#sbPin').innerHTML='';
  pin.forEach(function(id){var n=byId[id];var el=document.createElement('a');el.className='s-item pin-item'+(n.id===PAGE?' active':'');el.href=n.href;el.innerHTML='<span class="pin-dot"></span><span class="s-label">'+n.label+'</span>';$('#sbPin').appendChild(el);});
}
var prev=$('#sb-prev');
function showPrev(el,n){
  if(matchMedia('(max-width:860px)').matches)return;
  var r=el.getBoundingClientRect();
  prev.innerHTML='<div class="p-k">'+n.label.toUpperCase()+'</div><div class="p-t">'+n.label+'</div><div class="p-d">'+n.blurb+'</div>';
  prev.classList.add('open');
  var left=r.right+12,w=232;
  if(left+w>window.innerWidth-12)left=Math.max(12,window.innerWidth-w-12);
  prev.style.left=left+'px';
  prev.style.top=Math.max(12,Math.min(r.top,window.innerHeight-90))+'px';
}
function hidePrev(){prev.classList.remove('open')}
var sbOrder=['full','rail','closed'];
$('#sb-toggle').addEventListener('click',function(){
  if(matchMedia('(max-width:860px)').matches){setDrawer(false);return;}
  var i=sbOrder.indexOf(S.sb);S.sb=sbOrder[(i+1)%3];document.body.dataset.sb=S.sb;save();
});
$('#ws').addEventListener('click',function(){S.sb='closed';document.body.dataset.sb='closed';save()});
function setDrawer(open){document.body.dataset.sb=open?'drawer':(S.sb==='closed'?'closed':'full');}
function closeDrawer(){if(document.body.dataset.sb==='drawer'){setDrawer(false)}}
$('#btn-drawer').addEventListener('click',function(){setDrawer(document.body.dataset.sb!=='drawer')});
$('#scrim').addEventListener('click',function(){setDrawer(false)});
document.body.dataset.sb=S.sb;

/* palette */
var palItems=[];
function buildPal(){
  palItems=[];
  NAV.forEach(function(n){palItems.push({id:n.id,label:n.label,icon:n.icon,hint:'打开页面',href:n.href})});
  palItems.push({id:'__theme',label:S.theme==='dark'?'切换到浅色主题':'切换到深色主题',icon:'bolt',hint:'外观'});
  palItems.push({id:'__top',label:'回到顶部',icon:'arrow',hint:'操作'});
  palItems.push({id:'__demo',label:'运行产品演示',icon:'refresh',hint:'操作'});
  palItems.push({id:'__auth',label:curUser()?'打开个人中心':'登录 / 注册',icon:'users',hint:'账户'});
}
var palIdx=0,palFiltered=[];
function openPalette(){buildPal();$('#palette').classList.add('open');$('#palOverlay').classList.add('open');$('#palInput').value='';filterPal('');$('#palInput').focus();}
function closePalette(){$('#palette').classList.remove('open');$('#palOverlay').classList.remove('open')}
function filterPal(q){
  q=q.trim().toLowerCase();
  palFiltered=palItems.filter(function(it){return !q||it.label.toLowerCase().indexOf(q)>-1||it.hint.toLowerCase().indexOf(q)>-1});
  palIdx=0;renderPal();
}
function renderPal(){
  var list=$('#palList');
  if(!palFiltered.length){list.innerHTML='<div class="p-empty">没有匹配的结果</div>';return;}
  list.innerHTML='';
  palFiltered.forEach(function(it,i){
    var el=document.createElement(it.href?'a':'button');el.className='p-item'+(i===palIdx?' sel':'');if(it.href)el.href=it.href;
    el.innerHTML=ic(it.icon)+'<span>'+it.label+'</span><span class="p-hint">'+it.hint+'</span>';
    el.addEventListener('click',function(){runPal(it)});
    list.appendChild(el);
  });
  var sel=list.querySelector('.sel');
  if(sel){var r0=list.getBoundingClientRect(),rs=sel.getBoundingClientRect();if(rs.top<r0.top||rs.bottom>r0.bottom)list.scrollTop+=rs.top-r0.top-(r0.height-rs.height)/2;}
}
function runPal(it){
  closePalette();
  if(it.id.indexOf('__')===0){
    if(it.id==='__theme')toggleTheme();
    else if(it.id==='__top')window.scrollTo({top:0,behavior:RM?'auto':'smooth'});
    else if(it.id==='__demo'){var d=document.getElementById('demo');if(d)window.scrollTo({top:d.getBoundingClientRect().top+window.scrollY-120,behavior:RM?'auto':'smooth'});else goFile('/product#demo');}
    else if(it.id==='__auth'){curUser()?openProfile():openAuth('login');}
  }else{goFile(it.href);}
}
$('#btn-cmd').addEventListener('click',openPalette);
$('#palOverlay').addEventListener('click',closePalette);
$('#palInput').addEventListener('input',function(){filterPal(this.value)});
$('#palInput').addEventListener('keydown',function(e){
  if(e.key==='ArrowDown'){e.preventDefault();palIdx=Math.min(palIdx+1,palFiltered.length-1);renderPal();}
  else if(e.key==='ArrowUp'){e.preventDefault();palIdx=Math.max(palIdx-1,0);renderPal();}
  else if(e.key==='Enter'){if(palFiltered[palIdx])runPal(palFiltered[palIdx]);}
  else if(e.key==='Escape'){closePalette();}
});
document.addEventListener('keydown',function(e){
  if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();$('#palette').classList.contains('open')?closePalette():openPalette();}
  if(e.key==='Escape'){closePalette();$$('.modal.open').forEach(function(m){m.classList.remove('open')});closeDrop();closeDrawer();}
});

$$('[data-act]').forEach(function(el){el.addEventListener('click',function(e){e.preventDefault();var a=el.dataset.act;
  if(a==='start'){var u=curUser();if(u){openProfile();toast('欢迎回来，'+(u.nick||u.email))}else{openAuth('register')}}
  else if(a==='join'){location.href='/community';}
  else if(a==='mail'){var t=(el.textContent||'').trim();if(t==='文档中心'){location.href='/docs';}else if(t==='模板库'){location.href='/docs#templates';}else if(t.indexOf('API')>=0){location.href='/docs#api';}else{location.href='mailto:hello@openflow.dev';}}
  else if(a==='demo'){var d=document.getElementById('demo');if(d)window.scrollTo({top:d.getBoundingClientRect().top+window.scrollY-120,behavior:RM?'auto':'smooth'});}
})});

/* auth */
function openAuth(mode){
  $('#authModal').classList.add('open');
  setAuthMode(mode||'login');
}
function setAuthMode(mode){
  var login=mode==='login';
  $('#tabLogin').classList.toggle('on',login);$('#tabReg').classList.toggle('on',!login);
  $('#regFields').style.display=login?'none':'block';
  $('#authTitle').textContent=login?'登录 Open Flow':'注册 Open Flow';
  $('#authSubmit').textContent=login?'登录':'注册并进入个人中心';
  $('#authErr').textContent='';
}
$('#tabLogin').addEventListener('click',function(){setAuthMode('login')});
$('#tabReg').addEventListener('click',function(){setAuthMode('register')});
$('#authSubmit').addEventListener('click',function(){
  var mode=$('#tabLogin').classList.contains('on')?'login':'register';
  var mail=$('#fMail').value.trim().toLowerCase(),pwd=$('#fPwd').value,nick=$('#fNick').value.trim();
  var btn=$('#authSubmit'),err=$('#authErr');
  if(!/^\S+@\S+\.\S+$/.test(mail)){err.textContent='请输入有效的邮箱地址';return;}
  if(pwd.length<6){err.textContent='密码至少需要 6 位';return;}
  if(mode==='register'&&nick.length<2){err.textContent='昵称至少 2 个字符';return;}
  btn.disabled=true;btn.textContent='处理中…';
  var fd=new FormData();fd.append('account',mail);fd.append('password',pwd);
  if(mode==='register'){fd.append('name',nick);fd.append('email',mail);}
  fetch('/api/member.php?action='+mode,{method:'POST',body:fd})
    .then(function(r){return r.json().then(function(d){return {http:r.status,d:d};});})
    .then(function(res){
      btn.disabled=false;btn.textContent=mode==='register'?'注册并进入个人中心':'登录';
      var d=res.d;
      if(res.http===200&&d.ok){
        sess=mail;try{localStorage.setItem(SK,sess)}catch(e){}
        $('#authModal').classList.remove('open');
        $('#fMail').value='';$('#fPwd').value='';$('#fNick').value='';
        renderAvatar();save();
        toast(mode==='register'?'注册成功，欢迎加入':'登录成功，欢迎回来');
        openProfile();
      }else{
        err.textContent=d.error||'操作失败，请稍后再试';
      }
    })
    .catch(function(){btn.disabled=false;btn.textContent=mode==='register'?'注册并进入个人中心':'登录';err.textContent='网络异常，请稍后再试';});
});
function logout(){
  sess=null;try{localStorage.removeItem(SK)}catch(e){}
  $('#profileModal').classList.remove('open');$('#drop').classList.remove('open');
  renderAvatar();toast('已退出登录');
}
$('#pfLogout').addEventListener('click',logout);

/* profile */
function openProfile(){
  var u=curUser();
  if(!u){openAuth('login');return;}
  $('#setTheme').classList.toggle('on',S.theme==='dark');
  $('#setRM').classList.toggle('on',S.rm);
  $('#profileModal').classList.add('open');
}
$('#setTheme').addEventListener('click',function(){toggleTheme()});
$('#setRM').addEventListener('click',function(){S.rm=!S.rm;document.documentElement.classList.toggle('rm',S.rm);save();toast(S.rm?'已开启减少动效':'已关闭减少动效')});
$$('[data-close]').forEach(function(el){el.addEventListener('click',function(){$('#'+el.dataset.close).classList.remove('open')})});
$$('.overlay').forEach(function(o){o.addEventListener('click',function(){$$('.modal.open').forEach(function(m){m.classList.remove('open')})})});

/* ── connectors ── */
var CONN=['飞书','钉钉','企业微信','Slack','Notion','GitHub','Google Sheets','PostgreSQL','Webhook','OpenAPI','Salesforce','HubSpot'];
function renderConn(sel){$(sel).innerHTML='';CONN.forEach(function(c){var d=document.createElement('span');d.className='cc';d.innerHTML='<span class="cd"></span>'+c;$(sel).appendChild(d);});}
renderConn('#connChips');

/* ── faq ── */
 var FAQS=[['OpenFlow 需要写代码吗？','不需要。TIPS 框架下可视化配置触达/洞察/个性化/销售四力；需要时可用 Task Graph 编排 Agent，深浅兼顾。'],
 ['适合一人公司吗？','OpenFlow 就是为 OPC 一人公司设计的。装完即用，自生长 AI Engine 自动爬取、洞察、转化，一个人也能驱动整套增长系统。'],
 ['和「芭乐派」是什么关系？','OpenFlow 是芭乐派增长操作系统的开源底座。芭乐派讲方法论（利润公式/四引擎/Agent 系统），OpenFlow 是落地工具——鱼与渔相结合。'],
 ['核心能力真的永久开源吗？','是。Tools 和 Strategy 双向迭代，核心能力永久开源，坚持让用户既用得上工具，也能用最前沿的增长策略。'],
 ['数据安全如何保证？','传输与存储加密、细粒度权限、审计日志；支持私有化部署，数据不出域。']];
$('#faq').innerHTML='';
FAQS.forEach(function(f,i){var el=document.createElement('div');el.className='fq';
  el.innerHTML='<button class="fq-q" data-fq="'+i+'"><span>'+f[0]+'</span><span class="fx">'+I.plus+'</span></button><div class="fq-a"><div><p>'+f[1]+'</p></div></div>';
  el.querySelector('[data-fq]').addEventListener('click',function(){el.classList.toggle('open');});
  $('#faq').appendChild(el);});

/* ── demo ── */
var DLOG=[['info','> 09:00:00 触发器命中 · 流程开始'],
['info','> 09:00:01 正在读取「销售日报」表 · 12 条记录'],
['info','> 09:00:02 字段映射完成 · 12/12'],
['ok','> 09:00:03 AI 摘要生成 · 3 个要点'],
['ok','> 09:00:04 推送至企业微信「销售群」· 成功'],
['ok','> 09:00:05 本次运行完成 · 耗时 5.2s']];
var running=false,runTimers=[];
function resetDemo(){
  runTimers.forEach(clearTimeout);runTimers=[];
  running=false;
  var svg=$('#demo').querySelector('.demo-svg');
  svg.className='demo-svg';
  $('#demoLog').innerHTML='$ openflow run sales-daily<br><span class="t-dim"># 等待触发……</span>';
  $('#demoState').textContent='就绪 · 点击运行';
  $('#demoRun').disabled=false;
  $('#demoRun').innerHTML='<span class="ic">'+I.play+'</span><span>运行一次</span>';
}
$('#demoRun').addEventListener('click',function(){
  if(running){resetDemo();return;}
  running=true;this.disabled=true;
  this.innerHTML='<span class="ic">'+I.refresh+'</span><span>运行中…</span>';
  $('#demoState').textContent='运行中 · 预计 5 秒';
  var svg=$('#demo').querySelector('.demo-svg');
  var log=$('#demoLog');log.innerHTML='$ openflow run sales-daily<br>';
  [1,2,3,4].forEach(function(n){runTimers.push(setTimeout(function(){svg.className='demo-svg r'+n;},n*1100));});
  DLOG.forEach(function(l,i){runTimers.push(setTimeout(function(){
    var span=document.createElement('span');span.className='t-'+(l[0]==='ok'?'ok':l[0]==='warn'?'warn':'dim');
    span.innerHTML=l[1]+'<br>';log.appendChild(span);
  },900+i*820));});
  runTimers.push(setTimeout(function(){
    running=false;$('#demoRun').disabled=false;
    $('#demoRun').innerHTML='<span class="ic">'+I.refresh+'</span><span>再来一次</span>';
    $('#demoState').textContent='运行完成 · 5.2s';
    toast('演示流程运行完成');
  },900+DLOG.length*820));
});

/* scroll */
var chrome=$('#chrome'),backtop=$('#backtop');
window.addEventListener('scroll',function(){
  var y=window.scrollY;
  chrome.classList.toggle('scrolled',y>24);
  backtop.classList.toggle('show',y>480);
},{passive:true});
backtop.addEventListener('click',function(){window.scrollTo({top:0,behavior:RM?'auto':'smooth'})});

/* init */
renderAvatar();
renderSidebar();
renderTabs();
chrome.classList.toggle('scrolled',window.scrollY>24);
})();
</script>
</body>
</html>
