<?php
/**
 * 购物车前台 — CartSystem 接线（此前 293 行完整实现零调用，商城实际是单品直购）
 * 加购/数量/优惠码/结算 一次跑通
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/CartSystem.php';
require_once __DIR__ . '/lib/SiteConfig.php';

if (PageCache::begin('cart', 0)) exit;   // 购物车个性化，不缓存

$member = function_exists('member_current') ? member_current() : null;
$summary = cart_summary($member);
$siteName = site_config_get('site_name', 'OpenFlow');
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>购物车 | <?=htmlspecialchars($siteName)?></title>
<link rel="stylesheet" href="/assets/tokens.css?v=20260907a">
<link rel="stylesheet" href="/assets/modules.css?v=20260907a">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
.cart-row{display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--border-soft)}
.cart-row .nm{flex:1;min-width:0}
.cart-row .qty{display:flex;align-items:center;gap:6px}
.cart-row .qty button{width:28px;height:28px;border-radius:8px;border:1px solid var(--border);background:var(--surface);cursor:pointer;font-size:15px}
.cart-row .qty input{width:44px;text-align:center;border:1px solid var(--border);border-radius:8px;padding:4px}
.cart-sum{display:flex;flex-direction:column;gap:6px;font-size:14px}
.cart-sum .row{display:flex;justify-content:space-between}
.cart-sum .total{font-size:18px;font-weight:800;color:var(--accent)}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('cart'); ?>
<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section class="sec reveal in" data-od-anchor>
    <div class="sec-head row"><div><span class="kicker">购物车</span><h1>确认你的选择</h1></div></div>

    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px" class="cart-grid">
      <!-- 商品列表 -->
      <div class="card" style="padding:20px">
        <h2 style="font-size:15px;margin-bottom:6px">商品（<?=count($summary['items'])?>）</h2>
        <div id="cartItems">
        <?php if (empty($summary['items'])): ?>
          <div class="empty" style="padding:40px 0">购物车是空的。去 <a href="/courses" style="color:var(--accent)">课程</a> 或 <a href="/marketplace" style="color:var(--accent)">市场</a> 逛逛。</div>
        <?php else: foreach ($summary['items'] as $it): ?>
          <div class="cart-row" data-id="<?=htmlspecialchars($it['item_id'])?>" data-type="<?=htmlspecialchars($it['type'])?>">
            <div class="nm"><b><?=htmlspecialchars($it['name'])?></b><div class="text-sm text-muted mono">¥<?=number_format($it['price'],2)?> × <?=$it['qty']?></div></div>
            <div class="qty">
              <button onclick="cartQty('<?=$it['item_id']?>','<?=$it['type']?>',-1)">−</button>
              <input type="text" value="<?=$it['qty']?>" readonly>
              <button onclick="cartQty('<?=$it['item_id']?>','<?=$it['type']?>',1)">+</button>
            </div>
            <div style="min-width:80px;text-align:right;font-weight:700">¥<?=number_format($it['price'] * $it['qty'], 2)?></div>
            <button class="btn btn-ghost btn-sm" onclick="cartRemove('<?=$it['item_id']?>','<?=$it['type']?>')" title="移除">✕</button>
          </div>
        <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- 结算 -->
      <div class="card" style="padding:20px;align-self:start;position:sticky;top:90px">
        <h2 style="font-size:15px;margin-bottom:12px">结算</h2>
        <div class="cart-sum">
          <div class="row"><span>小计</span><b id="cartSubtotal">¥<?=number_format($summary['subtotal'],2)?></b></div>
          <div class="row"><span>优惠</span><b id="cartDiscount" style="color:var(--ok)">−¥<?=number_format($summary['discount'],2)?></b></div>
          <div class="row total"><span>应付</span><b id="cartTotal">¥<?=number_format($summary['total'],2)?></b></div>
        </div>
        <div style="display:flex;gap:6px;margin:14px 0">
          <input class="inp" id="couponCode" placeholder="优惠码" style="flex:1">
          <button class="btn btn-s btn-sm" onclick="cartCoupon()">使用</button>
        </div>
        <?php if (!empty($summary['coupon'])): ?><div class="text-sm text-ok" style="margin-bottom:10px">已用优惠码：<?=htmlspecialchars($summary['coupon'])?> <a href="javascript:cartRemoveCoupon()" style="color:var(--danger)">移除</a></div><?php endif; ?>
        <?php if (!$member): ?>
          <a href="/login?next=/cart" class="btn primary" style="width:100%;text-align:center;display:block">登录后结算</a>
        <?php else: ?>
          <button class="btn primary" style="width:100%" onclick="cartCheckout()" id="checkoutBtn">去结算 →</button>
        <?php endif; ?>
        <p class="text-xs text-muted" style="margin-top:10px;text-align:center">支付由安全通道处理 · 支持7天无理由退款</p>
      </div>
    </div>
  </section>
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<script>
function cartRefresh(d){
  if (!d || d.ok === false) return;
  // 重新渲染（简单方案：刷新页面拿服务端最新）
  location.reload();
}
function cartAdd(itemId, type, name, price, qty){
  fetch('/api/cart.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add',item_id:itemId,type:type,name:name,price:price,qty:qty||1})}).then(r=>r.json()).then(cartRefresh);
}
function cartQty(itemId, type, delta){
  // 读当前 qty 再 ±1（从 DOM input）
  var row = document.querySelector('.cart-row[data-id="'+itemId+'"][data-type="'+type+'"]');
  var cur = row ? parseInt(row.querySelector('.qty input').value,10) : 1;
  var next = Math.max(0, cur + delta);
  fetch('/api/cart.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'update',item_id:itemId,type:type,qty:next})}).then(r=>r.json()).then(cartRefresh);
}
function cartRemove(itemId, type){
  fetch('/api/cart.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'remove',item_id:itemId,type:type})}).then(r=>r.json()).then(cartRefresh);
}
function cartCoupon(){
  var code = document.getElementById('couponCode').value.trim();
  if (!code) return;
  fetch('/api/cart.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'coupon',code:code})}).then(r=>r.json()).then(function(d){ if(!d.ok){ fcToast(d.error||'优惠码无效'); } else cartRefresh(d); });
}
function cartRemoveCoupon(){
  fetch('/api/cart.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'remove_coupon'})}).then(r=>r.json()).then(cartRefresh);
}
function cartCheckout(){
  var btn = document.getElementById('checkoutBtn'); if (btn) { btn.disabled = true; btn.textContent = '创建订单中…'; }
  fetch('/api/cart.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'checkout'})}).then(r=>r.json()).then(function(d){
    if (!d.ok) { fcToast(d.error||'结算失败'); if (btn) { btn.disabled=false; btn.textContent='去结算 →'; } return; }
    if (d.pay_url) location.href = d.pay_url;
    else if (d.order) location.href = '/order-success?id=' + encodeURIComponent(d.order.id);
    else location.reload();
  }).catch(function(){ if (btn) { btn.disabled=false; btn.textContent='去结算 →'; } });
}
</script>
</body>
</html>
