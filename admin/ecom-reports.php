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

// ─── 购买链路分析 ───
// 全部订单（含近 days 天）状态分布
$allOrders = [];
try { $allOrders = Database::query("SELECT * FROM orders"); } catch (Exception $e) {}
$statusDist = [];
foreach ($allOrders as $o) {
    $st = $o['status'] ?? 'unknown';
    $statusDist[$st] = ($statusDist[$st] ?? 0) + 1;
}
// 支付方式分布（已支付）
$payDist = [];
foreach ($allOrders as $o) { if (($o['status'] ?? '') === 'paid') { $pm = $o['payment_method'] ?: 'unknown'; $payDist[$pm] = ($payDist[$pm] ?? 0) + 1; } }
// 退款率
$refundedCount = count(array_filter($allOrders, fn($o) => !empty($o['refunded_at'])));
$paidCount = count(array_filter($allOrders, fn($o) => ($o['status'] ?? '') === 'paid'));
$refundRate = $paidCount > 0 ? round($refundedCount / $paidCount * 100, 1) : 0;
// 购买漏斗（近 days 天）：浏览→下单→支付→退款
$funnelBrowse = 0; $funnelOrder = 0; $funnelPaid = 0; $funnelRefund = 0;
try {
    $funnelBrowse = (int)(Database::query("SELECT COUNT(DISTINCT uid) c FROM events WHERE event IN ('page_view','course_view','product_view') AND created_at >= ?", [$cutoff])[0]['c'] ?? 0);
    $funnelOrder = count($recent);
    $funnelPaid = count(array_filter($recent, fn($o) => ($o['status'] ?? '') === 'paid'));
    $funnelRefund = count(array_filter($recent, fn($o) => !empty($o['refunded_at'])));
} catch (Exception $e) {}
// 客件/件单价
$totalQty = array_sum(array_map(fn($o) => (int)($o['qty'] ?? 1), $recent));
$totalRevenue = array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), array_filter($recent, fn($o) => ($o['status'] ?? '') === 'paid')));
$avgItems = $orderCount > 0 ? round($totalQty / $orderCount, 1) : 0;

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

    <!-- 购买链路 -->
    <div class="panel" style="margin-top:20px">
      <div class="p-head"><h3>🔗 购买链路（近<?=$days?>天）</h3><span class="p-sub mono">浏览 → 下单 → 支付 → 退款</span></div>
      <div class="p-body">
        <div style="display:flex;gap:0;align-items:stretch" class="funnel-chain">
          <?php
          $funnelSteps = [
              ['label' => '浏览/查看', 'count' => $funnelBrowse, 'icon' => '👀'],
              ['label' => '下单', 'count' => $funnelOrder, 'icon' => '🛒'],
              ['label' => '支付成功', 'count' => $funnelPaid, 'icon' => '💰'],
              ['label' => '退款', 'count' => $funnelRefund, 'icon' => '↩️'],
          ];
          $prev = null;
          foreach ($funnelSteps as $fs):
              $rate = ($prev !== null && $prev > 0) ? round($fs['count'] / $prev * 100, 1) : 100;
              $color = $fs['count'] === 0 ? 'var(--faint)' : ($fs['label'] === '退款' ? 'var(--warn)' : 'var(--accent)');
          ?>
          <div style="flex:1;padding:14px;border:1px solid var(--border);border-radius:12px;margin:0 4px;text-align:center;background:var(--bg)">
            <div style="font-size:22px"><?=$fs['icon']?></div>
            <div style="font-size:20px;font-weight:800;color:<?=$color?>;margin-top:4px"><?=number_format($fs['count'])?></div>
            <div style="font-size:11px;color:var(--muted)"><?=$fs['label']?></div>
            <?php if ($prev !== null): ?><div style="font-size:10px;color:var(--faint);margin-top:4px">转化 <?=$rate?>%</div><?php endif; ?>
          </div>
          <?php if ($fs['label'] !== '退款'): ?><div style="align-self:center;color:var(--faint)">→</div><?php endif; ?>
          <?php $prev = $fs['count']; endforeach; ?>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:14px">
          <div style="padding:10px 14px;border-radius:10px;background:var(--bg)"><div style="font-size:13px;font-weight:700"><?=$refundRate?>%</div><div style="font-size:11px;color:var(--muted)">退款率（<?=$refundedCount?> / <?=$paidCount?> 已支付）</div></div>
          <div style="padding:10px 14px;border-radius:10px;background:var(--bg)"><div style="font-size:13px;font-weight:700"><?=$avgItems?> 件</div><div style="font-size:11px;color:var(--muted)">平均件单</div></div>
          <div style="padding:10px 14px;border-radius:10px;background:var(--bg)"><div style="font-size:13px;font-weight:700"><?=count($payDist)?> 种</div><div style="font-size:11px;color:var(--muted)">支付方式</div></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
          <?php foreach ($payDist as $pm => $n): ?><span class="badge badge-gray" style="font-size:12px"><?=htmlspecialchars($pm ?: '未填')?> <?=$n?>单</span><?php endforeach; ?>
          <?php if (empty($payDist)): ?><span style="font-size:11px;color:var(--faint)">暂无支付数据</span><?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
          <?php foreach ($statusDist as $st => $n): ?><span style="font-size:11px;padding:2px 10px;border-radius:999px;background:var(--hover);color:var(--muted)"><?=htmlspecialchars($st === 'paid' ? '已支付' : ($st === 'pending' ? '待支付' : $st))?> <?=$n?></span><?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="panels" style="margin-top:20px">
      <!-- 每日 GMV 趋势 -->
      <div class="panel">
        <div class="p-head"><h3>GMV 趋势</h3><span class="p-sub mono">按天</span></div>
        <div class="p-body">
          <div style="display:flex;gap:3px;align-items:flex-end;height:120px">
            <?php $maxG = max($dailyGmv) ?: 1; foreach ($dailyGmv as $d => $v): ?>
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
