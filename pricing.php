<?php
/**
 * 定价 | 芭乐派 · OpenFlow
 *
 * 定价策略（docs/GTM.md 既定）：开源优先 · SaaS 标价 + 私有化议价。
 * 所有价格与权益集中在下面的数组里，改数组即可，页面自动渲染。
 * 价格为起步草案，上线前按真实成本校准。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
$siteName = site_config_get('site_name', 'OpenFlow');
header('Cache-Control: no-cache, max-age=0');

/* ══ 定价数据（唯一出处，改这里） ══ */
$PLANS = [
  [
    'id'        => 'oss',
    'name'      => '开源自部署',
    'price'     => '¥0',
    'unit'      => '永久',
    'tagline'   => '核心能力全部开源，永远免费',
    'features'  => ['MIT 协议，可商用、可二次开发', '全部核心能力：内容 / CDP / 自动化 / CRM / 商城', 'PHP 8 + SQLite，10 分钟装在自己的服务器', '数据 100% 在你手里', '社区支持（GitHub Issues / 文档）'],
    'cta'       => ['GitHub 源码', 'https://github.com/sevenaaaaaaaaa/openflow', 'ghost'],
    'cta2'      => ['免费开始', '/register', 'subtle'],
    'highlight' => false,
  ],
  [
    'id'        => 'solo',
    'name'      => '云托管 · 个人版',
    'price'     => '¥99',
    'unit'      => '/月 · 年付 ¥990',
    'tagline'   => '不用管服务器，装完就能用',
    'features'  => ['免运维：升级 / 备份 / 安全补丁全托管', '自定义域名 + HTTPS', '每月 AI 额度（多模型统一入口）', '单站点 · 1 席位', '邮件工单支持（1 个工作日）'],
    'cta'       => ['咨询开通', '/consultation', 'primary'],
    'highlight' => true,
  ],
  [
    'id'        => 'growth',
    'name'      => '云托管 · 增长版',
    'price'     => '¥299',
    'unit'      => '/月 · 年付 ¥2,990',
    'tagline'   => '小团队协作，额度与支持都翻倍',
    'features'  => ['个人版全部权益', '3 席位 + 角色权限', '更高 AI 额度与优先队列', '多站点 / 多语言站群', '优先支持（4 小时内响应）+ 季度增长复盘'],
    'cta'       => ['咨询开通', '/consultation', 'primary'],
    'highlight' => false,
  ],
  [
    'id'        => 'private',
    'name'      => '私有化 / 定制',
    'price'     => '报价后详谈',
    'unit'      => '按需评估',
    'tagline'   => '企业发行版，长在您的环境里',
    'features'  => ['部署在您的服务器 / 专有云', 'SSO、审计导出、SLA、安全评审', '专属陪跑与实施服务', '定制开发：连接器 / 报表 / 工作流', '升级保障与版本维护承诺'],
    'cta'       => ['联系企业顾问', '/enterprise', 'primary'],
    'highlight' => false,
  ],
];

$COMPARE = [
  ['部署位置', '你的服务器', 'OpenFlow 云', 'OpenFlow 云', '你的环境 / 专有云'],
  ['日常运维', '自行维护', '全托管', '全托管', '共同承担'],
  ['核心功能', '全部开源', '全部可用', '全部可用', '全部可用 + 定制'],
  ['AI 额度', '用你自己的 API Key', '含月度额度', '更高额度 + 优先队列', '按需'],
  ['成员席位', '不限（自管理）', '1 席位', '3 席位', '按需'],
  ['数据归属', '100% 你的', '可随时完整导出', '可随时完整导出', '100% 你的'],
  ['支持', '社区', '工单（1 个工作日）', '优先（4 小时）+ 复盘', '专属顾问 + SLA'],
  ['适合谁', '动手能力强 / 数据敏感', '一个人的公司', '2-5 人小团队', '有合规与集成要求的企业'],
];

$FAQ = [
  ['开源版和付费版功能有区别吗？', '没有功能锁。核心能力全部开源，商业版卖的是托管、服务与支持——省下的是服务器运维、备份、升级和排查问题的时间。'],
  ['先用开源版，以后能迁到云托管吗？', '可以。数据结构同源，提供导出 / 导入工具，迁移路径是双向的；反之亦然，云托管数据可随时完整导出回到自己的服务器。'],
  ['AI 功能要额外花钱吗？', '开源版接入你自己的模型 Key，费用直接付给模型厂商；云托管版含月度额度，用完可加购，额度与用量在后台透明可查。'],
  ['可以退款吗？', '云托管版支持 7 天无理由退款（年付按月折算）；私有化与定制按合同约定。'],
  ['价格会变吗？', '开源版永久免费是写进 LICENSE 的承诺；云托管价格调整会提前 60 天通知现有客户，老客户续费锁定原价。'],
];
$ck = '<span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span>';
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>定价 | 芭乐派 · OpenFlow</title>
<?php if (function_exists('seo_head')): seo_head(['title' => '定价 | 芭乐派 · OpenFlow', 'description' => 'OpenFlow 定价：开源自部署永久免费；云托管个人版 ¥99/月起、增长版 ¥299/月；私有化与定制按需报价。核心能力无功能锁，商业版卖的是托管与服务。', 'canonical' => site_config_get('site_url') . '/pricing']); endif; ?>
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260903a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260903a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260911a">
<style>
/* 定价页局部：方案卡 / 对比表 */
.plans{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:26px;align-items:stretch}
@media (max-width:1080px){.plans{grid-template-columns:1fr 1fr}}
@media (max-width:640px){.plans{grid-template-columns:1fr}}
.plan{position:relative;display:flex;flex-direction:column;gap:14px;background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:26px 22px}
.plan.hl{border-color:var(--accent);box-shadow:0 8px 30px -18px var(--accent)}
.plan .tag{position:absolute;top:-11px;left:22px;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:99px;background:var(--accent);color:#fff}
.plan h3{margin:0;font-size:16.5px}
.plan .price{font-size:34px;font-weight:800;letter-spacing:-.5px}
.plan .unit{font-size:12.5px;color:var(--faint);margin-left:6px;font-weight:500}
.plan .tg{font-size:13px;color:var(--muted);margin:0}
.plan ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:9px;flex:1}
.plan li{display:flex;gap:8px;font-size:13.5px;line-height:1.55;color:var(--muted)}
.plan li .ck{flex:none;width:16px;height:16px;color:var(--accent-strong);margin-top:2px}
.plan .cta-row{margin-top:4px}
.plan .btn{flex:1;justify-content:center}
.pr-table{width:100%;border-collapse:separate;border-spacing:0;background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-top:22px}
.pr-table th,.pr-table td{padding:13px 18px;text-align:left;font-size:13.5px;border-bottom:1px solid var(--border);vertical-align:top;line-height:1.7}
.pr-table tr:last-child th,.pr-table tr:last-child td{border-bottom:none}
.pr-table thead th{background:var(--surface-2);font-size:14px}
.pr-table tbody th{width:110px;color:var(--faint);font-weight:500;font-size:12.5px;background:var(--surface-2)}
.pr-table td b{color:var(--accent-strong)}
.fit{display:flex;flex-direction:column;gap:10px;margin-top:22px}
.fit .fi{display:flex;gap:12px;align-items:flex-start;background:var(--surface);border:1px solid var(--border-soft);border-radius:14px;padding:14px 18px;font-size:14px}
.fit .fi b{flex:none}
</style>
<script src="/assets/inject.js?v=20260830b" data-cfasync="false" data-site-inject></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('pricing'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="pricing-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">定价 · 芭乐派 OpenFlow</span>
      <h1>核心能力<b class="si">永久免费</b>，<br>只为省下的时间付费</h1>
      <p class="lead">功能没有锁：全部核心能力都在开源版里。云托管卖的是「不用管服务器」，私有化卖的是「长在你的合规环境里」。选错了随时迁，数据双向可搬。</p>
      <div class="cta-row">
        <a class="btn primary" href="/register" data-od-id="pricing-cta-start">免费开始（开源）</a>
        <a class="btn ghost" href="#plans" data-od-id="pricing-cta-plans">看方案对比</a>
      </div>
      <div class="trust"><span class="dot"></span>MIT 开源 · 数据 100% 归你 · 7 天无理由退款</div>
    </div>
  </section>

  <!-- ══ 谁该选哪个 ══ -->
  <section id="fit" class="sec reveal" data-od-anchor data-od-id="pricing-fit" style="padding-bottom:0">
    <div class="fit">
      <div class="fi"><b>爱折腾、在意数据？</b><span>开源版装在自己服务器，PHP 8 + SQLite 十分钟起站，功能一点不少。</span></div>
      <div class="fi"><b>一个人创业，时间比钱贵？</b><span>云托管个人版，运维备份升级全不用管，每月一杯咖啡钱。</span></div>
      <div class="fi"><b>有团队、有合规要求？</b><span>增长版带席位与权限；私有化把整套系统放进你的环境，SSO / SLA / 评审都能谈。</span></div>
    </div>
  </section>

  <!-- ══ 方案 ══ -->
  <section id="plans" class="sec reveal" data-od-anchor data-od-id="pricing-plans">
    <div class="sec-head center">
      <span class="kicker">方案</span>
      <h2>四个方案，一个承诺</h2>
      <p class="lead">开源永久免费写进 LICENSE；付费方案随时可退、随时可迁。</p>
    </div>
    <div class="plans">
      <?php foreach ($PLANS as $p): ?>
      <div class="plan<?=$p['highlight']?' hl':''?>" id="plan-<?=htmlspecialchars($p['id'])?>">
        <?php if ($p['highlight']): ?><span class="tag">最多人选</span><?php endif; ?>
        <h3><?=htmlspecialchars($p['name'])?></h3>
        <div><span class="price"><?=htmlspecialchars($p['price'])?></span><span class="unit"><?=htmlspecialchars($p['unit'])?></span></div>
        <p class="tg"><?=htmlspecialchars($p['tagline'])?></p>
        <ul>
          <?php foreach ($p['features'] as $f): ?><li><?=$ck?><span><?=htmlspecialchars($f)?></span></li><?php endforeach; ?>
        </ul>
        <div class="cta-row">
          <?php [$t,$h,$cls] = $p['cta']; $__ext = strpos($h,'http')===0; ?>
          <a class="btn <?=$cls?>" href="<?=htmlspecialchars($h)?>" <?=$__ext?'target="_blank" rel="noopener"':''?>><?=htmlspecialchars($t)?></a>
          <?php if (!empty($p['cta2'])): [$t2,$h2,$cls2] = $p['cta2']; ?><a class="btn <?=$cls2?>" href="<?=htmlspecialchars($h2)?>"><?=htmlspecialchars($t2)?></a><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ══ 对比表 ══ -->
  <section id="compare" class="sec reveal" data-od-anchor data-od-id="pricing-compare">
    <div class="sec-head center">
      <span class="kicker">对比</span>
      <h2>一张表看清楚</h2>
    </div>
    <div style="overflow-x:auto">
    <table class="pr-table">
      <thead><tr><th></th><?php foreach ($PLANS as $p): ?><th><?=htmlspecialchars($p['name'])?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <?php foreach ($COMPARE as $row): ?>
        <tr><th><?=htmlspecialchars($row[0])?></th><?php foreach (array_slice($row,1) as $cell): ?><td><?=htmlspecialchars($cell)?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>

  <!-- ══ FAQ ══ -->
  <section id="faq" class="sec reveal" data-od-anchor data-od-id="pricing-faq">
    <div class="sec-head center">
      <span class="kicker">常见问题</span>
      <h2>付费前你会想问的</h2>
    </div>
    <div class="cols">
      <?php foreach ($FAQ as $q): ?>
      <div><h3><?=htmlspecialchars($q[0])?></h3><p><?=htmlspecialchars($q[1])?></p></div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ══ 收尾 CTA ══ -->
  <section id="cta" class="sec reveal" data-od-anchor data-od-id="pricing-cta">
    <div class="hero-center">
      <h2>先免费用起来，<i class="si">有效果再谈付费</i></h2>
      <p class="lead">开源版今天就能装；拿不准哪个方案，留个需求我们帮你选。</p>
      <div class="cta-row">
        <a class="btn primary" href="/register">免费开始（开源）</a>
        <a class="btn ghost" href="/consultation">预约诊断 →</a>
      </div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
