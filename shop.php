<?php
/**
 * 商城 — 实体商品 + 积分商城
 * /shop
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/MallSystem.php';

$member = member_current();
$products = mall_products();
$pointsProducts = mall_points_products();

$catNames = [];
foreach (get_categories('article') as $c) $catNames[$c['key']] = $c['name'];
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>商城 | <?=site_config_get('site_name')?></title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 商城独有：价格行。其余全部来自 modules.css。 */
.a-card .price{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px}
.a-card .price b{font-family:var(--font-display);font-size:20px;font-weight:700;color:var(--ok);letter-spacing:-.01em}
.a-card .price small{font-size:12px;color:var(--faint);margin-left:6px;font-weight:400}
.a-card .cov.emj{color:var(--accent)}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('marketplace'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <section id="top" class="reveal in" data-od-anchor data-od-id="shop-hero">
    <div class="hero-center">
      <span class="kicker">SHOP · 商城</span>
      <h1>实体商品 <i class="si">&amp;</i> 积分商城</h1>
      <p class="lead">周边、教材、工具包，以及用学习积分就能换的好东西。</p>
      <?php if ($member): ?>
      <div class="cta-row"><span class="badge ok"><span class="dot"></span>当前积分 <strong><?=(int)($member['points'] ?? 0)?></strong></span><a href="/account" class="btn subtle">如何获得积分 →</a></div>
      <?php else: ?>
      <div class="cta-row"><a href="/account?view=login&amp;next=/shop" class="btn ghost">登录后查看积分</a></div>
      <?php endif; ?>
    </div>
  </section>

  <section id="products" class="sec reveal" data-od-anchor data-od-id="shop-products">
    <div class="sec-head row"><div><span class="kicker">PRODUCTS</span><h2>实体商品</h2></div><span class="sub"><?=count($products)?> 件在售</span></div>
    <?php if (empty($products)): ?>
    <div class="empty">实体商品筹备中</div>
    <?php else: ?>
    <div class="a-grid">
      <?php foreach ($products as $p): ?>
      <article class="a-card">
        <div class="cov emj"><?php if ($p['image']): ?><img loading="lazy" src="<?=htmlspecialchars($p['image'])?>" alt="<?=htmlspecialchars($p['title'])?>"><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg><?php endif; ?></div>
        <div class="bd">
          <span class="cat">实体 · 库存 <?=(int)($p['stock'] ?? 0)?> 件</span>
          <h3><?=htmlspecialchars($p['title'])?></h3>
          <p class="note" style="margin-top:0"><?=htmlspecialchars($p['desc'] ?? '')?></p>
          <div class="price"><span><b>¥<?=number_format($p['price'], 2)?></b><?php if (!empty($p['shipping'])): ?><small><?=htmlspecialchars($p['shipping'])?></small><?php endif; ?></span><button type="button" class="btn primary" style="height:40px;padding:0 18px;font-size:14px" onclick="buyProduct('<?=htmlspecialchars($p['id'])?>','<?=htmlspecialchars($p['title'])?>')">立即购买</button></div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section id="points" class="sec reveal" data-od-anchor data-od-id="shop-points">
    <div class="sec-head row"><div><span class="kicker">POINTS</span><h2>积分商城</h2></div><span class="sub">学习 · 发帖 · 签到都能赚积分</span></div>
    <?php if (empty($pointsProducts)): ?>
    <div class="empty">积分商品筹备中</div>
    <?php else: ?>
    <div class="a-grid">
      <?php foreach ($pointsProducts as $p): ?>
      <article class="a-card">
        <div class="cov emj"><?php if ($p['image']): ?><img loading="lazy" src="<?=htmlspecialchars($p['image'])?>" alt="<?=htmlspecialchars($p['title'])?>"><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5"/><path d="m8.5 13-2 8 5.5-3 5.5 3-2-8"/></svg><?php endif; ?></div>
        <div class="bd">
          <span class="cat">积分兑换</span>
          <h3><?=htmlspecialchars($p['title'])?></h3>
          <p class="note" style="margin-top:0"><?=htmlspecialchars($p['desc'] ?? '')?></p>
          <div class="price"><span class="badge ok"><?=(int)$p['points']?> 积分</span><button type="button" class="btn primary" style="height:40px;padding:0 18px;font-size:14px" onclick="redeem('<?=htmlspecialchars($p['id'])?>','<?=htmlspecialchars($p['title'])?>',<?=(int)$p['points']?>)">兑换</button></div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="reveal" data-od-id="shop-cta">
    <div class="cta-band">
      <span class="kicker">EARN POINTS</span>
      <h2>积分从哪来？</h2>
      <p class="lead">完成课程、在门派社区发帖回帖、每日签到，都会自动累计到你的账户。</p>
      <div class="cta-row"><a href="/courses" class="btn primary">去学一门课</a><a href="/community" class="btn ghost">逛逛门派社区</a></div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
function buyProduct(id, title) {
  <?php if (!$member): ?>
  location.href = '/account?view=login&next=/shop'; return;
  <?php endif; ?>
  if (!confirm('确认购买「' + title + '」？')) return;
  fetch('/api/mall', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'order_product', product_id:id, qty:1})})
    .then(function(r){return r.json();})
    .then(function(d){
      if (d.ok && d.payment && d.payment.ok) {
        // 跳转虎皮椒支付
        var f = document.createElement('form'); f.method = 'post'; f.action = d.payment.gateway;
        Object.keys(d.payment.params).forEach(function(k){ var i=document.createElement('input'); i.type='hidden'; i.name=k; i.value=d.payment.params[k]; f.appendChild(i); });
        document.body.appendChild(f); f.submit();
      } else {
        alert(d.error || '下单失败');
      }
    })
    .catch(function(){ alert('网络异常'); });
}
function redeem(id, title, points) {
  <?php if (!$member): ?>
  location.href = '/account?view=login&next=/shop'; return;
  <?php endif; ?>
  var myPoints = <?=(int)($member['points'] ?? 0)?>;
  if (myPoints < points) { alert('积分不足，需要 ' + points + ' 积分'); return; }
  if (!confirm('确认用 ' + points + ' 积分兑换「' + title + '」？')) return;
  fetch('/api/mall', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'redeem', points_product_id:id})})
    .then(function(r){return r.json();})
    .then(function(d){
      if (d.ok) { alert('🎉 兑换成功！'); location.reload(); }
      else alert(d.error || '兑换失败');
    })
    .catch(function(){ alert('网络异常'); });
}
</script>
</body>
</html>
