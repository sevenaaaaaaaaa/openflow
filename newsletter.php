<?php
/**
 * Newsletter 订阅中心页 — 订阅内容更新、管理订阅
 *
 * v1（2026-09-05）：共享 archetype。复用 api/newsletter.php 订阅端点 + ofNewsletter JS。
 * 提供订阅表单 + 订阅价值说明 + 常见问题；不做往期邮件归档（缺已发送内容数据源）。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（5 分钟）
if (PageCache::begin('newsletter', 300)) exit;

$siteName = site_config_get('site_name', 'OpenFlow');
$subscribed = false; $subsCount = 0;
$subs = json_read(DATA_DIR . '/newsletter/subscribers.json');
if (is_array($subs)) $subsCount = count($subs);
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>订阅内容更新 | <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="订阅芭乐派增长系统的最新洞察与每周更新，了解内容增长、AI 运营与客户转化的一线实践。绝无打扰，随时可退订。">
<link rel="canonical" href="/newsletter">
<link rel="stylesheet" href="/assets/tokens.css?v=20260903a">
<link rel="stylesheet" href="/assets/modules.css?v=20260903a">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 订阅页独有：hero + 值点点。其余全部来自 modules.css。 */
.news-hero{max-width:720px;margin:0 auto;text-align:center;padding:56px 20px 24px}
.news-hero .kicker{color:var(--accent)}
.news-hero h1{font-size:clamp(30px,5vw,44px);font-weight:800;letter-spacing:-.02em;line-height:1.1;margin:12px 0 14px}
.news-hero p.lead{font-size:16px;color:var(--muted);line-height:1.8;max-width:560px;margin:0 auto}
.sub-form{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:26px}
.sub-form .inp{flex:1 1 260px;max-width:320px;height:48px;font-size:15px}
.sub-form .btn{height:48px;font-size:15px}
.sub-note{font-size:12.5px;color:var(--faint);margin-top:12px}
.news-benefits{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:44px}
.news-benefits .card{display:flex;gap:12px;align-items:flex-start;margin:0}
.news-benefits .ic{flex:0 0 auto;width:34px;height:34px;border-radius:9px;display:grid;place-items:center;background:var(--surface-2);color:var(--accent)}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('newsletter'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section id="top" class="reveal in" data-od-anchor data-od-id="newsletter-hero">
    <div class="news-hero">
      <span class="kicker">Newsletter · 订阅</span>
      <h1>每周获取增长与 AI 运营洞察</h1>
      <p class="lead">我们把内容增长、用户洞察、个性化运营和销售增强的一线实践，沉淀成每周更新的订阅内容。读完能直接上手，绝无打扰。</p>
      <form class="sub-form" onsubmit="return ofNewsletter(this,event)">
        <input class="inp" type="email" placeholder="你的邮箱" required aria-label="邮箱">
        <button class="btn primary" type="submit">订阅</button>
      </form>
      <p class="sub-note">已有 <?=number_format($subsCount)?> 位订阅者 · 随时可退订 · 支持隐私删除</p>
    </div>
  </section>

  <section class="sec reveal" data-od-anchor data-od-id="newsletter-benefits">
    <div class="sec-head row"><div><span class="kicker">为什么订阅</span><h2>不是更多信息，而是能落地的动作</h2></div></div>
    <div class="news-benefits">
      <div class="card"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg></span><div><b>内容增长</b><p class="text-sm" style="margin-top:4px;color:var(--muted)">文章、SEO/GEO、内容日历与分发的一线做法。</p></div></div>
      <div class="card"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/></svg></span><div><b>AI 运营</b><p class="text-sm" style="margin-top:4px;color:var(--muted)">Agent、Flow 与 Loop 如何落地，护栏与审批怎么设计。</p></div></div>
      <div class="card"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16v12H4z"/><path d="M8 4h8M8 11l2 2 4-4"/></svg></span><div><b>客户转化</b><p class="text-sm" style="margin-top:4px;color:var(--muted)">留资、培育、成交与复购的可复用打法与真实案例。</p></div></div>
    </div>
  </section>

  <section class="sec reveal" data-od-anchor data-od-id="newsletter-faq">
    <div class="sec-head row"><div><span class="kicker">常见问题</span><h2>订阅前你可能想问</h2></div></div>
    <div class="card" style="display:flex;flex-direction:column;gap:14px">
      <div><b>订阅后多久收到一次？</b><p class="text-sm" style="margin-top:4px;color:var(--muted)">每周一封，只在你关注的方向有新内容时发送，绝无垃圾轰炸。</p></div>
      <div><b>如何退订？</b><p class="text-sm" style="margin-top:4px;color:var(--muted)">每封邮件底部都有退订链接，或联系 hello@openflow.dev 帮你处理。</p></div>
      <div><b>会拿到我的邮箱做什么？</b><p class="text-sm" style="margin-top:4px;color:var(--muted)">仅用于发送订阅内容与必要提醒，不外泄、不转卖，可随时申请删除。</p></div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
function ofNewsletter(f,e){e.preventDefault();var em=f.querySelector('input').value;fetch('/api/newsletter.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:em,source:'newsletter'})}).then(function(r){return r.json();}).then(function(d){var b=f.querySelector('button');b.textContent=d.ok?'✅ 已订阅':'⚠️ '+(d.error||'失败');});return false;}
</script>
</body>
</html>
