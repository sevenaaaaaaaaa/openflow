<?php
/**
 * 分销系统管理 — 大使列表 / 佣金明细 / 提现审核 / 分销配置
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ShopSystem.php';

require_login();
if (!has_perm('shop-settings') && !has_perm('settings')) { http_response_code(403); exit('无权限'); }

$message = '';
$tab = $_GET['tab'] ?? 'ambassadors';

// ─── 提现审核 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_action'])) {
    csrf_verify();
    $withdrawals = json_read(DATA_DIR . '/shop/withdrawals.json');
    $wid = $_POST['withdraw_id'] ?? '';
    $action = $_POST['withdraw_action'] ?? '';
    $members = json_read(DATA_DIR . '/members/index.json');

    foreach ($withdrawals as &$w) {
        if (($w['id'] ?? '') === $wid && ($w['status'] ?? '') === 'pending') {
            if ($action === 'approve') {
                $w['status'] = 'approved';
                $w['approved_at'] = date('Y-m-d H:i:s');
                $message = '已通过提现申请 ¥' . $w['amount'];
            } elseif ($action === 'reject') {
                // 驳回：退回余额
                $w['status'] = 'rejected';
                $w['rejected_at'] = date('Y-m-d H:i:s');
                foreach ($members as &$m) {
                    if ($m['id'] === $w['member_id']) {
                        $m['balance'] = ($m['balance'] ?? 0) + $w['amount'];
                        break;
                    }
                }
                unset($m);
                json_write(DATA_DIR . '/members/index.json', $members);
                $message = '已驳回提现申请，余额已退回';
            }
            break;
        }
    }
    unset($w);
    json_write(DATA_DIR . '/shop/withdrawals.json', $withdrawals);
}

// ─── 分销配置保存 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify();
    $s = json_read(DATA_DIR . '/shop/settings.json');
    $s['commission_rate'] = (float)($_POST['commission_rate'] ?? 20);
    $s['min_withdraw'] = (float)($_POST['min_withdraw'] ?? 100);
    $s['enabled'] = isset($_POST['enabled']);
    json_write(DATA_DIR . '/shop/settings.json', $s);
    $message = '分销配置已保存';
}

$settings = json_read(DATA_DIR . '/shop/settings.json');
$members = json_read(DATA_DIR . '/members/index.json');
$orders = shop_all_orders();
$withdrawals = json_read(DATA_DIR . '/shop/withdrawals.json');

// 大使列表（有 referral_code 或 ambassador 标记的会员）
$ambassadors = array_values(array_filter($members, fn($m) => !empty($m['referral_code'])));
foreach ($ambassadors as &$a) {
    $a['_orders'] = count(array_filter($orders, fn($o) => ($o['referrer_id'] ?? '') === $a['id']));
    $a['_commission'] = array_sum(array_column(array_filter($orders, fn($o) => ($o['referrer_id'] ?? '') === $a['id'] && ($o['status'] ?? '') === 'paid'), 'commission'));
}
unset($a);

// 待审核提现
$pendingWithdrawals = array_values(array_filter($withdrawals, fn($w) => ($w['status'] ?? '') === 'pending'));

admin_header('分销管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('shop-settings'); ?>
  <div class="main">
<h1>分销系统</h1>
<p class="sub">管理推荐大使、佣金明细与提现审核</p>

<?php if ($message): ?><div class="msg msg-success"><?=htmlspecialchars($message)?></div><?php endif; ?>

<div class="tabs">
  <a href="?tab=ambassadors" class="<?=$tab==='ambassadors'?'active':''?>">大使列表</a>
  <a href="?tab=orders" class="<?=$tab==='orders'?'active':''?>">佣金明细</a>
  <a href="?tab=withdrawals" class="<?=$tab==='withdrawals'?'active':''?>">提现审核 <?=$pendingWithdrawals ? '(' . count($pendingWithdrawals) . ')' : ''?></a>
  <a href="?tab=settings" class="<?=$tab==='settings'?'active':''?>">分销配置</a>
</div>

<?php if ($tab === 'ambassadors'): ?>
<div class="stats">
  <div class="stat-card"><div class="num"><?=count($ambassadors)?></div><div class="label">大使总数</div></div>
  <div class="stat-card"><div class="num"><?=count(array_filter($orders, fn($o) => !empty($o['referrer_id'])))?></div><div class="label">分销订单</div></div>
  <div class="stat-card"><div class="num">¥<?=number_format(array_sum(array_column(array_filter($orders, fn($o) => ($o['status']??'')==='paid'), 'commission_amount')), 0)?></div><div class="label">已发放佣金</div></div>
  <div class="stat-card"><div class="num"><?=count($pendingWithdrawals)?></div><div class="label">待审核提现</div></div>
</div>
<div class="card">
  <h2>👥 推荐大使列表</h2>
  <?php if (empty($ambassadors)): ?>
  <div class="empty">暂无大使。前台会员可申请成为推荐大使。</div>
  <?php else: ?>
  <div style="overflow:auto">
  <table>
    <thead><tr><th>大使</th><th>推荐码</th><th>佣金余额</th><th>分销订单</th><th>已获佣金</th><th>推广点击</th></tr></thead>
    <tbody>
      <?php foreach ($ambassadors as $a): ?>
      <tr>
        <td><strong><?=htmlspecialchars($a['name'] ?? $a['email'] ?? $a['id'])?></strong></td>
        <td><code><?=htmlspecialchars($a['referral_code'] ?? '')?></code></td>
        <td>¥<?=number_format($a['balance'] ?? 0, 2)?></td>
        <td><?=$a['_orders']?></td>
        <td>¥<?=number_format($a['_commission'], 2)?></td>
        <td><?=$a['ambassador_stats']['clicks'] ?? 0?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'orders'): ?>
<div class="card">
  <h2>💰 分销订单佣金明细</h2>
  <?php
  $distOrders = array_values(array_filter($orders, fn($o) => !empty($o['referrer_id'])));
  if (empty($distOrders)): ?>
  <div class="empty">暂无分销订单。当大使推荐注册的用户下单时，此处会显示佣金记录。</div>
  <?php else: ?>
  <div style="overflow:auto">
  <table>
    <thead><tr><th>订单</th><th>商品</th><th>金额</th><th>佣金</th><th>状态</th><th>推荐人</th><th>时间</th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($distOrders) as $o):
        $referrerName = '';
        foreach ($members as $m) if ($m['id'] === ($o['referrer_id'] ?? '')) { $referrerName = $m['name'] ?? $m['email'] ?? ''; break; }
      ?>
      <tr>
        <td><code><?=htmlspecialchars($o['id'] ?? '')?></code></td>
        <td><?=htmlspecialchars($o['course_title'] ?? $o['goods_title'] ?? '')?></td>
        <td>¥<?=number_format($o['amount'] ?? 0, 2)?></td>
        <td><strong>¥<?=number_format($o['commission_amount'] ?? 0, 2)?></strong></td>
        <td><?=($o['status'] ?? '') === 'paid' ? '<span class="pill ok"><span class="dot"></span>已支付</span>' : '<span class="pill gray">' . htmlspecialchars($o['status'] ?? '') . '</span>'?></td>
        <td><?=htmlspecialchars($referrerName ?: '—')?></td>
        <td class="text-sm text-muted"><?=htmlspecialchars(substr($o['created_at'] ?? '', 0, 16))?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'withdrawals'): ?>
<div class="card">
  <h2>🏦 提现申请审核</h2>
  <?php if (empty($withdrawals)): ?>
  <div class="empty">暂无提现申请</div>
  <?php else: ?>
  <div style="overflow:auto">
  <table>
    <thead><tr><th>申请人</th><th>金额</th><th>状态</th><th>申请时间</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($withdrawals) as $w): ?>
      <tr>
        <td><?=htmlspecialchars($w['member_name'] ?? '')?></td>
        <td><strong>¥<?=number_format($w['amount'] ?? 0, 2)?></strong></td>
        <td>
          <?php if (($w['status'] ?? '') === 'pending'): ?><span class="pill warn"><span class="dot"></span>待审核</span>
          <?php elseif (($w['status'] ?? '') === 'approved'): ?><span class="pill ok"><span class="dot"></span>已通过</span>
          <?php else: ?><span class="pill err"><span class="dot"></span>已驳回</span><?php endif; ?>
        </td>
        <td class="text-sm text-muted"><?=htmlspecialchars(substr($w['created_at'] ?? '', 0, 16))?></td>
        <td>
          <?php if (($w['status'] ?? '') === 'pending'): ?>
          <form method="post" style="display:inline-flex;gap:6px">
            <?= csrf_field() ?>
            <input type="hidden" name="withdraw_id" value="<?=htmlspecialchars($w['id'] ?? '')?>">
            <button name="withdraw_action" value="approve" class="btn btn-sm" style="background:var(--ok);color:#fff" data-confirm="确认打款 ¥<?=$w['amount']?>？">通过</button>
            <button name="withdraw_action" value="reject" class="btn btn-sm" style="background:#fee2e2;color:var(--danger)" data-confirm="确认驳回并退回余额?">驳回</button>
          </form>
          <?php else: ?><span class="text-sm text-muted">—</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'settings'): ?>
<div class="card" style="max-width:480px">
  <h2>⚙️ 分销配置</h2>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="enabled" value="1" <?=!empty($settings['enabled'])?'checked':''?> style="width:18px;height:18px">启用分销系统</label></div>
    <div class="field"><label>佣金比例 (%)</label><input type="number" name="commission_rate" value="<?=htmlspecialchars($settings['commission_rate'] ?? 20)?>" min="0" max="100" step="0.5"></div>
    <div class="field"><label>最低提现金额 (¥)</label><input type="number" name="min_withdraw" value="<?=htmlspecialchars($settings['min_withdraw'] ?? 100)?>" min="1"></div>
    <button type="submit" name="save_settings" class="btn btn-primary">保存配置</button>
  </form>
  <div class="msg msg-info" style="margin-top:12px">佣金在下单支付成功后自动入账大使余额。推广链接格式：<code><?=site_config_get('site_url')?>/?ref=推荐码</code></div>
</div>
<?php endif; ?>

  </div>
</div>

<?php admin_footer(); ?>
