<?php
/**
 * 激活码兑换页 — 用户输入激活码激活课程/服务
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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>激活码兑换 | <?=site_config_get('site_name')?></title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:-apple-system,'PingFang SC','Noto Sans SC',system-ui,sans-serif}
  .code-input{letter-spacing:3px;text-align:center;font-family:ui-monospace,'SF Mono',monospace;font-weight:700}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260822" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-14" style="max-width:720px">
    <div class="bg-white border border-[var(--border)] rounded-3xl p-8" style="box-shadow:0 8px 32px rgba(0,0,0,.06)">
      <div class="text-center">
        <div class="text-5xl mb-3">🎫</div>
        <h1 class="text-2xl font-bold text-gray-900">激活码兑换</h1>
        <p class="text-gray-600 mt-2 text-sm">输入从渠道方获得的激活码，解锁对应课程或服务</p>
      </div>

      <?php if (empty($member)): ?>
      <div class="mt-8 text-center">
        <p class="text-sm text-gray-600 mb-4">请先登录后再兑换激活码</p>
        <a href="/member.php?view=login&next=/activate" class="inline-block rounded-full bg-[var(--accent)] text-white px-8 py-3 font-semibold">登录 / 注册</a>
      </div>
      <?php else: ?>
      <div class="mt-8 flex gap-3">
        <input type="text" id="actCode" class="code-input flex-1 border border-[var(--border)] rounded-2xl px-5 py-4 text-lg outline-none" placeholder="XXXX-XXXX-XXXX" autocomplete="off">
        <button id="actBtn" class="rounded-2xl bg-[var(--accent)] text-white px-8 font-bold" onclick="doActivate()">激活</button>
      </div>
      <div id="actMsg" class="mt-4 text-sm hidden rounded-2xl px-5 py-4"></div>

      <div class="mt-10 border-t border-[var(--bg)] pt-6">
        <h2 class="font-bold text-gray-900 mb-4">已激活的产品</h2>
        <?php if (empty($activated)): ?>
        <p class="text-sm text-gray-400">暂无已激活的产品</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach (array_reverse($activated) as $a): ?>
          <div class="flex items-center justify-between rounded-2xl border border-[var(--border)] px-5 py-3">
            <div>
              <div class="font-semibold text-gray-900"><?=htmlspecialchars($a['goods_type'])?> · <?=htmlspecialchars($a['goods_id'])?></div>
              <div class="text-xs text-gray-400 mt-1">码：<code><?=htmlspecialchars($a['code'])?></code> · <?=htmlspecialchars(substr($a['activated_at'] ?? '', 0, 10))?></div>
            </div>
            <span class="pill px-3 py-1 rounded-full text-xs" style="background:var(--ok-soft);color:var(--ok)">已激活</span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:720px">
      <div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
    </div>
  </footer>

<script>
function doActivate() {
  var code = document.getElementById('actCode').value.trim();
  var btn = document.getElementById('actBtn');
  var msg = document.getElementById('actMsg');
  if (!code) { showMsg('请输入激活码', 'error'); return; }
  btn.disabled = true; btn.textContent = '激活中…';
  fetch('/api/activation.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'activate', code:code})})
    .then(function(r){return r.json();})
    .then(function(d){
      if (d.ok) {
        showMsg('🎉 激活成功！' + (d.goods_type || '') + ' 已解锁', 'success');
        setTimeout(function(){ location.reload(); }, 1200);
      } else {
        if (d.need_login) { location.href = '/member.php?view=login&next=/activate'; return; }
        showMsg('⚠️ ' + d.error, 'error');
      }
    })
    .catch(function(){ showMsg('网络异常，请稍后再试', 'error'); })
    .finally(function(){ btn.disabled = false; btn.textContent = '激活'; });
}
function showMsg(text, type) {
  var msg = document.getElementById('actMsg');
  msg.textContent = text;
  msg.classList.remove('hidden');
  msg.style.background = type === 'success' ? 'var(--ok-soft)' : '#fee2e2';
  msg.style.color = type === 'success' ? 'var(--ok)' : 'var(--danger)';
}
</script>
</body>
</html>
