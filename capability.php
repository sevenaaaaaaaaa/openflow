<?php
/**
 * 产品能力 | OpenFlow（动态版）
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
<?php if (function_exists('seo_head')): seo_head(['title' => '产品能力 | OpenFlow', 'canonical' => site_config_get('site_url') . '/capability']); endif; ?>
<title>能力 · TIPS 四力 | 芭乐派 · OpenFlow</title>
<meta name="description" content="Open Flow 六大核心能力：可视化编排、AI 步骤、开放连接器、可观测与告警、企业级安全、多环境部署。">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<!-- 共享外壳样式契约：必须在页面级 <style> 之前，页面样式才能覆盖模块层。
     id 与 site-shell.js 的注入判重一致，故 site-shell 不会重复插入。 -->
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260826b">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260826b">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260826b">
<style>
/* 设计 token 与外壳样式来自 tokens.css + modules.css（见 <head> 三条 link）。
   本文件的 <style> 只保留 capability 页专属的内容层样式。 */
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

/* ── layout ── */
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

/* capability */
.cap-grid{display:grid; grid-template-columns:repeat(2,1fr); gap:14px}
.cap{display:flex; gap:14px; padding:22px; text-align:left; cursor:pointer; position:relative}
.cap .ci{width:44px;height:44px; flex:0 0 44px; border-radius:13px; background:var(--accent-soft); color:var(--accent); display:grid; place-items:center}
.cap .ci svg{width:20px;height:20px}
.cap .ct{font-size:16px; font-weight:800; margin-bottom:6px}
.cap .cd{font-size:13px; color:var(--muted); line-height:1.8}
.cap .cx{margin-left:auto; width:28px;height:28px; flex:0 0 28px; border-radius:10px; background:var(--hover); display:grid; place-items:center; color:var(--muted); transition:transform .35s var(--ease-spring), background .2s, color .2s}
.cap .cx svg{width:13px;height:13px}
.cap.on{border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft)}
.cap.on .cx{transform:rotate(45deg); background:var(--accent); color:var(--on-accent)}
.cap-detail{display:grid; grid-template-rows:0fr; transition:grid-template-rows .45s var(--ease-out)}
.cap-detail.open{grid-template-rows:1fr}
.cap-detail>div{overflow:hidden}
.cap-detail .inner{border:1px solid var(--border); border-radius:var(--r-md); background:var(--surface); padding:26px; margin-top:14px}
.cap-detail .grid{grid-template-columns:repeat(2,1fr)}
.cap-detail h4{font-size:15px; font-weight:800; display:flex; align-items:center; gap:8px; margin-bottom:8px}
.cap-detail h4 .ic{width:15px;height:15px; color:var(--accent)}
.cap-detail p{font-size:13px; color:var(--muted); line-height:1.7}
.deploy-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:16px}
.deploy{display:flex; flex-direction:column; gap:12px; padding:26px 24px}
.deploy .dt{font-size:16.5px; font-weight:800; display:flex; align-items:center; gap:9px}
.deploy .dt .ic{color:var(--accent)}
.deploy .tag{font-family:var(--font-mono); font-size:10.5px; color:var(--faint); letter-spacing:.08em}
.deploy ul{list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px}
.deploy li{display:flex; gap:8px; font-size:13px; color:var(--muted); line-height:1.6}
.deploy li::before{content:''; flex:0 0 5px; width:5px; height:5px; border-radius:50%; background:var(--ok); margin-top:7px}
.eco-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:14px}
.eco{display:flex; flex-direction:column; gap:8px; padding:22px 20px}
.eco .ei{width:38px;height:38px; border-radius:12px; background:var(--hover); color:var(--fg); display:grid; place-items:center}
.eco .ei svg{width:18px;height:18px}
.eco .et{font-size:14px; font-weight:700}
.eco .ed{font-size:12px; color:var(--muted); line-height:1.6}
.conn-chips{display:flex; flex-wrap:wrap; gap:8px}
.conn-chips .cc{display:inline-flex; align-items:center; gap:8px; height:40px; padding:0 14px; border-radius:12px; background:var(--surface); border:1px solid var(--border); font-size:13px; font-weight:600}
.conn-chips .cc .cd{width:8px;height:8px;border-radius:50%; background:var(--accent); box-shadow:0 0 0 3px var(--accent-soft)}

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


#backtop{position:fixed; right:20px; bottom:22px; z-index:70; width:44px; height:44px; border-radius:14px; background:var(--surface-strong); border:1px solid var(--border); box-shadow:var(--shadow-sm); display:grid; place-items:center; color:var(--muted); opacity:0; pointer-events:none; transform:translateY(10px); transition:opacity .3s, transform .3s var(--ease-spring), background .2s, color .2s}
#backtop.show{opacity:1; pointer-events:auto; transform:none}
#backtop:hover{background:var(--hover); color:var(--fg)}
#backtop svg{width:16px;height:16px}

/* ── responsive ── 外壳降档已收进 assets/modules.css，这里只留页面栅格 ── */
@media (max-width:1080px){
  .g4,.res-grid,.prin-grid,.eco-grid{grid-template-columns:repeat(2,1fr)}
  .stats{grid-template-columns:repeat(2,1fr)}
  .hero{grid-template-columns:1fr}
  .hero-win{max-width:560px}
  .dd{grid-template-columns:1fr}
  .dd.rev .dd-vis{order:0}
  .demo-wrap{grid-template-columns:1fr}
}
@media (max-width:860px){
  .g2,.g3,.g6,.q-grid,.steps,.pain-grid,.how,.cap-grid,.path,.course-grid,.art-feat,.art-grid,.ch-grid,.deploy-grid{grid-template-columns:1fr}
  .cap-detail .grid{grid-template-columns:1fr}
  .foot{grid-template-columns:1fr 1fr}
  .win-chip{right:8px; top:-10px}
}
@media (max-width:520px){
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
<script src="/assets/seo-inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('capability'); ?>


<main id="main" data-od-id="main">

<!-- ════════════ 能力 ════════════ -->
<section class="page" id="page-capability" data-od-id="page-capability">
  <div class="pg">
    <div class="pg-h">
      <span class="kicker">能力 · TIPS 框架</span>
      <h1>一套平台，<i class="si">四种增长力</i></h1>
      <p class="lead">触达、洞察、个性化、销售——四力合一，覆盖一人公司从获客到成交的每一步。不是功能堆砌，是让 Agent 替你把每个环节跑起来。</p>
      <div class="cta-row"><button class="btn primary" data-act="start">免费开始</button><a class="btn ghost" href="/product">了解产品原理</a></div>
    </div>

    <div class="cap-grid" id="capGrid"></div>
    <div class="cap-detail" id="capDetail"><div><div class="inner" id="capInner"></div></div></div>

    <div class="sec-head"><span class="kicker">集成生态</span><h2>连接你已经在用的工具</h2><p class="note">连接器清单为演示占位，正式版将展示完整列表。</p></div>
    <div class="card" style="padding:20px"><div class="conn-chips" id="connChips2"></div></div>

    <div class="sec-head"><span class="kicker">部署方式</span><h2>从云端到私有化，按需选择</h2></div>
    <div class="deploy-grid">
      <div class="card deploy"><div class="tag">SAAS</div><div class="dt"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 8v8l9 5 9-5V8"/></svg></span>云端 SaaS</div><p style="font-size:13px;color:var(--muted);line-height:1.8">最快上手，自动更新，无需运维。适合希望一周内跑起来的一人公司。</p><ul><li>开箱即用，免费起步</li><li>功能随版本自动更新</li><li>免运维，专注增长</li></ul></div>
      <div class="card deploy"><div class="tag">PRIVATE</div><div class="dt"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>私有化部署</div><p style="font-size:13px;color:var(--muted);line-height:1.8">数据不出域，核心能力永久开源。适合重视自主可控的团队。</p><ul><li>数据完全留在内网</li><li>核心能力开源自托管</li><li>专属技术支持</li></ul></div>
      <div class="card deploy"><div class="tag">HYBRID</div><div class="dt"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.2 2.2m8.4 8.4 2.2 2.2M18.4 5.6l-2.2 2.2M7.8 16.2l-2.2 2.2"/><circle cx="12" cy="12" r="4"/></svg></span>混合架构</div><p style="font-size:13px;color:var(--muted);line-height:1.8">核心增长引擎私有化，弹性能力走云端。兼顾自主与扩展。</p><ul><li>核心引擎私有部署</li><li>云端弹性扩缩容</li><li>灰度发布与回滚</li></ul></div>
    </div>

    <div class="sec-head"><span class="kicker">开放生态</span><h2>开放，是默认值（也是芭乐派的坚持）</h2></div>
    <div class="eco-grid">
      <div class="card eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4v12a4 4 0 0 0 8 0V4M8 8h8"/></svg></div><div class="et">开放 API</div><div class="ed">完整 REST API，把 OpenFlow 嵌入你的增长系统。</div></div>
      <div class="card eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13m0 0-4-4m4 4 4-4M4 20h16"/></svg></div><div class="et">Webhook</div><div class="ed">双向触发与回调，与任意系统实时对接。</div></div>
      <div class="card eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2.5"/><path d="M4 9h16M9 4v5"/></svg></div><div class="et">Skill / 模板</div><div class="ed">社区与芭乐派模板，一键复用增长打法。</div></div>
      <div class="card eco"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4m0 12v4M2 12h4m12 0h4M5 5l3 3m8 8 3 3M19 5l-3 3M8 16l-3 3"/><circle cx="12" cy="12" r="3.5"/></svg></div><div class="et">永久开源</div><div class="ed">核心能力开源，鱼与渔相结合，策略随工具迭代。</div></div>
    </div>

    <div class="sec-head"><span class="kicker">应用场景</span><h2>四力合起来，跑出这四类增长</h2></div>
    <div class="deploy-grid">
      <div class="card deploy"><div class="dt"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.7 2.6 4 5.8 4 9s-1.3 6.4-4 9c-2.7-2.6-4-5.8-4-9s1.3-6.4 4-9Z"/></svg></span>内容获客</div><p style="font-size:13px;color:var(--muted);line-height:1.8">舆情爬取找选题 → AI 生成草稿 → 多平台分发。让内容这条线自己转起来。</p></div>
      <div class="card deploy"><div class="dt"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 7-5 5 5 5m8-10 5 5-5 5M13 4l-2 16"/></svg></span>私域转化</div><p style="font-size:13px;color:var(--muted);line-height:1.8">线索池 + 分群 + 自动化触达。把加过来的人，一步步培育成客户。</p></div>
      <div class="card deploy"><div class="dt"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18M7 14l4-4 4 3 5-6"/></svg></span>数据洞察</div><p style="font-size:13px;color:var(--muted);line-height:1.8">从几百个指标里捞出那 3-5 个。知道该优化哪一环，比优化本身更重要。</p></div>
      <div class="card deploy"><div class="dt"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 7v5c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V7l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>增长中台</div><p style="font-size:13px;color:var(--muted);line-height:1.8">把 Task Graph 变成自己的资产。Agent 跑流程，人只做 Agent 做不到的五件事。</p></div>
    </div>

    <div class="sec-head"><span class="kicker">适合谁</span><h2>如果你正处在这三种状态，能力会更快产生价值</h2><p>不用先学会所有功能。先从最卡的一环开始，再把可复用的流程逐步接回增长链路。</p></div>
    <div class="deploy-grid" data-od-id="capability-fit">
      <div class="card deploy"><div class="tag">01 · OPC</div><div class="dt">一个人做增长</div><p style="font-size:13px;color:var(--muted);line-height:1.8">选题、内容、触达和复盘都由你负责，希望把重复动作交给 Agent，把时间留给判断。</p><a class="btn subtle" href="/product#demo">看完整增长闭环 →</a></div>
      <div class="card deploy"><div class="tag">02 · SMALL TEAM</div><div class="dt">小团队协同运转</div><p style="font-size:13px;color:var(--muted);line-height:1.8">已有内容或销售流程，但数据散在多个工具里，需要统一触发、权限和交接。</p><a class="btn subtle" href="#connChips2">查看连接与部署 →</a></div>
      <div class="card deploy"><div class="tag">03 · OPERATOR</div><div class="dt">想把方法变成资产</div><p style="font-size:13px;color:var(--muted);line-height:1.8">不只想买工具，而是希望把自己的增长打法沉淀成可复制、可迭代的工作流。</p><a class="btn subtle" href="#capGrid">展开六项能力 →</a></div>
    </div>

    <div class="band" data-od-id="capability-cta">
      <span class="kicker" style="color:inherit;opacity:.75">能力在手</span>
      <h2>现在，让增长引擎替你跑起来</h2>
      <p>TIPS 四力不是宣传页上的名词——它们都能在你今天的业务里主动运行。</p>
       <div class="cta-row"><button class="btn primary" data-act="start">免费开始</button><a class="btn ghost" href="/courses">报名课程</a></div>
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
  <div class="f-bottom"><span>© 2026 芭乐派 · OpenFlow 增长操作系统</span><?php if (function_exists('i18n_enabled') && i18n_enabled()): ?><?=i18n_switcher()?><?php endif; ?><span>帮一人公司设计 Agent 能跑的增长系统</span></div>
</footer>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>

<script>
/* ────────────────────────────────────────────────────────────────
 * capability.php · 页面级脚本
 * 外壳（导航 / 侧栏 / 主题 / 命令面板 / 登录 / toast）全部由
 * assets/site-shell.js 提供，本文件不再自绘导航，也不再维护 NAV。
 * 下面的 shim 只是把页面原有调用转接到 window.OFShell。
 * ──────────────────────────────────────────────────────────────── */
(function(){
'use strict';
var $=function(s){return document.querySelector(s)};
var $$=function(s){return Array.prototype.slice.call(document.querySelectorAll(s))};
var RM=false;try{RM=matchMedia('(prefers-reduced-motion: reduce)').matches}catch(e){}
function shell(){return window.OFShell||{}}
function toast(m){var f=shell().toast;if(f)f(m)}
function curUser(){var f=shell().curUser;return f?f():null}
function openAuth(m){var f=shell().openAuth;if(f)f(m);else{var a=document.getElementById('btn-av');if(a)a.click()}}
function openProfile(){var f=shell().openProfile;if(f)f();else openAuth('login')}
function goFile(h){location.href=h}

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

/* ── connectors ── */
var CONN=['飞书','钉钉','企业微信','Slack','Notion','GitHub','Google Sheets','PostgreSQL','Webhook','OpenAPI','Salesforce','HubSpot'];
function renderConn(sel){$(sel).innerHTML='';CONN.forEach(function(c){var d=document.createElement('span');d.className='cc';d.innerHTML='<span class="cd"></span>'+c;$(sel).appendChild(d);});}
renderConn('#connChips2');

/* ── capability accordion ── */
 var CAPS=[
{icon:'bolt',t:'触达 Touch',d:'内容引擎 + 分发渠道 + 触达体系。正确的时间、渠道、内容，把信息递到用户面前。',pts:[['内容引擎','文章/课程/资料/播客一站式生产'],['分发渠道','多平台自动分发'],['触达体系','强度 × 精度 × 温度']]},
{icon:'users',t:'洞察 Insight',d:'数据、CDP、舆情、分析。从几百个指标捞出该看的那 3-5 个，把数据变成判断。',pts:[['CDP 画像','统一身份与行为追踪'],['舆情爬取','行业信号自动抓取'],['数据分析','从洞察走到策略']]},
{icon:'refresh',t:'个性化 Personality',d:'画像、分群、自动化。给对的人，在对的时刻，说对的话。',pts:[['用户分群','行为驱动动态标签'],['营销自动化','行为触发工作流'],['动态内容','千人千面触达']]},
{icon:'check',t:'销售 Sales',d:'CRM、转化、商城、订阅。从触达到成交，让支付能力流向你。',pts:[['CRM 管道','线索评分与跟进'],['转化组件','落地页/表单/CTA'],['商城订阅','付费闭环与分销']]},
{icon:'box',t:'自生长 AI Engine',d:'每 6 小时推一轮：爬取信号 → AI 洞察 → 生成草稿 → 主动转化。装完即用。',pts:[['主动爬取','舆情热点自动收集'],['AI 撰写','生成草稿待人工审核'],['主动转化','从 Marketing 到 Sales']]},
{icon:'doc',t:'永久开源',d:'核心能力永久开源，Tools 和 Strategy 双向迭代，鱼与渔相结合。',pts:[['Tools 开源','工具即渔具'],['Strategy 同步','最前沿增长策略'],['自托管','数据完全可控']]}
 ];
var capSel=-1;
function renderCaps(){
  var g=$('#capGrid');g.innerHTML='';
  CAPS.forEach(function(c,i){
    var el=document.createElement('button');el.className='card hov cap'+(i===capSel?' on':'');el.dataset.odId='cap-'+i;
    el.innerHTML='<span class="ci">'+(I[c.icon]||'')+'</span><div style="min-width:0"><div class="ct">'+c.t+'</div><div class="cd">'+c.d+'</div></div><span class="cx">'+I.plus+'</span>';
    el.addEventListener('click',function(){capSel=capSel===i?-1:i;renderCaps();renderCapDetail();});
    g.appendChild(el);
  });
}
function renderCapDetail(){
  var box=$('#capDetail'),inner=$('#capInner');
  if(capSel<0){box.classList.remove('open');inner.innerHTML='';return;}
  var c=CAPS[capSel];
  inner.innerHTML='<div class="sec-head" style="margin-bottom:18px"><span class="kicker">'+c.t+'</span><h2 style="font-size:22px">'+c.d+'</h2></div><div class="grid">'+
    c.pts.map(function(p){return '<div><h4><span class="ic">'+I.check+'</span>'+p[0]+'</h4><p>'+p[1]+'</p></div>'}).join('')+'</div>';
  box.classList.add('open');
}
renderCaps();


$$('[data-act]').forEach(function(el){el.addEventListener('click',function(e){e.preventDefault();var a=el.dataset.act;
  if(a==='start'){var u=curUser();if(u){openProfile();toast('欢迎回来，'+(u.nick||u.email))}else{openAuth('register')}}
  else if(a==='join'){location.href='/community';}
  else if(a==='mail'){var t=(el.textContent||'').trim();if(t==='文档中心'){location.href='/docs';}else if(t==='模板库'){location.href='/docs#templates';}else if(t.indexOf('API')>=0){location.href='/docs#api';}else{location.href='mailto:hello@openflow.dev';}}
  else if(a==='demo'){goFile('/product#demo');}
})});

/* ── 回到顶部（chrome 滚动胶囊由 site-shell 负责） ── */
var backtop=$('#backtop');
if(backtop){
  window.addEventListener('scroll',function(){backtop.classList.toggle('show',window.scrollY>480)},{passive:true});
  backtop.addEventListener('click',function(){window.scrollTo({top:0,behavior:RM?'auto':'smooth'})});
  backtop.classList.toggle('show',window.scrollY>480);
}
})();
</script>
</body>
</html>
