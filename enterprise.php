<?php
/**
 * 商业发行版申请页 — ToB（SaaS 订阅 / 私有化部署 / 定制开发）
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
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>商业发行版 | 芭乐派 · OpenFlow</title>
<meta name="description" content="OpenFlow 商业发行版：SaaS 订阅、私有化部署、定制开发。一个 all-in-one 平台，缺什么用插件和技能自己改造。">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260830b" defer></script>
<style>
:root{
  --bg:oklch(96.5% .016 85);--bg-soft:oklch(94% .02 85);
  --surface:oklch(100% 0 0 / .62);--surface-strong:oklch(100% 0 0 / .88);
  --fg:oklch(22% .02 70);--muted:oklch(46% .016 70);--faint:oklch(51% .014 75);
  --border:oklch(86% .014 80);--border-strong:oklch(76% .02 80);
  --hover:oklch(22% .02 70 / .055);
  --accent:oklch(52% .17 258);--accent-strong:oklch(46% .17 258);--accent-soft:oklch(52% .17 258/.12);--on-accent:oklch(100% 0 0);
  --ok:oklch(58% .17 152);--ok-soft:oklch(58% .17 152/.12);
  --warn:oklch(66% .15 75);--warn-soft:oklch(66% .15 75/.14);
  --danger:oklch(55% .2 25);--danger-soft:oklch(55% .2 25/.12);
  --glass:oklch(100% 0 0 / .5);
  --shadow:0 24px 60px -24px oklch(30% .04 80 / .3);--shadow-sm:0 10px 28px -14px oklch(30% .04 80 / .24);
  --r-lg:26px;--r-md:18px;--r-sm:12px;
  --grad:linear-gradient(135deg,oklch(52% .17 258),oklch(58% .16 285));
  --font-body:"Space Grotesk",-apple-system,BlinkMacSystemFont,"PingFang SC","HarmonyOS Sans SC","MiSans","Segoe UI",system-ui,sans-serif;
  --font-display:"Space Grotesk","PingFang SC","HarmonyOS Sans SC","MiSans","Segoe UI",system-ui,sans-serif;
  --font-mono:ui-monospace,'SF Mono','JetBrains Mono',Menlo,monospace;
  color-scheme:light;
}
body{font-family:var(--font-body);background:var(--bg);color:var(--fg);-webkit-font-smoothing:antialiased;line-height:1.6;overflow-x:clip}
.kicker{font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;color:var(--accent);text-transform:uppercase}
.plan-card{border:1px solid var(--border);border-radius:var(--r-lg);padding:26px;background:var(--surface);cursor:pointer;transition:border-color .2s,box-shadow .2s,transform .2s}
.plan-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm)}
.plan-card.sel{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.fld{margin-bottom:16px}
.fld label{display:block;font-size:12.5px;font-weight:700;color:var(--muted);margin-bottom:6px}
.inp{width:100%;height:44px;padding:0 14px;border-radius:12px;border:1px solid var(--border);background:var(--surface-strong);color:var(--fg);font-size:14px;outline:none;transition:border-color .2s,box-shadow .2s}
.inp:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
textarea.inp{height:auto;padding:12px 14px;resize:vertical;line-height:1.6}
select.inp{appearance:none;background-image:linear-gradient(45deg,transparent 50%,var(--muted) 50%),linear-gradient(135deg,var(--muted) 50%,transparent 50%);background-position:calc(100% - 18px) 19px,calc(100% - 13px) 19px;background-size:5px 5px;background-repeat:no-repeat;padding-right:34px}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260826b" data-cfasync="false" data-page="home"></script>

<section style="padding:clamp(30px,5vw,64px) 0">
  <div class="mx-auto px-5" style="max-width:1080px">
    <div style="text-align:center;max-width:720px;margin:0 auto">
      <span class="kicker">OPENFLOW BUSINESS</span>
      <h1 style="font-size:clamp(32px,5vw,52px);font-weight:800;letter-spacing:-.03em;line-height:1.1;margin:14px 0 12px">一个平台，撑起你公司的整条增长链</h1>
      <p style="color:var(--muted);font-size:16px;line-height:1.9">不是一套套买工具的堆砌。OpenFlow 给你一个 all-in-one 的增长平台，缺什么用插件、技能自己改造——不用再走"加系统"的老路。</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;margin-top:40px">
      <?php $order = ['saas','private','custom']; $icons = ['saas'=>'⬢','private'=>'⬡','custom'=>'◇']; foreach ($order as $key): $p = $plans[$key]; ?>
      <div class="plan-card <?=$key==='saas'?'sel':''?>" data-plan="<?=$key?>" onclick="pickPlan('<?=$key?>',this)">
        <div style="font-size:26px"><?=$icons[$key]?></div>
        <h3 style="font-size:19px;font-weight:700;margin:12px 0 4px"><?=htmlspecialchars($p['label'])?></h3>
        <p style="color:var(--muted);font-size:13.5px;min-height:42px"><?=htmlspecialchars($p['desc'])?></p>
        <div style="margin-top:14px;font-size:12.5px;color:var(--faint)"><?=in_array($key,['saas','private'])?'报价后详谈':'按项目评估'?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(24px,4vw,48px);margin-top:56px;align-items:start">
      <div>
        <span class="kicker">为什么选商业版</span>
        <h2 style="font-size:26px;font-weight:800;letter-spacing:-.02em;margin:12px 0 18px">数据不出域，能力不打折</h2>
        <ul style="list-style:none;padding:0;display:flex;flex-direction:column;gap:14px">
          <li style="display:flex;gap:12px"><span style="color:var(--ok);font-weight:800">✓</span><div><b>内容引擎 + 增长自动化</b><div style="color:var(--muted);font-size:13.5px">爬信号、出草稿、发内容、盯转化，Agent 跑流程，你只做判断</div></div></li>
          <li style="display:flex;gap:12px"><span style="color:var(--ok);font-weight:800">✓</span><div><b>CRM + 商城 + 订阅</b><div style="color:var(--muted);font-size:13.5px">线索、订单、会员、订阅一条链路打通，不收"平台税"</div></div></li>
          <li style="display:flex;gap:12px"><span style="color:var(--ok);font-weight:800">✓</span><div><b>插件 / Skill 生态</b><div style="color:var(--muted);font-size:13.5px">缺什么装什么，不够就用 Skill 自己造，不被任何一家绑定</div></div></li>
          <li style="display:flex;gap:12px"><span style="color:var(--ok);font-weight:800">✓</span><div><b>私有化 · 数据自主</b><div style="color:var(--muted);font-size:13.5px">可部署到你的环境，数据不出域，随你二次开发</div></div></li>
        </ul>
      </div>

      <div class="card" style="border:1px solid var(--border);border-radius:var(--r-lg);padding:28px;background:var(--surface)">
        <h3 style="font-size:19px;font-weight:800">申请商业发行版</h3>
        <p style="color:var(--muted);font-size:13px;margin:6px 0 18px">留下企业信息，商务顾问将在 1 个工作日内联系。提交后我们会为你创建账户，邮件告知设置密码。</p>
        <form id="tobForm" onsubmit="return submitTob(event)">
          <input type="text" name="website" class="hp" style="position:absolute;left:-9999px;height:0;width:0;opacity:0" tabindex="-1" autocomplete="off">
          <div class="fld"><label>企业名称 *</label><input class="inp" name="company" required placeholder="公司 / 组织名称"></div>
          <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
            <div class="fld"><label>行业</label><input class="inp" name="industry" placeholder="如 教育 / SaaS / 电商"></div>
            <div class="fld"><label>团队规模</label>
              <select class="inp" name="size"><option value="">选择规模</option><option>1-10 人</option><option>11-50 人</option><option>51-200 人</option><option>200+ 人</option></select>
            </div>
          </div>
          <div class="fld"><label>需求类型</label>
            <select class="inp" name="plan_type" id="planType">
              <?php foreach ($plans as $k => $p): ?><option value="<?=$k?>"><?=htmlspecialchars($p['label'])?> — <?=htmlspecialchars($p['desc'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
            <div class="fld"><label>联系人 *</label><input class="inp" name="contact_name" required placeholder="怎么称呼您"></div>
            <div class="fld"><label>预算区间</label>
              <select class="inp" name="budget"><option value="">选择预算</option><option>5 万以内</option><option>5-20 万</option><option>20-50 万</option><option>50 万以上</option><option>先了解，暂未定</option></select>
            </div>
          </div>
          <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
            <div class="fld"><label>邮箱 *</label><input class="inp" type="email" name="email" required placeholder="you@company.com"></div>
            <div class="fld"><label>手机</label><input class="inp" type="tel" name="phone" placeholder="选填"></div>
          </div>
          <div class="fld"><label>想解决的问题</label><textarea class="inp" name="note" rows="3" placeholder="简单描述你现在的增长瓶颈、想部署/改造的方向"></textarea></div>
          <div id="tobMsg" style="font-size:13px;margin-bottom:10px;min-height:20px"></div>
          <button type="submit" class="btn" style="width:100%;padding:14px;border-radius:999px;background:var(--accent);color:var(--on-accent);font-weight:700;font-size:15px;border:none;cursor:pointer">提交申请 →</button>
          <p style="text-align:center;color:var(--faint);font-size:11.5px;margin-top:10px">提交即代表同意我们为跟进需求与您联系</p>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
function pickPlan(key, el) {
  document.querySelectorAll('.plan-card').forEach(function(c){ c.classList.remove('sel'); });
  el.classList.add('sel');
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
