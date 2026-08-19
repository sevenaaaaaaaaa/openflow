<?php
/**
 * 触达频控 / 疲劳度 — 跨渠道触达上限配置
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/FrequencyCap.php';
require_login();
require_perm('settings');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    freq_save_config([
        'email_daily' => max(1, (int)($_POST['email_daily'] ?? 2)),
        'email_weekly' => max(1, (int)($_POST['email_weekly'] ?? 6)),
        'inbox_daily' => max(1, (int)($_POST['inbox_daily'] ?? 3)),
        'notify_daily' => max(1, (int)($_POST['notify_daily'] ?? 2)),
    ]);
    $message = '频控配置已保存';
}
$cfg = freq_config();

admin_header('触达频控');
?>
<div class="admin-layout">
  <?php admin_sidebar('frequency-cap'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>🛡 触达频控 · 疲劳度</h1><p class="v-sub">限制每个用户每日/每周各渠道触达上限，自动化流程自动遵守</p></div>
    </div>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <form method="post" class="card">
      <?= csrf_field() ?>
      <h2 style="margin-bottom:16px">各渠道每日/每周上限</h2>
      <div class="field-row">
        <div class="field"><label>📧 邮件 · 每日上限</label><input type="number" name="email_daily" value="<?=$cfg['email_daily']?>" min="1"></div>
        <div class="field"><label>📧 邮件 · 每周上限</label><input type="number" name="email_weekly" value="<?=$cfg['email_weekly']?>" min="1"></div>
      </div>
      <div class="field-row">
        <div class="field"><label>✉️ 站内信 · 每日上限</label><input type="number" name="inbox_daily" value="<?=$cfg['inbox_daily']?>" min="1"></div>
        <div class="field"><label>🔔 通知 · 每日上限</label><input type="number" name="notify_daily" value="<?=$cfg['notify_daily']?>" min="1"></div>
      </div>
      <p class="text-sm text-muted" style="margin-top:8px">自动化流程发送邮件/站内信/通知前自动检查，超限则跳过并记日志。防止过度营销导致退订与反感。</p>
      <button class="btn btn-s btn-sm" style="margin-top:8px">保存配置</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
