<?php
/**
 * 关于我们 | 芭乐派 · OpenFlow（动态版）
 *
 * v7（2026-09-01）：换骨架不换文案。
 *   - 全部模块来自 assets/modules.css 的共享 archetype（hero-center / worlds / sp-* /
 *     wf / tl / stats / cta-band / foot），本页不再定义任何自己的按钮、卡片、页脚。
 *   - 原先由 JS 渲染的「主张 / 思想源流 / 历程」改为服务端直出：爬虫可见，且少三段脚本。
 *   - 文案与 v6 逐字相同；「创始人Seven · 十年增长操盘」原本出现两次，现只在首屏 trust 行保留一次。
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
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260902a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260902a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260902a">
<style>
/* 关于页独有：创始人战绩窄栏里的四行成果。其余全部来自 modules.css。 */
.win-lead{padding:18px 22px 4px;font-size:12px;font-weight:700;letter-spacing:.08em;color:var(--faint);text-transform:uppercase;font-family:var(--font-mono)}
.flow-row .badge{margin-left:auto;flex:0 0 auto}
</style>
<script src="/assets/seo-inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('about'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="about-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">关于芭乐派</span>
      <h1>我们只服务一种人：<br><i class="si">一个人扛着一家公司的人</i></h1>
      <p class="lead">芭乐派是主品牌，OpenFlow 是它的开源平台。我们的信念很朴素：你不缺"怎么做"的工具，你缺的是"该做什么"的系统——设计你的系统，而不是操作你的系统。</p>
      <div class="cta-row">
        <a class="btn primary" href="/community" data-od-id="about-cta-join">加入门派</a>
        <a class="btn ghost" href="/product" data-od-id="about-cta-product">看看平台</a>
      </div>
      <div class="trust"><span class="dot"></span>创始人Seven · 十年增长操盘</div>
    </div>
  </section>

  <!-- ══ 品牌故事：芭乐，与派 ══ -->
  <section id="story" class="sec reveal" data-od-anchor data-od-id="about-story">
    <div class="sec-head center">
      <span class="kicker">品牌故事</span>
      <h2>芭乐，与派</h2>
      <p class="lead">芭乐，番石榴。长得不起眼，不像草莓好看，不像芒果浓郁。但切开之后香气独特，维生素 C 是橙子的 4 倍。一人公司也是这样——看起来小，但内核密度极高。</p>
    </div>
    <div class="worlds">
      <div class="w-col">
        <span class="w-tag">派 · 第一层</span>
        <h3>致敬树莓派</h3>
        <p class="w-q">一张信用卡大小的开发板，插上外设就是一个完整系统，一人公司不需要大团队。</p>
      </div>
      <div class="w-col w-gap">
        <span class="w-tag">派 · 第二层</span>
        <h3>是 π</h3>
        <p class="w-q">3.14159… 无限不循环，没有两个创业者走出过完全一样的路。</p>
      </div>
      <div class="w-col">
        <span class="w-tag">派 · 第三层</span>
        <h3>是门派</h3>
        <p class="w-q">一个人走得快，一群人走得远。</p>
      </div>
    </div>
    <div class="stats">
      <div class="st"><div class="st-n">10年</div><span class="st-en">Growth</span><span class="st-t">增长操盘</span></div>
      <div class="st"><div class="st-n">7</div><span class="st-en">Industries</span><span class="st-t">跨行业覆盖</span></div>
      <div class="st"><div class="st-n">50+</div><span class="st-en">Playbooks</span><span class="st-t">方法论落地</span></div>
      <div class="st"><div class="st-n">1套</div><span class="st-en">System</span><span class="st-t">Agent 增长系统</span></div>
    </div>
  </section>

  <!-- ══ 创始人 ══ -->
  <section id="founder" class="sec reveal" data-od-anchor data-od-id="about-founder">
    <div class="sec-head center">
      <span class="kicker">创始人</span>
      <h2>Seven：十年增长操盘手</h2>
    </div>
    <div class="split">
      <div class="sp-txt">
        <p class="lead">我不是教增长理论的讲师，是操盘过增长的人。芭乐派的内容不是从书里摘的——是从十年、七个行业的操盘经历里提炼的。</p>
        <ul class="sp-list">
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span><span><b>内容增长：</b>把搜索流量占比做到七成，靠内容持续获客</span></li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span><span><b>组织效率：</b>把大团队重构为精干小队，人效不降反升</span></li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span><span><b>私域获客：</b>把获客成本降到原来的五分之一</span></li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span><span><b>增长体系：</b>把方法论落地到 50+ 团队，可复制、可验证</span></li>
        </ul>
      </div>
      <div class="sp-vis">
        <div class="hero-win">
          <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">seven · track-record</div></div>
          <div class="win-lead">十年 · 七个行业 · 四件事</div>
          <div class="win-flow">
            <div class="flow-row"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V5l7-2v18M12 21V9l7 2v10M9 7h.01M9 11h.01M9 15h.01"/></svg></span><div><div class="ft">内容增长</div><div class="fd">content-led acquisition</div></div><span class="badge ok">流量七成</span></div>
            <div class="flow-link"></div>
            <div class="flow-row"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></svg></span><div><div class="ft">组织效率</div><div class="fd">lean team, higher output</div></div><span class="badge ok">人效提升</span></div>
            <div class="flow-link"></div>
            <div class="flow-row"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 0 0 0 20c1.5 0 2-.8 2-1.8 0-1-.6-1.7-.6-2.7 0-1.2 1-2 2.2-2H18a4 4 0 0 0 4-4c0-5-4.5-9.5-10-9.5Z"/><circle cx="7.5" cy="10.5" r="1.2"/><circle cx="10.5" cy="6.8" r="1.2"/><circle cx="14.5" cy="6.8" r="1.2"/></svg></span><div><div class="ft">私域获客</div><div class="fd">owned-channel CAC</div></div><span class="badge ok">成本-80%</span></div>
            <div class="flow-link"></div>
            <div class="flow-row"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg></span><div><div class="ft">增长体系</div><div class="fd">playbooks in production</div></div><span class="badge ok">50+落地</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ══ 主张 ══ -->
  <section id="principles" class="sec reveal" data-od-anchor data-od-id="about-principles">
    <div class="sec-head center">
      <span class="kicker">主张</span>
      <h2>我们相信的四件事</h2>
    </div>
    <div class="wf n4">
      <div class="wf-step"><span class="wf-n">01</span><h3>设计系统，不操作系统</h3><p>你不缺怎么做，你缺该做什么。工具解决怎么做，系统解决该做什么。</p></div>
      <div class="wf-step"><span class="wf-n">02</span><h3>Agent 能跑，人做判断</h3><p>把规则明确的交给 Agent，把判断留给人——人只做 Agent 做不到的五件事。</p></div>
      <div class="wf-step"><span class="wf-n">03</span><h3>核心能力永久开源</h3><p>Tools 和 Strategy 双向迭代，鱼与渔相结合，让用户既用得上工具也用得上策略。</p></div>
      <div class="wf-step"><span class="wf-n">04</span><h3>自生长，不是被操作</h3><p>每个人安装后都能快速改造成专属自己的增长引擎，从 Marketing 到 Sales 主动驱动。</p></div>
    </div>
  </section>

  <!-- ══ 思想源流 ══ -->
  <section id="thinking" class="sec reveal" data-od-anchor data-od-id="about-thinking">
    <div class="sec-head center">
      <span class="kicker">思想源流</span>
      <h2>为什么我们把核心永久开源</h2>
    </div>
    <div class="scn">
      <div class="scn-f">
        <span class="f-tag">源流 · 01</span>
        <h3>判断力来自失败</h3>
        <p>乔布斯被赶出公司、NeXT 失败、Pixar 生死——失败训练判断力。你的每次失败都在训练，不是浪费时间。</p>
      </div>
      <div class="scn-s">
        <div class="scn-row"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22h18"/><path d="M6 18v-7M10 18v-7M14 18v-7M18 18v-7"/><path d="M4 11 12 4l8 7"/></svg></span><div><h3>宏观设计系统</h3><p>商业模式全史：直销→平台→订阅→免费增值，每次演进都是对支付能力富集效率的一次优化。</p></div></div>
        <div class="scn-row"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg></span><div><h3>微观执行触达</h3><p>顾客为什么买：一个人怎么走、怎么看、为什么拿起又放下。宏观设计系统，微观执行触达。</p></div></div>
        <div class="scn-row"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 19h.01M12 19h.01M16 19h.01"/></svg></span><div><h3>利润公式与销转率</h3><p>销转率是唯一的小数因子。在乘式中，小数变动一个数量级，结果就塌缩或爆炸。</p></div></div>
      </div>
    </div>
  </section>

  <!-- ══ 历程 ══ -->
  <section id="timeline" class="sec reveal" data-od-anchor data-od-id="about-timeline">
    <div class="sec-head center">
      <span class="kicker">历程</span>
      <h2>走到今天</h2>
    </div>
    <div class="tl n4">
      <div class="tl-step"><span class="tl-n">01</span><span class="tl-y">2015-2025</span><h3>十年增长操盘</h3><p>横跨快消/SaaS/教育/3C/跨境/金融科技/AI 产品 7 行业，从 400 人团队到 AI 产品操盘。</p></div>
      <div class="tl-step"><span class="tl-n">02</span><span class="tl-y">2026</span><h3>芭乐派成立</h3><p>帮一人公司设计 Agent 能跑的增长系统，把十年操盘提炼成方法论。</p></div>
      <div class="tl-step"><span class="tl-n">03</span><span class="tl-y">2026</span><h3>OpenFlow 开源</h3><p>芭乐派增长操作系统的开源底座，TIPS 框架四力合一。</p></div>
      <div class="tl-step"><span class="tl-n">04</span><span class="tl-y">现在</span><h3>自生长引擎上线</h3><p>主动爬取、主动洞察、主动转化——每个人都能长出专属增长引擎。</p></div>
    </div>
  </section>

  <!-- ══ 加入门派 ══ -->
  <section id="join" class="sec reveal" data-od-anchor data-od-id="about-join">
    <div class="sec-head center">
      <span class="kicker">加入门派</span>
      <h2>一起把增长系统做得更好</h2>
      <p class="lead">无论你是正在从 0 到 1 死磕的一人公司，还是想和 Agent 时代一起成长的创业者——这里都有你的位置。</p>
    </div>
    <div class="link-grid">
      <a class="link-it top" href="mailto:hello@openflow.dev" data-od-id="about-mail-biz">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12h8m-8 4h5M9 4h6a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/></svg></span>
        <span class="lt"><b>商务合作</b><span>企业采购、私有化部署、渠道合作，请联系商务团队。</span><span class="mono">hello@openflow.dev</span></span>
        <span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
      </a>
      <a class="link-it top" href="mailto:careers@openflow.dev" data-od-id="about-mail-careers">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.5c2.8.3 5 2.6 5 5.5"/></svg></span>
        <span class="lt"><b>加入团队</b><span>开放岗位与内推渠道，简历直达团队。</span><span class="mono">careers@openflow.dev</span></span>
        <span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
      </a>
      <a class="link-it top" href="mailto:community@openflow.dev" data-od-id="about-mail-community">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 7v5c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V7l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span>
        <span class="lt"><b>媒体与社区</b><span>报道、合作与内容共创，欢迎来信聊聊。</span><span class="mono">community@openflow.dev</span></span>
        <span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
      </a>
    </div>
  </section>

  <!-- ══ 收尾 CTA ══ -->
  <section id="next" class="reveal" data-od-anchor data-od-id="about-cta">
    <div class="cta-band">
      <span class="kicker">下一步</span>
      <h2>从了解我们，到跑出你的增长系统</h2>
      <p class="lead">产品、课程、社区——三条路，都通向同一个地方：不再被重复消耗，让 Agent 替你驱动增长。</p>
      <div class="cta-row">
        <a class="btn primary" href="/courses">开始学习</a>
        <a class="btn ghost" href="/product">看看产品</a>
        <a class="btn ghost" href="/community">进入门派社区</a>
      </div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>

<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
