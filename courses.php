<?php
/**
 * 课程 | OpenFlow（动态版）
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
<?php if (function_exists('seo_head')): seo_head(['title' => '课程 | OpenFlow', 'canonical' => site_config_get('site_url') . '/courses']); endif; ?>
<title>课程 · New-1~4 + R.B.E 训练营 | 芭乐派</title>
<meta name="description" content="芭乐派 R.B.E 训练营：New-1~4 基石课 + 八周系统设计营，用 OpenFlow 设计 Agent 能跑的增长系统，让方法论边学边用。">
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
.chips{display:flex; gap:8px; flex-wrap:wrap}
.chip{height:38px; padding:0 14px; border-radius:99px; border:1px solid var(--border); background:var(--glass); color:var(--muted); font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; transition:background .2s,color .2s,border-color .2s}
.chip:hover{background:var(--hover); color:var(--fg)}
.chip.on{background:var(--accent-soft); border-color:var(--accent); color:var(--accent)}

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

/* courses */
.path{display:grid; grid-template-columns:repeat(3,1fr); gap:16px; align-items:stretch}
.path .card{position:relative; display:flex; flex-direction:column; gap:10px; padding:26px 24px}
.path .pl{font-family:var(--font-mono); font-size:11px; font-weight:800; letter-spacing:.14em; color:var(--accent)}
.path h3{font-size:17px; font-weight:800}
.path p{font-size:13px; color:var(--muted); line-height:1.8}
.path ul{list-style:none; margin:6px 0 0; padding:0; display:flex; flex-direction:column; gap:7px}
.path li{display:flex; gap:8px; font-size:12.5px; line-height:1.55; color:var(--fg)}
.path li::before{content:''; flex:0 0 5px; width:5px; height:5px; border-radius:50%; background:var(--accent); margin-top:6px}
.course-grid{display:grid; grid-template-columns:repeat(2,1fr); gap:16px}
.course{border-radius:var(--r-md); background:var(--surface); border:1px solid var(--border); overflow:hidden; -webkit-backdrop-filter:blur(16px) saturate(150%); backdrop-filter:blur(16px) saturate(150%); transition:border-color .25s, box-shadow .25s}
.course:hover{border-color:var(--border-strong); box-shadow:var(--shadow-sm)}
.course .c-top{padding:24px 24px 6px; display:flex; flex-direction:column; gap:10px; cursor:pointer}
.course .c-meta{display:flex; gap:8px; align-items:center; font-family:var(--font-mono); font-size:11px; color:var(--faint)}
.course h3{font-size:17.5px; font-weight:800}
.course .c-d{font-size:13px; color:var(--muted); line-height:1.8}
.course .c-acc{display:grid; grid-template-rows:0fr; transition:grid-template-rows .4s var(--ease-out)}
.course.open .c-acc{grid-template-rows:1fr}
.course .c-acc>div{overflow:hidden}
.course .c-inner{padding:4px 24px 20px}
.course .c-inner ul{list-style:none; margin:0 0 14px; padding:0; display:flex; flex-direction:column; gap:7px}
.course .c-inner li{display:flex; gap:8px; font-size:12.5px; color:var(--fg); line-height:1.55}
.course .c-inner li::before{content:''; flex:0 0 5px; width:5px; height:5px; border-radius:50%; background:var(--ok); margin-top:6px}
.c-foot{display:flex; align-items:center; justify-content:space-between; gap:10px; padding:16px 24px; border-top:1px solid var(--border); background:var(--hover)}
.c-foot .st{font-size:12px; color:var(--faint); font-weight:600}
.res-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:14px}
.eco{display:flex; flex-direction:column; gap:8px; padding:22px 20px}
.eco .ei{width:38px;height:38px; border-radius:12px; background:var(--hover); color:var(--fg); display:grid; place-items:center}
.eco .ei svg{width:18px;height:18px}
.eco .et{font-size:14px; font-weight:700}
.eco .ed{font-size:12px; color:var(--muted); line-height:1.6}
.ch-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:14px}
.persona{display:flex; flex-direction:column; gap:10px; padding:24px}
.persona .ph{font-size:15px; font-weight:800; display:flex; align-items:center; gap:9px}
.persona .ph .ic{color:var(--accent)}
.persona p{font-size:13px; color:var(--muted); line-height:1.8}

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
<script src="/assets/seo-inject.js?v=20260813ad" data-page="courses"></script>
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

<!-- ════════════ 课程 ════════════ -->
<section class="page" id="page-courses" data-od-id="page-courses">
  <div class="pg">
    <div class="pg-h">
      <span class="kicker">芭乐派 · R.B.E 训练营</span>
      <h1>用 OpenFlow，<i class="si">设计 Agent 能跑的增长系统</i></h1>
      <p class="lead">学完 New-1~4，你会知道业务里哪里该让 Agent 做；走完 R.B.E 训练营，你会画出自己专属的 Task Graph。理论（芭乐派方法论）→ 工具（OpenFlow）→ 落地（Agent 增长引擎），边学边用。</p>
      <div class="cta-row"><button class="btn primary" data-act="start">免费开始学习</button><a class="btn ghost" href="/community">进入门派</a></div>
    </div>

    <div class="sec-head"><span class="kicker">课程体系</span><h2>一条主线，从方法论到增长引擎</h2></div>
    <div class="path" id="coursePath"></div>

    <div class="sec-head" style="margin-top:56px"><span class="kicker">课程目录</span><h2>选择你的学习路径</h2></div>
    <div class="chips" id="courseChips" style="margin-bottom:18px"></div>
    <div class="course-grid" id="courseGrid"></div>

    <div class="sec-head"><span class="kicker">免费资源</span><h2>正式课程之外，先从这里开始</h2></div>
    <div class="res-grid">
      <div class="card hov eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h10l4 4v14H7V3Z"/><path d="M17 3v4h4"/></svg></div><div class="et">New-1~4 基石课</div><div class="ed">一人公司冷启动 / 增长模型 / 精算体系 / Agent 知识管理。</div></div>
      <div class="card hov eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2.5"/><path d="M3 9h18M9 3v18"/></svg></div><div class="et">利润公式计算器</div><div class="ed">把销转率杠杆算明白，看哪些环节 Agent 化收益最高。</div></div>
      <div class="card hov eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5.5v13l11-6.5-11-6.5Z"/></svg></div><div class="et">视频实操</div><div class="ed">用 OpenFlow 跑增长闭环的实操演示，跟着做一遍就会。</div></div>
      <div class="card hov eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.5c2.8.3 5 2.6 5 5.5"/></svg></div><div class="et">门派社区</div><div class="ed">卡住了？提问，热心成员与官方都会回答。</div></div>
    </div>

    <div class="sec-head"><span class="kicker">适合谁</span><h2>这套课程为谁而设</h2></div>
    <div class="ch-grid">
      <div class="card persona"><div class="ph"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.7 2.6 4 5.8 4 9s-1.3 6.4-4 9c-2.7-2.6-4-5.8-4-9s1.3-6.4 4-9Z"/></svg></span>一人公司创始人</div><p>年营收 100-1000 万，增长失速。知道 AI 重要，但不知道业务里哪里该让 Agent 做。</p></div>
      <div class="card persona"><div class="ph"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 7-5 5 5 5m8-10 5 5-5 5M13 4l-2 16"/></svg></span>超级个体 / 运营者</div><p>用 OpenFlow 设计自己的增长系统：内容、获客、转化全闭环，不再靠手动堆时间。</p></div>
      <div class="card persona"><div class="ph"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 7v5c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V7l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>开发者 / Agent 工程师</div><p>想用 Task Graph 把增长漏斗拆成 Agent 可执行的任务图，打造可落地的 Agent 系统。</p></div>
    </div>

    <div class="sec-head"><span class="kicker">学完你能拿到</span><h2>不是听完就忘，是带走一份可跑的系统</h2></div>
    <div class="res-grid">
      <div class="card hov eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/></svg></div><div class="et">你的利润公式</div><div class="ed">算出销转率杠杆，知道该先优化哪个环节。</div></div>
      <div class="card hov eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14m6-12v14"/></svg></div><div class="et">你的 Task Graph</div><div class="ed">把增长漏斗拆成 Agent 可执行的任务图。</div></div>
      <div class="card hov eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></div><div class="et">增长模型白皮书</div><div class="ed">R.B.E 毕业交付，专属你的增长系统蓝图。</div></div>
      <div class="card hov eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px"><path d="M7 11V6a1.5 1.5 0 0 1 3 0v4m0-5.5V5a1.5 1.5 0 0 1 3 0v4m0-4.5A1.5 1.5 0 0 1 16 5v4m0-3.5a1.5 1.5 0 0 1 3 0V14a6 6 0 0 1-6 6h-1.5a6 6 0 0 1-4.7-2.3L4 13.5a1.6 1.6 0 0 1 2.4-2.1L8 13V8a1.5 1.5 0 0 1 3 0"/></svg></div><div class="et">门派入场券</div><div class="ed">毕业进门派，和同行切磋、交换案例。</div></div>
    </div>

    <div class="sec-head"><span class="kicker">学员怎么说</span><h2>他们用这套方法，跑出了结果</h2></div>
    <div class="ch-grid">
      <div class="card persona"><p style="font-size:14px;line-height:1.75">「利润公式那节课点醒我：我一直优化流量，其实该优化的是销转率。调整后两个月营收涨了 60%。」</p><div class="ph"><span class="ic" style="font-weight:800;font-size:13px">林</span>林晓 · 知识付费</div></div>
      <div class="card persona"><p style="font-size:14px;line-height:1.75">「Task Graph 是让我最值回票价的部分。以前不知道哪些活儿该外包给 AI，现在每个环节都标得清清楚楚。」</p><div class="ph"><span class="ic" style="font-weight:800;font-size:13px">陈</span>陈默 · 内容工作室</div></div>
      <div class="card persona"><p style="font-size:14px;line-height:1.75">「8 周走完，最意外的收获是白皮书。它不只是一份文档，是我整个业务的地图，团队现在照着它跑。」</p><div class="ph"><span class="ic" style="font-weight:800;font-size:13px">王</span>王珩 · SaaS 团队</div></div>
    </div>

    <div class="band" data-od-id="courses-cta">
      <span class="kicker" style="color:inherit;opacity:.75">开始学习</span>
      <h2>今天加入芭乐派，明天设计你的增长系统</h2>
      <p>New-1~4 免费开放，R.B.E 训练营带你 8 周设计出专属的 Agent-Native 增长模型。</p>
      <div class="cta-row"><button class="btn primary" data-act="start">免费开始学习</button><a class="btn ghost" href="/academy">去学院学习</a></div>
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
var PAGE='courses';

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
      else if(f.act==='demo')goFile('/product#demo');
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
    else if(it.id==='__demo')goFile('/product#demo');
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
  else if(a==='demo'){goFile('/product#demo');}
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

/* ── courses: paths ── */
var PATH=[{lv:'基石',t:'New-1~4 入门课',h:'免费 · 4 节',d:'用 OpenFlow 理解一人公司增长：冷启动 / 增长模型 / 精算体系 / Agent 知识管理。',pts:['一人公司冷启动诀窍','核心增长模型','AI 精算体系','Agent 知识管理']},
{lv:'方法',t:'芭乐派方法论',h:'R.B.E 前四模块',d:'利润公式、四引擎、DIKW 洞察、触达体系——理解增长系统的底层逻辑。',pts:['Agent-Native 利润公式','四引擎模型','DIKW 数据洞察','触达体系']},
{lv:'训练营',t:'R.B.E 系统设计营',h:'8 周 · ¥9,999',d:'M0-M8 九模块，用 OpenFlow 画出你的 Task Graph，产出专属增长模型白皮书。',pts:['O.L.B 诊断','Task Graph 设计','增长模型白皮书','毕业后进门派']}];
$('#coursePath').innerHTML='';
PATH.forEach(function(p,i){var el=document.createElement('div');el.className='card';
  el.innerHTML=(i<2?'<span class="sline" style="position:absolute;top:34px;right:-8px;width:16px;height:1px;background:var(--border-strong)"></span>':'')+
  '<div class="pl">'+p.lv+'</div><h3>'+p.t+'</h3><p>'+p.h+' · '+p.d+'</p><ul>'+p.pts.map(function(x){return '<li>'+x+'</li>'}).join('')+'</ul>';
  $('#coursePath').appendChild(el);});

/* ── courses: catalog ── */
var COURSES=[
{id:'c1',lv:'基石',t:'New-1 · 一人公司冷启动',meta:'免费 · 以 OpenFlow 演示',d:'AI 红利出现，人人都是 CEO。用 OpenFlow 从 0 搭建一人公司的增长起点。',out:['一人公司冷启动诀窍','用 OpenFlow 搭增长起点','内容获客链路','转化基础']},
{id:'c2',lv:'基石',t:'New-2 · 核心增长模型',meta:'免费 · 以 OpenFlow 演示',d:'加速主义来袭，一人公司最重要是核心增长模型。',out:['增长模型拆解','利润公式入门','用 OpenFlow 跑模型','指标看板']},
{id:'c3',lv:'基石',t:'New-3 · AI 精算体系',meta:'免费 · 以 OpenFlow 演示',d:'AI 是灵药也是毒药，超级个体必须打造精算体系，量化增长。',out:['精算体系','量化你的增长','OpenFlow 数据分析','决策仪表盘']},
{id:'c4',lv:'基石',t:'New-4 · Agent 知识管理',meta:'免费 · 以 OpenFlow 演示',d:'超频你的增长，手把手教你 AI Agent 时代的知识管理。',out:['知识管理方法论','喂给 Agent 的认知资产','知识库接入','内容资产化']},
{id:'c5',lv:'训练营',t:'R.B.E · 利润公式 + 四引擎',meta:'训练营 M1-M2',d:'销转率是杠杆支点，四引擎同时驱动。人只做 Agent 做不到的五件事。',out:['Agent-Native 利润公式','四引擎模型','五个不可替代角色','Agent 化决策']},
{id:'c6',lv:'训练营',t:'R.B.E · Agent 系统设计',meta:'训练营 M7 · 核心',d:'把增长漏斗拆成 Agent 可执行的 Task Graph，用五维判据标执行主体。',out:['漏斗→Task Graph','原子性/可观测/可中断','五维判据 D1-D5','用 OpenFlow 落地']}];
var courseFilter='全部';
function renderCourseChips(){
  var chips=['全部','基石','训练营'];
  $('#courseChips').innerHTML='';
  chips.forEach(function(c){var el=document.createElement('button');el.className='chip'+(c===courseFilter?' on':'');el.textContent=c;
    el.addEventListener('click',function(){courseFilter=c;renderCourseChips();renderCourses();});$('#courseChips').appendChild(el);});
}
function renderCourses(){
  var list=COURSES.filter(function(c){return courseFilter==='全部'||c.lv===courseFilter});
  var g=$('#courseGrid');g.innerHTML='';
  list.forEach(function(c){
    var enrolled=S.enrolled.indexOf(c.id)>-1;
    var el=document.createElement('div');el.className='course';el.dataset.odId='course-'+c.id;
    el.innerHTML='<div class="c-top"><div class="c-meta"><span class="pill '+(c.lv==='入门'?'ok':c.lv==='进阶'?'warn':'neu')+'">'+c.lv+'</span><span>'+c.meta+'</span><span>'+c.id+'</span></div><h3>'+c.t+'</h3><p class="c-d">'+c.d+'</p></div>'+
      '<div class="c-acc"><div><div class="c-inner"><ul>'+c.out.map(function(o){return '<li>'+o+'</li>'}).join('')+'</ul>'+
      '<button class="btn '+(enrolled?'ghost':'primary')+' sm" data-enroll="'+c.id+'">'+(enrolled?'已加入 ✓':'加入学习')+'</button></div></div></div>'+
      '<div class="c-foot"><span class="st">'+(enrolled?'已加入该课程':'点击卡片查看大纲')+'</span><button class="btn ghost sm" data-open="'+c.id+'">查看大纲</button></div>';
    el.querySelector('[data-open="'+c.id+'"]').addEventListener('click',function(){el.classList.toggle('open')});
    el.querySelector('[data-enroll="'+c.id+'"]').addEventListener('click',function(){
      var i=S.enrolled.indexOf(c.id);
      if(i>-1){S.enrolled.splice(i,1);toast('已取消加入「'+c.t+'」');}
      else{S.enrolled.push(c.id);toast('已加入「'+c.t+'」，开始学习吧');}
      save();renderCourses();renderAvatar();
    });
    g.appendChild(el);
  });
  if(!list.length)g.innerHTML='<p class="note">该级别暂无课程</p>';
}
renderCourseChips();renderCourses();

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
