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
<!-- 共享外壳样式契约：必须在页面级 <style> 之前，页面样式才能覆盖模块层。
     id 与 site-shell.js 的注入判重一致，故 site-shell 不会重复插入。 -->
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260826b">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260826b">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260826b">
<style>
/* 设计 token 与外壳样式来自 tokens.css + modules.css（见 <head> 三条 link）。
   本文件的 <style> 只保留 product 页专属的内容层样式。 */
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
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('product'); ?>


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

    <div class="sec-head"><span class="kicker">价值</span><h2>增长引擎正在产生实实在在的价值</h2><p class="note">增长引擎的示例运行输出</p></div>
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
  <div class="f-bottom"><span>© 2026 芭乐派 · OpenFlow 增长操作系统</span><?php if (function_exists('i18n_enabled') && i18n_enabled()): ?><?=i18n_switcher()?><?php endif; ?><span>帮一人公司设计 Agent 能跑的增长系统</span></div>
</footer>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>

<script>
/* ────────────────────────────────────────────────────────────────
 * product.php · 页面级脚本
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


$$('[data-act]').forEach(function(el){el.addEventListener('click',function(e){e.preventDefault();var a=el.dataset.act;
  if(a==='start'){var u=curUser();if(u){openProfile();toast('欢迎回来，'+(u.nick||u.email))}else{openAuth('register')}}
  else if(a==='join'){location.href='/community';}
  else if(a==='mail'){var t=(el.textContent||'').trim();if(t==='文档中心'){location.href='/docs';}else if(t==='模板库'){location.href='/docs#templates';}else if(t.indexOf('API')>=0){location.href='/docs#api';}else{location.href='mailto:hello@openflow.dev';}}
  else if(a==='demo'){var d=document.getElementById('demo');if(d)window.scrollTo({top:d.getBoundingClientRect().top+window.scrollY-120,behavior:RM?'auto':'smooth'});}
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
