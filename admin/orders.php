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

$orders = [];
try {
    $sql = "SELECT * FROM orders";
    $where = []; $args = [];
    if ($status !== '') { $where[] = 'status = ?'; $args[] = $status; }
    if ($q !== '')      { $where[] = '(id LIKE ? OR email LIKE ? OR member_id LIKE ?)';
                          $args[] = "%{$q}%"; $args[] = "%{$q}%"; $args[] = "%{$q}%"; }
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY created_at DESC LIMIT 200';
    $orders = Database::query($sql, $args);
} catch (Exception $e) { $error = $error ?: '读取订单失败：' . $e->getMessage(); }

$statusLabels = ['pending'=>'待支付','paid'=>'已支付','refunded'=>'已退款','cancelled'=>'已取消'];

if (!defined('OF_EMBED')) admin_header('订单与退款');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('orders'); ?>
  <div class="main">
<?php endif; ?>
    <h1>订单与退款</h1>
    <p class="sub">查询订单并办理退款。退款会同步回收分销佣金、作者分成，并撤销订阅与技能解锁。</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <form method="get" class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="field" style="margin:0">
        <label>订单号 / 邮箱 / 会员</label>
        <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="模糊匹配">
      </div>
      <div class="field" style="margin:0">
        <label>状态</label>
        <select name="status">
          <option value="">全部</option>
          <?php foreach ($statusLabels as $k => $v): ?>
            <option value="<?=$k?>"<?=$status===$k?' selected':''?>><?=$v?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary">查询</button>
    </form>

    <div class="card" style="padding:0;overflow-x:auto">
      <table class="table">
        <thead><tr>
          <th>订单号</th><th>商品</th><th>买家</th><th>金额</th>
          <th>状态</th><th>时间</th><th style="width:1%">操作</th>
        </tr></thead>
        <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="7" class="empty">没有匹配的订单</td></tr>
        <?php endif; ?>
        <?php foreach ($orders as $o):
          $st = $o['status'] ?? '';
          $amt = (float)($o['amount'] ?? 0);
        ?>
          <tr>
            <td class="mono" style="font-size:12px"><?=htmlspecialchars($o['id'] ?? '')?></td>
            <td><?=htmlspecialchars($o['course_title'] ?? ($o['goods_type'] ?? '—'))?></td>
            <td style="font-size:12px"><?=htmlspecialchars($o['email'] ?? ($o['member_id'] ?? '—'))?></td>
            <td class="mono">¥<?=number_format($amt, 2)?>
              <?php if ($st === 'refunded' && !empty($o['refund_amount'])): ?>
                <span style="color:var(--danger);font-size:11px">（退 ¥<?=number_format((float)$o['refund_amount'], 2)?>）</span>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $st === 'paid' ? 'ok' : ($st === 'refunded' ? 'danger' : '') ?>">
              <?=htmlspecialchars($statusLabels[$st] ?? $st)?></span></td>
            <td style="font-size:12px;color:var(--text-3)"><?=htmlspecialchars($o['created_at'] ?? '')?></td>
            <td>
              <?php if ($st === 'paid'): ?>
                <button type="button" class="btn btn-ghost btn-sm"
                  onclick="openRefund('<?=htmlspecialchars($o['id'] ?? '', ENT_QUOTES)?>', <?=$amt?>)">退款</button>
              <?php elseif ($st === 'refunded'): ?>
                <span style="font-size:11px;color:var(--text-3)" title="<?=htmlspecialchars($o['refund_reason'] ?? '')?>">已退</span>
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
    <div id="refundBox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:900;align-items:center;justify-content:center">
      <form method="post" class="card" style="max-width:420px;width:92%;margin:0">
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
        <div style="display:flex;gap:8px;margin-top:6px">
          <button type="submit" class="btn btn-primary">确认退款</button>
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('refundBox').style.display='none'">取消</button>
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
