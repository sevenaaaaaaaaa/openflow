<?php
/**
 * 产品 | 芭乐派 · OpenFlow（动态版）
 *
 * v7（2026-09-01）：换骨架不换文案。模块全部来自 assets/modules.css 的共享 archetype；
 * 本页 <style> 只保留产品页独有的四个演示部件（编排画布 / 对话 / 连接器 / 闭环演示与日志）。
 * 连接器与 FAQ 原由 JS 渲染 → 服务端直出。文案与 v6 逐字相同。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
$siteName = site_config_get('site_name', 'OpenFlow');
header('Cache-Control: no-cache, max-age=0');
$CONN = ['飞书','企业微信','WhatsApp','Notion','GitHub 导入','SMTP 邮件','Ghost','虎皮椒支付','Search Console','Webhook','OpenAPI','MCP']; // 全部在 lib/ 与 api/ 里核过：NotifyChannels / NotionClient / api/ingest / MailChannel / PaymentChannel / SeoConsole / WebhookSystem / mcp-server
$FAQS = [
  ['OpenFlow 需要写代码吗？','不需要。TIPS 框架下可视化配置触达/洞察/个性化/销售四力；需要时可用 Task Graph 编排 Agent，深浅兼顾。'],
  ['适合一人公司吗？','OpenFlow 就是为 OPC 一人公司设计的。装完即用，自生长 AI Engine 自动爬取、洞察、转化，一个人也能驱动整套增长系统。'],
  ['和「芭乐派」是什么关系？','OpenFlow 是芭乐派增长操作系统的开源底座。芭乐派讲方法论（利润公式/四引擎/Agent 系统），OpenFlow 是落地工具——鱼与渔相结合。'],
  ['核心能力真的永久开源吗？','是。Tools 和 Strategy 双向迭代，核心能力永久开源，坚持让用户既用得上工具，也能用最前沿的增长策略。'],
  ['数据安全如何保证？','传输与存储加密、细粒度权限、审计日志；支持私有化部署，数据不出域。'],
];
$ck = '<span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span>';
$plus = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>';
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
<!-- 共享外壳样式契约：必须在页面级 <style> 之前，页面样式才能覆盖模块层。 -->
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260903a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260903a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260903a">
<style>
/* 产品页独有：四个演示部件。其余全部来自 modules.css。 */
.mock-canvas{position:relative;border-radius:14px;background:var(--bg-soft);border:1px solid var(--border);height:190px;overflow:hidden;margin:18px}
.mnode{position:absolute;background:var(--surface-strong);border:1.5px solid var(--border-strong);border-radius:12px;padding:8px 12px;font-size:12px;font-weight:700;box-shadow:var(--shadow-sm)}
.mchat{display:flex;flex-direction:column;gap:12px;padding:20px}
.mchat .bub{max-width:88%;border-radius:14px;padding:12px 15px;font-size:13.5px;line-height:1.65;width:auto;height:auto;cursor:default}
.mchat .bub.u{align-self:flex-start;background:var(--surface-strong);border:1px solid var(--border)}
.mchat .bub.a{align-self:flex-end;background:var(--accent);color:var(--on-accent)}
.mchat .gen{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px}
.mchat .gen span{font-family:var(--font-mono);font-size:11px;padding:6px 10px;border-radius:9px;background:var(--ok-soft);color:var(--ok);font-weight:700}
.conn-chips{display:flex;flex-wrap:wrap;gap:8px;padding:20px}
.conn-chips .cc{display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 14px;border-radius:12px;background:var(--surface);border:1px solid var(--border);font-size:13px;font-weight:600}
.conn-chips .cc .cd{width:8px;height:8px;border-radius:50%;background:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.flow-row .badge{margin-left:auto;flex:0 0 auto}
/* 闭环演示 */
.demo-wrap{display:grid;grid-template-columns:1.2fr .8fr;gap:16px;align-items:stretch}
.demo-fig{display:flex;flex-direction:column;gap:14px}
.demo-svg{width:100%;height:auto;padding:14px 18px 6px}
.demo-svg .nd rect{fill:var(--surface-strong);stroke:var(--border-strong);stroke-width:1.4;transition:fill .35s,stroke .35s}
.demo-svg .nd .nt{fill:var(--fg);font-size:13px;font-weight:700}
.demo-svg .nd .nd2{fill:var(--faint);font-size:10.5px;font-family:var(--font-mono)}
.demo-svg .ln{stroke:var(--border-strong);stroke-width:1.6;stroke-dasharray:none;animation:none}
.demo-svg .fd{fill:var(--accent);opacity:0;transition:opacity .3s}
.demo-svg.r1 .fd1,.demo-svg.r2 .fd1,.demo-svg.r3 .fd1,.demo-svg.r4 .fd1{opacity:1}
.demo-svg.r2 .fd2,.demo-svg.r3 .fd2,.demo-svg.r4 .fd2{opacity:1}
.demo-svg.r3 .fd3,.demo-svg.r4 .fd3{opacity:1}
.demo-svg.r4 .fd4{opacity:1}
.demo-svg .nd0 rect{fill:var(--accent);stroke:var(--accent)}
.demo-svg .nd0 .nt,.demo-svg .nd0 .nd2{fill:var(--on-accent)}
.demo-ctrl{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:0 4px}
.demo-log{background:oklch(18% .01 140/.92);border-radius:var(--r-lg);border:1px solid oklch(100% 0 0/.08);padding:20px;font-family:var(--font-mono);font-size:12.5px;line-height:1.9;color:oklch(85% .01 140);min-height:200px;overflow:hidden;position:relative}
.demo-log .ln2{white-space:pre-wrap;word-break:break-all}
.demo-log .t-ok{color:oklch(80% .15 152)}
.demo-log .t-warn{color:oklch(82% .13 75)}
.demo-log .t-dim{color:oklch(60% .01 140)}
.demo-log::before{content:'执行日志 · 演示环境';position:absolute;top:12px;right:16px;font-size:9.5px;letter-spacing:.14em;color:oklch(55% .01 140)}
@media (max-width:1080px){.demo-wrap{grid-template-columns:1fr}}
</style>
<script src="/assets/seo-inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('product'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="product-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">产品 · 芭乐派 OpenFlow</span>
      <h1>一个平台，<br>跑通你的<i class="si">整条增长链路</i></h1>
      <p class="lead">一人公司最缺的，不是一个工具，而是一套系统。OpenFlow 把内容、数据、自动化、触达连成一套增长引擎——让 Agent 跑流程，你只做判断。不是 All in one，而是 Everything。</p>
      <div class="cta-row">
        <button class="btn primary" data-act="start" data-od-id="product-cta-start">免费开始</button>
        <a class="btn ghost" href="#demo" data-od-id="product-cta-demo">运行演示</a>
      </div>
      <div class="trust"><span class="dot"></span>核心能力永久开源 · 鱼与渔相结合</div>
    </div>
  </section>

  <!-- ══ 痛点 ══ -->
  <section id="pain" class="sec reveal" data-od-anchor data-od-id="product-pain">
    <div class="sec-head center">
      <span class="kicker">痛点</span>
      <h2>一人公司最缺的，不是一个工具，而是一套系统</h2>
    </div>
    <div class="cols">
      <div><span class="ic danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></span><h3>增长靠「手动堆」</h3><p>爬热点、写文章、发触达、盯数据——每件事都亲力亲为，时间被重复动作吃掉，策略没人做。</p></div>
      <div><span class="ic danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5h14v6H5zM5 13h14v6H5zM9 8h.01M9 16h.01"/></svg></span><h3>工具之间互相割裂</h3><p>CMS、CDP、MA、CRM 各自为政。数据散落各处，触达和转化接不上，洞察变不成动作。</p></div>
      <div><span class="ic danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 19V5m0 14h16M7 15l3-4 3 2 4-6"/></svg></span><h3>增长黑盒不可见</h3><p>不知道访客从哪来、什么内容有效、哪个环节漏单。没有洞察，增长就是撞运气。</p></div>
    </div>
  </section>

  <!-- ══ TIPS 框架 ══ -->
  <section id="tips" class="sec reveal" data-od-anchor data-od-id="product-tips">
    <div class="sec-head center">
      <span class="kicker">框架</span>
      <h2>TIPS 四力：触达 · 洞察 · 个性化 · 销售</h2>
      <p class="lead">OpenFlow 的一切都围绕这四个力组织。理解 TIPS，你就理解了整个平台——也是芭乐派增长操作系统的方法论底座。</p>
    </div>
    <div class="cols n4">
      <div><span class="ltr">T</span><h3>触达 Touch</h3><p>内容引擎、分发渠道、触达体系。正确的时间、渠道、内容，把信息递到用户面前。</p></div>
      <div><span class="ltr">I</span><h3>洞察 Insight</h3><p>数据、CDP、舆情、分析。从几百个指标捞出该看的那 3-5 个，把数据变成判断。</p></div>
      <div><span class="ltr">P</span><h3>个性化 Personality</h3><p>画像、分群、自动化。给对的人，在对的时刻，说对的话。</p></div>
      <div><span class="ltr">S</span><h3>销售 Sales</h3><p>CRM、转化、商城、订阅。从触达到成交，让支付能力流向你。</p></div>
    </div>
  </section>

  <!-- ══ 能力 ══ -->
  <section id="features" class="sec reveal" data-od-anchor data-od-id="product-features">
    <div class="sec-head center">
      <span class="kicker">能力</span>
      <h2>不是 All in one，而是 Everything</h2>
    </div>

    <div class="split">
      <div class="sp-txt">
        <h3>可视化编排画布</h3>
        <p class="lead">节点即逻辑。拖拽触发器、条件、动作与人工确认步骤，连线即成流程——零代码上手，也不挡专业用户的路。</p>
        <ul class="sp-list">
          <li><?=$ck?><span>分支、循环、并行与等待结构</span></li>
          <li><?=$ck?><span>实时预览与一键回滚历史版本</span></li>
          <li><?=$ck?><span>模板库一键复用成熟流程</span></li>
        </ul>
      </div>
      <div class="sp-vis"><div class="sp-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">canvas · task-graph</div></div>
        <div class="mock-canvas">
          <div class="mnode" style="left:14px;top:26px">触发器</div>
          <div class="mnode" style="left:56px;top:96px">条件判断</div>
          <div class="mnode" style="right:14px;top:26px">动作 A</div>
          <div class="mnode" style="right:14px;bottom:18px">动作 B</div>
          <svg viewBox="0 0 320 190" style="position:absolute;inset:0;width:100%;height:100%" aria-hidden="true"><g stroke="var(--border-strong)" stroke-width="1.6" fill="none" stroke-dasharray="4 5"><path d="M78 42 C 120 42, 120 112, 150 112"/><path d="M150 112 C 180 112, 180 42, 210 42"/><path d="M150 112 C 180 112, 180 150, 210 150"/></g><circle r="3.5" fill="var(--accent)"><animateMotion dur="2.4s" repeatCount="indefinite" path="M78 42 C 120 42, 120 112, 150 112"/></circle></svg>
        </div>
      </div></div>
    </div>

    <div class="split rev">
      <div class="sp-txt">
        <h3>AI 步骤：给流程装上判断力</h3>
        <p class="lead">用自然语言描述需求，AI 自动生成流程步骤与字段映射。摘要、分类、改写、抽取——大模型能力以步骤的形式进入你的工作流。</p>
        <ul class="sp-list">
          <li><?=$ck?><span>自然语言生成流程草稿</span></li>
          <li><?=$ck?><span>字段智能映射，少填一次表</span></li>
          <li><?=$ck?><span>异常自动降级与重试</span></li>
        </ul>
      </div>
      <div class="sp-vis"><div class="sp-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">ai-step · natural-language</div></div>
        <div class="mchat">
          <div class="bub u">每天 9 点汇总销售日报，提取 Top 3 要点，发到企业微信销售群</div>
          <div class="bub a">已生成 4 步流程：定时触发 → 读取日报 → AI 摘要 → 推送通知</div>
          <div class="gen"><span>✓ 定时触发</span><span>✓ 读取数据</span><span>✓ AI 摘要</span><span>✓ 推送</span></div>
        </div>
      </div></div>
    </div>

    <div class="split">
      <div class="sp-txt">
        <h3>开放连接器生态</h3>
        <p class="lead">不是封闭的私有集成，而是开放的连接标准。核心能力永久开源；飞书 / 企业微信 / Notion / Search Console 等常用系统已接好，私有系统用 OpenAPI 或 Webhook 自定义接入。每一项都能在代码里翻到。</p>
        <ul class="sp-list">
          <li><?=$ck?><span>18 个 MCP 工具、32 个插件钩子、92 个 API，Agent 可直接调用</span></li>
          <li><?=$ck?><span>核心能力永久开源 · 鱼与渔结合</span></li>
          <li><?=$ck?><span>Webhook 双向触发与回调</span></li>
        </ul>
      </div>
      <div class="sp-vis"><div class="sp-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">connectors</div></div>
        <div class="conn-chips"><?php foreach ($CONN as $c): ?><span class="cc"><span class="cd"></span><?=htmlspecialchars($c)?></span><?php endforeach; ?></div>
      </div></div>
    </div>

    <div class="split rev">
      <div class="sp-txt">
        <h3>自生长 AI Engine，从 Marketing 到 Sales</h3>
        <p class="lead">OpenFlow 不是被动工具，而是主动驱动增长的引擎：按你设的周期自动爬取信号、AI 洞察、生成内容、主动触达转化。装完即用，每个人都能改造成专属自己的增长引擎。</p>
        <ul class="sp-list">
          <li><?=$ck?><span>主动爬取舆情与行业热点</span></li>
          <li><?=$ck?><span>AI 撰写草稿（人工审核后发布）</span></li>
          <li><?=$ck?><span>洞察→优化→转化全闭环</span></li>
        </ul>
      </div>
      <div class="sp-vis"><div class="hero-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">growth-engine · live</div></div>
        <div class="win-flow">
          <div class="flow-row"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12a8 8 0 1 1-2.3-5.6M20 4v4h-4"/></svg></span><div><div class="ft">爬取行业信号 · 自动</div><div class="fd">Loop</div></div><span class="badge ok">进行中</span></div>
          <div class="flow-link"></div>
          <div class="flow-row"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg></span><div><div class="ft">AI 总结热点洞察</div><div class="fd">Insight</div></div><span class="badge ok">完成</span></div>
          <div class="flow-link"></div>
          <div class="flow-row"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h10l4 4v14H7V3Z"/><path d="M17 3v4h4"/></svg></span><div><div class="ft">生成文章草稿（待审）</div><div class="fd">Write</div></div><span class="badge warn">待确认</span></div>
          <div class="flow-link"></div>
          <div class="flow-row"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span><div><div class="ft">主动触达转化</div><div class="fd">Convert</div></div><span class="badge ok">完成</span></div>
        </div>
      </div></div>
    </div>
  </section>

  <!-- ══ 增长闭环演示 ══ -->
  <section id="demo" class="sec reveal" data-od-anchor data-od-id="product-demo">
    <div class="sec-head center">
      <span class="kicker">增长闭环</span>
      <h2>点一下，看增长引擎跑起来</h2>
      <p class="lead">下面的增长闭环按你配置的 cron 周期自动执行：爬取信号 → AI 洞察 → 生成草稿 → 主动触达。点击「运行一轮」观察完整过程。</p>
    </div>
    <div class="demo-wrap">
      <div class="demo-fig">
        <div class="sp-win">
          <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">openflow · growth-loop</div></div>
          <svg class="demo-svg" viewBox="0 0 740 150" aria-hidden="true">
            <g class="nd nd0"><rect x="10" y="42" width="150" height="66" rx="16"/><text class="nt" x="85" y="70" text-anchor="middle">爬取信号</text><text class="nd2" x="85" y="90" text-anchor="middle">舆情 · RSS 热点</text></g>
            <g class="nd nd1"><rect x="200" y="42" width="150" height="66" rx="16"/><text class="nt" x="275" y="70" text-anchor="middle">AI 洞察</text><text class="nd2" x="275" y="90" text-anchor="middle">总结增长机会</text></g>
            <g class="nd nd2"><rect x="390" y="42" width="150" height="66" rx="16"/><text class="nt" x="465" y="70" text-anchor="middle">AI 撰写</text><text class="nd2" x="465" y="90" text-anchor="middle">生成草稿 · 待审</text></g>
            <g class="nd nd3"><rect x="580" y="42" width="150" height="66" rx="16"/><text class="nt" x="655" y="70" text-anchor="middle">主动触达</text><text class="nd2" x="655" y="90" text-anchor="middle">转化 · 销售闭环</text></g>
            <g stroke="var(--border-strong)" stroke-width="1.8" fill="none"><path class="ln" d="M160 75 H200"/><path class="ln" d="M350 75 H390"/><path class="ln" d="M540 75 H580"/></g>
            <circle class="fd fd1" r="4"><animateMotion dur="1s" repeatCount="indefinite" path="M160 75 H200"/></circle>
            <circle class="fd fd2" r="4"><animateMotion dur="1s" repeatCount="indefinite" path="M350 75 H390"/></circle>
            <circle class="fd fd3" r="4"><animateMotion dur="1s" repeatCount="indefinite" path="M540 75 H580"/></circle>
            <circle class="fd fd4" r="4"><animateMotion dur="1s" repeatCount="indefinite" path="M160 75 H580"/></circle>
          </svg>
        </div>
        <div class="demo-ctrl">
          <button class="btn primary" id="demoRun" data-od-id="demo-run"><span class="ic"><svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px"><path d="M8 5.5v13l11-6.5-11-6.5Z"/></svg></span><span>运行一轮</span></button>
          <span class="note" id="demoState">就绪 · 点击运行</span>
        </div>
      </div>
      <div class="demo-log"><div class="ln2" id="demoLog">$ openflow growth-loop<br><span class="t-dim"># 等待触发……</span></div></div>
    </div>
  </section>

  <!-- ══ 价值 ══ -->
  <section id="value" class="sec reveal" data-od-anchor data-od-id="product-value">
    <div class="sec-head center">
      <span class="kicker">价值</span>
      <h2>增长引擎正在产生实实在在的价值</h2>
      <p class="note">增长引擎的示例运行输出</p>
    </div>
    <div class="stats">
      <div class="st"><div class="st-n">8/8</div><span class="st-en">Loop health</span><span class="st-t">增长闭环环节正常</span></div>
      <div class="st"><div class="st-n">24/7</div><span class="st-en">Always on</span><span class="st-t">自生长引擎主动运行</span></div>
      <div class="st"><div class="st-n">100%</div><span class="st-en">Open source</span><span class="st-t">核心能力永久开源</span></div>
      <div class="st"><div class="st-n">1人</div><span class="st-en">Operator</span><span class="st-t">即可驱动整套增长系统</span></div>
    </div>
  </section>

  <!-- ══ FAQ ══ -->
  <section id="faq-sec" class="sec reveal" data-od-anchor data-od-id="product-faq">
    <div class="sec-head center">
      <span class="kicker">常见问题</span>
      <h2>你可能会关心</h2>
    </div>
    <div class="faq" id="faq">
      <?php foreach ($FAQS as $i => $f): ?>
      <div class="fq"><button class="fq-q" data-fq="<?=$i?>" aria-expanded="false"><span><?=htmlspecialchars($f[0])?></span><span class="fx"><?=$plus?></span></button><div class="fq-a"><div><p><?=htmlspecialchars($f[1])?></p></div></div></div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ══ 客户评价 ══ -->
  <section id="reviews" class="sec reveal" data-od-anchor data-od-id="product-reviews">
    <div class="sec-head center">
      <span class="kicker">客户评价</span>
      <h2>他们已经在用 OpenFlow 跑增长</h2>
    </div>
    <div class="qr">
      <div class="q-i"><div class="stars">★★★★★</div><blockquote>「以前每天 3 小时找选题改文章，现在 OpenFlow 爬完信号直接给草稿，我只管把关。效率翻了三倍。」</blockquote><div class="who"><span class="av">陈</span><div><b>陈默</b><span>内容工作室</span></div></div></div>
      <div class="q-i"><div class="stars">★★★★★</div><blockquote>「销转率从 2.1% 提到 3.8%，靠的不是更多流量，是把转化每一环都拆出来让 Agent 盯。」</blockquote><div class="who"><span class="av">林</span><div><b>林晓</b><span>知识付费</span></div></div></div>
      <div class="q-i"><div class="stars">★★★★★</div><blockquote>「4 个人的团队，周报、监控、跨群通知全交给工作流，省出的时间够多做一个客户。」</blockquote><div class="who"><span class="av">王</span><div><b>王珩</b><span>SaaS 服务商</span></div></div></div>
    </div>
  </section>

  <!-- ══ 收尾 CTA ══ -->
  <section id="next" class="reveal" data-od-anchor data-od-id="product-cta">
    <div class="cta-band">
      <span class="kicker">立即开始</span>
      <h2>装完即用，今天就能长出你的增长引擎</h2>
      <p class="lead">免费开始，无需信用卡。安装后 OpenFlow 自动开始爬取信号、主动洞察、主动转化——每个人都能改造成专属自己的增长系统。</p>
      <div class="cta-row">
        <button class="btn primary" data-act="start">免费开始</button>
        <a class="btn ghost" href="/capability">了解 TIPS 能力</a>
      </div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>

<script>
/* product.php · 页面级脚本：FAQ 手风琴 · 闭环演示 · 账户 CTA 转交共享外壳。
   reveal / 回到顶部 由 site-shell.js 统一处理。 */
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
var I={
  refresh:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M20 12a8 8 0 1 1-2.3-5.6M20 4v4h-4"/></svg>',
  play:'<svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px"><path d="M8 5.5v13l11-6.5-11-6.5Z"/></svg>'
};

/* ── FAQ ── */
$$('.fq').forEach(function(el){
  var q=el.querySelector('.fq-q');
  q.addEventListener('click',function(){var on=el.classList.toggle('open');q.setAttribute('aria-expanded',on?'true':'false');});
});

/* ── 闭环演示 ── */
var DLOG=[['info','> 09:00:00 触发器命中 · 流程开始'],
['info','> 09:00:01 正在读取「销售日报」表 · 12 条记录'],
['info','> 09:00:02 字段映射完成 · 12/12'],
['ok','> 09:00:03 AI 摘要生成 · 3 个要点'],
['ok','> 09:00:04 推送至企业微信「销售群」· 成功'],
['ok','> 09:00:05 本次运行完成 · 耗时 5.2s']];
var running=false,runTimers=[];
function resetDemo(){
  runTimers.forEach(clearTimeout);runTimers=[];running=false;
  $('.demo-svg').className.baseVal='demo-svg';
  $('#demoLog').innerHTML='$ openflow growth-loop<br><span class="t-dim"># 等待触发……</span>';
  $('#demoState').textContent='就绪 · 点击运行';
  $('#demoRun').disabled=false;
  $('#demoRun').innerHTML='<span class="ic">'+I.play+'</span><span>运行一轮</span>';
}
$('#demoRun').addEventListener('click',function(){
  if(running){resetDemo();return;}
  running=true;this.disabled=true;
  this.innerHTML='<span class="ic">'+I.refresh+'</span><span>运行中…</span>';
  $('#demoState').textContent='运行中 · 预计 5 秒';
  var svg=$('.demo-svg');var log=$('#demoLog');log.innerHTML='$ openflow growth-loop<br>';
  [1,2,3,4].forEach(function(n){runTimers.push(setTimeout(function(){svg.className.baseVal='demo-svg r'+n;},n*1100));});
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

/* ── 账户 CTA ── */
$$('[data-act]').forEach(function(el){el.addEventListener('click',function(e){e.preventDefault();var a=el.dataset.act;
  if(a==='start'){var u=curUser();if(u){openProfile();toast('欢迎回来，'+(u.nick||u.email))}else{openAuth('register')}}
  else if(a==='demo'){var d=document.getElementById('demo');if(d)window.scrollTo({top:d.getBoundingClientRect().top+window.scrollY-120,behavior:RM?'auto':'smooth'});}
})});
})();
</script>
</body>
</html>
