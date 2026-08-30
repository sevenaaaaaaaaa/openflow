<?php
/**
 * 电商运营报表 — GMV / 订单 / 复购 / 客单价 / RFM
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_login();
require_perm('settings');

// 订单数据（Database orders 表）
try { $orders = Database::query("SELECT * FROM orders WHERE status='paid' ORDER BY paid_at DESC"); } catch (Exception $e) { $orders = []; }

$days = (int)($_GET['days'] ?? 30);
$cutoff = date('Y-m-d', strtotime("-{$days} days"));
$recent = array_values(array_filter($orders, fn($o) => ($o['paid_at'] ?? '') >= $cutoff));

// GMV / 订单统计
$gmv = round(array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $recent)), 2);
$orderCount = count($recent);
$aov = $orderCount > 0 ? round($gmv / $orderCount, 2) : 0;

// 复购率（周期内购买≥2次的用户 / 购买≥1次）
$userOrders = [];
foreach ($recent as $o) { $k = $o['member_id'] ?: $o['member_email']; if ($k) $userOrders[$k] = ($userOrders[$k] ?? 0) + 1; }
$buyers = count($userOrders);
$repeatBuyers = count(array_filter($userOrders, fn($n) => $n >= 2));
$repeatRate = $buyers > 0 ? round($repeatBuyers / $buyers * 100, 1) : 0;

// 每日 GMV 趋势
$dailyGmv = [];
foreach ($recent as $o) {
    $d = substr($o['paid_at'] ?? '', 0, 10);
    $dailyGmv[$d] = ($dailyGmv[$d] ?? 0) + (float)($o['amount'] ?? 0);
}
ksort($dailyGmv);

// 商品销量 TOP
$productSales = [];
foreach ($recent as $o) {
    $k = $o['course_title'] ?: '其他';
    $productSales[$k] = ($productSales[$k] ?? 0) + 1;
}
arsort($productSales);

// RFM（CDP）
$rfm = [];
try { $rfm = CdpSystem::getRFMAnalysis(); } catch (Throwable $e) {}

admin_header('电商报表');
?>
<div class="admin-layout">
  <?php admin_sidebar('commerce'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>电商运营报表</h1><p class="v-sub">GMV · 订单 · 复购 · 客单价 · 商品销量</p></div>
      <div class="v-actions">
        <a href="?days=7" class="btn btn-s btn-sm <?=$days==7?'on':''?>" style="<?=$days==7?'border-color:var(--accent);color:var(--accent)':''?>">7天</a>
        <a href="?days=30" class="btn btn-s btn-sm <?=$days==30?'on':''?>" style="<?=$days==30?'border-color:var(--accent);color:var(--accent)':''?>">30天</a>
        <a href="?days=90" class="btn btn-s btn-sm <?=$days==90?'on':''?>" style="<?=$days==90?'border-color:var(--accent);color:var(--accent)':''?>">90天</a>
      </div>
    </div>

    <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
      <div class="kpi"><div class="k-label">GMV（<?=$days?>天）</div><div class="k-val mono" style="color:var(--ok)">¥<?=number_format($gmv,0)?></div></div>
      <div class="kpi"><div class="k-label">订单数</div><div class="k-val mono"><?=$orderCount?></div></div>
      <div class="kpi"><div class="k-label">客单价</div><div class="k-val mono">¥<?=number_format($aov,0)?></div></div>
      <div class="kpi"><div class="k-label">购买用户</div><div class="k-val mono"><?=$buyers?></div></div>
      <div class="kpi"><div class="k-label">复购率</div><div class="k-val mono" style="color:<?=$repeatRate>30?'var(--ok)':'var(--warn)'?>"><?=$repeatRate?>%</div><div class="k-sub"><?=$repeatBuyers?> 人复购</div></div>
    </div>

    <div class="panels" style="margin-top:20px">
      <!-- 每日 GMV 趋势 -->
      <div class="panel">
        <div class="p-head"><h3>GMV 趋势</h3><span class="p-sub mono">按天</span></div>
        <div class="p-body">
          <div style="display:flex;gap:3px;align-items:flex-end;height:120px">
            <?php $maxG = $dailyGmv ? (max($dailyGmv) ?: 1) : 1; foreach ($dailyGmv as $d => $v): ?>
            <div style="flex:1;text-align:center" title="<?=$d?> · ¥<?=number_format($v,0)?>">
              <div style="background:var(--accent);opacity:<?=max(0.2,$v/$maxG)?>;border-radius:3px 3px 0 0;height:<?=$v>0?max(4,round($v/$maxG*100)):2?>px"></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;justify-content:space-between;font-family:var(--font-mono);font-size:9px;color:var(--faint);margin-top:4px"><span><?=substr(array_key_first($dailyGmv)??'',5)?></span><span><?=substr(array_key_last($dailyGmv)??'',5)?></span></div>
        </div>
      </div>

      <!-- 商品销量 TOP -->
      <div class="panel">
        <div class="p-head"><h3>商品销量 TOP</h3><span class="p-sub mono">近<?=$days?>天</span></div>
        <div class="p-body">
          <?php if (empty($productSales)): ?><div class="empty" style="padding:12px 0;font-size:12px;color:var(--faint)">暂无销售数据</div>
          <?php else: $maxS = max($productSales) ?: 1; foreach (array_slice($productSales, 0, 8, true) as $name => $cnt): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <span style="font-size:12px;width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)"><?=htmlspecialchars($name)?></span>
            <div style="flex:1;height:20px;border-radius:6px;background:var(--hover);overflow:hidden"><div style="height:100%;width:<?=round($cnt/$maxS*100)?>%;background:var(--accent);border-radius:6px"></div></div>
            <span class="num" style="font-size:11px;width:30px;text-align:right"><?=$cnt?></span>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($rfm['distribution'] ?? [])): ?>
    <div class="panel" style="margin-top:20px">
      <div class="p-head"><h3>RFM 客户分层</h3><span class="p-sub mono">对标 CDP</span></div>
      <div class="p-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px">
          <?php foreach (($rfm['distribution'] ?? []) as $seg => $cnt): ?>
          <div style="padding:12px;border-radius:10px;background:var(--bg);text-align:center"><div style="font-size:22px;font-weight:800;color:var(--accent)"><?=$cnt?></div><div style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($seg)?></div></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
