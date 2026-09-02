<?php
/**
 * 能力 | 芭乐派 · OpenFlow（动态版）
 *
 * v7（2026-09-01）：换骨架不换文案。模块全部来自 assets/modules.css 的共享 archetype；
 * 六项能力原为 JS 渲染的可展开卡片 → 服务端直出的 tab（tab-bar + tab-panel），爬虫可见、键盘可达。
 * 文案与 v6 逐字相同。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
$siteName = site_config_get('site_name', 'OpenFlow');
header('Cache-Control: no-cache, max-age=0');

$I = [
  'bolt'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg>',
  'users'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.5c2.8.3 5 2.6 5 5.5"/></svg>',
  'refresh'=> '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12a8 8 0 1 1-2.3-5.6M20 4v4h-4"/></svg>',
  'check'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.5 5 5 10-11"/></svg>',
  'box'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 8v8l9 5 9-5V8"/></svg>',
  'doc'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h10l4 4v14H7V3Z"/><path d="M17 3v4h4"/></svg>',
];
$CAPS = [
  ['bolt','触达 Touch','内容引擎 + 分发渠道 + 触达体系。正确的时间、渠道、内容，把信息递到用户面前。',[['内容引擎','文章/课程/资料/播客一站式生产'],['分发渠道','多平台自动分发'],['触达体系','强度 × 精度 × 温度']]],
  ['users','洞察 Insight','数据、CDP、舆情、分析。从几百个指标捞出该看的那 3-5 个，把数据变成判断。',[['CDP 画像','统一身份与行为追踪'],['舆情爬取','行业信号自动抓取'],['数据分析','从洞察走到策略']]],
  ['refresh','个性化 Personality','画像、分群、自动化。给对的人，在对的时刻，说对的话。',[['用户分群','行为驱动动态标签'],['营销自动化','行为触发工作流'],['动态内容','千人千面触达']]],
  ['check','销售 Sales','CRM、转化、商城、订阅。从触达到成交，让支付能力流向你。',[['CRM 管道','线索评分与跟进'],['转化组件','落地页/表单/CTA'],['商城订阅','付费闭环与分销']]],
  ['box','自生长 AI Engine','按你设的周期自动推一轮：爬取信号 → AI 洞察 → 生成草稿 → 主动转化。周期用 cron 自己定，装完即用。',[['主动爬取','舆情热点自动收集'],['AI 撰写','生成草稿待人工审核'],['主动转化','从 Marketing 到 Sales']]],
  ['doc','永久开源','核心能力永久开源，Tools 和 Strategy 双向迭代，鱼与渔相结合。',[['Tools 开源','工具即渔具'],['Strategy 同步','最前沿增长策略'],['自托管','数据完全可控']]],
];
$CONN = ['飞书','企业微信','WhatsApp','Notion','GitHub 导入','SMTP 邮件','Ghost','虎皮椒支付','Search Console','Webhook','OpenAPI','MCP']; // 与 product.php 同一份，全部在代码里核过
$ck = '<span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span>';
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
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260903a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260903a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260903a">
<style>
/* 能力页独有：连接器 chips（与产品页同款，等第三处出现再收进共享层） */
.conn-chips{display:flex;flex-wrap:wrap;gap:8px;padding:22px}
.conn-chips .cc{display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 14px;border-radius:12px;background:var(--surface);border:1px solid var(--border);font-size:13px;font-weight:600}
.conn-chips .cc .cd{width:8px;height:8px;border-radius:50%;background:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
</style>
<script src="/assets/seo-inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('capability'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="capability-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">能力 · TIPS 框架</span>
      <h1>四种增长力，<br><i class="si">覆盖你从选题到收款的每一段</i></h1>
      <p class="lead">触达、洞察、个性化、销售——四力合一，覆盖一人公司从获客到成交的每一步。不是功能堆砌，是让 Agent 替你把每个环节跑起来。</p>
      <div class="cta-row">
        <button class="btn primary" data-act="start" data-od-id="capability-cta-start">免费开始</button>
        <a class="btn ghost" href="/product" data-od-id="capability-cta-product">了解产品原理</a>
      </div>
    </div>
  </section>

  <!-- ══ 六项能力（tab） ══ -->
  <section id="caps" class="sec reveal" data-od-anchor data-od-id="capability-caps">
    <div class="tab-bar dense" id="cap-tabs" role="tablist" aria-label="六项能力" data-tabs>
      <?php foreach ($CAPS as $i => $c): ?>
      <button type="button" class="tab-p" role="tab" id="cap-t<?=$i?>" data-hash="<?=['cap-touch','cap-insight','cap-personality','cap-sales','cap-engine','cap-open'][$i] ?? 'cap-'.$i?>" aria-selected="<?=$i===0?'true':'false'?>" aria-controls="cap-p<?=$i?>" data-od-id="cap-<?=$i?>"><span class="ic"><?=$I[$c[0]]?></span><?=htmlspecialchars($c[1])?></button>
      <?php endforeach; ?>
    </div>
    <div class="tab-panels">
      <?php foreach ($CAPS as $i => $c): ?>
      <div class="tab-panel<?=$i===0?' on':''?>" id="cap-p<?=$i?>" role="tabpanel" aria-labelledby="cap-t<?=$i?>">
        <div class="tp-txt">
          <span class="kicker"><?=htmlspecialchars($c[1])?></span>
          <h3><?=htmlspecialchars($c[2])?></h3>
          <div class="tags"><?php foreach ($c[3] as $p): ?><span><?=htmlspecialchars($p[0])?></span><?php endforeach; ?></div>
        </div>
        <div class="tp-steps">
          <?php foreach ($c[3] as $j => $p): ?>
          <div class="tp-step"><span class="tp-n">0<?=$j+1?></span><div><b><?=htmlspecialchars($p[0])?></b><span><?=htmlspecialchars($p[1])?></span></div></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ══ 集成生态 ══ -->
  <section id="connectors" class="sec reveal" data-od-anchor data-od-id="capability-connectors">
    <div class="sec-head center">
      <span class="kicker">集成生态</span>
      <h2>不用推翻你现在在用的东西</h2>
      <p class="lead">下面每一项都已在代码里实现：通知与知识同步走飞书 / 企业微信 / WhatsApp / Notion，内容可从 GitHub 导入，邮件走 SMTP 或 Ghost，支付走虎皮椒，搜索数据来自 Search Console；其余系统用 Webhook / OpenAPI / MCP 接入。</p>
    </div>
    <div class="sp-win" id="connChips2">
      <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">connectors</div></div>
      <div class="conn-chips"><?php foreach ($CONN as $c): ?><span class="cc"><span class="cd"></span><?=htmlspecialchars($c)?></span><?php endforeach; ?></div>
    </div>
  </section>

  <!-- ══ 部署方式 ══ -->
  <section id="deploy" class="sec reveal" data-od-anchor data-od-id="capability-deploy">
    <div class="sec-head center">
      <span class="kicker">部署方式</span>
      <h2>托管还是自己装，你说了算</h2>
    </div>
    <div class="cols">
      <div>
        <span class="ic"><?=$I['box']?></span>
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
  </section>

  <!-- ══ 开放生态 ══ -->
  <section id="open" class="sec reveal" data-od-anchor data-od-id="capability-open">
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

  <!-- ══ 应用场景 ══ -->
  <section id="scenes" class="sec reveal" data-od-anchor data-od-id="capability-scenes">
    <div class="sec-head center">
      <span class="kicker">应用场景</span>
      <h2>四力合起来能跑出什么</h2>
    </div>
    <div class="scn">
      <div class="scn-f">
        <span class="f-tag">最典型场景</span>
        <h3>内容获客</h3>
        <p>舆情爬取找选题 → AI 生成草稿 → 多平台分发。让内容这条线自己转起来。</p>
        <div class="cta-row"><a class="btn subtle" href="/product#demo">看完整增长闭环 →</a></div>
      </div>
      <div class="scn-s">
        <div class="scn-row"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 7-5 5 5 5m8-10 5 5-5 5M13 4l-2 16"/></svg></span><div><h3>私域转化</h3><p>线索池 + 分群 + 自动化触达。把加过来的人，一步步培育成客户。</p></div></div>
        <div class="scn-row"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18M7 14l4-4 4 3 5-6"/></svg></span><div><h3>数据洞察</h3><p>从几百个指标里捞出那 3-5 个。知道该优化哪一环，比优化本身更重要。</p></div></div>
        <div class="scn-row"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 7v5c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V7l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg></span><div><h3>增长中台</h3><p>把 Task Graph 变成自己的资产。Agent 跑流程，人只做 Agent 做不到的五件事。</p></div></div>
      </div>
    </div>
  </section>

  <!-- ══ 适合谁 ══ -->
  <section id="fit" class="sec reveal" data-od-anchor data-od-id="capability-fit">
    <div class="sec-head center">
      <span class="kicker">适合谁</span>
      <h2>这三种状态下，它见效最快</h2>
      <p class="lead">不用先学会所有功能。先从最卡的一环开始，再把可复用的流程逐步接回增长链路。</p>
    </div>
    <div class="tl">
      <div class="tl-step"><span class="tl-n">01</span><span class="tl-y">OPC</span><h3>一个人做增长</h3><p>选题、内容、触达和复盘都由你负责，希望把重复动作交给 Agent，把时间留给判断。</p><a class="btn subtle" style="align-self:flex-start;margin-left:-14px" href="/product#demo">看完整增长闭环 →</a></div>
      <div class="tl-step"><span class="tl-n">02</span><span class="tl-y">SMALL TEAM</span><h3>小团队协同运转</h3><p>已有内容或销售流程，但数据散在多个工具里，需要统一触发、权限和交接。</p><a class="btn subtle" style="align-self:flex-start;margin-left:-14px" href="#connectors">查看连接与部署 →</a></div>
      <div class="tl-step"><span class="tl-n">03</span><span class="tl-y">OPERATOR</span><h3>想把方法变成资产</h3><p>不只想买工具，而是希望把自己的增长打法沉淀成可复制、可迭代的工作流。</p><a class="btn subtle" style="align-self:flex-start;margin-left:-14px" href="#caps">展开六项能力 →</a></div>
    </div>
  </section>

  <!-- ══ 收尾 CTA ══ -->
  <section id="next" class="reveal" data-od-anchor data-od-id="capability-cta">
    <div class="cta-band">
      <span class="kicker">能力在手</span>
      <h2>现在，让增长引擎替你跑起来</h2>
      <p class="lead">TIPS 四力不是宣传页上的名词——它们都能在你今天的业务里主动运行。</p>
      <div class="cta-row">
        <button class="btn primary" data-act="start">免费开始</button>
        <a class="btn ghost" href="/courses">报名课程</a>
      </div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>

<script>
/* capability.php · 页面级脚本：账户 CTA 转交共享外壳。tab / reveal / 回到顶部由 site-shell.js 处理。 */
(function(){
'use strict';
function shell(){return window.OFShell||{}}
function toast(m){var f=shell().toast;if(f)f(m)}
function curUser(){var f=shell().curUser;return f?f():null}
function openAuth(m){var f=shell().openAuth;if(f)f(m);else{var a=document.getElementById('btn-av');if(a)a.click()}}
function openProfile(){var f=shell().openProfile;if(f)f();else openAuth('login')}
Array.prototype.slice.call(document.querySelectorAll('[data-act]')).forEach(function(el){el.addEventListener('click',function(e){e.preventDefault();
  if(el.dataset.act==='start'){var u=curUser();if(u){openProfile();toast('欢迎回来，'+(u.nick||u.email))}else{openAuth('register')}}
})});
})();
</script>
</body>
</html>
