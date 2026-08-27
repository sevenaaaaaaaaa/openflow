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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>商城 | <?=site_config_get('site_name')?></title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" defer></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .pcard{background:var(--surface);border:1px solid var(--border);border-radius:18px;overflow:hidden;transition:.2s}
  .pcard:hover{transform:translateY(-3px);box-shadow:0 16px 40px rgba(0,0,0,.1);border-color:var(--accent)}
  .pcard .thumb{aspect-ratio:16/10;background:linear-gradient(135deg,var(--ok-soft),var(--accent-soft));display:grid;place-items:center;overflow:hidden}
  .pcard .thumb img{width:100%;height:100%;object-fit:cover}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260826b" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-10" style="max-width:1100px">
    <?php if ($member): ?>
    <div class="rounded-2xl px-5 py-4 mb-8 flex items-center justify-between" style="background:var(--ok-soft);color:var(--ok)">
      <div>当前积分：<strong class="text-xl"><?=(int)($member['points'] ?? 0)?></strong></div>
      <a href="/account" class="text-sm underline">如何获得积分 →</a>
    </div>
    <?php endif; ?>

    <!-- 实体商品 -->
    <h1 class="text-2xl font-bold mb-6"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1.5 13.5a1 1 0 0 1-1 1.1H5.5a1 1 0 0 1-1-1.1L6 7Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/></svg></span> 实体商品</h1>
    <?php if (empty($products)): ?>
    <div class="rounded-3xl p-12 text-center mb-12" style="background:var(--surface);border:1px solid var(--border);color:var(--faint)">实体商品筹备中</div>
    <?php else: ?>
    <div class="grid gap-5 mb-12 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($products as $p): ?>
      <div class="pcard">
        <div class="thumb"><?php if ($p['image']): ?><img loading="lazy" src="<?=htmlspecialchars($p['image'])?>" alt="<?=htmlspecialchars($p['title'])?>"><?php else: ?><span class="text-4xl"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg></span></span><?php endif; ?></div>
        <div class="p-5">
          <h3 class="font-bold text-gray-900"><?=htmlspecialchars($p['title'])?></h3>
          <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?=htmlspecialchars($p['desc'] ?? '')?></p>
          <div class="flex items-center justify-between mt-4">
            <div>
              <span class="font-bold text-lg text-green-600">¥<?=number_format($p['price'], 2)?></span>
              <?php if (!empty($p['shipping'])): ?><span class="text-xs text-gray-400 ml-1"><?=htmlspecialchars($p['shipping'])?></span><?php endif; ?>
            </div>
            <button class="rounded-full bg-[var(--accent)] text-white px-6 py-2.5 text-sm font-semibold" onclick="buyProduct('<?=htmlspecialchars($p['id'])?>','<?=htmlspecialchars($p['title'])?>')">立即购买</button>
          </div>
          <div class="text-xs text-gray-400 mt-2">库存 <?=$p['stock'] ?? 0?> 件</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 积分商城 -->
    <h1 class="text-2xl font-bold mb-6"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4"/><path d="M5 12v8h14v-8M12 8v12M12 8s-1-5-4-5c-2 0-2.5 1.5-1 3 1.5 1.5 5 2 5 2ZM12 8s1-5 4-5c2 0 2.5 1.5 1 3-1.5 1.5-5 2-5 2Z"/></svg></span> 积分商城</h1>
    <?php if (empty($pointsProducts)): ?>
    <div class="rounded-3xl p-12 text-center" style="background:var(--surface);border:1px solid var(--border);color:var(--faint)">积分商品筹备中</div>
    <?php else: ?>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($pointsProducts as $p): ?>
      <div class="pcard">
        <div class="thumb"><?php if ($p['image']): ?><img loading="lazy" src="<?=htmlspecialchars($p['image'])?>" alt="<?=htmlspecialchars($p['title'])?>"><?php else: ?><span class="text-4xl"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5"/><path d="m8.5 13-2 8 5.5-3 5.5 3-2-8"/></svg></span></span><?php endif; ?></div>
        <div class="p-5">
          <h3 class="font-bold text-gray-900"><?=htmlspecialchars($p['title'])?></h3>
          <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?=htmlspecialchars($p['desc'] ?? '')?></p>
          <div class="flex items-center justify-between mt-4">
            <span class="pill px-3 py-1 rounded-full text-xs" style="background:var(--ok-soft);color:var(--ok)"><?=$p['points']?> 积分</span>
            <button class="rounded-full bg-[var(--accent)] text-white px-6 py-2.5 text-sm font-semibold" onclick="redeem('<?=htmlspecialchars($p['id'])?>','<?=htmlspecialchars($p['title'])?>',<?=(int)$p['points']?>)">兑换</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:1100px">
      <div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
    </div>
  </footer>

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
