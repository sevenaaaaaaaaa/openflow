<?php
/**
 * 支付设置 — 多渠道支付配置
 * 虎皮椒（完整）/ 微信 / 支付宝 / PayPal / 信用卡 / 云闪付 / Stripe / Link（预留）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/PaymentChannel.php';
require_login();
require_perm('settings');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $channels = payment_channels();
    $enabled = $_POST['enabled'] ?? [];
    $params = $_POST['params'] ?? [];
    foreach ($channels as $key => $ch) {
        if (isset($channels[$key])) {
            $channels[$key]['enabled'] = !empty($enabled[$key]);
            $channels[$key]['params'] = $params[$key] ?? [];
        }
    }
    if (!empty($_POST['default_channel'])) $channels['_default'] = $_POST['default_channel'];
    payment_channels_save($channels);
    $message = '支付设置已保存';
}

$channels = payment_channels();
$defs = payment_channel_defs();

if (!defined('OF_EMBED')) admin_header('支付设置');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('payment-settings'); ?>
  <div class="main">
<?php endif; ?>
    <div class="v-head">
      <div><h1>支付设置</h1><p class="v-sub">配置支付渠道。虎皮椒已完整接入；其余渠道已预留配置入口，接入 SDK 后即插即用。</p></div>
      <div class="v-actions"></div>
    </div>
    <?php if ($message): ?><div class="msg msg-success" style="margin-bottom:16px"><?=htmlspecialchars($message)?></div><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <?php foreach ($defs as $key => $def): $ch = $channels[$key] ?? []; $status = $def['status'] ?? 'skeleton'; ?>
      <div class="card" style="margin-bottom:16px;padding:20px">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <div style="min-width:0">
            <h3 style="font-size:15px;font-weight:600;display:flex;align-items:center;gap:8px">
              <?=htmlspecialchars($def['label'])?>
              <?php if ($status === 'ready'): ?><span class="st st-ok">已接入</span><?php else: ?><span class="st st-faint">预留</span><?php endif; ?>
            </h3>
            <p style="font-size:12px;color:var(--muted);margin-top:2px"><?=htmlspecialchars($def['desc'])?></p>
          </div>
          <label style="margin-left:auto;display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--muted)">
            启用
            <input type="checkbox" name="enabled[<?=$key?>]" value="1" <?=!empty($ch['enabled'])?'checked':''?> style="width:18px;height:18px">
          </label>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:14px">
          <?php foreach ($def['fields'] as $f => $label): $isSecret = in_array($f, ['secret','api_key','pass','token','private_key','secret_key']); ?>
          <div class="field">
            <label style="font-size:12px;font-weight:600;color:var(--muted);display:block;margin-bottom:4px"><?=htmlspecialchars($label)?></label>
            <input type="<?=$isSecret?'password':'text'?>" name="params[<?=$key?>][<?=$f?>]" value="<?=htmlspecialchars($ch['params'][$f] ?? '')?>" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:10px;font-size:13px;background:var(--surface);color:var(--fg)" placeholder="<?=htmlspecialchars($label)?>">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <div class="card" style="padding:20px;margin-bottom:16px">
        <h3 style="font-size:15px;font-weight:600;margin-bottom:12px">默认支付渠道</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php foreach ($defs as $key => $def): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:var(--muted)">
            <input type="radio" name="default_channel" value="<?=$key?>" <?=(($channels['_default'] ?? 'xfpay') === $key)?'checked':''?> style="width:16px;height:16px">
            <?=htmlspecialchars($def['label'])?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-p">保存支付设置</button>
    </form>
<?php if (!defined('OF_EMBED')): ?>
  </div>
</div>
<?php admin_footer(); endif; ?>
