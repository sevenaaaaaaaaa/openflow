<?php
/**
 * 收款页（公开）—— 客户凭链接 /pay?t=<token> 打开并付款
 *
 * 不需要登录：客户往往不是注册会员。付款走 api/shop.php?action=pay_quote，
 * 付成功由支付回调 shop_mark_paid 结算并把 CRM 线索推进为已成交。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/QuoteSystem.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$token = trim($_GET['t'] ?? '');
$order = $token !== '' ? quote_get_by_token($token) : null;

$site = function_exists('site_config') ? site_config() : [];
$siteName = $site['site_name'] ?? 'OpenFlow';

$state = 'ok';
if (!$order)                                 $state = 'notfound';
elseif (($order['status'] ?? '') === 'paid') $state = 'paid';
elseif (($order['status'] ?? '') === 'refunded') $state = 'refunded';
elseif (quote_is_expired($order))            $state = 'expired';

$amount = (float)($order['amount'] ?? 0);
$title  = $order['goods_title'] ?? '收款';
$items  = (array)($order['items'] ?? []);
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?=htmlspecialchars($title)?> · <?=htmlspecialchars($siteName)?></title>
<style>
  :root{--bg:#f5f6f8;--card:#fff;--fg:#1a1a1a;--muted:#6b7280;--border:#e5e7eb;--accent:#2563eb;--ok:#16a34a;--danger:#dc2626}
  *{box-sizing:border-box}
  body{margin:0;font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--fg)}
  .wrap{max-width:460px;margin:0 auto;padding:32px 18px}
  .card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:26px;box-shadow:0 4px 20px rgba(0,0,0,.05)}
  .brand{text-align:center;color:var(--muted);font-size:13px;margin-bottom:18px}
  h1{font-size:19px;margin:0 0 4px}
  .amount{font-size:38px;font-weight:800;letter-spacing:-1px;margin:14px 0 4px}
  .amount small{font-size:20px;font-weight:600}
  .muted{color:var(--muted)}
  .items{border-top:1px dashed var(--border);margin-top:18px;padding-top:14px}
  .row{display:flex;justify-content:space-between;gap:12px;font-size:14px;padding:4px 0}
  .row .n{color:var(--muted)}
  .note{margin-top:14px;padding:12px 14px;background:#f9fafb;border-radius:10px;font-size:13px;color:var(--muted);white-space:pre-wrap}
  .pay{margin-top:22px;width:100%;padding:14px;border:none;border-radius:12px;background:var(--accent);color:#fff;font-size:16px;font-weight:600;cursor:pointer}
  .pay:disabled{opacity:.5;cursor:not-allowed}
  .state{text-align:center;padding:20px 0}
  .state .big{font-size:44px}
  .err{color:var(--danger);font-size:13px;text-align:center;margin-top:12px;min-height:18px}
  .foot{text-align:center;color:var(--muted);font-size:12px;margin-top:18px}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand"><?=htmlspecialchars($siteName)?> · 安全收款</div>
  <div class="card">
  <?php if ($state === 'notfound'): ?>
    <div class="state"><div class="big">🔍</div><h1>收款单不存在</h1><p class="muted">链接可能有误，请向对方确认。</p></div>
  <?php elseif ($state === 'paid'): ?>
    <div class="state"><div class="big">✅</div><h1>已支付</h1><p class="muted">这笔款项已经完成支付，无需重复付款。</p></div>
  <?php elseif ($state === 'refunded'): ?>
    <div class="state"><div class="big">↩️</div><h1>已退款</h1><p class="muted">该收款单已退款。</p></div>
  <?php elseif ($state === 'expired'): ?>
    <div class="state"><div class="big">⏰</div><h1>链接已过期</h1><p class="muted">请向对方索取新的收款链接。</p></div>
  <?php else: ?>
    <h1><?=htmlspecialchars($title)?></h1>
    <?php if (!empty($order['customer'])): ?><div class="muted">致：<?=htmlspecialchars($order['customer'])?></div><?php endif; ?>
    <div class="amount"><small>¥</small><?=number_format($amount, 2)?></div>
    <?php if ($items): ?>
      <div class="items">
        <?php foreach ($items as $it): ?>
          <div class="row">
            <span class="n"><?=htmlspecialchars($it['name'] ?? '')?><?php if ((int)($it['qty'] ?? 1) > 1): ?> ×<?=(int)$it['qty']?><?php endif; ?></span>
            <span>¥<?=number_format((float)($it['subtotal'] ?? 0), 2)?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($order['note'])): ?><div class="note"><?=htmlspecialchars($order['note'])?></div><?php endif; ?>
    <button class="pay" id="payBtn" onclick="startPay()">立即支付 ¥<?=number_format($amount, 2)?></button>
    <div class="err" id="err"></div>
    <div class="foot">支付由 <?=htmlspecialchars($siteName)?> 提供 · 请确认对方身份后再付款</div>
  <?php endif; ?>
  </div>
</div>

<?php if ($state === 'ok'): ?>
<script>
var TOKEN = <?=json_encode($token)?>;
function startPay() {
  var btn = document.getElementById('payBtn'), err = document.getElementById('err');
  btn.disabled = true; btn.textContent = '正在发起支付…'; err.textContent = '';
  var body = new URLSearchParams({ action: 'pay_quote', token: TOKEN, channel: 'xfpay' });
  fetch('/api/shop.php?action=pay_quote', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok) { err.textContent = d.error || '发起支付失败'; btn.disabled = false; btn.textContent = '重试支付'; return; }
      var p = d.payment || {};
      // 虎皮椒：拿到网关地址与参数，构造表单跳转
      if (p.gateway && p.params) {
        var f = document.createElement('form'); f.method = 'POST'; f.action = p.gateway;
        Object.keys(p.params).forEach(function(k){ var i=document.createElement('input'); i.type='hidden'; i.name=k; i.value=p.params[k]; f.appendChild(i); });
        document.body.appendChild(f); f.submit();
      } else if (p.url) {
        location.href = p.url;
      } else {
        err.textContent = '支付渠道未正确配置'; btn.disabled = false; btn.textContent = '重试支付';
      }
    })
    .catch(function(){ err.textContent = '网络错误，请重试'; btn.disabled = false; btn.textContent = '重试支付'; });
}
</script>
<?php endif; ?>
</body>
</html>
