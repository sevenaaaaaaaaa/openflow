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
$CONN = ['飞书','企业微信','WhatsApp','Notion','GitHub 导入','SMTP 邮件','Ghost','虎皮椒支付','微信支付','支付宝','Stripe','Telegram','Discord','Mastodon','WordPress','Search Console','Webhook','OpenAPI','MCP']; // 全部在 lib/ 与 api/ 里核过：NotifyChannels / NotionClient / api/ingest / MailChannel / PaymentChannel / PublishAdapters / SeoConsole / WebhookSystem / mcp-server
$FAQS = [
  ['OpenFlow 需要写代码吗？','不需要。TIPS 框架下可视化配置触达/洞察/个性化/销售四力；需要时可用 Task Graph 编排 Agent，深浅兼顾。'],
  ['适合一人公司吗？','OpenFlow 就是为 OPC 一人公司设计的。装完即用，增长引擎自动爬取、洞察、转化，一个人也能驱动整套增长系统。'],
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
<?php if (function_exists('seo_head')): seo_head(['title' => '产品矩阵总览（预览）· 芭乐派', 'description' => '七个独立产品按需组合：OpenFlow 全家桶、MFlow 内容分发、Webs Flow 落地页、UserLoop 全域数据、inFlow 情报、PayFlow 收款、LearnFlow 课程交付。零强制绑定，API 互通。', 'canonical' => site_config_get('site_url') . '/demo/products']); endif; ?>
<meta name="robots" content="noindex,follow">
<title>产品矩阵总览（预览）· 芭乐派</title>
<meta name="description" content="七个独立产品按需组合：OpenFlow 全家桶、MFlow 内容分发、Webs Flow 落地页、UserLoop 全域数据、inFlow 情报、PayFlow 收款、LearnFlow 课程交付。零强制绑定，API 互通。">
<!-- 共享外壳样式契约：必须在页面级 <style> 之前，页面样式才能覆盖模块层。 -->
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 产品页独有：闭环演示（流程图 + 执行日志）与首屏证据图。
   .real-grid / .conn-chips / .dep-table / .bt-go 已收编进 modules.css，本页不再私有。 */
.hero-shot{max-width:980px;margin:36px auto 0;text-align:left}
.hero-shot img{width:100%;height:auto;display:block;aspect-ratio:1520/950;object-fit:cover;object-position:top}
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
      <h1>七个独立产品，<br>按需组合成<i class="si">一套系统</i></h1>
      <p class="lead">不必为一个用不上的大系统付费。每件产品都能单独跑、单独见效；组合起来就是一套自转的增长系统。互通走 API——拔掉任何一个，其余照常运行。</p>
      <div class="cta-row">
        <a class="btn primary" href="#products" data-od-id="product-cta-start">看七个产品</a>
        <a class="btn ghost" href="#demo" data-od-id="product-cta-demo">运行演示</a>
      </div>
      <div class="trust"><span class="dot"></span>七个独立产品 · API 互通 · 零强制绑定 · 核心开源</div>
      <div class="sp-win hero-shot">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">openflow · workspace</div></div>
        <img src="/assets/images/product/workspace.png" alt="OpenFlow 工作台真实界面：KPI、待办与增长动态一览" loading="eager">
      </div>
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
      <div><span class="ltr">T</span><h3>触达 Touch</h3><p>内容引擎、分发渠道、触达体系。正确的时间、渠道、内容，把信息递到用户面前。</p><ul class="sp-list"><li><span>内容引擎 · 分发适配器 · Newsletter</span></li><li><span>承载产品：MFlow（轻量）/ OpenFlow（全家桶）</span></li></ul></div>
      <div><span class="ltr">I</span><h3>洞察 Insight</h3><p>数据、CDP、舆情、分析。从几百个指标捞出该看的那 3-5 个，把数据变成判断。</p><ul class="sp-list"><li><span>CDP 画像 · 分群 RFM · 漏斗 · 问数据</span></li><li><span>承载产品：UserLoop（全域）/ inFlow（外部情报）</span></li></ul></div>
      <div><span class="ltr">P</span><h3>个性化 Personality</h3><p>画像、分群、自动化。给对的人，在对的时刻，说对的话。</p><ul class="sp-list"><li><span>人群定向 · 推荐引擎 · 动态内容</span></li><li><span>承载产品：OpenFlow（全站）/ UserLoop（数据底座）</span></li></ul></div>
      <div><span class="ltr">S</span><h3>销售 Sales</h3><p>CRM、转化、商城、订阅。从触达到成交，让支付能力流向你。</p><ul class="sp-list"><li><span>CRM 管道 · 订阅计费 · 佣金结算</span></li><li><span>承载产品：PayFlow（收款）/ LearnFlow（课程交付）</span></li></ul></div>
    </div>
  </section>

  <!-- ══ 七个独立产品（预览版核心） ══ -->
  <section id="products" class="sec reveal" data-od-anchor data-od-id="product-matrix">
    <div class="sec-head center">
      <span class="kicker">产品矩阵</span>
      <h2>七件独立产品，按需组合</h2>
      <p class="lead">同一个增长问题，七种解法。先看你缺哪一环，再决定要不要整套。</p>
    </div>
    <div class="bento" style="margin-top:26px">
<div data-w="4" data-r="2" class="bt-hi"><span class="bt-k">全家桶 · 四力合一</span><h3>OpenFlow · 增长操作系统</h3><p>要一整套系统、数据要在一处、团队要一个后台——四力合一，其余六件按需嵌入。</p><ul><li><b>交付</b>：内容引擎 + CDP + 自动化 + CRM + 商城</li><li><b>适合</b>：要一整套系统的团队</li><li><b>依赖</b>：深度（自家 CMS + CDP）</li><li><b>上手</b>：30 分钟上线</li></ul><a href="/product" class="bt-go">进入 OpenFlow →</a></div><div data-w="2"><span class="bt-k">轻量内容</span><h3>MFlow · 内容生产与分发</h3><p>AI 生成 + 多平台分发 + 轻触达，不换 CMS、不锁模型。</p><ul><li><b>交付</b>：生成 + 多平台分发 + 轻触达</li><li><b>适合</b>：创作者 · 电商卖家</li><li><b>依赖</b>：低</li></ul><a href="/product/mflow" class="bt-go">进入 MFlow →</a></div><div data-w="2"><span class="bt-k">落地页</span><h3>Webs Flow · 落地页专精</h3><p>44 种展示区块 + 组件工厂，承接页以小时计上线。</p><ul><li><b>交付</b>：落地页 / 活动页</li><li><b>适合</b>：投手 · 活动运营</li><li><b>依赖</b>：无</li></ul><a href="/product/webs-flow" class="bt-go">进入 Webs Flow →</a></div><div data-w="3"><span class="bt-k">全域数据</span><h3>UserLoop · 全域营销数据中枢</h3><p>埋点 + 身份合并 + 分群，对接任何 MA。</p><ul><li><b>适合</b>：多平台团队 · 代理商</li><li><b>依赖</b>：无</li></ul><a href="/product/userloop" class="bt-go">进入 UserLoop →</a></div><div data-w="3"><span class="bt-k">外部情报</span><h3>inFlow · 情报增长站</h3><p>趋势 / 舆情 / 竞品 + 每日情报。</p><ul><li><b>适合</b>：品牌方 · 内容策划</li><li><b>依赖</b>：无</li></ul><a href="/product/inflow" class="bt-go">进入 inFlow →</a></div><div data-w="3"><span class="bt-k">收款变现</span><h3>PayFlow · 商业变现引擎</h3><p>一行嵌入收款 + 订阅 + 裂变佣金。</p><ul><li><b>适合</b>：创作者 · 独立开发者</li><li><b>依赖</b>：无</li></ul><a href="/product/payflow" class="bt-go">进入 PayFlow →</a></div><div data-w="3"><span class="bt-k">课程交付</span><h3>LearnFlow · 课程与训练营</h3><p>上课 → 进度 → 测验 → 证书，交付闭环。</p><ul><li><b>适合</b>：讲师 · 教练 · 训练营主理人</li><li><b>依赖</b>：轻（收款接 PayFlow）</li></ul><a href="/product/learnflow" class="bt-go">进入 LearnFlow →</a></div>    </div>
  </section>

  <!-- ══ 常见组合：按场景拼装（bento 错落） ══ -->
  <section id="combos" class="sec reveal" data-od-anchor data-od-id="product-combos">
    <div class="sec-head center">
      <span class="kicker">组合方案</span>
      <h2>常见组合：按场景拼装</h2>
      <p class="lead">先看你的场景像哪一种，再决定从哪件开始。</p>
    </div>
    <div class="bento" style="margin-top:24px">
      <div data-w="2"><span class="bt-k">单点起步</span><h3>只缺一环</h3><ul><li>只缺收款 → <b>PayFlow</b></li><li>只缺落地页 → <b>Webs Flow</b></li><li>只缺情报 → <b>inFlow</b></li></ul><p>零依赖，今天就能用。</p></div>
      <div data-w="2"><span class="bt-k">电商增长</span><h3>投放 → 收款 → 沉淀</h3><ul><li>承接页：<b>Webs Flow</b></li><li>收款：<b>PayFlow</b></li><li>数据：<b>UserLoop</b></li></ul><p>一条线跑完，不用手工搬数据。</p></div>
      <div data-w="2"><span class="bt-k">内容营销</span><h3>选题到收录一条线</h3><ul><li>情报：<b>inFlow</b></li><li>生产分发：<b>MFlow</b></li><li>效果回流：<b>UserLoop</b></li></ul><p>一个人当编辑部。</p></div>
      <div data-w="2"><span class="bt-k">知识变现</span><h3>开营到复购</h3><ul><li>交付：<b>LearnFlow</b></li><li>收款：<b>PayFlow</b></li><li>触达：<b>MFlow</b></li></ul><p>学员进度与转化都在一处。</p></div>
      <div data-w="4" class="bt-hi"><span class="bt-k">全家桶</span><h3>一整套自转系统：OpenFlow</h3><ul><li>要一整套系统、数据要在一处、团队要统一后台 → <b>OpenFlow 四力合一</b></li><li>其余六件按需嵌入；所有产品的数据都能平滑并入，不重复建设</li></ul><a href="/demo/capabilities" class="bt-go">看能力全景 →</a></div>
    </div>
  </section>

  <!-- ══ 真实界面 ══ -->
  <section id="real" class="sec reveal" data-od-anchor data-od-id="product-real">
    <div class="sec-head center">
      <span class="kicker">真实界面</span>
      <h2>不是效果图，是正在跑的系统</h2>
      <p class="lead">上面四张图全部截自 OpenFlow 真实后台，不是设计稿。你装上的就是这一套。</p>
    </div>
    <div class="real-grid">
      <figure class="real-shot"><div class="sp-win"><div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">workspace</div></div><img src="/assets/images/product/workspace.png" alt="工作台真实界面" loading="lazy"></div><figcaption><b>工作台</b> · KPI、待办、增长动态一屏掌握</figcaption></figure>
      <figure class="real-shot"><div class="sp-win"><div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">studio</div></div><img src="/assets/images/product/studio.png" alt="自动化编排画布真实界面" loading="lazy"></div><figcaption><b>编排画布</b> · 触发器、条件、动作拖拽成流</figcaption></figure>
      <figure class="real-shot"><div class="sp-win"><div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">audience</div></div><img src="/assets/images/product/audience.png" alt="CDP 用户画像真实界面" loading="lazy"></div><figcaption><b>CDP 画像</b> · 分群、标签、行为轨迹全记录</figcaption></figure>
      <figure class="real-shot"><div class="sp-win"><div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">content-hub</div></div><img src="/assets/images/product/content-hub.png" alt="内容中心真实界面" loading="lazy"></div><figcaption><b>内容中心</b> · 文章、专题、SEO 一站式管理</figcaption></figure>
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
          <button class="btn subtle" id="demoRun" data-od-id="demo-run"><span class="ic"><svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px"><path d="M8 5.5v13l11-6.5-11-6.5Z"/></svg></span><span>运行一轮</span></button>
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
      <div class="st"><div class="st-n">24/7</div><span class="st-en">Always on</span><span class="st-t">增长引擎主动运行</span></div>
      <div class="st"><div class="st-n">100%</div><span class="st-en">Open source</span><span class="st-t">核心能力永久开源</span></div>
      <div class="st"><div class="st-n">1人</div><span class="st-en">Operator</span><span class="st-t">即可驱动整套增长系统</span></div>
    </div>
  </section>

  <!-- ══ 部署方式 ══ -->
  <section id="deploy" class="sec reveal" data-od-anchor data-od-id="product-deploy">
    <div class="sec-head center">
      <span class="kicker">部署方式</span>
      <h2>托管还是自己装，你说了算</h2>
    </div>
    <div class="cols">
      <div>
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg></span>
        <span class="w-tag">SAAS</span>
        <h3>云端 SaaS</h3>
        <p>最快上手，自动更新，无需运维。适合希望一周内跑起来的一人公司。</p>
        <ul class="sp-list"><li><?=$ck?><span>开箱即用，免费起步</span></li><li><?=$ck?><span>功能随版本自动更新</span></li><li><?=$ck?><span>免运维，专注增长</span></li></ul>
      </div>
      <div>
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
        <span class="w-tag">PRIVATE</span>
        <h3>私有化部署</h3>
        <p>数据不出域，核心能力永久开源。适合重视自主可控的团队。</p>
        <ul class="sp-list"><li><?=$ck?><span>数据完全留在内网</span></li><li><?=$ck?><span>核心能力开源自托管</span></li><li><?=$ck?><span>专属技术支持</span></li></ul>
      </div>
      <div>
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.2 2.2m8.4 8.4 2.2 2.2M18.4 5.6l-2.2 2.2M7.8 16.2l-2.2 2.2"/><circle cx="12" cy="12" r="3"/></svg></span>
        <span class="w-tag">HYBRID</span>
        <h3>混合架构</h3>
        <p>核心增长引擎私有化，弹性能力走云端。兼顾自主与扩展。</p>
        <ul class="sp-list"><li><?=$ck?><span>核心引擎私有部署</span></li><li><?=$ck?><span>云端弹性扩缩容</span></li><li><?=$ck?><span>灰度发布与回滚</span></li></ul>
      </div>
    </div>
    <div class="dep-wrap"><table class="dep-table">
      <thead><tr><th></th><th>云端 SaaS</th><th>私有化部署</th><th>混合架构</th></tr></thead>
      <tbody>
        <tr><th>上手时间</th><td><b>当天</b>，注册即用</td><td>1-3 天，含环境准备</td><td>3-7 天，含架构评审</td></tr>
        <tr><th>运维成本</th><td><b>零</b>，平台托管</td><td>自己运维（或购买托管运维）</td><td>核心自控 + 云端弹性</td></tr>
        <tr><th>数据归属</th><td>云端加密存储，可随时导出</td><td><b>完全出不了你的域</b></td><td>核心数据私有，匿名化上云</td></tr>
        <tr><th>适合谁</th><td>想立刻跑起来的一人公司</td><td>重视自主可控的团队</td><td>既要安全又要弹性的成长型团队</td></tr>
        <tr><th>起步价</th><td><b>免费</b></td><td>开源免费 · 支持服务另议</td><td>按需评估</td></tr>
        <tr><th></th><td><button class="btn ghost" data-act="start">免费开始</button></td><td><a class="btn ghost" href="https://github.com/sevenaaaaaaaaa/openflow" target="_blank" rel="noopener">GitHub 自取</a></td><td><a class="btn ghost" href="/about">聊聊需求 →</a></td></tr>
      </tbody>
    </table></div>
  </section>



  <!-- ══ 开放生态 ══ -->
  <section id="open" class="sec reveal" data-od-anchor data-od-id="product-open">
    <div class="sec-head center">
      <span class="kicker">开放生态</span>
      <h2>开放，是默认值（也是芭乐派的坚持）</h2>
    </div>
    <div class="cols n4">
      <div><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4v12a4 4 0 0 0 8 0V4M8 8h8"/></svg></span><h3>开放 API</h3><p>完整 REST API，把 OpenFlow 嵌入你的增长系统。</p></div>
      <div><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13m0 0-4-4m4 4 4-4M4 20h16"/></svg></span><h3>Webhook</h3><p>双向触发与回调，与任意系统实时对接。</p></div>
      <div><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2.5"/><path d="M4 9h16M9 4v5"/></svg></span><h3>Skill / 模板</h3><p>社区与芭乐派模板，一键复用增长打法。</p></div>
      <div><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4m0 12v4M2 12h4m12 0h4M5 5l3 3m8 8 3 3M19 5l-3 3M8 16l-3 3"/><circle cx="12" cy="12" r="3"/></svg></span><h3>永久开源</h3><p>核心能力开源，鱼与渔相结合，策略随工具迭代。</p></div>
    </div>
  </section>



  <!-- ══ FAQ ══ -->
  <section id="faq-sec" class="sec reveal" data-od-anchor data-od-id="product-faq">
    <div class="sec-head center">
      <span class="kicker">常见问题</span>
      <h2>你可能会关心</h2>
    </div>
    <div class="faq-bar"><button type="button" id="faqExpand">全部展开</button></div>
    <div class="faq" id="faq">
      <?php foreach ($FAQS as $i => $f): ?>
      <div class="fq"><button class="fq-q" data-fq="<?=$i?>" aria-expanded="false"><span><?=htmlspecialchars($f[0])?></span><span class="fx"><?=$plus?></span></button><div class="fq-a"><div><p><?=htmlspecialchars($f[1])?></p></div></div></div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ══ 客户评价 ══ -->
  <section id="reviews" class="sec reveal" data-od-anchor data-od-id="product-reviews">
    <div class="sec-head center">
      <span class="kicker">真实反馈</span>
      <h2>用过的人怎么说</h2>
      <p class="lead">不是案例包装，是他们在自己业务里跑出来的话。</p>
    </div>
    <div class="qr notes">
      <div class="q-i q-hi" style="--tilt:-1.1deg"><div class="stars">★★★★★</div><blockquote>「以前每天 3 小时找选题改文章，现在 OpenFlow 爬完信号直接给草稿，我只管把关。效率翻了三倍。」</blockquote><div class="who"><span class="av">陈</span><div><b>陈默</b><span>内容工作室</span></div></div></div>
      <div class="q-i" style="--tilt:0.9deg"><div class="stars">★★★★★</div><blockquote>「销转率从 2.1% 提到 3.8%，靠的不是更多流量，是把转化每一环都拆出来让 Agent 盯。」</blockquote><div class="who"><span class="av">林</span><div><b>林晓</b><span>知识付费</span></div></div></div>
      <div class="q-i" style="--tilt:-0.7deg"><div class="stars">★★★★☆</div><blockquote>「4 个人的团队，周报、监控、跨群通知全交给工作流，省出的时间够多做一个客户。上手花了一周，值。」</blockquote><div class="who"><span class="av">王</span><div><b>王珩</b><span>SaaS 服务商</span></div></div></div>
      <div class="q-i" style="--tilt:1.1deg"><div class="stars">★★★★★</div><blockquote>「一个人做播客加卖课，数据原来散在三个地方。现在选题、发布、转化在一条线上，月底复盘不用再翻六个后台。」</blockquote><div class="who"><span class="av">林</span><div><b>林之然</b><span>播客主理人</span></div></div></div>
      <div class="q-i" style="--tilt:-0.9deg"><div class="stars">★★★★☆</div><blockquote>「开源版装在自己服务器，数据不出域这点对我们行业是硬要求。评估了三个 SaaS，最后是这个谈得最痛快。」</blockquote><div class="who"><span class="av">沈</span><div><b>沈亦舟</b><span>财税咨询</span></div></div></div>
      <div class="q-i" style="--tilt:0.8deg"><div class="stars">★★★★★</div><blockquote>「托管版最值的是睡得着觉：升级、备份、证书彻底不用惦记。一个人创业，省下的心力比钱值钱。」</blockquote><div class="who"><span class="av">青</span><div><b>青禾</b><span>独立电商</span></div></div></div>
    </div>
  </section>

  <!-- ══ 预约诊断（原 60+ 行 inline style → .field/.inp 模块） ══ -->
  <section id="contact" class="reveal" data-od-anchor data-od-id="contact">
    <div class="contact-wrap">
      <div class="ct-pitch">
        <span class="kicker">O.L.B 增长诊断</span>
        <h2>30 分钟，告诉你哪一段最该交给 Agent</h2>
        <p class="lead">用 O.L.B 评分卡把你的增长链拆开，指出漏得最多的那一环，和最该先交出去的那一段。</p>
        <ul class="ct-list">
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span>O.L.B 评分卡：三分钟自查增长健康度</li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span>标出 Agent 化收益最高的环节</li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6.5"/></svg></span>带走一份可执行的改造顺序</li>
        </ul>
      </div>
      <div class="form-card">
        <form id="lead-form" data-lead-form novalidate class="form-grid">
          <div class="grid g2">
            <div class="field"><label for="ld-name">姓名 *</label><input class="inp" id="ld-name" name="name" required placeholder="怎么称呼您" autocomplete="name"></div>
            <div class="field"><label for="ld-company">企业名称 *</label><input class="inp" id="ld-company" name="company" required placeholder="公司 / 组织" autocomplete="organization"></div>
          </div>
          <div class="grid g2">
            <div class="field"><label for="ld-title">职位</label><input class="inp" id="ld-title" name="title" placeholder="如 CMO / 增长负责人"></div>
            <div class="field"><label for="ld-contact">手机或邮箱 *</label><input class="inp" id="ld-contact" name="contact" required placeholder="手机或邮箱" autocomplete="email"></div>
          </div>
          <div class="field"><label for="ld-note">想解决的问题</label><textarea class="inp" id="ld-note" name="note" rows="3" placeholder="简单描述当前网站增长遇到的情况"></textarea></div>
          <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <div id="form-msg" role="status" aria-live="polite"></div>
          <div class="f-row">
            <button type="submit" class="btn ghost" data-od-id="lead-submit">提交预约 →</button>
            <span class="f-note">提交后进入顾问队列，1 个工作日内联系</span>
          </div>
        </form>
      </div>
    </div>
  </section>



  <!-- ══ 收尾 CTA ══ -->
  <section id="next" class="reveal" data-od-anchor data-od-id="product-cta">
    <div class="cta-band">
      <span class="kicker">立即开始</span>
      <h2>装完即用，今天就能长出你的增长引擎</h2>
      <p class="lead">免费开始，无需信用卡。安装后 OpenFlow 自动开始爬取信号、主动洞察、主动转化——每个人都能改造成专属自己的增长系统。</p>
      <ul class="sp-list" style="max-width:820px;margin:14px auto 0;text-align:left"><li><span>核心能力永久开源（MIT），数据与代码都在你自己的服务器</span></li><li><span>托管 / 私有化 / 混合三种部署，随时可迁走，不锁数据</span></li><li><span>危险动作有审批闸门、全程留痕，Agent 只在白名单内执行</span></li></ul>
      <div class="cta-row">
        <button class="btn ghost" data-act="start">免费开始</button>
        <a class="btn ghost" href="https://github.com/sevenaaaaaaaaa/openflow" target="_blank" rel="noopener">GitHub 源码</a>
        <a class="btn ghost" href="/capability">了解 TIPS 能力</a>
        <a class="btn subtle" href="/consultation">预约演示 →</a>
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

<script>
(function(){
  var bar=document.getElementById('faqExpand');
  if(bar){bar.addEventListener('click',function(){
    var fs=document.querySelectorAll('#faq .fq');
    var all=[].every.call(fs,function(f){return f.classList.contains('open');});
    fs.forEach(function(f){f.classList.toggle('open',!all);});
    bar.textContent=all?'全部展开':'全部收起';
  });}
})();
</script>
</body>
</html>
