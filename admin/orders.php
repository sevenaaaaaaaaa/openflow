<?php
/**
 * 订单管理与退款
 *
 * 此前全站没有退款入口：ShopSystem 只有 shop_mark_paid()，
 * 收得进、退不出。本页提供订单查询 + 全额/部分退款。
 *
 * 退款走 shop_refund_order()，它会对称回滚支付时发生的每一笔
 * 权益与入账（分销佣金、作者分成、订阅、技能解锁）。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ShopSystem.php';
require_login();
require_perm('shop-settings');

$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refund') {
    csrf_verify();
    $oid    = trim($_POST['order_id'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $r = shop_refund_order($oid, $reason, $amount);
    if ($r['ok']) {
        $message = "订单 {$oid} 已退款 ¥{$r['amount']}";
        if (class_exists('AuditLog')) {
            try {
                AuditLog::log("订单退款 {$oid} ¥{$r['amount']}", 'shop',
                              ['order_id' => $oid, 'amount' => $r['amount'], 'reason' => $reason]);
            } catch (\Throwable $e) {}
        }
    } else {
        $error = $r['error'];
    }
}

$status = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');

// 订单是双源存储：SQLite 为主，data/shop/orders.json 存历史与订阅/实物订单。
// 必须走 shop_all_orders()，否则订阅类订单在后台根本看不见，也就退不了款。
$orders = [];
try {
    $all = shop_all_orders();
    $orders = array_values(array_filter($all, function ($o) use ($status, $q) {
        if ($status !== '' && ($o['status'] ?? '') !== $status) return false;
        if ($q === '') return true;
        $hay = ($o['id'] ?? '') . ' ' . ($o['email'] ?? '') . ' ' . ($o['member_id'] ?? '');
        return mb_stripos($hay, $q) !== false;
    }));
    usort($orders, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    $total  = count($orders);
    $orders = array_slice($orders, 0, 200);
    // 状态统计（全量，不受筛选影响）
    $stats = ['n' => count($all), 'paid' => 0, 'refunded' => 0, 'pending' => 0, 'revenue' => 0.0, 'refund' => 0.0];
    foreach ($all as $o) {
        $st = $o['status'] ?? '';
        if (isset($stats[$st])) $stats[$st]++;
        if ($st === 'paid') $stats['revenue'] += (float)($o['amount'] ?? 0);
        if ($st === 'refunded') $stats['refund'] += (float)($o['refund_amount'] ?? $o['amount'] ?? 0);
    }
} catch (Exception $e) { $error = $error ?: '读取订单失败：' . $e->getMessage(); $total = 0; $stats = null; }

$statusLabels = ['pending'=>'待支付','paid'=>'已支付','refunded'=>'已退款','cancelled'=>'已取消'];

if (!defined('OF_EMBED')) admin_header('订单与退款');
?>
<style>
.od-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.od-kpi{display:block;padding:14px 18px;border-radius:14px;border:1px solid var(--border);background:var(--surface);text-decoration:none;color:inherit;transition:border-color .15s}
a.od-kpi:hover{border-color:var(--border-strong)}
.od-kpi.on{border-color:var(--accent);box-shadow:inset 0 0 0 1px var(--accent)}
.od-kpi .n{font-family:var(--font-mono);font-size:22px;font-weight:800;letter-spacing:-.02em;color:var(--ok)}
.od-kpi .l{font-size:12px;color:var(--muted);margin-top:2px}
.od-modal{display:none;position:fixed;inset:0;background:oklch(12% 0 0/.42);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);z-index:900;align-items:center;justify-content:center;padding:20px}
@media(max-width:840px){.od-kpis{grid-template-columns:1fr 1fr}}
</style>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('orders'); ?>
  <div class="main">
<?php endif; ?>
    <h1>订单与退款</h1>
    <p class="sub">退款会同步回收分销佣金、作者分成、购物积分，并撤销订阅与技能解锁；部分退款只退金额、保留权益。</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <?php if (!empty($stats)): ?>
    <div class="od-kpis">
      <a href="?" class="od-kpi <?=$status===''?'on':''?>"><div class="n">¥<?=number_format($stats['revenue'],0)?></div><div class="l">已支付金额 · <?=$stats['paid']?> 笔</div></a>
      <a href="?status=refunded" class="od-kpi <?=$status==='refunded'?'on':''?>"><div class="n" style="color:var(--danger)">¥<?=number_format($stats['refund'],0)?></div><div class="l">已退款 · <?=$stats['refunded']?> 笔</div></a>
      <a href="?status=pending" class="od-kpi <?=$status==='pending'?'on':''?>"><div class="n" style="color:var(--warn)"><?=$stats['pending']?></div><div class="l">待支付</div></a>
      <div class="od-kpi"><div class="n" style="color:var(--muted)"><?=$stats['n']?></div><div class="l">全部订单</div></div>
    </div>
    <?php endif; ?>

    <form method="get" class="lst-filter" role="search">
      <div class="lst-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg><input type="search" name="q" value="<?=htmlspecialchars($q)?>" placeholder="订单号 / 邮箱 / 会员 ID" aria-label="搜索订单"></div>
      <select name="status" class="lst-sel" onchange="this.form.submit()" aria-label="状态">
        <option value="">全部状态</option>
        <?php foreach ($statusLabels as $k => $v): ?>
          <option value="<?=$k?>"<?=$status===$k?' selected':''?>><?=$v?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-ghost btn-sm">查询</button>
      <?php if ($q || $status): ?><a href="?" class="btn btn-ghost btn-sm">清除</a><?php endif; ?>
      <span class="lst-count"><?=(int)($total ?? 0)?> 笔<?=(($total ?? 0) > 200) ? ' · 显示最近 200' : ''?></span>
    </form>

    <div class="card lst-card">
      <table class="lst-table">
        <thead><tr>
          <th style="width:190px">订单号</th><th class="c-title">商品</th><th style="width:200px">买家</th><th style="width:130px">金额</th>
          <th style="width:90px">状态</th><th style="width:150px">时间</th><th class="c-act" style="width:80px"></th>
        </tr></thead>
        <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="7"><div class="of-empty" style="border:0;margin:0"><?=($q||$status)?'没有匹配的订单，试试清除筛选。':'还没有订单。用户在商城 / 课程 / 会员页付款后会出现在这里。'?></div></td></tr>
        <?php endif; ?>
        <?php foreach ($orders as $o):
          $st = $o['status'] ?? '';
          $amt = (float)($o['amount'] ?? 0);
        ?>
          <tr>
            <td class="lst-slug" style="font-size:12px;overflow:hidden;text-overflow:ellipsis" title="<?=htmlspecialchars($o['id'] ?? '')?>"><?=htmlspecialchars($o['id'] ?? '')?></td>
            <td class="c-title"><div class="lst-title" style="font-size:13.5px"><?=htmlspecialchars($o['course_title'] ?? ($o['goods_type'] ?? '—'))?></div></td>
            <td style="font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($o['email'] ?? ($o['member_id'] ?? ''))?>"><?=htmlspecialchars($o['email'] ?? ($o['member_id'] ?? '—'))?></td>
            <td class="mono" style="font-weight:700">¥<?=number_format($amt, 2)?>
              <?php if ($st === 'refunded' && !empty($o['refund_amount'])): ?>
                <div style="color:var(--danger);font-size:11px;font-weight:500">退 ¥<?=number_format((float)$o['refund_amount'], 2)?></div>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $st === 'paid' ? 'badge-green' : ($st === 'refunded' ? 'badge-red' : ($st === 'pending' ? 'badge-yellow' : 'badge-gray')) ?>">
              <?=htmlspecialchars($statusLabels[$st] ?? $st)?></span></td>
            <td class="lst-when" style="font-size:12px"><?=htmlspecialchars(substr($o['created_at'] ?? '', 0, 16))?></td>
            <td class="c-act">
              <?php if ($st === 'paid'): ?>
                <button type="button" class="btn btn-ghost btn-sm"
                  onclick="openRefund('<?=htmlspecialchars($o['id'] ?? '', ENT_QUOTES)?>', <?=$amt?>)">退款</button>
              <?php elseif ($st === 'refunded'): ?>
                <span style="font-size:11px;color:var(--text-3)" title="<?=htmlspecialchars($o['refund_reason'] ?? '')?>"><?=!empty($o['refund_reason'])?'原因 ⓘ':'已退'?></span>
              <?php else: ?>
                <span style="font-size:11px;color:var(--text-3)">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 退款确认 -->
    <div id="refundBox" class="od-modal" onclick="if(event.target===this)this.style.display='none'">
      <form method="post" class="card" style="max-width:440px;width:100%;margin:0" data-no-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="refund">
        <input type="hidden" name="order_id" id="rfOrder">
        <h2 style="margin-top:0">确认退款</h2>
        <p class="sub" style="margin-bottom:14px">订单 <b id="rfOrderText" class="mono"></b>，已付 <b id="rfPaid"></b></p>
        <div class="field">
          <label>退款金额 <span class="hint">· 留空或填 0 = 全额</span></label>
          <input type="number" name="amount" id="rfAmount" step="0.01" min="0" placeholder="全额">
        </div>
        <div class="field">
          <label>退款原因</label>
          <input type="text" name="reason" placeholder="如：用户申请 / 重复下单">
        </div>
        <p style="font-size:12px;color:var(--danger);line-height:1.7">
          全额退款会同时撤销该订单带来的订阅与技能解锁，并回收已发放的分销佣金与作者分成。部分退款只退金额、保留权益。
        </p>
        <div style="display:flex;gap:8px;margin-top:6px;justify-content:flex-end">
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('refundBox').style.display='none'">取消</button>
          <button type="submit" class="btn btn-primary" style="background:var(--danger);border-color:transparent">确认退款</button>
        </div>
      </form>
    </div>

    <script>
    function openRefund(id, paid) {
      document.getElementById('rfOrder').value = id;
      document.getElementById('rfOrderText').textContent = id;
      document.getElementById('rfPaid').textContent = '¥' + Number(paid).toFixed(2);
      document.getElementById('rfAmount').max = paid;
      document.getElementById('rfAmount').value = '';
      document.getElementById('refundBox').style.display = 'flex';
    }
    </script>
<?php if (!defined('OF_EMBED')): ?>
  </div>
</div>
<?php admin_footer(); endif; ?>
