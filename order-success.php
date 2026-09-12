<?php
/**
 * 购买成功交付页 — /order-success?order={id}（会员订单）或 ?t={token}（收款单）
 *
 * F2d 交易闭环最后一环：支付 return_url 落点。
 * 按商品类型给交付动作（课程→立即学习 / 数字商品→订单交付 / 服务→预约指引），
 * 直播上下文（from=live 或 of_live_room cookie）显示「返回直播间」——
 * 在直播浮层 iframe 内时 postMessage 让父页收起浮层。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/ShopSystem.php';
require_once __DIR__ . '/lib/QuoteSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';

$orderId = trim((string)($_GET['order'] ?? ''));
$token   = trim((string)($_GET['t'] ?? ''));
$member  = member_current();

$order = null; $kind = '';   // course | product | quote
if ($orderId !== '' && $member) {
    foreach (shop_orders_for_member($member['id']) as $o) {
        if (($o['id'] ?? '') === $orderId) { $order = $o; break; }
    }
    if ($order) $kind = (($order['goods_type'] ?? '') === 'product') ? 'product' : 'course';
} elseif ($token !== '') {
    $order = quote_get_by_token($token);
    $kind = 'quote';
}

// 直播上下文：URL 参数优先，cookie 兜底（购买链路跨网关跳转后仍能找回直播间）
$liveRoom = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['room'] ?? ($_COOKIE['of_live_room'] ?? '')));
$fromLive = (($_GET['from'] ?? '') === 'live') || $liveRoom !== '';

$paid = $order && (($order['status'] ?? '') === 'paid');
$title = $order['course_title'] ?? $order['goods_title'] ?? '订单';
$courseId = (string)($order['course_id'] ?? '');
?><!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=$paid ? '支付成功' : '订单状态'?> | <?=site_config_get("site_name")?></title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
.os-wrap{max-width:560px;margin:0 auto;padding:48px 20px}
.os-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:36px 30px;text-align:center;box-shadow:var(--shadow)}
.os-icon{width:72px;height:72px;border-radius:50%;display:grid;place-items:center;margin:0 auto 18px;font-size:34px}
.os-icon.ok{background:var(--ok-soft);color:var(--ok)}
.os-icon.wait{background:var(--warn-soft);color:var(--warn)}
.os-card h1{font-size:24px;font-weight:800;margin:0 0 8px}
.os-card .muted{color:var(--muted);font-size:14px;line-height:1.7}
.os-amount{font-family:var(--font-display);font-size:34px;font-weight:800;margin:14px 0 4px}
.os-actions{display:grid;gap:10px;margin-top:26px}
.os-actions .btn{height:48px;font-size:15px;justify-content:center}
.os-back{display:inline-flex;align-items:center;gap:6px;margin-top:16px;font-size:14px;color:var(--accent);text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="os-wrap">
  <div class="os-card">
  <?php if (!$order): ?>
    <div class="os-icon wait">🔍</div>
    <h1>订单不存在</h1>
    <p class="muted">链接可能有误，或订单不属于当前登录账号。</p>
    <div class="os-actions"><a class="btn primary" href="/">返回首页</a></div>
  <?php elseif (!$paid): ?>
    <div class="os-icon wait">⏳</div>
    <h1>支付确认中…</h1>
    <p class="muted">支付网关正在回调，通常 3 秒内完成。本页会自动刷新状态。</p>
    <div class="os-amount">¥<?=number_format((float)($order['amount'] ?? 0), 2)?></div>
    <p class="muted"><?=htmlspecialchars($title)?></p>
  <?php else: ?>
    <div class="os-icon ok">✓</div>
    <h1>支付成功</h1>
    <div class="os-amount">¥<?=number_format((float)($order['amount'] ?? 0), 2)?></div>
    <p class="muted"><?=htmlspecialchars($title)?></p>
    <div class="os-actions">
      <?php if ($kind === 'course' && $courseId !== ''): ?>
      <a class="btn primary" href="/courses/<?=urlencode($courseId)?>">🎓 立即开始学习</a>
      <?php elseif ($kind === 'product'): ?>
      <a class="btn primary" href="/member.php?view=orders">📦 查看我的交付（下载 / 密钥）</a>
      <?php elseif ($kind === 'quote'): ?>
      <p class="muted" style="margin:0">服务类订单：我们会在 24 小时内通过你留下的联系方式与你对接交付细节。</p>
      <a class="btn primary" href="/member.php?view=orders">查看订单</a>
      <?php endif; ?>
      <a class="btn" href="/receipt?order=<?=urlencode($orderId)?>" style="border:1px solid var(--border)">🧾 查看收据</a>
      <?php if ($fromLive && $liveRoom !== ''): ?>
      <button class="btn" id="backLive" style="border:1px solid var(--border)">🔴 返回直播间</button>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  </div>
</div>
<script>
(function(){
  // 待支付 → 轮询订单状态，到账自动刷新
  <?php if ($order && !$paid && $orderId !== ''): ?>
  var t = setInterval(function(){
    var body = new FormData(); body.append('order_id', <?=json_encode($orderId)?>);
    fetch('/api/shop.php?action=order_status', {method:'POST', body:body}).then(function(r){return r.json()}).then(function(d){
      if (d.status === 'paid') { clearInterval(t); location.reload(); }
    }).catch(function(){});
  }, 3000);
  setTimeout(function(){ clearInterval(t); }, 60000);
  <?php endif; ?>
  // 返回直播间：在直播浮层 iframe 内 → 通知父页收起；否则直接跳转
  var btn = document.getElementById('backLive');
  if (btn) btn.onclick = function(){
    if (window.parent !== window) { window.parent.postMessage('of-back-to-live', '*'); }
    else location.href = '/live?room=' + encodeURIComponent(<?=json_encode($liveRoom)?>);
  };
})();
</script>
</body>
</html>
