<?php
/**
 * 课程 | OpenFlow（动态版）
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
// 页面缓存：命中直接输出（跳过登录态/爬虫），减少重复渲染
if (PageCache::begin('courses', 1800)) exit;
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
<!-- 共享外壳样式契约：必须在页面级 <style> 之前，页面样式才能覆盖模块层。
     id 与 site-shell.js 的注入判重一致，故 site-shell 不会重复插入。 -->
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260826b">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260826b">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260826b">
<style>
/* 设计 token 与外壳样式来自 tokens.css + modules.css（见 <head> 三条 link）。
   本文件的 <style> 只保留 courses 页专属的内容层样式。 */
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
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('courses'); ?>


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
  <div class="f-bottom"><span>© 2026 芭乐派 · OpenFlow 增长操作系统</span><?php if (function_exists('i18n_enabled') && i18n_enabled()): ?><?=i18n_switcher()?><?php endif; ?><span>帮一人公司设计 Agent 能跑的增长系统</span></div>
</footer>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>

<script>
/* ────────────────────────────────────────────────────────────────
 * courses.php · 页面级脚本
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
var CK='openflow-courses-v1';
var S={enrolled:[]};
try{var _s=JSON.parse(localStorage.getItem(CK)||'{}');if(Array.isArray(_s.enrolled))S.enrolled=_s.enrolled;}catch(e){}
function save(){try{localStorage.setItem(CK,JSON.stringify({enrolled:S.enrolled}))}catch(e){}}
function renderAvatar(){/* 头像由共享外壳维护 */}

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
<?php PageCache::end('courses', 1800); ?>
