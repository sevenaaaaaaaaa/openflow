<?php
/**
 * 关于我们 | OpenFlow（动态版）
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
<?php if (function_exists('seo_head')): seo_head(['title' => '关于我们 | OpenFlow', 'canonical' => site_config_get('site_url') . '/about']); endif; ?>
<title>关于我们 · 芭乐派门派 | OpenFlow</title>
<meta name="description" content="Open Flow 的使命、原则与时间线，以及加入我们的方式。">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<!-- 共享外壳样式契约：必须在页面级 <style> 之前，页面样式才能覆盖模块层。
     id 与 site-shell.js 的注入判重一致，故 site-shell 不会重复插入。 -->
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260826b">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260826b">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260826b">
<style>
/* 设计 token 与外壳样式来自 tokens.css + modules.css（见 <head> 中的三条 link）。
   本文件的 <style> 只保留 about 页专属的内容层样式。 */
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

/* about */
.dd{display:grid; grid-template-columns:1fr 1fr; gap:clamp(20px,4vw,48px); align-items:center; padding:clamp(20px,3.5vw,44px) 0}
.dd-copy{display:flex; flex-direction:column; gap:12px}
.dd-copy h3{font-size:clamp(20px,2.4vw,26px); font-weight:800; letter-spacing:-.015em}
.dd-copy p{color:var(--muted); font-size:14px; line-height:1.75}
.dd-vis{min-width:0}
.dd-frame{background:var(--glass); border:1px solid var(--border); border-radius:var(--r-md); box-shadow:var(--shadow-sm); padding:14px; -webkit-backdrop-filter:blur(16px) saturate(150%); backdrop-filter:blur(16px) saturate(150%)}
.stats{display:grid; grid-template-columns:repeat(4,1fr); gap:14px}
.stat{text-align:center; padding:26px 18px}
.stat .sv{font-size:clamp(26px,3vw,38px); font-weight:800; letter-spacing:-.03em; background:linear-gradient(120deg,var(--accent),oklch(58% .16 295)); -webkit-background-clip:text; background-clip:text; color:transparent}
.stat .sl{font-size:12.5px; color:var(--muted); margin-top:6px; line-height:1.6}
.prin-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:14px}
.prin{display:flex; flex-direction:column; gap:9px; padding:22px 20px}
.prin .pn{font-family:var(--font-mono); font-size:11px; font-weight:800; color:var(--accent); letter-spacing:.1em}
.prin h3{font-size:15px; font-weight:800}
.prin p{font-size:12.5px; color:var(--muted); line-height:1.8}
.timeline{position:relative; display:flex; flex-direction:column; gap:0; padding-left:26px}
.timeline::before{content:''; position:absolute; left:7px; top:8px; bottom:8px; width:2px; background:var(--border-strong); border-radius:2px}
.tl{position:relative; padding:0 0 28px 18px}
.tl::before{content:''; position:absolute; left:-23px; top:6px; width:12px; height:12px; border-radius:50%; background:var(--accent); box-shadow:0 0 0 4px var(--accent-soft)}
.tl .ty{font-family:var(--font-mono); font-size:11px; font-weight:800; color:var(--accent); letter-spacing:.06em}
.tl h4{font-size:15.5px; font-weight:800; margin:5px 0 6px}
.tl p{font-size:13px; color:var(--muted); line-height:1.8; max-width:520px}
.deploy-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:16px}
.deploy{display:flex; flex-direction:column; gap:12px; padding:26px 24px}
.deploy .dt{font-size:16.5px; font-weight:800; display:flex; align-items:center; gap:9px}
.deploy .dt .ic{color:var(--accent)}

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

/* ── responsive ──
   外壳（chrome / tabs / sidebar / drop）的降档已全部收进 assets/modules.css，
   这里只留 about 页自己的栅格降档。 */
@media (max-width:1080px){
  .g4,.res-grid,.prin-grid,.eco-grid{grid-template-columns:repeat(2,1fr)}
  .stats{grid-template-columns:repeat(2,1fr)}
  .dd{grid-template-columns:1fr}
  .dd.rev .dd-vis{order:0}
}
@media (max-width:960px){
  .g2,.g3,.g6,.deploy-grid{grid-template-columns:1fr}
  .foot{grid-template-columns:1fr 1fr}
}
@media (max-width:520px){
  .foot{grid-template-columns:1fr}
  .stats{grid-template-columns:1fr 1fr}
  .pg-h h1{font-size:30px}
  .band{padding:28px 22px}
}
@media (prefers-reduced-motion: reduce), html.rm{
  *,*::before,*::after{animation-duration:.01ms!important; animation-iteration-count:1!important; transition-duration:.01ms!important}
  html{scroll-behavior:auto}
}
</style>
<script src="/assets/seo-inject.js?v=20260830a" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('about'); ?>

<main id="main" data-od-id="main">

<!-- ════════════ 关于我们 ════════════ -->
<section class="page" id="page-about" data-od-id="page-about">
  <div class="pg">
    <div class="pg-h">
      <span class="kicker">关于芭乐派</span>
      <h1>帮一人公司，<i class="si">设计 Agent 能跑的增长系统</i></h1>
      <p class="lead">芭乐派是主品牌，OpenFlow 是它的开源平台。我们的信念很朴素：你不缺"怎么做"的工具，你缺的是"该做什么"的系统——设计你的系统，而不是操作你的系统。</p>
      <div class="cta-row"><button class="btn primary" data-act="join">加入门派</button><a class="btn ghost" href="/product">看看平台</a></div>
    </div>

    <div class="dd" style="align-items:start">
      <div class="dd-copy">
        <span class="kicker">品牌故事</span>
        <h3 style="margin-top:10px">芭乐，与派</h3>
        <p>芭乐，番石榴。长得不起眼，不像草莓好看，不像芒果浓郁。但切开之后香气独特，维生素 C 是橙子的 4 倍。一人公司也是这样——看起来小，但内核密度极高。</p>
        <p>派，有三层意思。第一层，致敬树莓派：一张信用卡大小的开发板，插上外设就是一个完整系统，一人公司不需要大团队。第二层，是 π——3.14159… 无限不循环，没有两个创业者走出过完全一样的路。第三层，是门派：一个人走得快，一群人走得远。</p>
      </div>
      <div class="dd-vis"><div class="dd-frame"><div class="stats" style="grid-template-columns:repeat(2,1fr);gap:10px">
        <div class="card stat" style="padding:18px 12px"><div class="sv" style="font-size:24px">10年</div><div class="sl">增长操盘</div></div>
        <div class="card stat" style="padding:18px 12px"><div class="sv" style="font-size:24px">7</div><div class="sl">跨行业覆盖</div></div>
        <div class="card stat" style="padding:18px 12px"><div class="sv" style="font-size:24px">50+</div><div class="sl">方法论落地</div></div>
        <div class="card stat" style="padding:18px 12px"><div class="sv" style="font-size:24px">1套</div><div class="sl">Agent 增长系统</div></div>
      </div><p class="note" style="margin-top:10px">创始人Seven · 十年增长操盘</p></div></div>
    </div>

    <div class="sec-head"><span class="kicker">创始人</span><h2>Seven：十年增长操盘手</h2></div>
    <div class="dd rev" style="align-items:start">
      <div class="dd-copy">
        <p style="line-height:1.8;color:var(--muted)">我不是教增长理论的讲师，是操盘过增长的人。芭乐派的内容不是从书里摘的——是从十年、七个行业的操盘经历里提炼的。</p>
        <ul style="line-height:2;color:var(--muted);font-size:14px">
          <li>内容增长：把搜索流量占比做到七成，靠内容持续获客</li>
          <li>组织效率：把大团队重构为精干小队，人效不降反升</li>
          <li>私域获客：把获客成本降到原来的五分之一</li>
          <li>增长体系：把方法论落地到 50+ 团队，可复制、可验证</li>
        </ul>
      </div>
      <div class="dd-vis"><div class="dd-frame"><div class="run-list">
        <div class="run-row"><span class="rn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px"><path d="M3 21h18M5 21V5l7-2v18M12 21V9l7 2v10M9 7h.01M9 11h.01M9 15h.01"/></svg></span><span class="rt">内容增长</span><span class="pill ok rs">流量七成</span></div>
        <div class="run-row"><span class="rn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></svg></span><span class="rt">组织效率</span><span class="pill ok rs">人效提升</span></div>
        <div class="run-row"><span class="rn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px"><path d="M12 2a10 10 0 0 0 0 20c1.5 0 2-.8 2-1.8 0-1-.6-1.7-.6-2.7 0-1.2 1-2 2.2-2H18a4 4 0 0 0 4-4c0-5-4.5-9.5-10-9.5Z"/><circle cx="7.5" cy="10.5" r="1.2"/><circle cx="10.5" cy="6.8" r="1.2"/><circle cx="14.5" cy="6.8" r="1.2"/></svg></span><span class="rt">私域获客</span><span class="pill ok rs">成本-80%</span></div>
        <div class="run-row"><span class="rn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg></span><span class="rt">增长体系</span><span class="pill ok rs">50+落地</span></div>
      </div></div></div>
    </div>

    <div class="sec-head"><span class="kicker">主张</span><h2>我们相信的四件事</h2></div>
    <div class="prin-grid" id="prinGrid"></div>

    <div class="sec-head"><span class="kicker">思想源流</span><h2>塑造芭乐派的底层逻辑</h2></div>
    <div class="prin-grid" id="thinkGrid"></div>

    <div class="sec-head"><span class="kicker">历程</span><h2>走到今天</h2></div>
    <div class="card" style="padding:30px"><div class="timeline" id="timeline"></div></div>

    <div class="sec-head"><span class="kicker">加入门派</span><h2>一起把增长系统做得更好</h2><p>无论你是正在从 0 到 1 死磕的一人公司，还是想和 Agent 时代一起成长的创业者——这里都有你的位置。</p></div>
    <div class="deploy-grid">
      <div class="card deploy"><div class="dt"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12h8m-8 4h5M9 4h6a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/></svg></span>商务合作</div><p style="font-size:13px;color:var(--muted);line-height:1.8">企业采购、私有化部署、渠道合作，请联系商务团队。</p><button class="btn ghost sm" style="margin-top:auto" data-act="mail">hello@openflow.dev</button></div>
      <div class="card deploy"><div class="dt"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.5c2.8.3 5 2.6 5 5.5"/></svg></span>加入团队</div><p style="font-size:13px;color:var(--muted);line-height:1.8">开放岗位与内推渠道，简历直达团队。</p><button class="btn ghost sm" style="margin-top:auto" data-act="mail">careers@openflow.dev</button></div>
      <div class="card deploy"><div class="dt"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 7v5c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V7l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>媒体与社区</div><p style="font-size:13px;color:var(--muted);line-height:1.8">报道、合作与内容共创，欢迎来信聊聊。</p><button class="btn ghost sm" style="margin-top:auto" data-act="mail">community@openflow.dev</button></div>
    </div>

    <div class="band" data-od-id="about-cta">
      <span class="kicker" style="color:inherit;opacity:.75">下一步</span>
      <h2>从了解我们，到跑出你的增长系统</h2>
      <p>产品、课程、社区——三条路，都通向同一个地方：不再被重复消耗，让 Agent 替你驱动增长。</p>
      <div class="cta-row"><a class="btn primary" href="/courses">开始学习</a><a class="btn ghost" href="/product">看看产品</a><a class="btn ghost" href="/community">进入门派社区</a></div>
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
 * about.php · 页面级脚本
 * 外壳（导航 / 侧栏 / 主题 / 命令面板 / 登录 / toast）全部由
 * assets/site-shell.js 提供，本文件不再自绘任何导航，也不再维护 NAV。
 * 需要调用外壳能力时走 window.OFShell（toast / openAuth / openProfile …）。
 * ──────────────────────────────────────────────────────────────── */
(function(){
'use strict';
var $=function(s){return document.querySelector(s)};
var $$=function(s){return Array.prototype.slice.call(document.querySelectorAll(s))};
var RM=false;try{RM=matchMedia('(prefers-reduced-motion: reduce)').matches}catch(e){}
function shell(){return window.OFShell||{}}
function toast(m){var t=shell().toast;if(t)t(m);}

/* ── about: principles & timeline ── */
var PRINS=[['01','设计系统，不操作系统','你不缺怎么做，你缺该做什么。工具解决怎么做，系统解决该做什么。'],['02','Agent 能跑，人做判断','把规则明确的交给 Agent，把判断留给人——人只做 Agent 做不到的五件事。'],['03','核心能力永久开源','Tools 和 Strategy 双向迭代，鱼与渔相结合，让用户既用得上工具也用得上策略。'],['04','自生长，不是被操作','每个人安装后都能快速改造成专属自己的增长引擎，从 Marketing 到 Sales 主动驱动。']];
$('#prinGrid').innerHTML='';
PRINS.forEach(function(p){var el=document.createElement('div');el.className='card prin';el.innerHTML='<div class="pn">'+p[0]+'</div><h3>'+p[1]+'</h3><p>'+p[2]+'</p>';$('#prinGrid').appendChild(el);});
var THINKS=[["<svg width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'><path d='M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z'/><path d='M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4'/></svg>",'判断力来自失败','乔布斯被赶出公司、NeXT 失败、Pixar 生死——失败训练判断力。你的每次失败都在训练，不是浪费时间。'],["<svg width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'><path d='M3 22h18'/><path d='M6 18v-7M10 18v-7M14 18v-7M18 18v-7'/><path d='M4 11 12 4l8 7'/></svg>",'宏观设计系统','商业模式全史：直销→平台→订阅→免费增值，每次演进都是对支付能力富集效率的一次优化。'],["<svg width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'><circle cx='11' cy='11' r='7'/><path d='m20 20-3.4-3.4'/></svg>",'微观执行触达','顾客为什么买：一个人怎么走、怎么看、为什么拿起又放下。宏观设计系统，微观执行触达。'],["<svg width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'><rect x='5' y='3' width='14' height='18' rx='2'/><path d='M8 7h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 19h.01M12 19h.01M16 19h.01'/></svg>",'利润公式与销转率','销转率是唯一的小数因子。在乘式中，小数变动一个数量级，结果就塌缩或爆炸。']];
$('#thinkGrid').innerHTML='';
THINKS.forEach(function(p){var el=document.createElement('div');el.className='card prin';el.innerHTML='<div class="pn" style="font-size:22px">'+p[0]+'</div><h3>'+p[1]+'</h3><p>'+p[2]+'</p>';$('#thinkGrid').appendChild(el);});
var TL=[['2015-2025','十年增长操盘','横跨快消/SaaS/教育/3C/跨境/金融科技/AI 产品 7 行业，从 400 人团队到 AI 产品操盘。'],['2026','芭乐派成立','帮一人公司设计 Agent 能跑的增长系统，把十年操盘提炼成方法论。'],['2026','OpenFlow 开源','芭乐派增长操作系统的开源底座，TIPS 框架四力合一。'],['现在','自生长引擎上线','主动爬取、主动洞察、主动转化——每个人都能长出专属增长引擎。']];
$('#timeline').innerHTML='';
TL.forEach(function(t){var el=document.createElement('div');el.className='tl';el.innerHTML='<div class="ty">'+t[0]+'</div><h4>'+t[1]+'</h4><p>'+t[2]+'</p>';$('#timeline').appendChild(el);});

/* ── 页面 CTA：账户相关一律转交共享外壳 ── */
$$('[data-act]').forEach(function(el){
  el.addEventListener('click',function(e){
    var a=el.dataset.act;
    if(a==='start'){
      e.preventDefault();
      var s=shell();
      if(s.openProfile){s.openProfile();}
      else{var av=document.getElementById('btn-av');if(av)av.click();}
      var u=s.curUser&&s.curUser();
      if(u)toast('欢迎回来，'+(u.nick||u.email));
    }
    else if(a==='join'){e.preventDefault();location.href='/community';}
    else if(a==='demo'){e.preventDefault();location.href='/product#demo';}
    else if(a==='mail'){
      e.preventDefault();
      var t=(el.textContent||'').trim();
      if(t==='文档中心')location.href='/docs';
      else if(t==='模板库')location.href='/docs#templates';
      else if(t.indexOf('API')>=0)location.href='/docs#api';
      else location.href='mailto:hello@openflow.dev';
    }
  });
});

/* ── 回到顶部（chrome 的滚动胶囊由 site-shell 负责） ── */
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
