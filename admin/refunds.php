<?php
/**
 * 退款/售后工作台 — 退款处理 + 佣金自动扣回
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_login();
require_perm('settings');

$message = '';

// 退款处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund'])) {
    csrf_verify();
    $orderId = trim($_POST['order_id'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    try {
        $order = Database::query("SELECT * FROM orders WHERE id = ?", [$orderId]);
        $o = $order[0] ?? null;
        if (!$o || $o['status'] !== 'paid') { flash('error', '订单不存在或非已支付状态'); }
        else {
            // 标记退款
            Database::execute("UPDATE orders SET status='refunded', refunded_at=?, refund_reason=? WHERE id=?", [date('Y-m-d H:i:s'), $reason, $orderId]);
            // 佣金扣回：分销者
            if (!empty($o['referrer_id']) && (float)$o['commission'] > 0) {
                Database::execute("UPDATE members SET balance = balance - ? WHERE id = ?", [(float)$o['commission'], $o['referrer_id']]);
                Database::insert('point_logs', ['member_id'=>$o['referrer_id'],'points'=>0,'type'=>'refund','description'=>"订单 {$orderId} 退款扣回佣金 ¥{$o['commission']}",'created_at'=>date('Y-m-d H:i:s')]);
            }
            // 作者分成扣回（商品订单）
            if (!empty($o['author']) && ($o['goods_type'] ?? '') === 'product') {
                $platform = round((float)$o['amount'] * 0.1, 2);
                $authorAmt = round((float)$o['amount'] - $platform - (float)$o['commission'], 2);
                if ($authorAmt > 0) {
                    Database::execute("UPDATE members SET balance = balance - ? WHERE id = ?", [$authorAmt, $o['author']]);
                    Database::insert('point_logs', ['member_id'=>$o['author'],'points'=>0,'type'=>'refund','description'=>"订单 {$orderId} 退款扣回分成 ¥{$authorAmt}",'created_at'=>date('Y-m-d H:i:s')]);
                }
            }
            // 解锁撤销（unlocked_skills 移除该商品）
            if (!empty($o['product_id'])) {
                try {
                    $m = Database::query("SELECT unlocked_skills FROM members WHERE id=?", [$o['member_id']]);
                    if ($m) {
                        $skills = json_decode($m[0]['unlocked_skills'] ?? '[]', true) ?: [];
                        $new = array_values(array_filter($skills, fn($s) => $s !== $o['product_id']));
                        Database::execute("UPDATE members SET unlocked_skills=? WHERE id=?", [json_encode($new), $o['member_id']]);
                    }
                } catch (Exception $e) {}
            }
            // 库存回滚（退款恢复库存）
            try {
                require_once __DIR__ . '/../lib/CommerceSystem.php';
                commerce_stock_increment($o['product_id'] ?? '', $o['sku_id'] ?? '');
            } catch (Exception $e) {}
            flash('success', '已退款，佣金与分成已扣回');
        }
    } catch (Exception $e) { flash('error', '退款失败：' . $e->getMessage()); }
    header('Location: /xmp/refunds');
    exit;
}

// 发货处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ship'])) {
    csrf_verify();
    $orderId = trim($_POST['order_id'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $tracking = trim($_POST['tracking_no'] ?? '');
    try {
        Database::execute("INSERT INTO shipments (order_id, company, tracking_no, status, created_at, updated_at) VALUES (?,?,?,?,?,?)", [$orderId,$company,$tracking,'shipped',date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);
        Database::execute("UPDATE orders SET status='shipped', shipped_at=? WHERE id=?", [date('Y-m-d H:i:s'),$orderId]);
        flash('success', '已发货：' . $company . ' ' . $tracking);
    } catch (Exception $e) { flash('error', '发货失败：' . $e->getMessage()); }
    header('Location: /xmp/refunds');
    exit;
}

// 订单列表（paid 状态可退款）
try {
    $orders = Database::query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 200");
} catch (Exception $e) { $orders = []; }
$members = json_read(DATA_DIR . '/members/index.json');
$mName = [];
foreach ((array)$members as $m) $mName[$m['id']] = $m['name'] ?? ($m['email'] ?? $m['id']);
$statusMap = ['pending'=>'待支付','paid'=>'已支付','shipped'=>'已发货','refunded'=>'已退款','cancelled'=>'已取消'];

admin_header('退款售后');
?>
<div class="admin-layout">
  <?php admin_sidebar('refunds'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>退款售后</h1><p class="v-sub">退款处理 · 自动扣回分销佣金与作者分成 · 撤销商品解锁</p></div>
      <div class="v-actions"><?php if ($message): ?><span class="st st-ok"><?=htmlspecialchars($message)?></span><?php endif; ?></div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
      <?php $c = ['paid'=>0,'refunded'=>0,'pending'=>0]; foreach ($orders as $o) { if (isset($c[$o['status']])) $c[$o['status']]++; } ?>
      <div class="card" style="padding:14px;text-align:center"><div style="font-size:24px;font-weight:800;color:var(--ok)"><?=$c['paid']?></div><div style="font-size:12px;color:var(--muted)">可退款（已支付）</div></div>
      <div class="card" style="padding:14px;text-align:center"><div style="font-size:24px;font-weight:800;color:var(--danger)"><?=$c['refunded']?></div><div style="font-size:12px;color:var(--muted)">已退款</div></div>
      <div class="card" style="padding:14px;text-align:center"><div style="font-size:24px;font-weight:800;color:var(--warn)"><?=$c['pending']?></div><div style="font-size:12px;color:var(--muted)">待支付</div></div>
    </div>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>订单号</th><th>买家</th><th>商品</th><th>金额</th><th>佣金</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($orders)): ?><tr><td colspan="8" class="empty">暂无订单</td></tr><?php endif; ?>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($o['id'],-12))?></td>
            <td><?=htmlspecialchars($mName[$o['member_id']] ?? $o['member_id'])?></td>
            <td><?=htmlspecialchars(mb_substr($o['course_title'] ?? '',0,24))?></td>
            <td style="font-weight:600">¥<?=number_format($o['amount']??0,2)?><?=!empty($o['original_amount']) && $o['original_amount'] != $o['amount'] ? ' <span style="color:var(--faint);text-decoration:line-through;font-size:11px">¥'.number_format($o['original_amount'],0).'</span>' : ''?></td>
            <td class="text-sm text-muted"><?=!empty($o['commission'])?'¥'.$o['commission']:'—'?></td>
            <td><span style="color:<?=['paid'=>'var(--ok)','shipped'=>'var(--accent)','refunded'=>'var(--danger)','pending'=>'var(--warn)','cancelled'=>'var(--faint)'][$o['status']]??'var(--faint)'?>"><?=$statusMap[$o['status']]??$o['status']?></span></td>
            <td class="text-sm text-muted"><?=substr($o['created_at']??'',0,16)?></td>
            <td>
              <?php if ($o['status'] === 'paid'): ?>
              <form method="post" style="display:flex;gap:6px;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="ship" value="1">
                <input type="hidden" name="order_id" value="<?=htmlspecialchars($o['id'])?>">
                <input type="text" name="company" placeholder="快递公司" style="width:70px;padding:5px 8px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
                <input type="text" name="tracking_no" placeholder="运单号" style="width:90px;padding:5px 8px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
                <button class="btn btn-s btn-sm">发货</button>
              </form>
              <form method="post" style="display:flex;gap:6px;align-items:center;margin-top:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="refund" value="1">
                <input type="hidden" name="order_id" value="<?=htmlspecialchars($o['id'])?>">
                <input type="text" name="reason" placeholder="退款原因" style="width:70px;padding:5px 8px;border:1.5px solid var(--border);border-radius:8px;font-size:12px">
                <button class="btn btn-danger btn-sm" data-confirm="确认退款？将扣回佣金与分成">退款</button>
              </form>
              <?php else: ?><span class="text-sm" style="color:var(--faint)">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
