<?php
/**
 * 商城与分销设置 — 课程定价 / 虎皮椒支付 / 分销规则 / 提现
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ShopSystem.php';
require_login();
require_perm('settings');

$settings = shop_settings();
$message = '';

// 保存设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $course_prices = [];
    foreach (($_POST['course_price'] ?? []) as $cid => $price) $course_prices[$cid] = (float)$price;
    $settings['enabled'] = isset($_POST['enabled']);
    $settings['xfpay_appid'] = trim($_POST['xfpay_appid'] ?? '');
    $settings['xfpay_secret'] = trim($_POST['xfpay_secret'] ?? '');
    $settings['commission_rate'] = max(0, min(90, (int)($_POST['commission_rate'] ?? 20)));
    $settings['min_withdraw'] = (float)($_POST['min_withdraw'] ?? 100);
    $settings['course_prices'] = $course_prices;
    json_write(shop_settings_file(), $settings);
    $message = '商城设置已保存';
}

// 提现审核
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_withdraw'])) {
    csrf_verify();
    $withdrawals = json_read(DATA_DIR . '/shop/withdrawals.json');
    foreach ($withdrawals as &$w) {
        if ($w['member_id'] === ($_POST['member_id'] ?? '') && ($w['status'] ?? '') === 'pending') {
            $w['status'] = $_POST['approve'] === '1' ? 'approved' : 'rejected';
            break;
        }
    }
    unset($w);
    json_write(DATA_DIR . '/shop/withdrawals.json', $withdrawals);
    flash('success', '提现已处理');
    header('Location: shop-settings.php');
    exit;
}

$courses = json_read(DATA_DIR . '/courses/index.json');
$withdrawals = json_read(DATA_DIR . '/shop/withdrawals.json');
$pendingWithdrawals = array_values(array_filter($withdrawals, fn($w) => ($w['status'] ?? '') === 'pending'));
$members = member_get_all();

admin_header('商城与分销');
?>
<div class="admin-layout">
  <?php admin_sidebar('shop-settings'); ?>
  <div class="main">
    <h1>🛒 商城与分销</h1>
    <p class="sub">课程定价 · 虎皮椒支付 · 分销佣金 · 提现审核</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
          <h2 style="margin-bottom:0">⚙️ 支付设置</h2>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px"><input type="checkbox" name="enabled" value="1" <?=$settings['enabled']?'checked':''?> style="width:16px;height:16px"> 启用在线购买</label>
        </div>
        <div class="field-row">
          <div class="field"><label>虎皮椒 APPID</label><input type="text" name="xfpay_appid" value="<?=htmlspecialchars($settings['xfpay_appid'])?>" placeholder="在虎皮椒后台获取"></div>
          <div class="field"><label>虎皮椒通讯密钥</label><input type="password" name="xfpay_secret" value="<?=htmlspecialchars($settings['xfpay_secret'])?>" placeholder="通讯密钥"></div>
        </div>
        <p class="text-sm text-muted">虎皮椒（XorPay）聚合支付，一个商户号支持微信/支付宝/云闪付等。访问 <a href="https://www.xunhupay.com" target="_blank" rel="noopener" style="color:#0284c7">xunhupay.com</a> 注册获取。</p>
      </div>

      <div class="card">
        <h2>💳 课程定价</h2>
        <table>
          <thead><tr><th>课程</th><th>类型</th><th>状态</th><th style="width:140px">价格（元）</th></tr></thead>
          <tbody>
            <?php foreach ($courses as $c): ?>
            <tr>
              <td><strong><?=htmlspecialchars($c['title'])?></strong></td>
              <td class="text-sm text-muted"><?=htmlspecialchars($c['type'] ?? '课程')?></td>
              <td><span class="badge <?=($c['status']??'draft')==='published'?'badge-green':'badge-gray'?>"><?=$c['status']??'draft'?></span></td>
              <td><input type="number" name="course_price[<?=htmlspecialchars($c['id'])?>]" value="<?=htmlspecialchars($settings['course_prices'][$c['id']] ?? '')?>" step="0.01" min="0" style="width:100%;padding:8px;border:1.5px solid var(--border);border-radius:8px"></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <h2>🤝 分销规则</h2>
        <div class="field-row">
          <div class="field"><label>佣金比例 <span class="hint">· 成交价 %</span></label><input type="number" name="commission_rate" value="<?=htmlspecialchars($settings['commission_rate'])?>" min="0" max="90">%</div>
          <div class="field"><label>最低提现金额</label><input type="number" name="min_withdraw" value="<?=htmlspecialchars($settings['min_withdraw'])?>" min="0"> 元</div>
        </div>
        <button type="submit" name="save" class="btn btn-primary">保存设置</button>
      </div>
    </form>

    <!-- 提现审核 -->
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">💰 提现申请 <?php if($pendingWithdrawals): ?><span class="badge badge-red"><?=count($pendingWithdrawals)?></span><?php endif; ?></h2>
      <table>
        <thead><tr><th>大使</th><th>金额</th><th>时间</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($withdrawals)): ?><tr><td colspan="5" class="empty">暂无提现记录</td></tr><?php endif; ?>
          <?php foreach (array_reverse($withdrawals) as $w): ?>
          <tr>
            <td><?=htmlspecialchars($w['member_name'] ?? '')?></td>
            <td><strong>¥<?=number_format($w['amount']??0,2)?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($w['created_at']??'',0,10))?></td>
            <td><span class="badge <?=['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-gray'][$w['status']??'pending']?>"><?=['pending'=>'待处理','approved'=>'已打款','rejected'=>'已驳回'][$w['status']??'pending']?></span></td>
            <td>
              <?php if (($w['status']??'')==='pending'): ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
