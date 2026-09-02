<?php
/**
 * 课程 | 芭乐派 · OpenFlow（动态版）
 *
 * v7（2026-09-01）：换骨架不换文案。模块全部来自 assets/modules.css 的共享 archetype；
 * 本页 <style> 只保留课程卡（可展开大纲 + 加入学习）这一件独有部件。
 * 课程体系 / 课程目录原由 JS 渲染 → 服务端直出；筛选、展开、加入仍由页面脚本处理。文案与 v6 逐字相同。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
// 页面缓存：命中直接输出（跳过登录态/爬虫），减少重复渲染
if (PageCache::begin('courses', 1800)) exit;
$siteName = site_config_get('site_name', 'OpenFlow');
header('Cache-Control: no-cache, max-age=0');

$PATH = [
  ['基石','New-1~4 入门课','免费 · 4 节','用 OpenFlow 理解一人公司增长：冷启动 / 增长模型 / 精算体系 / Agent 知识管理。',['一人公司冷启动诀窍','核心增长模型','AI 精算体系','Agent 知识管理']],
  ['方法','芭乐派方法论','R.B.E 前四模块','利润公式、四引擎、DIKW 洞察、触达体系——理解增长系统的底层逻辑。',['Agent-Native 利润公式','四引擎模型','DIKW 数据洞察','触达体系']],
  ['训练营','R.B.E 系统设计营','8 周 · ¥9,999','M0-M8 九模块，用 OpenFlow 画出你的 Task Graph，产出专属增长模型白皮书。',['O.L.B 诊断','Task Graph 设计','增长模型白皮书','毕业后进门派']],
];
$COURSES = [
  ['c1','基石','New-1 · 一人公司冷启动','免费 · 以 OpenFlow 演示','AI 红利出现，人人都是 CEO。用 OpenFlow 从 0 搭建一人公司的增长起点。',['一人公司冷启动诀窍','用 OpenFlow 搭增长起点','内容获客链路','转化基础']],
  ['c2','基石','New-2 · 核心增长模型','免费 · 以 OpenFlow 演示','加速主义来袭，一人公司最重要是核心增长模型。',['增长模型拆解','利润公式入门','用 OpenFlow 跑模型','指标看板']],
  ['c3','基石','New-3 · AI 精算体系','免费 · 以 OpenFlow 演示','AI 是灵药也是毒药，超级个体必须打造精算体系，量化增长。',['精算体系','量化你的增长','OpenFlow 数据分析','决策仪表盘']],
  ['c4','基石','New-4 · Agent 知识管理','免费 · 以 OpenFlow 演示','超频你的增长，手把手教你 AI Agent 时代的知识管理。',['知识管理方法论','喂给 Agent 的认知资产','知识库接入','内容资产化']],
  ['c5','训练营','R.B.E · 利润公式 + 四引擎','训练营 M1-M2','销转率是杠杆支点，四引擎同时驱动。人只做 Agent 做不到的五件事。',['Agent-Native 利润公式','四引擎模型','五个不可替代角色','Agent 化决策']],
  ['c6','训练营','R.B.E · Agent 系统设计','训练营 M7 · 核心','把增长漏斗拆成 Agent 可执行的 Task Graph，用五维判据标执行主体。',['漏斗→Task Graph','原子性/可观测/可中断','五维判据 D1-D5','用 OpenFlow 落地']],
];
$ck = '<span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span>';
$plus = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>';
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
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260902a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260902a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260902a">
<style>
/* 课程页独有：课程卡（大纲可展开 · 加入学习）。其余全部来自 modules.css。 */
.course{display:flex;flex-direction:column;padding:0;overflow:hidden}
.course[hidden]{display:none}
.c-top{padding:30px 32px 10px;display:flex;flex-direction:column;gap:10px;cursor:pointer}
.c-meta{display:flex;gap:10px;align-items:center;flex-wrap:wrap;font-family:var(--font-mono);font-size:11.5px;color:var(--faint)}
.c-meta .badge{height:24px;font-size:11.5px}
.course h3{font-size:19px;font-weight:800;letter-spacing:-.01em;line-height:1.35}
.c-d{font-size:14.5px;color:var(--muted);line-height:1.8}
.c-acc{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s var(--ease-out)}
.course.open .c-acc{grid-template-rows:1fr}
.c-acc>div{overflow:hidden}
.c-inner{padding:6px 32px 22px;display:flex;flex-direction:column;gap:16px}
.c-inner .sp-list{gap:9px}
.c-inner .sp-list li{font-size:14px}
.c-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 32px;border-top:1px solid var(--border-soft);background:var(--glass);margin-top:auto}
.c-foot .st{font-size:12.5px;color:var(--faint);font-weight:600;padding:0}
.c-foot .fx{width:28px;height:28px;border-radius:9px;background:var(--hover);display:grid;place-items:center;color:var(--muted);transition:transform .35s var(--ease-spring),background .2s,color .2s}
.c-foot .fx svg{width:13px;height:13px}
.course.open .c-foot .fx{transform:rotate(45deg);background:var(--accent-soft);color:var(--accent)}
.c-foot .fx-wrap{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--muted)}
.wf-step .tags{justify-content:center;margin-top:2px}
</style>
<script src="/assets/seo-inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('courses'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="courses-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">芭乐派 · R.B.E 训练营</span>
      <h1>学完，你手上会有<br><i class="si">一套在跑的增长系统</i></h1>
      <p class="lead">学完 New-1~4，你会知道业务里哪里该让 Agent 做；走完 R.B.E 训练营，你会画出自己专属的 Task Graph。理论（芭乐派方法论）→ 工具（OpenFlow）→ 落地（Agent 增长引擎），边学边用。</p>
      <div class="cta-row">
        <button class="btn primary" data-act="start" data-od-id="courses-cta-start">免费开始学习</button>
        <a class="btn ghost" href="/community" data-od-id="courses-cta-community">进入门派</a>
      </div>
      <div class="trust"><span class="dot"></span>New-1~4 免费开放 · R.B.E 训练营 8 周</div>
    </div>
  </section>

  <!-- ══ 课程体系 ══ -->
  <section id="path" class="sec reveal" data-od-anchor data-od-id="courses-path">
    <div class="sec-head center">
      <span class="kicker">课程体系</span>
      <h2>一条主线，从方法论到增长引擎</h2>
    </div>
    <div class="wf">
      <?php foreach ($PATH as $i => $p): ?>
      <div class="wf-step">
        <span class="wf-n">0<?=$i+1?></span>
        <span class="w-tag"><?=htmlspecialchars($p[0])?> · <?=htmlspecialchars($p[2])?></span>
        <h3><?=htmlspecialchars($p[1])?></h3>
        <p><?=htmlspecialchars($p[3])?></p>
        <div class="tags"><?php foreach ($p[4] as $x): ?><span><?=htmlspecialchars($x)?></span><?php endforeach; ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ══ 课程目录 ══ -->
  <section id="catalog" class="sec reveal" data-od-anchor data-od-id="courses-catalog">
    <div class="sec-head center">
      <span class="kicker">课程目录</span>
      <h2>选择你的学习路径</h2>
    </div>
    <div class="tab-bar" id="courseChips" role="group" aria-label="课程筛选">
      <button type="button" class="tab-p" aria-selected="true" data-filter="全部">全部</button>
      <button type="button" class="tab-p" aria-selected="false" data-filter="基石">基石</button>
      <button type="button" class="tab-p" aria-selected="false" data-filter="训练营">训练营</button>
    </div>
    <div class="grid g2" id="courseGrid" style="gap:18px">
      <?php foreach ($COURSES as $c): ?>
      <article class="card course" data-lv="<?=htmlspecialchars($c[1])?>" data-id="<?=$c[0]?>" data-od-id="course-<?=$c[0]?>">
        <div class="c-top" data-open>
          <div class="c-meta"><span class="badge <?=$c[1]==='训练营'?'warn':'ok'?>"><?=htmlspecialchars($c[1])?></span><span><?=htmlspecialchars($c[3])?></span><span><?=$c[0]?></span></div>
          <h3><?=htmlspecialchars($c[2])?></h3>
          <p class="c-d"><?=htmlspecialchars($c[4])?></p>
        </div>
        <div class="c-acc"><div><div class="c-inner">
          <ul class="sp-list"><?php foreach ($c[5] as $o): ?><li><?=$ck?><span><?=htmlspecialchars($o)?></span></li><?php endforeach; ?></ul>
          <div class="cta-row"><button class="btn primary" data-enroll>加入学习</button></div>
        </div></div></div>
        <div class="c-foot"><span class="st" data-st>点击卡片查看大纲</span><button type="button" class="fx-wrap" data-open><span>查看大纲</span><span class="fx"><?=$plus?></span></button></div>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="note" id="courseEmpty" hidden style="text-align:center">该级别暂无课程</p>
  </section>

  <!-- ══ 免费资源 ══ -->
  <section id="free" class="sec reveal" data-od-anchor data-od-id="courses-free">
    <div class="sec-head center">
      <span class="kicker">免费资源</span>
      <h2>不确定要不要报？先从这里开始</h2>
    </div>
    <div class="cols n4">
      <div><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h10l4 4v14H7V3Z"/><path d="M17 3v4h4"/></svg></span><h3>New-1~4 基石课</h3><p>一人公司冷启动 / 增长模型 / 精算体系 / Agent 知识管理。</p></div>
      <div><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2.5"/><path d="M3 9h18M9 3v18"/></svg></span><h3>利润公式计算器</h3><p>把销转率杠杆算明白，看哪些环节 Agent 化收益最高。</p></div>
      <div><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5.5v13l11-6.5-11-6.5Z"/></svg></span><h3>视频实操</h3><p>用 OpenFlow 跑增长闭环的实操演示，跟着做一遍就会。</p></div>
      <div><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.5c2.8.3 5 2.6 5 5.5"/></svg></span><h3>门派社区</h3><p>卡住了？提问，热心成员与官方都会回答。</p></div>
    </div>
  </section>

  <!-- ══ 适合谁 ══ -->
  <section id="fit" class="sec reveal" data-od-anchor data-od-id="courses-fit">
    <div class="sec-head center">
      <span class="kicker">适合谁</span>
      <h2>这套课程为谁而设</h2>
    </div>
    <div class="tl">
      <div class="tl-step"><span class="tl-n">01</span><span class="tl-y">FOUNDER</span><h3>一人公司创始人</h3><p>年营收 100-1000 万，增长失速。知道 AI 重要，但不知道业务里哪里该让 Agent 做。</p></div>
      <div class="tl-step"><span class="tl-n">02</span><span class="tl-y">OPERATOR</span><h3>超级个体 / 运营者</h3><p>用 OpenFlow 设计自己的增长系统：内容、获客、转化全闭环，不再靠手动堆时间。</p></div>
      <div class="tl-step"><span class="tl-n">03</span><span class="tl-y">ENGINEER</span><h3>开发者 / Agent 工程师</h3><p>想用 Task Graph 把增长漏斗拆成 Agent 可执行的任务图，打造可落地的 Agent 系统。</p></div>
    </div>
  </section>

  <!-- ══ 学完你能拿到 ══ -->
  <section id="outcomes" class="sec reveal" data-od-anchor data-od-id="courses-outcomes">
    <div class="sec-head center">
      <span class="kicker">学完你能拿到</span>
      <h2>不是听完就忘，是带走一份可跑的系统</h2>
    </div>
    <div class="scn">
      <div class="scn-f">
        <span class="f-tag">R.B.E 毕业交付</span>
        <h3>增长模型白皮书</h3>
        <p>R.B.E 毕业交付，专属你的增长系统蓝图。</p>
      </div>
      <div class="scn-s">
        <div class="scn-row"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/></svg></span><div><h3>你的利润公式</h3><p>算出销转率杠杆，知道该先优化哪个环节。</p></div></div>
        <div class="scn-row"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14m6-12v14"/></svg></span><div><h3>你的 Task Graph</h3><p>把增长漏斗拆成 Agent 可执行的任务图。</p></div></div>
        <div class="scn-row"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11V6a1.5 1.5 0 0 1 3 0v4m0-5.5V5a1.5 1.5 0 0 1 3 0v4m0-4.5A1.5 1.5 0 0 1 16 5v4m0-3.5a1.5 1.5 0 0 1 3 0V14a6 6 0 0 1-6 6h-1.5a6 6 0 0 1-4.8-2.4L4 14a1.5 1.5 0 0 1 2.4-1.8L7 13"/></svg></span><div><h3>门派入场券</h3><p>毕业进门派，和同行切磋、交换案例。</p></div></div>
      </div>
    </div>
  </section>

  <!-- ══ 学员怎么说 ══ -->
  <section id="reviews" class="sec reveal" data-od-anchor data-od-id="courses-reviews">
    <div class="sec-head center">
      <span class="kicker">学员怎么说</span>
      <h2>他们用这套方法，跑出了结果</h2>
    </div>
    <div class="qr">
      <div class="q-i"><div class="stars">★★★★★</div><blockquote>「利润公式那节课点醒我：我一直优化流量，其实该优化的是销转率。调整后两个月营收涨了 60%。」</blockquote><div class="who"><span class="av">林</span><div><b>林晓</b><span>知识付费</span></div></div></div>
      <div class="q-i"><div class="stars">★★★★★</div><blockquote>「Task Graph 是让我最值回票价的部分。以前不知道哪些活儿该外包给 AI，现在每个环节都标得清清楚楚。」</blockquote><div class="who"><span class="av">陈</span><div><b>陈默</b><span>内容工作室</span></div></div></div>
      <div class="q-i"><div class="stars">★★★★★</div><blockquote>「8 周走完，最意外的收获是白皮书。它不只是一份文档，是我整个业务的地图，团队现在照着它跑。」</blockquote><div class="who"><span class="av">王</span><div><b>王珩</b><span>SaaS 团队</span></div></div></div>
    </div>
  </section>

  <!-- ══ 收尾 CTA ══ -->
  <section id="next" class="reveal" data-od-anchor data-od-id="courses-cta">
    <div class="cta-band">
      <span class="kicker">开始学习</span>
      <h2>今天加入芭乐派，明天设计你的增长系统</h2>
      <p class="lead">New-1~4 免费开放，R.B.E 训练营带你 8 周设计出专属的 Agent-Native 增长模型。</p>
      <div class="cta-row">
        <button class="btn primary" data-act="start">免费开始学习</button>
        <a class="btn ghost" href="/academy">去学院学习</a>
      </div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
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
      <a href="/courses">芭乐派课程</a><a href="/docs">文档中心</a><a href="/docs#templates">模板库</a><a href="/docs#api">开放 API</a>
    </div>
    <div class="fb">
      <h4>联系</h4>
      <a href="mailto:hello@openflow.dev">hello@openflow.dev</a><a href="mailto:hello@openflow.dev">商务合作</a><a href="mailto:careers@openflow.dev">加入团队</a><a href="/community">门派社区</a>
    </div>
    <div class="f-bottom"><span>© 2026 芭乐派 · OpenFlow 增长操作系统</span><?php if (function_exists('i18n_enabled') && i18n_enabled()): ?><?=i18n_switcher()?><?php endif; ?><span>帮一人公司设计 Agent 能跑的增长系统</span></div>
  </footer>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>

<script>
/* courses.php · 页面级脚本：课程筛选 / 大纲展开 / 加入学习（localStorage）· 账户 CTA 转交共享外壳。
   reveal / 回到顶部 由 site-shell.js 处理。 */
(function(){
'use strict';
var $=function(s){return document.querySelector(s)};
var $$=function(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))};
function shell(){return window.OFShell||{}}
function toast(m){var f=shell().toast;if(f)f(m)}
function curUser(){var f=shell().curUser;return f?f():null}
function openAuth(m){var f=shell().openAuth;if(f)f(m);else{var a=document.getElementById('btn-av');if(a)a.click()}}
function openProfile(){var f=shell().openProfile;if(f)f();else openAuth('login')}

/* 报名状态 */
var CK='openflow-courses-v1', S={enrolled:[]};
try{var _s=JSON.parse(localStorage.getItem(CK)||'{}');if(Array.isArray(_s.enrolled))S.enrolled=_s.enrolled;}catch(e){}
function save(){try{localStorage.setItem(CK,JSON.stringify({enrolled:S.enrolled}))}catch(e){}}
function paint(card){
  var id=card.dataset.id, on=S.enrolled.indexOf(id)>-1, b=card.querySelector('[data-enroll]'), st=card.querySelector('[data-st]');
  b.className='btn '+(on?'ghost':'primary'); b.textContent=on?'已加入 ✓':'加入学习';
  st.textContent=on?'已加入该课程':(card.classList.contains('open')?'大纲已展开':'点击卡片查看大纲');
}
$$('.course').forEach(function(card){
  paint(card);
  $$('[data-open]',card).forEach(function(t){t.addEventListener('click',function(){card.classList.toggle('open');paint(card);});});
  card.querySelector('[data-enroll]').addEventListener('click',function(e){
    e.stopPropagation();
    var id=card.dataset.id, t=card.querySelector('h3').textContent, i=S.enrolled.indexOf(id);
    if(i>-1){S.enrolled.splice(i,1);toast('已取消加入「'+t+'」');}else{S.enrolled.push(id);toast('已加入「'+t+'」，开始学习吧');}
    save();paint(card);
  });
});

/* 筛选 */
$$('#courseChips .tab-p').forEach(function(ch){
  ch.addEventListener('click',function(){
    var f=ch.dataset.filter, n=0;
    $$('#courseChips .tab-p').forEach(function(x){x.setAttribute('aria-selected',x===ch?'true':'false')});
    $$('.course').forEach(function(c){var show=f==='全部'||c.dataset.lv===f;c.hidden=!show;if(show)n++;});
    $('#courseEmpty').hidden=n>0;
  });
});

/* 账户 CTA */
$$('[data-act]').forEach(function(el){el.addEventListener('click',function(e){e.preventDefault();
  if(el.dataset.act==='start'){var u=curUser();if(u){openProfile();toast('欢迎回来，'+(u.nick||u.email))}else{openAuth('register')}}
})});
})();
</script>
</body>
</html>
<?php PageCache::end('courses', 1800); ?>
