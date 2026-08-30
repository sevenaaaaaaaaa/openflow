<?php
/**
 * 平台运营驾驶舱 —— Agent 出选品建议 + 新品质量初判（BACKLOG T1-12）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/PlatformOps.php';
require_once __DIR__ . '/../lib/CommerceSystem.php';
require_login();
require_perm('commerce');

// 取商品并补上销量/浏览（尽力而为，缺就当 0）
$products = [];
try {
    foreach ((array)CommerceSystem::products() as $p) {
        $pid = (string)($p['id'] ?? '');
        $sales = 0; $views = 0;
        try {
            $r = Database::query("SELECT COUNT(*) c FROM orders WHERE product_id = ? AND status='paid'", [$pid]);
            $sales = (int)($r[0]['c'] ?? 0);
        } catch (\Throwable $e) {}
        try {
            $r = Database::query("SELECT COUNT(*) c FROM events WHERE event='page_view' AND page LIKE ?", ['%' . $pid . '%']);
            $views = (int)($r[0]['c'] ?? 0);
        } catch (\Throwable $e) {}
        $products[] = [
            'id' => $pid, 'title' => (string)($p['title'] ?? ''), 'price' => (float)($p['pricing']['price'] ?? ($p['price'] ?? 0)),
            'description' => (string)($p['description'] ?? ''), 'cover' => (string)($p['cover'] ?? ''),
            'asset_id' => (string)($p['asset_id'] ?? ''), 'type' => (string)($p['type'] ?? ''),
            'sales' => $sales, 'views' => $views, 'created_at' => (string)($p['created_at'] ?? ''),
            'featured' => !empty($p['featured']), 'status' => (string)($p['status'] ?? 'active'),
        ];
    }
} catch (\Throwable $e) { $products = []; }

$curation = platops_curate($products);
// 待审新品：最近 30 天、状态非 active 的先给初判；没有则对最新几个做体检
$pending = array_values(array_filter($products, fn($p) => ($p['status'] ?? '') !== 'active'));
if (!$pending) $pending = array_slice($products, 0, 5);

admin_header('平台运营驾驶舱');
$kindMeta = ['promote'=>['🚀','#2563eb'],'spotlight'=>['✨','#7c3aed'],'demote'=>['⬇️','#dc2626']];
?>
<div style="max-width:1000px">
  <h1 style="margin:0 0 4px">🧭 平台运营驾驶舱</h1>
  <p class="v-sub" style="margin:0 0 16px">请不起运营团队也能经营市场：Agent 按真实成交与浏览数据给出「推谁 / 曝光谁 / 换谁」，并对新品做质量初判。<strong>只提议，上下架由你拍板。</strong></p>

  <div style="font-weight:700;margin:18px 0 10px">本周选品建议（<?=count($curation)?>）</div>
  <?php if (!$curation): ?>
    <div class="card" style="padding:26px;text-align:center;color:var(--faint)">还没有足够的商品与成交数据。等有了浏览/成交，这里会排出该推谁、该换谁。</div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($curation as $c): $m = $kindMeta[$c['kind']] ?? ['•','#64748b']; ?>
    <div class="card" style="padding:12px 14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;border-left:3px solid <?=$m[1]?>">
      <span style="font-size:18px"><?=$m[0]?></span>
      <div style="flex:1;min-width:220px">
        <div style="font-weight:700;font-size:14px"><?=htmlspecialchars($c['title'] ?: $c['product_id'])?></div>
        <div style="font-size:12.5px;color:var(--text-soft,#475569)"><?=htmlspecialchars($c['reason'])?></div>
      </div>
      <a href="/xmp/commerce" class="btn btn-ghost btn-sm"><?=htmlspecialchars($c['suggest'])?> →</a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div style="font-weight:700;margin:24px 0 10px">新品质量初判</div>
  <?php if (!$pending): ?>
    <div class="card" style="padding:26px;text-align:center;color:var(--faint)">暂无待判商品。</div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach (array_slice($pending, 0, 8) as $p): $r = platops_review($p, $products);
      $vc = ['pass'=>'#16a34a','revise'=>'#d97706','reject'=>'#dc2626'][$r['verdict']] ?? '#64748b'; ?>
    <div class="card" style="padding:12px 14px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <strong style="font-size:14px"><?=htmlspecialchars($p['title'] ?: $p['id'])?></strong>
        <span style="font-size:11px;padding:1px 8px;border-radius:999px;color:#fff;background:<?=$vc?>"><?=platops_verdict_label($r['verdict'])?></span>
        <span style="font-size:12px;color:var(--faint)">质量分 <?=$r['score']?></span>
      </div>
      <?php if ($r['issues']): ?>
      <ul style="margin:6px 0 0;padding-left:18px;font-size:12.5px;color:#b45309"><?php foreach ($r['issues'] as $i): ?><li><?=htmlspecialchars($i)?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if ($r['notes']): ?>
      <ul style="margin:4px 0 0;padding-left:18px;font-size:12.5px;color:var(--faint)"><?php foreach ($r['notes'] as $i): ?><li><?=htmlspecialchars($i)?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if (!$r['issues'] && !$r['notes']): ?><div style="font-size:12.5px;color:#16a34a;margin-top:4px">没发现问题。</div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
