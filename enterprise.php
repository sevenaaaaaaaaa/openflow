<?php
/**
 * 商业发行版申请页 — ToB（SaaS 订阅 / 私有化部署 / 定制开发）
 *
 * v7（2026-09-01）：从 tailwind + 自带 token 副本迁到共享 archetype。
 * 方案卡 → cols 三栏可选；权益 + 表单 → 首页「预约诊断」同款 contact-wrap（ct-pitch + form-card）。
 * 表单字段名 / id / 提交逻辑 / 蜜罐原样保留。文案逐字相同。
 * 游客可提交申请：自动创建 C 端账户 + 邮件设置密码，关联 C/B。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/OrgSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';

$member = member_current();
$plans = org_plans();
$siteName = site_config_get('site_name');
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>商业发行版 | 芭乐派 · OpenFlow</title>
<meta name="description" content="OpenFlow 商业发行版：SaaS 订阅、私有化部署、定制开发。一个 all-in-one 平台，缺什么用插件和技能自己改造。">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260902b">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260902b">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260902b">
<style>
/* 企业页独有：可选方案卡。其余全部来自 modules.css。 */
.plan-card{cursor:pointer;border-radius:var(--r-md);padding:22px 24px;border:1px solid var(--border);background:var(--surface);transition:border-color .2s,box-shadow .2s,transform .25s var(--ease-spring)}
.plan-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm)}
.plan-card.sel{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.plan-card .pc-tag{font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.1em;color:var(--faint);text-transform:uppercase}
.plan-card h3{font-size:18px;font-weight:800;margin-top:10px}
.plan-card p{font-size:13.5px;color:var(--muted);line-height:1.7;margin-top:6px;min-height:44px}
.plan-card .pc-note{font-size:12.5px;color:var(--faint);margin-top:12px;font-family:var(--font-mono)}
.form-grid .g2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
select.inp{appearance:none;background-image:linear-gradient(45deg,transparent 50%,var(--faint) 50%),linear-gradient(135deg,var(--faint) 50%,transparent 50%);background-position:calc(100% - 20px) 55%,calc(100% - 15px) 55%;background-size:5px 5px,5px 5px;background-repeat:no-repeat;padding-right:36px}
@media (max-width:860px){.form-grid .g2{grid-template-columns:1fr}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('product'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="enterprise-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">OPENFLOW BUSINESS</span>
      <h1>一个平台，<br>撑起你公司的<i class="si">整条增长链</i></h1>
      <p class="lead">不是一套套买工具的堆砌。OpenFlow 给你一个 all-in-one 的增长平台，缺什么用插件、技能自己改造——不用再走"加系统"的老路。</p>
      <div class="cta-row">
        <a class="btn primary" href="#apply">申请方案</a>
        <a class="btn ghost" href="/capability">先看能力</a>
      </div>
    </div>
  </section>

  <!-- ══ 三种方案 ══ -->
  <section id="plans" class="sec reveal" data-od-anchor data-od-id="enterprise-plans">
    <div class="sec-head center">
      <span class="kicker">三种拿法</span>
      <h2>托管、私有化、定制——选一个开始</h2>
    </div>
    <div class="grid g3" role="radiogroup" aria-label="需求类型" style="gap:16px">
      <?php $order = ['saas','private','custom']; $tags = ['saas'=>'SAAS','private'=>'PRIVATE','custom'=>'CUSTOM']; foreach ($order as $key): $p = $plans[$key]; ?>
      <div class="plan-card <?=$key==='saas'?'sel':''?>" data-plan="<?=$key?>" role="radio" aria-checked="<?=$key==='saas'?'true':'false'?>" tabindex="0" onclick="pickPlan('<?=$key?>',this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();pickPlan('<?=$key?>',this)}">
        <span class="pc-tag"><?=$tags[$key]?></span>
        <h3><?=htmlspecialchars($p['label'])?></h3>
        <p><?=htmlspecialchars($p['desc'])?></p>
        <div class="pc-note"><?=in_array($key,['saas','private'])?'报价后详谈':'按项目评估'?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ══ 权益 + 申请表（首页预约诊断同款） ══ -->
  <section id="apply" class="reveal" data-od-anchor data-od-id="enterprise-apply">
    <div class="contact-wrap">
      <div class="ct-pitch">
        <span class="kicker">为什么选商业版</span>
        <h2>数据不出域，能力不打折</h2>
        <ul class="ct-list">
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span><span><b>内容引擎 + 增长自动化</b><br>爬信号、出草稿、发内容、盯转化，Agent 跑流程，你只做判断</span></li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span><span><b>CRM + 商城 + 订阅</b><br>线索、订单、会员、订阅一条链路打通，不收"平台税"</span></li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span><span><b>插件 / Skill 生态</b><br>缺什么装什么，不够就用 Skill 自己造，不被任何一家绑定</span></li>
          <li><span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span><span><b>私有化 · 数据自主</b><br>可部署到你的环境，数据不出域，随你二次开发</span></li>
        </ul>
      </div>
      <div class="form-card">
        <div class="sec-head" style="gap:8px;margin-bottom:22px">
          <h3 class="h3" style="font-size:22px">申请商业发行版</h3>
          <p class="lead" style="font-size:14px;line-height:1.75">留下企业信息，商务顾问将在 1 个工作日内联系。提交后我们会为你创建账户，邮件告知设置密码。</p>
        </div>
        <form id="tobForm" class="form-grid" onsubmit="return submitTob(event)">
          <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
          <div class="field"><label for="tob-company">企业名称 *</label><input class="inp" id="tob-company" name="company" required placeholder="公司 / 组织名称"></div>
          <div class="g2">
            <div class="field"><label for="tob-industry">行业</label><input class="inp" id="tob-industry" name="industry" placeholder="如 教育 / SaaS / 电商"></div>
            <div class="field"><label for="tob-size">团队规模</label><select class="inp" id="tob-size" name="size"><option value="">选择规模</option><option>1-10 人</option><option>11-50 人</option><option>51-200 人</option><option>200+ 人</option></select></div>
          </div>
          <div class="field"><label for="planType">需求类型</label>
            <select class="inp" name="plan_type" id="planType">
              <?php foreach ($plans as $k => $p): ?><option value="<?=$k?>"><?=htmlspecialchars($p['label'])?> — <?=htmlspecialchars($p['desc'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="g2">
            <div class="field"><label for="tob-contact">联系人 *</label><input class="inp" id="tob-contact" name="contact_name" required placeholder="怎么称呼您"></div>
            <div class="field"><label for="tob-budget">预算区间</label><select class="inp" id="tob-budget" name="budget"><option value="">选择预算</option><option>5 万以内</option><option>5-20 万</option><option>20-50 万</option><option>50 万以上</option><option>待定</option></select></div>
          </div>
          <div class="g2">
            <div class="field"><label for="tob-email">邮箱 *</label><input class="inp" id="tob-email" type="email" name="email" required placeholder="you@company.com"></div>
            <div class="field"><label for="tob-phone">手机</label><input class="inp" id="tob-phone" type="tel" name="phone" placeholder="选填"></div>
          </div>
          <div class="field"><label for="tob-note">想解决的问题</label><textarea class="inp" id="tob-note" name="note" rows="3" placeholder="简单描述你现在的增长瓶颈、想部署/改造的方向"></textarea></div>
          <div id="tobMsg" class="f-note" style="min-height:20px"></div>
          <div class="f-row">
            <button type="submit" class="btn primary" style="width:100%">提交申请 →</button>
          </div>
          <p class="f-note" style="text-align:center">提交即代表同意我们为跟进需求与您联系</p>
        </form>
      </div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
function pickPlan(key, el) {
  document.querySelectorAll('.plan-card').forEach(function(c){ c.classList.remove('sel'); });
  el.classList.add('sel');
  document.querySelectorAll('.plan-card').forEach(function(c){ c.setAttribute('aria-checked', c===el?'true':'false'); });
  var sel = document.getElementById('planType');
  if (sel) sel.value = key;
}
function submitTob(e) {
  e.preventDefault();
  var f = document.getElementById('tobForm');
  var msg = document.getElementById('tobMsg');
  var btn = f.querySelector('button[type=submit]');
  if (!f.checkValidity()) { f.reportValidity(); return false; }
  btn.disabled = true; btn.textContent = '提交中…';
  msg.style.color = 'var(--muted)'; msg.textContent = '正在提交…';
  var fd = new FormData(f);
  fetch('/api/tob-apply', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
    .then(function(r){ return r.json().catch(function(){ return {}; }); })
    .then(function(d){
      if (d && d.ok) {
        btn.disabled = true; btn.textContent = '✅ 已提交';
        msg.style.color = 'var(--ok)'; msg.textContent = d.message || '申请已提交，商务顾问将在 1 个工作日内联系您。';
        f.querySelector('[name=company]').value = '';
      } else {
        btn.disabled = false; btn.textContent = '提交申请 →';
        msg.style.color = 'var(--danger)'; msg.textContent = (d && d.message) || '提交失败，请稍后再试';
      }
    })
    .catch(function(){ btn.disabled = false; btn.textContent = '提交申请 →'; msg.style.color = 'var(--danger)'; msg.textContent = '网络异常，请稍后再试'; });
  return false;
}
</script>
</body>
</html>
