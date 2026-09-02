<?php
/**
 * 激活码兑换页 — 用户输入激活码激活课程/服务
 *
 * v7（2026-09-01）：迁到共享 archetype（reader + form-card）。激活接口原样保留。
 * /activate
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/ActivationSystem.php';

$member = member_current();
$activated = [];
if ($member) $activated = act_member_activated($member['id']);
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>激活码兑换 | <?=site_config_get('site_name')?></title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 激活页独有：激活码输入与已激活列表。其余全部来自 modules.css。 */
.code-input{letter-spacing:3px;text-align:center;font-family:var(--font-mono);font-weight:700;font-size:18px}
.act-row{display:flex;gap:10px}
.act-row .btn{flex:0 0 auto}
#actMsg{border-radius:var(--r-sm);padding:12px 16px;font-size:14px;font-weight:600}
.done{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 4px;border-bottom:1px solid var(--border-soft)}
.done:last-child{border-bottom:none}
.done b{font-size:14.5px}
.done .sub{font-family:var(--font-mono);font-size:12px;color:var(--faint);margin-top:3px}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('home'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section class="reader reveal in" data-od-id="activate" style="max-width:720px">
    <div class="hero-center" style="padding-top:8px;padding-bottom:28px">
      <span class="kicker">激活码</span>
      <h1 style="font-size:clamp(28px,4vw,40px)">激活码<i class="si">兑换</i></h1>
      <p class="lead">输入从渠道方获得的激活码，解锁对应课程或服务</p>
    </div>
    <div class="form-card">
      <?php if (empty($member)): ?>
      <div class="gate-box"><p>请先登录后再兑换激活码</p><a href="/account?view=login&next=/activate" class="btn primary">登录 / 注册</a></div>
      <?php else: ?>
      <div class="act-row"><input type="text" id="actCode" class="inp code-input" placeholder="XXXX-XXXX-XXXX" autocomplete="off" aria-label="激活码"><button id="actBtn" class="btn primary" onclick="doActivate()">激活</button></div>
      <div id="actMsg" hidden style="margin-top:14px"></div>
      <div style="margin-top:32px">
        <div class="sec-head row"><div><span class="kicker">已激活的产品</span></div></div>
        <?php if (empty($activated)): ?>
        <p class="note" style="margin-top:12px">暂无已激活的产品</p>
        <?php else: ?>
        <div style="margin-top:8px">
          <?php foreach (array_reverse($activated) as $a): ?>
          <div class="done"><div><b><?=htmlspecialchars($a['goods_type'])?> · <?=htmlspecialchars($a['goods_id'])?></b><div class="sub">码：<?=htmlspecialchars($a['code'])?> · <?=htmlspecialchars(substr($a['activated_at'] ?? '', 0, 10))?></div></div><span class="badge ok">已激活</span></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
function doActivate() {
  var code = document.getElementById('actCode').value.trim();
  var btn = document.getElementById('actBtn');
  var msg = document.getElementById('actMsg');
  if (!code) { showMsg('请输入激活码', 'error'); return; }
  btn.disabled = true; btn.textContent = '激活中…';
  fetch('/api/activation', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'activate', code:code})})
    .then(function(r){return r.json();})
    .then(function(d){
      if (d.ok) {
        showMsg('🎉 激活成功！' + (d.goods_type || '') + ' 已解锁', 'success');
        setTimeout(function(){ location.reload(); }, 1200);
      } else {
        if (d.need_login) { location.href = '/account?view=login&next=/activate'; return; }
        showMsg('⚠️ ' + d.error, 'error');
      }
    })
    .catch(function(){ showMsg('网络异常，请稍后再试', 'error'); })
    .finally(function(){ btn.disabled = false; btn.textContent = '激活'; });
}
function showMsg(text, type) {
  var msg = document.getElementById('actMsg');
  msg.textContent = text;
  msg.hidden = false;
  msg.style.background = type === 'success' ? 'var(--ok-soft)' : 'var(--danger-soft)';
  msg.style.color = type === 'success' ? 'var(--ok)' : 'var(--danger)';
}
</script>
</body>
</html>
