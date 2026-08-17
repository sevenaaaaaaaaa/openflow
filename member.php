<?php
/**
 * 前台用户中心 — 注册/登录/个人中心
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/Gamification.php';
require_once __DIR__ . '/lib/SubscriptionSystem.php';
require_once __DIR__ . '/lib/MembershipSystem.php';
require_once __DIR__ . '/lib/MessageSystem.php';
require_once __DIR__ . '/lib/OrgSystem.php';

$view = $_GET['view'] ?? (member_current() ? 'dashboard' : 'login');
$member = member_current();
$next = $_GET['next'] ?? '';

// 登录后跳转
if (!$member && $view === 'dashboard') {
    header('Location: member.php?view=login' . ($next ? '&next=' . urlencode($next) : ''));
    exit;
}

$orders = json_read(DATA_DIR . '/shop/orders.json');
$myOrders = $member ? array_values(array_filter($orders, fn($o) => ($o['member_id'] ?? '') === $member['id'])) : [];

$pageTitle = ['login' => '登录', 'register' => '注册', 'dashboard' => '个人中心', 'profile' => '个人资料', 'password' => '修改密码', 'reset-password' => '重置密码'][$view] ?? '用户中心';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$pageTitle?> | <?=site_config_get("site_name")?></title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
/* ── 设计语言统一：token 语义工具类（终版契约） ── */
  .text-fg{color:var(--fg)}.text-muted{color:var(--muted)}.text-faint{color:var(--faint)}
  .text-accent{color:var(--accent)}.text-ok{color:var(--ok)}.text-danger{color:var(--danger)}
  .text-on-accent{color:var(--on-accent)}
  body{background:var(--bg);font-family:var(--font-body)}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:16px;box-shadow:0 4px 16px rgba(30,30,30,.05)}
  .field{margin-bottom:16px}
  .field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--fg)}
  .field input,.field select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;outline:none;box-sizing:border-box}
  .field input:focus{border-color:var(--accent)}
  .nav-item{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;font-size:14px;color:var(--muted);cursor:pointer;transition:.12s;text-decoration:none}
  .nav-item:hover{background:var(--bg)}
  .nav-item.active{background:var(--accent);color:var(--on-accent);font-weight:600}
  .tag{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600}
  .tag.green{background:var(--ok-soft);color:var(--ok)}
  .tag.orange{background:var(--warn-soft);color:var(--warn)}
  .tag.gray{background:var(--bg);color:var(--muted)}
</style>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
</head>
<body class="min-h-screen">
  <!-- 顶部导航 -->
  <header class="border-b" style="background:var(--glass-bright);border-color:var(--border);backdrop-filter:blur(10px);position:sticky;top:0;z-index:40">
    <div class="mx-auto max-w-site px-5 py-3 flex items-center justify-between" style="max-width:1100px">
      <a href="/" class="font-bold text-lg text-fg"><?=site_config_get("site_name")?></a>
      <nav class="flex items-center gap-4 text-sm">
        <a href="/academy" class="text-muted">OpenFlow 社区</a>
        <a href="/courses" class="text-muted hover:text-fg">课程</a>
        <?php if ($member): ?>
        <a href="member.php" class="font-semibold text-ok"><?=htmlspecialchars($member['name'])?></a>
        <a href="javascript:memberLogout()" class="text-danger">退出</a>
        <?php else: ?>
        <a href="member.php?view=login" class="font-semibold text-ok">登录</a>
        <a href="member.php?view=register" class="rounded-full bg-[var(--accent)] text-on-accent px-5 py-2 font-semibold">注册</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <?php if (!$member && in_array($view, ['login','register','reset-password'])): ?>
  <!-- 登录/注册/密码重置 -->
  <div class="mx-auto px-5 py-14" style="max-width:440px">
    <?php if ($view === 'reset-password'): ?>
      <?php include_member_reset_password(); ?>
    <?php else: ?>
    <div class="card p-8">
      <h1 class="text-2xl font-bold text-center"><?=$view==='login'?'欢迎回来':'创建账号'?></h1>
      <p class="text-center text-sm text-muted mt-2 mb-8"><?=$view==='login'?'登录你的 OpenFlow 账号':'注册后可购买课程、成为讲师'?></p>

      <?php if ($view === 'login'): ?>
      <form onsubmit="memberLogin(event)">
        <div class="field"><label>邮箱或手机号</label><input type="text" name="account" id="l_account" required placeholder="you@example.com 或手机号"></div>
        <div class="field"><label>密码</label><input type="password" name="password" id="l_password" required placeholder="••••••"></div>
        <button type="submit" class="w-full rounded-full py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">登录</button>
      </form>
      <p class="text-center text-sm text-muted mt-6">还没有账号？<a href="member.php?view=register" class="text-accent font-semibold">立即注册</a></p>
      <?php else: ?>
      <form onsubmit="memberRegister(event)">
        <div class="field"><label>姓名</label><input type="text" name="name" id="r_name" required placeholder="你的真实姓名"></div>
        <div class="field"><label>手机号</label><div style="display:flex;gap:8px">
          <input type="tel" name="phone" id="r_phone" required placeholder="11 位手机号" style="flex:1">
          <button type="button" class="rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap" style="background:var(--bg);color:var(--accent)" onclick="memberSendCaptcha(document.getElementById('r_phone').value)">发验证码</button>
        </div></div>
        <div class="field"><label>邮箱</label><input type="email" name="email" id="r_email" required placeholder="you@example.com"></div>
        <div class="field"><label>密码</label><input type="password" name="password" id="r_password" required minlength="6" placeholder="至少 6 位"></div>
        <div class="field"><label>短信验证码</label><input type="text" name="captcha" id="r_captcha" required placeholder="6 位验证码"></div>
        <?php if (!empty($_GET['ref'])): ?><input type="hidden" name="referral" value="<?=htmlspecialchars($_GET['ref'])?>"><?php endif; ?>
        <button type="submit" class="w-full rounded-full py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">注册</button>
      </form>
      <p class="text-center text-sm text-muted mt-6">已有账号？<a href="member.php?view=login" class="text-accent font-semibold">直接登录</a></p>
      <?php endif; ?>
      <div id="memberMsg" style="margin-top:14px"></div>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif ($member): ?>
  <!-- 个人中心 -->
  <div class="mx-auto px-5 py-10" style="max-width:1100px">
    <div class="grid gap-6" style="grid-template-columns:240px 1fr">
      <!-- 侧边栏 -->
      <div class="card p-3 h-fit" style="position:sticky;top:20px">
        <div class="px-3 py-4 border-b border-[var(--border)] mb-2">
          <div class="font-bold text-fg"><?=htmlspecialchars($member['name'])?></div>
          <div class="text-sm text-muted mt-1"><?=htmlspecialchars($member['email'])?></div>
          <?php $mLevel = gamification_level_of($member['points'] ?? 0); ?>
          <?php $mEnt = member_entitlements($member); ?>
          <div class="mt-2 text-sm font-semibold"><?=$mLevel['icon']?> <?=htmlspecialchars($mLevel['name'])?> <span class="text-xs text-faint font-normal">· <?=$member['points']??0?> 积分</span></div>
          <span class="tag green mt-1" style="display:inline-block"><?=$mEnt['icon']?> <?=htmlspecialchars($mEnt['tier_name'])?></span>
          <?php if (sub_is_active($member['id'])): ?><span class="tag orange mt-1">⭐ 订阅</span><?php endif; ?>
          <?php if (!empty($member['ambassador'])): ?><span class="tag green mt-1"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5"/><path d="m8.5 13-2 8 5.5-3 5.5 3-2-8"/></svg></span> 推荐大使</span><?php endif; ?>
        </div>
        <a class="nav-item <?=$view==='dashboard'?'active':''?>" href="member.php?view=dashboard">🏠 个人中心</a>
        <a class="nav-item <?=$view==='membership'?'active':''?>" href="member.php?view=membership"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 12L2 9l4-6Z"/><path d="M2 9h20M9 3 7 9l5 12M15 3l2 6-5 12"/></svg></span> 会员中心</a>
        <a class="nav-item" href="member.php?view=level"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3M10 14h4v3h-4zM12 17v3M8 21h8"/></svg></span> 我的等级</a>
        <a class="nav-item" href="member.php?view=subscribe">⭐ 付费订阅</a>
        <a class="nav-item" href="member.php?view=orders"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg></span> 我的订单</a>
        <a class="nav-item" href="member.php?view=courses"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg></span> 我的课程</a>
        <a class="nav-item" href="member.php?view=ambassador"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5"/><path d="m8.5 13-2 8 5.5-3 5.5 3-2-8"/></svg></span> 推荐大使</a>
        <a class="nav-item" href="member.php?view=teacher">👨‍<span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-6 9 6-9 6-9-6Z"/><path d="M6 11.5V17c0 1.5 2.7 3 6 3s6-1.5 6-3v-5.5M21 9v5"/></svg></span> 成为讲师</a>
        <a class="nav-item" href="member.php?view=submit"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg></span> 投稿文章</a>
        <a class="nav-item" href="/consultation?view=my">🤝 我的1v1咨询</a>
        <a class="nav-item <?=$view==='org'?'active':''?>" href="member.php?view=org"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V5l7-2v18M12 21V9l7 2v10"/></svg></span> 企业控制台</a>
        <a class="nav-item <?=$view==='developer'?'active':''?>" href="member.php?view=developer"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 9-3 3 3 3M13 15h4"/><path d="M7 4h13a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg></span> 开发者中心</a>
        <a class="nav-item <?=$view==='distribution'?'active':''?>" href="member.php?view=distribution"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span> 分销中心</a>
        <a class="nav-item" href="/messages.php">🔔 站内信<?php $msgUnread = inbox_unread($member); if ($msgUnread): ?> <span style="background:var(--danger);color:var(--surface);border-radius:999px;padding:1px 7px;font-size:11px"><?=$msgUnread?></span><?php endif; ?></a>
        <div style="border-top:1px solid var(--border);margin:8px 0"></div>
        <a class="nav-item <?=$view==='profile'?'active':''?>" href="member.php?view=profile">👤 个人资料</a>
        <a class="nav-item <?=$view==='password'?'active':''?>" href="member.php?view=password"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span> 修改密码</a>
      </div>

      <!-- 内容区 -->
      <div>
        <?php $tab = $_GET['view'] ?? 'dashboard'; ?>
        <?php if ($tab === 'dashboard'): ?>
        <div class="card p-8">
          <h2 class="text-xl font-bold mb-6">个人中心</h2>
          <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
            <div class="p-5 rounded-xl" style="background:var(--bg)"><div class="text-2xl font-bold"><?=count(array_filter($myOrders, fn($o)=>$o['status']==='paid'))?></div><div class="text-sm text-muted">已购课程</div></div>
            <div class="p-5 rounded-xl" style="background:var(--bg)"><div class="text-2xl font-bold"><?=count($myOrders)?></div><div class="text-sm text-muted">全部订单</div></div>
            <?php if (!empty($member['ambassador'])): ?>
            <div class="p-5 rounded-xl" style="background:var(--bg)"><div class="text-2xl font-bold">¥<?=$member['balance']??0?></div><div class="text-sm text-muted">佣金余额</div></div>
            <?php endif; ?>
          </div>
          <p class="text-sm text-muted mt-6">从「我的课程」开始你的学习之旅。</p>
        </div>
        <?php elseif ($tab === 'membership'): include_member_membership($member); ?>
        <?php elseif ($tab === 'orders'): include_member_orders($myOrders); ?>
        <?php elseif ($tab === 'level'): include_member_level($member); ?>
        <?php elseif ($tab === 'subscribe'): include_member_subscribe($member); ?>
        <?php elseif ($tab === 'courses'): include_member_courses($member); ?>
        <?php elseif ($tab === 'ambassador'): include_member_ambassador($member); ?>
        <?php elseif ($tab === 'teacher'): include_member_teacher($member); ?>
        <?php elseif ($tab === 'submit'): include_member_submit($member); ?>
        <?php elseif ($tab === 'profile'): include_member_profile($member); ?>
        <?php elseif ($tab === 'password'): include_member_password($member); ?>
        <?php elseif ($tab === 'org'): include_member_org($member); ?>
        <?php elseif ($tab === 'developer'): include_member_developer($member); ?>
        <?php elseif ($tab === 'distribution'): include_member_distribution($member); ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

<script>
function memberMsg(html, isErr) {
  document.getElementById('memberMsg').innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;' + (isErr?'background:var(--danger-soft);color:var(--danger)':'background:var(--ok-soft);color:var(--ok)') + '">' + html + '</div>';
}
function memberLogin(e) {
  e.preventDefault();
  var fd = new FormData();
  fd.append('action','login');
  fd.append('account', document.getElementById('l_account').value);
  fd.append('password', document.getElementById('l_password').value);
  fetch('/api/member.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) location.href = '/member.php'; else memberMsg(d.error, true); });
}
function memberRegister(e) {
  e.preventDefault();
  var fd = new FormData(e.target);
  fd.append('action','register');
  fetch('/api/member.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) location.href = '/member.php'; else memberMsg(d.error, true); });
}
function memberSendCaptcha(target) {
  if (!target) return alert('请先填手机号');
  var fd = new FormData();
  fd.append('action','send_captcha');
  fd.append('target', target);
  fetch('/api/member.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ memberMsg(d.ok ? d.message : d.error, !d.ok); });
}
function memberLogout() {
  var fd = new FormData(); fd.append('action','logout');
  fetch('/api/member.php', { method:'POST', body: fd }).then(function(){ location.href = '/'; });
}
function subscribePlan(planId) {
  if (!confirm('确认订阅该计划？将跳转支付。')) return;
  var fd = new FormData();
  fd.append('action','create_subscription');
  fd.append('plan_id', planId);
  fetch('/api/shop.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok) { alert(d.error); return; }
      var form = document.createElement('form');
      form.method = 'POST'; form.action = d.payment.gateway;
      Object.keys(d.payment.params).forEach(function(k){
        var input = document.createElement('input'); input.type='hidden'; input.name=k; input.value=d.payment.params[k];
        form.appendChild(input);
      });
      document.body.appendChild(form); form.submit();
    });
}
</script>
<?php
// ─── 各 tab 内容（内联函数）───
function include_member_membership($member): void {
    $e = member_entitlements($member);
    $lists = member_benefit_list($member);
    $plans = mem_plans();
    $current = $e['tier'];
    ?>
    <div class="card p-8">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding-bottom:20px;border-bottom:1px solid var(--border)">
        <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--ok));display:grid;place-items:center;font-size:32px"><?=$e['icon']?></div>
        <div style="flex:1">
          <h2 class="text-xl font-bold"><?=htmlspecialchars($e['tier_name'])?></h2>
          <div class="text-sm text-muted mt-1"><?=$e['points']?> 积分 · <?=htmlspecialchars($e['level']['name'] ?? '')?> 等级<?php if ($e['subscription']): ?> · ⭐ 订阅中<?php endif; ?></div>
        </div>
        <?php if ($current === 'free'): ?>
        <a href="member.php?view=subscribe" class="px-6 py-3 rounded-full font-bold" style="background:var(--accent);color:var(--on-accent)">开通会员 →</a>
        <?php endif; ?>
      </div>

      <!-- 权益总览 -->
      <?php foreach ($lists as $cat => $items): ?>
      <div class="mt-6">
        <h3 class="text-sm font-bold text-muted mb-3"><?=htmlspecialchars($cat)?></h3>
        <div class="grid gap-2" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
          <?php foreach ($items as $it): ?>
          <div style="padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:var(--bg-soft)">
            <div style="font-size:13px;font-weight:600"><?=htmlspecialchars($it['权益'])?></div>
            <div style="font-size:12px;margin-top:3px;<?=strpos($it['状态'],'🔒')!==false?'color:var(--faint)':'color:var(--ok)'?>"><?=htmlspecialchars($it['状态'])?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- 会员计划 -->
      <div class="mt-8">
        <h3 class="text-sm font-bold text-muted mb-3"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg></span> 会员计划</h3>
        <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
          <?php foreach ($plans as $p): $isCurrent = ($p['id'] === $current); ?>
          <div style="padding:18px;border-radius:14px;border:2px solid <?=$isCurrent?'var(--accent)':'var(--border)'?>;<?=$isCurrent?'background:var(--warn-soft)':''?>">
            <div class="text-2xl"><?=htmlspecialchars($p['icon'] ?? '')?></div>
            <div class="font-bold mt-1"><?=htmlspecialchars($p['name'] ?? '')?></div>
            <div class="text-sm mt-1" style="color:var(--ok)"><?=$p['price']>0 ? '¥' . $p['price'] . '/' . htmlspecialchars($p['period'] ?? '') : '免费'?></div>
            <ul class="text-xs text-muted mt-2" style="line-height:1.8;list-style:none;padding:0">
              <?php foreach ($p['benefits'] ?? [] as $b): ?><li>✓ <?=htmlspecialchars($b)?></li><?php endforeach; ?>
            </ul>
            <?php if ($isCurrent): ?><div class="text-xs mt-3 font-bold" style="color:var(--warn)">当前等级</div>
            <?php elseif ($p['price'] > 0): ?><a href="member.php?view=subscribe" class="inline-block mt-3 text-xs font-bold px-4 py-2 rounded-full" style="background:var(--accent);color:var(--on-accent)">升级 →</a>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php
}

function include_member_level($member): void {
    $points = $member['points'] ?? 0;
    $levels = gamification_levels();
    $current = gamification_level_of($points);
    // 下一级
    $next = null;
    foreach ($levels as $l) if ($l['min_points'] > $points) { $next = $l; break; }
    echo '<div class="card p-8"><h2 class="text-xl font-bold mb-2">我的等级</h2>';
    echo '<div class="mb-6" style="background:var(--bg);padding:20px;border-radius:14px">';
    echo '<div class="text-3xl font-bold">' . $current['icon'] . ' ' . htmlspecialchars($current['name']) . '</div>';
    echo '<div class="text-sm text-muted mt-1">当前积分：<strong>' . $points . '</strong></div>';
    if ($next) {
        $need = $next['min_points'] - $points;
        echo '<div style="height:8px;background:var(--border);border-radius:99px;margin-top:12px;overflow:hidden"><div style="height:100%;width:' . min(100, round($points/$next['min_points']*100)) . '%;background:linear-gradient(90deg,var(--ok),var(--accent))"></div></div>';
        echo '<div class="text-xs text-muted mt-2">距 ' . $next['icon'] . ' ' . htmlspecialchars($next['name']) . ' 还需 <strong>' . $need . '</strong> 积分</div>';
    } else { echo '<div class="text-xs text-ok mt-2">已达最高等级 🎉</div>'; }
    echo '</div>';
    // 等级权益
    echo '<h3 class="font-bold text-sm mb-3">等级权益</h3><div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">';
    foreach ($levels as $l) {
        $isCur = $l['key'] === $current['key'];
        echo '<div style="padding:14px;border-radius:12px;border:2px solid ' . ($isCur ? 'var(--accent)' : 'var(--bg)') . ';background:' . ($isCur ? 'var(--warn-soft)' : 'var(--surface)') . '">' .
            '<div class="font-bold text-sm">' . $l['icon'] . ' ' . htmlspecialchars($l['name']) . '</div>' .
            '<div class="text-xs text-muted mt-1">' . $l['min_points'] . ' 积分起</div>' .
            '<div class="text-xs text-faint mt-1">' . implode('、', array_map(fn($p)=>['post'=>'发帖','comment'=>'评论','vote'=>'投票','no_review'=>'免审核','featured'=>'推荐位'][$p]??$p, $l['perms'])) . '</div>' .
            '</div>';
    }
    echo '</div>';
    // 积分记录
    echo '<h3 class="font-bold text-sm mb-3 mt-6">积分记录</h3>';
    $log = $member['points_log'] ?? [];
    if (empty($log)) echo '<p class="text-sm text-muted">暂无积分记录</p>';
    else {
        echo '<table class="w-full text-sm"><thead><tr class="text-left text-muted border-b border-[var(--border)]"><th class="py-2">积分</th><th>原因</th><th>时间</th></tr></thead><tbody>';
        foreach (array_slice(array_reverse($log),0,20) as $pl) {
            echo '<tr class="border-b border-[var(--bg)]"><td class="py-2" style="color:' . ($pl['points']>=0?'var(--ok)':'var(--danger)') . '">' . ($pl['points']>=0?'+':'') . $pl['points'] . '</td><td>' . htmlspecialchars($pl['reason']) . '</td><td class="text-muted">' . htmlspecialchars(substr($pl['time']??'',0,16)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}
function include_member_subscribe($member): void {
    $settings = sub_settings();
    $plans = array_values(array_filter(sub_get_plans(), fn($p) => !empty($p['enabled'])));
    $mySub = sub_get_member($member['id']);
    $active = sub_is_active($member['id']);
    echo '<div class="card p-8"><h2 class="text-xl font-bold mb-4">⭐ 付费订阅</h2>';
    if ($active) {
        echo '<div style="background:var(--ok-soft);padding:16px;border-radius:14px;color:var(--ok);margin-bottom:16px">🎉 你已是订阅会员，有效期至 <strong>' . htmlspecialchars($mySub['expires_at'] ?? '') . '</strong></div>';
    }
    if (empty($settings['enabled'])) {
        echo '<p class="text-sm text-muted">订阅暂未开放，敬请期待。</p>';
    } elseif (empty($plans)) {
        echo '<p class="text-sm text-muted">暂无可订阅计划。</p>';
    } else {
        echo '<div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">';
        foreach ($plans as $p) {
            $period = ($p['period'] ?? 'month') === 'month' ? '/月' : '/年';
            echo '<div style="border:2px solid var(--border);border-radius:14px;padding:20px;position:relative">' .
                '<div class="font-bold text-lg">' . htmlspecialchars($p['name']) . '</div>' .
                '<div class="text-2xl font-bold mt-2">¥' . number_format($p['price']??0,2) . '<span class="text-sm text-faint font-normal">' . $period . '</span></div>' .
                '<div class="text-sm text-muted mt-2 min-h-10">' . htmlspecialchars($p['description'] ?? '') . '</div>' .
                '<button onclick="subscribePlan(\'' . htmlspecialchars($p['id']) . '\')" class="mt-4 w-full rounded-full py-2.5 font-bold" style="background:var(--accent);color:var(--on-accent)">订阅</button>' .
                '</div>';
        }
        echo '</div>';
        echo '<p class="text-xs text-faint mt-4">海外用户：' . ($settings['ghost_enabled'] ? '<a href="' . htmlspecialchars($settings['ghost_api_url']) . '/#/portal" target="_blank" class="text-accent">通过 Ghost 订阅 →</a>' : '未开启 Ghost 海外订阅') . '</p>';
    }
    echo '</div>';
}
function include_member_orders(array $orders): void {
    echo '<div class="card p-8"><h2 class="text-xl font-bold mb-6">我的订单</h2>';
    if (empty($orders)) { echo '<p class="text-sm text-muted">暂无订单，去逛逛课程吧 → <a href="/courses" class="text-accent">浏览课程</a></p>'; }
    else {
        echo '<table class="w-full text-sm"><thead><tr class="text-left text-muted border-b border-[var(--border)]"><th class="py-2">订单号</th><th>课程</th><th>金额</th><th>状态</th><th>时间</th></tr></thead><tbody>';
        foreach ($orders as $o) {
            $statusTag = ['paid'=>'已支付','pending'=>'待支付','cancelled'=>'已取消','refunded'=>'已退款'][$o['status']] ?? $o['status'];
            $tagCls = $o['status']==='paid'?'green':($o['status']==='pending'?'orange':'gray');
            echo '<tr class="border-b border-[var(--bg)]"><td class="py-3 text-muted">' . htmlspecialchars(substr($o['id'],-10)) . '</td><td>' . htmlspecialchars($o['course_title']) . '</td><td>¥' . number_format($o['amount']??0,2) . '</td><td><span class="tag ' . $tagCls . '">' . $statusTag . '</span></td><td class="text-muted">' . htmlspecialchars(substr($o['created_at']??'',0,10)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}
function include_member_courses($member): void {
    require_once __DIR__ . '/lib/ProgressSystem.php';
    $orders = array_filter(json_read(DATA_DIR . '/shop/orders.json'), fn($o) => ($o['member_id']??'') === $member['id'] && $o['status'] === 'paid');
    $courseIds = array_unique(array_map(fn($o)=>$o['course_id'], $orders));
    $courses = json_read(DATA_DIR . '/courses/index.json');
    echo '<div class="card p-8"><h2 class="text-xl font-bold mb-6">我的课程</h2>';
    if (empty($courseIds)) { echo '<p class="text-sm text-muted">你还没有购买课程，去逛逛 → <a href="/courses" class="text-accent">浏览课程</a></p>'; }
    else {
        echo '<div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">';
        foreach ($courses as $c) {
            if (!in_array($c['id'], $courseIds)) continue;
            $s = progress_summary($member['id'], $c['id'], $c);
            echo '<a href="course-player.php?id=' . urlencode($c['id']) . '" class="card p-5 text-decoration-none" style="text-decoration:none;color:inherit">' .
                '<div class="font-bold mb-1">' . htmlspecialchars($c['title']) . '</div>' .
                ($s['percent'] > 0 ?
                    '<div class="mt-2" style="height:6px;background:var(--border);border-radius:99px;overflow:hidden"><div style="height:100%;width:' . $s['percent'] . '%;background:linear-gradient(90deg,var(--ok),var(--ok),var(--accent))"></div></div>' .
                    '<div class="text-xs text-muted mt-1">已学 ' . $s['done'] . '/' . $s['total'] . ' 节 · ' . $s['percent'] . '%</div>'
                    : '<div class="text-sm text-muted">' . count($c['chapters']??[]) . ' 章 · 点击开始学习 →</div>') .
                '</a>';
        }
        echo '</div>';
    }
    echo '</div>';
}
function include_member_ambassador($member): void {
    if (empty($member['ambassador'])) {
        echo '<div class="card p-8 text-center"><div style="font-size:44px">🏅</div><h2 class="text-xl font-bold mt-4 mb-2">成为推荐大使</h2><p class="text-sm text-muted max-w-md mx-auto mb-6">分享你的专属链接，好友通过链接购买课程，你将获得佣金。</p><a href="api/ambassador.php?action=apply" class="inline-block rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">立即申请成为大使</a></div>';
    } else {
        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST']??'');
        echo '<div class="card p-8"><h2 class="text-xl font-bold mb-4">我的推广</h2>' .
            '<div class="mb-6"><div class="text-sm font-semibold mb-2">我的专属推广链接</div>' .
            '<div class="flex gap-2"><input readonly value="' . $base . '/member.php?view=register&ref=' . htmlspecialchars($member['referral_code']) . '" style="flex:1;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px"><button class="rounded-full px-4 py-2 text-sm font-semibold" style="background:var(--bg)" onclick="navigator.clipboard.writeText(this.previousElementSibling.value).then(()=>alert(\'已复制\'))">复制</button></div></div>' .
            '<div class="grid gap-4 mb-6" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">' .
            '<div class="p-5 rounded-xl" style="background:var(--bg)"><div class="text-2xl font-bold">' . ($member['ambassador_stats']['clicks']??0) . '</div><div class="text-sm text-muted">点击</div></div>' .
            '<div class="p-5 rounded-xl" style="background:var(--bg)"><div class="text-2xl font-bold">' . ($member['ambassador_stats']['orders']??0) . '</div><div class="text-sm text-muted">成交</div></div>' .
            '<div class="p-5 rounded-xl" style="background:var(--bg)"><div class="text-2xl font-bold">¥' . number_format($member['balance']??0,2) . '</div><div class="text-sm text-muted">佣金余额</div></div></div>' .
            '<p class="text-sm text-muted">佣金规则由管理员在后台「分销设置」中配置。可在后台查看提现记录。</p></div>';
    }
}
function include_member_teacher($member): void {
    $status = $member['teacher_status'] ?? 'none';
    $labels = ['none'=>'未申请','pending'=>'审核中','approved'=>'已通过','rejected'=>'未通过'];
    $tagCls = ['none'=>'gray','pending'=>'orange','approved'=>'green','rejected'=>'gray'][$status];
    echo '<div class="card p-8"><h2 class="text-xl font-bold mb-6">成为讲师</h2>';
    if ($status === 'approved') echo '<p class="text-sm" style="background:var(--ok-soft);padding:12px 16px;border-radius:10px;color:var(--ok)">🎉 你已成为讲师，可以在「投稿文章」中发布内容了。</p>';
    elseif ($status === 'pending') echo '<p class="text-sm" style="background:var(--warn-soft);padding:12px 16px;border-radius:10px;color:var(--warn)">申请审核中，请耐心等待。</p>';
    else {
        echo '<p class="text-sm text-muted mb-6">分享你的专业经验，成为 OpenFlow 认证讲师。提交申请后由管理员审核。</p>';
        if ($status === 'rejected') echo '<p class="text-sm mb-4" style="background:var(--danger-soft);padding:10px 14px;border-radius:10px;color:var(--danger)">上次申请未通过，可重新提交。</p>';
        echo '<form onsubmit="return false" id="teacherForm" class="grid gap-4" style="grid-template-columns:1fr 1fr">' .
            '<div class="field"><label>讲师简介</label><textarea name="intro" rows="3" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px" placeholder="介绍你的专业领域和经验"></textarea></div>' .
            '<div class="field"><label>擅长方向</label><input name="expertise" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px" placeholder="如：SEO/GEO、内容策略、AI 运营"></div>' .
            '<div style="grid-column:1/-1"><button class="rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)" onclick="submitTeacher()">提交申请</button></div></form>';
    }
    echo '</div>';
}
function include_member_submit($member): void {
    echo '<div class="card p-8"><h2 class="text-xl font-bold mb-2">投稿文章</h2><p class="text-sm text-muted mb-6">提交后由管理员审核，审核通过后发布。</p>' .
        '<form onsubmit="return false" id="submitForm" class="grid gap-4" style="grid-template-columns:1fr 1fr">' .
        '<div style="grid-column:1/-1" class="field"><label>文章标题</label><input name="title" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px" placeholder="标题"></div>' .
        '<div class="field"><label>分类</label><select name="category" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px"><option value="insight">增长洞察</option><option value="leadership">内容与 SEO</option><option value="ai_ops">AI 运营</option><option value="industry">行业实践</option></select></div>' .
        '<div class="field"><label>摘要</label><input name="excerpt" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px" placeholder="一句话摘要"></div>' .
        '<div style="grid-column:1/-1" class="field"><label>正文</label><textarea name="content" rows="8" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px" placeholder="文章正文"></textarea></div>' .
        '<div style="grid-column:1/-1"><button class="rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)" onclick="submitArticle()">提交投稿</button></div></form>' .
        '<div id="submitMsg" style="margin-top:14px"></div></div>';
}

// ─── 个人资料编辑 ───
function include_member_profile($member): void {
    ?>
    <div class="card p-8">
      <h2 class="text-xl font-bold mb-6">个人资料</h2>
      <form onsubmit="return updateProfile(event)">
        <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
          <div class="field">
            <label>昵称</label>
            <input type="text" name="nickname" value="<?=htmlspecialchars($member['nickname'] ?? '')?>" placeholder="你的昵称">
          </div>
          <div class="field">
            <label>头像 URL</label>
            <input type="url" name="avatar" value="<?=htmlspecialchars($member['avatar'] ?? '')?>" placeholder="https://example.com/avatar.jpg">
          </div>
          <div class="field">
            <label>手机号</label>
            <input type="tel" name="phone" value="<?=htmlspecialchars($member['phone'] ?? '')?>" placeholder="手机号码">
          </div>
          <div class="field">
            <label>个人网站</label>
            <input type="url" name="website" value="<?=htmlspecialchars($member['website'] ?? '')?>" placeholder="https://yoursite.com">
          </div>
          <div class="field">
            <label>公司</label>
            <input type="text" name="company" value="<?=htmlspecialchars($member['company'] ?? '')?>" placeholder="公司名称">
          </div>
          <div class="field">
            <label>职位</label>
            <input type="text" name="job_title" value="<?=htmlspecialchars($member['job_title'] ?? '')?>" placeholder="职位名称">
          </div>
          <div style="grid-column:1/-1" class="field">
            <label>个人简介</label>
            <textarea name="bio" rows="3" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px" placeholder="介绍一下自己"><?=htmlspecialchars($member['bio'] ?? '')?></textarea>
          </div>
        </div>
        <div style="margin-top:20px;display:flex;gap:12px">
          <button type="submit" class="rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">保存修改</button>
          <a href="member.php?view=password" class="rounded-full px-8 py-3 font-bold border border-[var(--border)]" style="color:var(--muted)">修改密码</a>
        </div>
        <div id="profileMsg" style="margin-top:14px"></div>
      </form>
    </div>
    <?php
}

// ─── 修改密码 ───
function include_member_password($member): void {
    ?>
    <div class="card p-8">
      <h2 class="text-xl font-bold mb-6">修改密码</h2>
      <form onsubmit="return changePassword(event)">
        <div class="field">
          <label>当前密码</label>
          <input type="password" name="old_password" required placeholder="输入当前密码">
        </div>
        <div class="field">
          <label>新密码</label>
          <input type="password" name="new_password" required placeholder="至少 8 位" minlength="8">
        </div>
        <div class="field">
          <label>确认新密码</label>
          <input type="password" name="confirm_password" required placeholder="再次输入新密码" minlength="8">
        </div>
        <button type="submit" class="rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">修改密码</button>
        <div id="passwordMsg" style="margin-top:14px"></div>
      </form>
    </div>
    <?php
}

// ─── 企业控制台（ToB）───
function include_member_org($member): void {
    $org = org_by_member($member['id'] ?? '');
    $statuses = org_statuses();
    $plans = org_plans();
    ?>
    <div class="card p-8">
      <h2 class="text-xl font-bold mb-6">企业控制台</h2>
      <?php if ($org): $status = $statuses[$org['status']]['label'] ?? $org['status']; ?>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px">
          <div style="width:44px;height:44px;border-radius:12px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:20px">🏢</div>
          <div>
            <div style="font-size:17px;font-weight:800"><?=htmlspecialchars($org['name'])?></div>
            <div style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($org['industry'])?> / <?=htmlspecialchars($org['size'])?></div>
          </div>
          <span class="tag <?=$org['status']==='active'?'green':($org['status']==='lead'?'orange':'gray')?>" style="margin-left:auto"><?=htmlspecialchars($status)?></span>
        </div>

        <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:22px">
          <div style="padding:16px;border-radius:14px;background:var(--bg)"><div style="font-size:22px;font-weight:800"><?=org_plan_label($org['plan_type'])?></div><div style="font-size:12px;color:var(--muted)">合作方案</div></div>
          <div style="padding:16px;border-radius:14px;background:var(--bg)"><div style="font-size:22px;font-weight:800"><?=count((array)($org['members'] ?? []))?></div><div style="font-size:12px;color:var(--muted)">团队成员</div></div>
          <div style="padding:16px;border-radius:14px;background:var(--bg)"><div style="font-size:22px;font-weight:800"><?=htmlspecialchars($org['budget'] ?: '—')?></div><div style="font-size:12px;color:var(--muted)">预算区间</div></div>
        </div>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:10px">团队成员</h3>
        <?php foreach ((array)($org['members'] ?? []) as $mid): $m = member_get($mid); ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-soft)">
          <div style="width:34px;height:34px;border-radius:50%;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;font-weight:700;font-size:13px"><?=strtoupper(mb_substr(($m['name'] ?? ($m['email'] ?? '?')),0,1))?></div>
          <div style="flex:1;min-width:0"><div style="font-size:13.5px;font-weight:600"><?=htmlspecialchars($m['name'] ?? '')?></div><div style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($m['email'] ?? '')?></div></div>
          <?php if (($org['admin_member_id'] ?? '') === $mid): ?><span class="tag green">管理员</span><?php endif; ?>
        </div>
        <?php endforeach; ?>

        <p style="font-size:12.5px;color:var(--faint);margin-top:16px">更多成员由商务顾问在合作确认后邀请加入。企业专属支持与部署进度将在此展示。</p>
      <?php else: ?>
        <div style="text-align:center;padding:30px 0">
          <div style="font-size:40px;margin-bottom:10px">🏢</div>
          <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">你的企业还没有商业版申请</h3>
          <p style="font-size:13.5px;color:var(--muted);max-width:420px;margin:0 auto 18px">为团队申请 OpenFlow 商业发行版（SaaS 订阅 / 私有化部署 / 定制开发），一个平台撑起整条增长链。</p>
          <a href="/enterprise" class="rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">申请商业版 →</a>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

// ─── 开发者中心（入驻/开发套件/我的产品/提交） ───
function include_member_developer($member): void {
    $devStatus = $member['developer_status'] ?? 'none';
    $types = skill_types();
    $mine = skill_by_author($member['id']);
    ?>
    <div class="card p-8">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px">
        <div style="width:44px;height:44px;border-radius:12px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:20px">🧑‍💻</div>
        <div>
          <h2 class="text-xl font-bold">开发者中心</h2>
          <div style="font-size:12px;color:var(--muted)">把技能 / 主题做成产品，上架 OpenFlow 市场，被更多人使用</div>
        </div>
        <span class="tag <?=$devStatus==='approved'?'green':($devStatus==='pending'?'orange':'gray')?>" style="margin-left:auto">
          <?=['none'=>'未申请','pending'=>'审核中','approved'=>'已认证开发者','rejected'=>'申请被拒'][$devStatus] ?? '未申请'?>
        </span>
      </div>

      <?php if ($devStatus === 'none'): ?>
      <!-- 申请成为开发者 -->
      <div style="padding:20px;border-radius:14px;background:var(--bg);margin-bottom:20px">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:6px">成为开发者，上传你的第一个产品</h3>
        <p style="font-size:13px;color:var(--muted);margin-bottom:16px">认证后即可提交 Skill（AI 指令 / 工具 / 工作流）和主题模板。审核通过后上架市场，供所有用户启用。</p>
        <form onsubmit="return applyDev(event)">
          <div class="field"><label>开发者简介 *（至少 10 字，介绍你会做什么）</label><textarea name="bio" rows="2" required placeholder="如：专注增长类 Skill，擅长小红书文案与 SEO"></textarea></div>
          <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
            <div class="field"><label>擅长的技能方向</label><input type="text" name="skills" placeholder="SEO / 文案 / 自动化…"></div>
            <div class="field"><label>个人/团队主页（选填）</label><input type="text" name="website" placeholder="https://…"></div>
          </div>
          <button type="submit" class="rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">提交申请</button>
          <div id="applyDevMsg" style="margin-top:12px;font-size:13px"></div>
        </form>
      </div>
      <?php elseif ($devStatus === 'pending'): ?>
      <div style="padding:24px;border-radius:14px;background:var(--bg);text-align:center">
        <div style="font-size:34px;margin-bottom:8px">⏳</div>
        <h3 style="font-size:16px;font-weight:700">申请审核中</h3>
        <p style="font-size:13px;color:var(--muted);margin-top:6px">管理员审核通过后，你就可以上传产品了。</p>
      </div>
      <?php elseif ($devStatus === 'rejected'): ?>
      <div style="padding:20px;border-radius:14px;background:var(--danger-soft);margin-bottom:20px">
        <h3 style="font-size:15px;font-weight:700;color:var(--danger);margin-bottom:6px">申请未通过</h3>
        <p style="font-size:13px;color:var(--muted)">可完善简介后重新提交，或联系管理员。</p>
      </div>
      <?php elseif ($devStatus === 'approved'): ?>
      <!-- 我的产品 -->
      <div style="margin-bottom:20px">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px">我的产品（<?=count($mine)?>）</h3>
        <?php if (empty($mine)): ?><p style="font-size:13px;color:var(--faint)">还没有产品，用下面的表单提交第一个。</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:8px">
          <?php foreach ($mine as $s): $stMap = ['pending'=>'待审核','published'=>'已上架','rejected'=>'被拒','draft'=>'草稿']; $stCls = ['pending'=>'orange','published'=>'green','rejected'=>'red','draft'=>'gray']; ?>
          <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--border);border-radius:12px">
            <span style="font-size:20px"><?=htmlspecialchars($s['icon'] ?? '⚡')?></span>
            <div style="flex:1;min-width:0"><div style="font-size:14px;font-weight:600"><?=htmlspecialchars($s['title'])?></div><div style="font-size:11.5px;color:var(--muted)"><?=htmlspecialchars($types[$s['type']]['name'] ?? $s['type'])?> · 安装 <?=$s['installs']??0?> · 评分 <?=$s['rating']??0?></div></div>
            <span class="tag <?=$stCls[$s['status']]??'gray'?>"><?=$stMap[$s['status']]??$s['status']?></span>
            <?php if (($s['status'] ?? '') !== 'published'): ?><button class="text-sm" style="color:var(--danger);background:none;border:none;cursor:pointer" onclick="delProduct('<?=htmlspecialchars($s['id'])?>')">删除</button><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- 提交新产品 -->
      <div style="padding:20px;border-radius:14px;background:var(--bg)">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:6px">提交新产品</h3>
        <p style="font-size:12.5px;color:var(--faint);margin-bottom:16px">填写表单，自动生成标准 Skill 产品。审核通过后上架市场。</p>
        <form onsubmit="return submitSkill(event)">
          <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
            <div class="field"><label>产品名称 *</label><input type="text" name="title" required placeholder="如：小红书爆款文案"></div>
            <div class="field"><label>类型</label><select name="type" id="devSkillType">
              <?php foreach ($types as $k => $t): ?><option value="<?=$k?>"><?=$t['icon']?> <?=$t['name']?> — <?=$t['desc']?></option><?php endforeach; ?>
            </select></div>
          </div>
          <div class="field"><label>一句话描述</label><input type="text" name="description" placeholder="产品能做什么？"></div>
          <div class="field"><label>标签（逗号分隔）</label><input type="text" name="tags" placeholder="SEO, 文案, 增长"></div>
          <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
            <div class="field"><label>售价 ¥（0 = 免费）</label><input type="number" name="price" min="0" step="1" value="0"></div>
            <div class="field"><label>分销者佣金比例 %（5-80）</label><input type="number" name="distributor_rate" min="5" max="80" step="5" value="30"></div>
          </div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="distribution_enabled" value="1" checked style="width:16px;height:16px"> 允许分销：任何人可帮你卖，佣金归分销者</label></div>
          <div style="font-size:12px;color:var(--faint);margin:-6px 0 12px">分成结构：平台抽 10%（覆盖支付手续费）→ 分销者拿上比例 → 你拿剩余（约 <?=100-10-30?>%）。一级分销，不设多级。</div>
          <div class="field"><label>内容 / 指令模板 *（AI 指令 / 工具说明，用 {topic} 等占位符）</label><textarea name="content" rows="6" required placeholder="你是…请为「{topic}」…"></textarea></div>
          <div id="devSkillTip" style="font-size:12px;color:var(--faint);margin-bottom:12px">💡 开发套件：参考市场里的官方 Skill 结构。AI 指令用 <code>{topic}</code> 等变量占位，工作流可多段描述。</div>
          <button type="submit" class="rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">提交审核</button>
          <div id="submitSkillMsg" style="margin-top:12px;font-size:13px"></div>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <script>
    function applyDev(e) {
      e.preventDefault();
      var f = e.target, msg = document.getElementById('applyDevMsg');
      var fd = new FormData(f); fd.append('action', 'apply_developer');
      fetch('/api/developer.php', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)'; msg.textContent = d.message || d.error; if (d.ok) setTimeout(function(){ location.reload(); }, 1200); });
      return false;
    }
    function submitSkill(e) {
      e.preventDefault();
      var f = e.target, msg = document.getElementById('submitSkillMsg');
      var fd = new FormData(f); fd.append('action', 'submit_skill');
      fetch('/api/developer.php', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ msg.style.color = d.ok ? 'var(--ok)' : 'var(--danger)'; msg.textContent = d.message || d.error; if (d.ok) setTimeout(function(){ location.reload(); }, 1200); });
      return false;
    }
    function delProduct(id) {
      if (!confirm('确认删除该产品？')) return;
      var fd = new FormData(); fd.append('action','delete_product'); fd.append('id', id);
      fetch('/api/developer.php', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){ if (d.ok) location.reload(); else alert(d.error); });
    }
    </script>
    <?php
}

// ─── 分销中心（一级分销） ───
function include_member_distribution($member): void {
    $stats = commerce_distributor_stats($member['id']);
    $refCode = $member['referral_code'] ?? ('of' . substr(md5($member['id']), 0, 8));
    $siteUrl = site_config_get('site_url');
    // 可推广的商品（已发布且允许分销）
    $distProducts = array_values(array_filter(CommerceSystem::allPublished(), fn($p) => !empty($p['distribution_enabled']) && (float)($p['pricing']['price'] ?? 0) > 0));
    ?>
    <div class="card p-8">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
        <div style="width:44px;height:44px;border-radius:12px;background:var(--ok-soft);color:var(--ok);display:grid;place-items:center;font-size:20px">💰</div>
        <div>
          <h2 class="text-xl font-bold">分销中心</h2>
          <div style="font-size:12px;color:var(--muted)">帮你推广平台上的 Skill 产品，卖出即赚佣金（一级分销，平台抽 10% 覆盖支付手续费）</div>
        </div>
      </div>

      <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:22px">
        <div style="padding:16px;border-radius:14px;background:var(--bg)"><div style="font-size:24px;font-weight:800;color:var(--ok)">¥<?=number_format($stats['total_commission'],2)?></div><div style="font-size:12px;color:var(--muted)">累计佣金</div></div>
        <div style="padding:16px;border-radius:14px;background:var(--bg)"><div style="font-size:24px;font-weight:800"><?=$stats['total_orders']?></div><div style="font-size:12px;color:var(--muted)">带来的订单</div></div>
        <div style="padding:16px;border-radius:14px;background:var(--bg)"><div style="font-size:24px;font-weight:800;color:var(--warn)">¥<?=number_format($stats['pending_commission'],2)?></div><div style="font-size:12px;color:var(--muted)">待结算（未支付）</div></div>
        <div style="padding:16px;border-radius:14px;background:var(--bg)"><div style="font-size:24px;font-weight:800;color:var(--accent)"><?=$refCode?></div><div style="font-size:12px;color:var(--muted)">我的分销码</div></div>
      </div>

      <div style="padding:14px 16px;border:1px dashed var(--border-strong);border-radius:14px;background:var(--surface);margin-bottom:22px">
        <div style="font-size:13px;font-weight:700;margin-bottom:6px">🔗 复制推广链接（分享给任何人，他购买你拿佣金）</div>
        <div style="font-size:12.5px;color:var(--muted);margin-bottom:10px">平台抽 10% 覆盖支付手续费；分销者拿产品配置的佣金比例；作者拿剩余。</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <input type="text" id="refBase" value="<?=htmlspecialchars($siteUrl)?>/marketplace?ref=<?=htmlspecialchars($refCode)?>" readonly style="flex:1;min-width:220px;padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:12.5px">
          <button type="button" class="rounded-full px-5 py-2 font-bold" style="background:var(--accent);color:var(--on-accent);font-size:13px" onclick="var i=document.getElementById('refBase');i.select();document.execCommand('copy');alert('已复制推广链接')">复制</button>
        </div>
      </div>

      <h3 style="font-size:15px;font-weight:700;margin-bottom:10px">可推广的产品（<?=count($distProducts)?>）</h3>
      <?php if (empty($distProducts)): ?><p style="font-size:13px;color:var(--faint)">暂无可分销产品。开发者上架时开启分销后即可推广。</p>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px">
        <?php foreach (array_slice($distProducts, 0, 12) as $p): ?>
        <div style="padding:14px;border:1px solid var(--border);border-radius:14px;background:var(--surface)">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <span style="font-size:18px"><?=htmlspecialchars($p['title'] ?? '')?></span>
          </div>
          <div style="font-size:12px;color:var(--muted);margin-bottom:8px"><?=htmlspecialchars(mb_substr($p['description'] ?? '', 0, 50))?></div>
          <div style="display:flex;align-items:center;justify-content:space-between">
            <b style="color:var(--ok)">¥<?=number_format($p['pricing']['price'] ?? 0,0)?></b>
            <span style="font-size:11px;color:var(--accent)">分销佣 <?=round((float)($p['distributor_rate'] ?? 0.3)*100)?>%</span>
          </div>
          <button class="rounded-full px-4 py-1.5 font-bold mt-2" style="background:var(--hover);font-size:12px" onclick="copyDistLink('<?=htmlspecialchars($refCode)?>','<?=htmlspecialchars($p['id'])?>')">复制专属链接</button>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <script>
    function copyDistLink(ref, productId) {
      var url = '<?=htmlspecialchars($siteUrl)?>/marketplace?ref=' + ref + '&product=' + productId;
      navigator.clipboard.writeText(url).then(function(){ alert('已复制该产品的推广链接'); });
    }
    </script>
    <?php
}

// ─── 密码重置（未登录）───
function include_member_reset_password(): void {
    $step = $_GET['step'] ?? 'request';
    ?>
    <div class="card p-8">
      <?php if ($step === 'request'): ?>
      <h2 class="text-xl font-bold mb-2">重置密码</h2>
      <p class="text-sm text-muted mb-6">输入你的邮箱或手机号，我们将发送验证码</p>
      <form onsubmit="return requestReset(event)">
        <div class="field">
          <label>邮箱或手机号</label>
          <input type="text" name="account" required placeholder="you@example.com 或手机号">
        </div>
        <button type="submit" class="rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">发送验证码</button>
        <div id="resetMsg" style="margin-top:14px"></div>
      </form>
      <?php elseif ($step === 'verify'): ?>
      <h2 class="text-xl font-bold mb-2">验证身份</h2>
      <p class="text-sm text-muted mb-6">请输入收到的验证码</p>
      <form onsubmit="return verifyReset(event)">
        <input type="hidden" name="token" value="<?=htmlspecialchars($_GET['token'] ?? '')?>">
        <div class="field">
          <label>验证码</label>
          <input type="text" name="code" required placeholder="6 位验证码" maxlength="6">
        </div>
        <button type="submit" class="rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">验证</button>
        <div id="verifyMsg" style="margin-top:14px"></div>
      </form>
      <?php elseif ($step === 'newpassword'): ?>
      <h2 class="text-xl font-bold mb-2">设置新密码</h2>
      <p class="text-sm text-muted mb-6">请输入你的新密码</p>
      <form onsubmit="return resetPassword(event)">
        <input type="hidden" name="token" value="<?=htmlspecialchars($_GET['token'] ?? '')?>">
        <div class="field">
          <label>新密码</label>
          <input type="password" name="new_password" required placeholder="至少 8 位" minlength="8">
        </div>
        <div class="field">
          <label>确认新密码</label>
          <input type="password" name="confirm_password" required placeholder="再次输入新密码" minlength="8">
        </div>
        <button type="submit" class="rounded-full px-8 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">重置密码</button>
        <div id="newPasswordMsg" style="margin-top:14px"></div>
      </form>
      <?php endif; ?>
    </div>
    <?php
}
?>
<script>
function submitTeacher() {
  var f = document.getElementById('teacherForm');
  var fd = new FormData(); fd.append('action','apply_teacher');
  fd.append('intro', f.querySelector('textarea').value);
  fd.append('expertise', f.querySelector('input[name=expertise]').value);
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){ location.href = '/member.php?view=teacher'; });
}
function submitArticle() {
  var f = document.getElementById('submitForm');
  var fd = new FormData(); fd.append('action','submit_article');
  fd.append('title', f.querySelector('input[name=title]').value);
  fd.append('category', f.querySelector('select[name=category]').value);
  fd.append('excerpt', f.querySelector('input[name=excerpt]').value);
  fd.append('content', f.querySelector('textarea').value);
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      var box = document.getElementById('submitMsg');
      box.innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:' + (d.ok?'var(--ok-soft);color:var(--ok)':'var(--danger-soft);color:var(--danger)') + '">' + (d.message||d.error) + '</div>';
    });
}
// 个人资料更新
function updateProfile(e) {
  e.preventDefault();
  var f = e.target;
  var fd = new FormData(f); fd.append('action','update_profile');
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      var box = document.getElementById('profileMsg');
      box.innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:' + (d.ok?'var(--ok-soft);color:var(--ok)':'var(--danger-soft);color:var(--danger)') + '">' + (d.message||'资料已更新') + '</div>';
    });
  return false;
}
// 修改密码
function changePassword(e) {
  e.preventDefault();
  var f = e.target;
  var np = f.querySelector('input[name=new_password]').value;
  var cp = f.querySelector('input[name=confirm_password]').value;
  if (np !== cp) {
    document.getElementById('passwordMsg').innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:var(--danger-soft);color:var(--danger)">两次输入的密码不一致</div>';
    return false;
  }
  var fd = new FormData(f); fd.append('action','change_password');
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      var box = document.getElementById('passwordMsg');
      box.innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:' + (d.ok?'var(--ok-soft);color:var(--ok)':'var(--danger-soft);color:var(--danger)') + '">' + (d.message||'密码已修改') + '</div>';
      if (d.ok) f.reset();
    });
  return false;
}
// 请求重置密码
function requestReset(e) {
  e.preventDefault();
  var fd = new FormData(e.target); fd.append('action','request_reset');
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      var box = document.getElementById('resetMsg');
      if (d.ok) {
        box.innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:var(--ok-soft);color:var(--ok)">验证码已发送，请查收</div>';
        setTimeout(function(){ location.href = '/member.php?view=reset-password&step=verify&token=' + d.token; }, 1500);
      } else {
        box.innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:var(--danger-soft);color:var(--danger)">' + d.error + '</div>';
      }
    });
  return false;
}
// 验证重置码
function verifyReset(e) {
  e.preventDefault();
  var fd = new FormData(e.target); fd.append('action','verify_reset');
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      var box = document.getElementById('verifyMsg');
      if (d.ok) {
        box.innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:var(--ok-soft);color:var(--ok)">验证成功</div>';
        setTimeout(function(){ location.href = '/member.php?view=reset-password&step=newpassword&token=' + d.token; }, 1000);
      } else {
        box.innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:var(--danger-soft);color:var(--danger)">' + d.error + '</div>';
      }
    });
  return false;
}
// 重置密码
function resetPassword(e) {
  e.preventDefault();
  var f = e.target;
  var np = f.querySelector('input[name=new_password]').value;
  var cp = f.querySelector('input[name=confirm_password]').value;
  if (np !== cp) {
    document.getElementById('newPasswordMsg').innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:var(--danger-soft);color:var(--danger)">两次输入的密码不一致</div>';
    return false;
  }
  var fd = new FormData(f); fd.append('action','reset_password');
  fetch('/api/member.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      var box = document.getElementById('newPasswordMsg');
      if (d.ok) {
        box.innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:var(--ok-soft);color:var(--ok)">密码重置成功，正在跳转登录...</div>';
        setTimeout(function(){ location.href = '/member.php?view=login'; }, 1500);
      } else {
        box.innerHTML = '<div style="padding:10px 14px;border-radius:10px;font-size:13px;background:var(--danger-soft);color:var(--danger)">' + d.error + '</div>';
      }
    });
  return false;
}
</script>
<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)"><div class="mx-auto px-5 text-center text-sm" style="max-width:1100px"><div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div><div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div></div></footer>
</body>
</html>
