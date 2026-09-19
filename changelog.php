<?php
/**
 * 更新日志 | 芭乐派 · OpenFlow
 *
 * 2026-09-19：页面改版转正，顺带把老首页（第一代）收进这里作纪念。
 * 只列「用户能感知的变化」，不写内部重构流水账。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
$siteName = site_config_get('site_name', 'OpenFlow');
header('Cache-Control: no-cache, max-age=0');

$entries = [
    [
        'date' => '2026-09-19',
        'title' => '首页与产品 / 能力页改版转正',
        'kicker' => '首页 · 产品矩阵 · 能力全景',
        'body' => '首页补上「七件独立产品，按需组合」「三条路，通向同一个地方」「用过的人怎么说」三段；产品页从「一个平台跑通整条链路」改成产品矩阵总览，按场景给出常见组合；能力页补上每个能力在后台的真实界面入口、能力在谁的机器上跑、以及可被程序调用的扩展点。原来的产品页与能力页没有丢，降级成了更细一层：OpenFlow 单产品与能力详情仍在 /product/openflow 与 /capability/openflow。',
        'links' => [
            ['label' => '看现在的首页', 'href' => '/'],
            ['label' => '看产品矩阵', 'href' => '/product'],
            ['label' => '看能力全景', 'href' => '/capability'],
        ],
    ],
    [
        'date' => '2026-09-02',
        'title' => '首页换叙事：从「设计你的增长系统」到「你不缺怎么做，你缺该做什么」',
        'kicker' => '首页',
        'body' => '第一代首页讲的是「把增长系统设计出来，让 Agent 替你跑流程」；第二代把话说直白了些——多数人不是不会做，而是不知道该做什么。同一年里有人在裁员、有人在一个人开公司，差别不在勤奋，在于有没有一条能持续跑的增长链路。这一版后来成为今天的首页骨架。',
        'links' => [
            ['label' => '第一代首页（快照纪念）', 'href' => '/legacy/home-gen1-2026-09-01.html'],
        ],
    ],
];
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (function_exists('seo_head')): seo_head(['title' => '更新日志 | 芭乐派 · OpenFlow', 'description' => '首页、产品矩阵与能力页的改版记录，以及第一代首页的快照存档。', 'canonical' => site_config_get('site_url') . '/changelog']); endif; ?>
<title>更新日志 · 芭乐派 · OpenFlow</title>
<meta name="description" content="首页、产品矩阵与能力页的改版记录，以及第一代首页的快照存档。">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}</script>
<!-- 共享外壳样式契约：必须在页面级 <style> 之前，页面样式才能覆盖模块层。 -->
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 更新日志：条目卡（其余全部来自 modules.css 的共享 archetype） */
.cl-wrap{max-width:820px;margin:26px auto 0}
.cl-item{display:grid;grid-template-columns:104px 1fr;gap:18px;padding:20px 0;border-bottom:1px solid var(--border)}
.cl-item:last-child{border-bottom:none}
.cl-date{font-size:12.5px;font-weight:700;color:var(--faint);letter-spacing:.02em;padding-top:3px}
.cl-date .cl-k{display:block;font-weight:600;font-size:11.5px;color:var(--muted);margin-top:4px;line-height:1.5}
.cl-body h3{margin:0 0 8px;font-size:16.5px;font-weight:700;letter-spacing:-.01em}
.cl-body p{margin:0 0 12px;font-size:13.5px;line-height:1.85;color:var(--muted)}
.cl-links{display:flex;gap:8px;flex-wrap:wrap}
@media (max-width:720px){.cl-item{grid-template-columns:1fr;gap:8px}}
</style>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('changelog'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="hero">
    <div class="hero-center">
      <span class="kicker">更新日志</span>
      <h1>改了什么，摊开说</h1>
      <p class="lead">只记用户能感知的变化：页面改版、能力上线、老页面的去向。内部重构不占篇幅。</p>
      <div class="proof-strip">
        <span>当前首页为第二代叙事</span><span>产品页 = 七件产品矩阵</span><span>能力页 = 四力 × 42 模块</span>
      </div>
    </div>
  </section>

  <!-- ══ 记录 ══ -->
  <section id="log" class="sec reveal" data-od-anchor data-od-id="changelog-list">
    <div class="sec-head center">
      <span class="kicker">按时间倒序</span>
      <h2>记录</h2>
    </div>
    <div class="cl-wrap">
      <div class="cl-list">
        <?php foreach ($entries as $e): ?>
        <article class="cl-item">
          <div class="cl-date"><?=htmlspecialchars($e['date'])?><span class="cl-k"><?=htmlspecialchars($e['kicker'])?></span></div>
          <div class="cl-body">
            <h3><?=htmlspecialchars($e['title'])?></h3>
            <p><?=htmlspecialchars($e['body'])?></p>
            <div class="cta-row">
              <?php foreach ($e['links'] as $l): ?>
              <a class="btn ghost" href="<?=htmlspecialchars($l['href'])?>"><?=htmlspecialchars($l['label'])?></a>
              <?php endforeach; ?>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <p class="note" style="margin-top:18px;font-size:12.5px;color:var(--muted)">老页面不删也不改，只是退到下一层；第一代首页以静态快照保留，仅作纪念。</p>
    </div>
  </section>

  <!-- ══ 收口 ══ -->
  <section id="next" class="reveal" data-od-anchor data-od-id="changelog-cta">
    <div class="cta-band">
      <span class="kicker">下一步</span>
      <h2>想从今天的版本开始看？</h2>
      <p class="lead">首页讲清楚这是什么，产品矩阵讲清楚怎么拼，能力全景讲清楚每一段怎么落地。</p>
      <div class="cta-row">
        <a class="btn primary" href="/">回到首页</a>
        <a class="btn ghost" href="/product">看产品矩阵</a>
        <a class="btn ghost" href="/capability">看能力全景</a>
      </div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>

<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 7-7 7 7M12 5v14"/></svg></button>
<script src="/assets/site-shell.js?v=<?=defined('OF_SHELL_VER') ? OF_SHELL_VER : '1'?>" defer></script>
</body>
</html>
