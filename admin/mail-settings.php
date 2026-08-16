<?php
/**
 * 邮件设置 — 多渠道邮件配置
 * SMTP（完整）/ BillionMail / Ghost / 自定义 Webhook
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MailChannel.php';
require_login();
require_perm('settings');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $channels = mail_channels();
    $enabled = $_POST['enabled'] ?? [];
    $params = $_POST['params'] ?? [];
    foreach ($channels as $key => $ch) {
        if ($key === '_default') continue;
        $channels[$key]['enabled'] = !empty($enabled[$key]);
        $channels[$key]['params'] = $params[$key] ?? [];
    }
    if (!empty($_POST['default_channel'])) $channels['_default'] = $_POST['default_channel'];
    mail_channels_save($channels);
    $message = '邮件设置已保存';
}

$channels = mail_channels();
$defs = mail_channel_defs();

admin_header('邮件设置');
?>
<div class="admin-layout">
  <?php admin_sidebar('mail-settings'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>邮件设置</h1><p class="v-sub">配置邮件渠道，用于表单提交等通知邮件。SMTP 已完整实现，其余渠道 HTTP 接入。</p></div>
      <div class="v-actions"></div>
    </div>
    <?php if ($message): ?><div class="msg msg-success" style="margin-bottom:16px"><?=htmlspecialchars($message)?></div><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <?php foreach ($defs as $key => $def): $ch = $channels[$key] ?? []; ?>
      <div class="card" style="margin-bottom:16px;padding:20px">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <div style="min-width:0">
            <h3 style="font-size:15px;font-weight:600"><?=htmlspecialchars($def['label'])?></h3>
            <p style="font-size:12px;color:var(--muted);margin-top:2px"><?=htmlspecialchars($def['desc'])?></p>
          </div>
          <label style="margin-left:auto;display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--muted)">
            启用
            <input type="checkbox" name="enabled[<?=$key?>]" value="1" <?=!empty($ch['enabled'])?'checked':''?> style="width:18px;height:18px">
          </label>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:14px">
          <?php foreach ($def['fields'] as $f => $label): $isSecret = in_array($f, ['pass','api_key']); ?>
          <div class="field">
            <label style="font-size:12px;font-weight:600;color:var(--muted);display:block;margin-bottom:4px"><?=htmlspecialchars($label)?></label>
            <input type="<?=$isSecret?'password':'text'?>" name="params[<?=$key?>][<?=$f?>]" value="<?=htmlspecialchars($ch['params'][$f] ?? '')?>" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:10px;font-size:13px;background:var(--surface);color:var(--fg)" placeholder="<?=htmlspecialchars($label)?>">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <div class="card" style="padding:20px;margin-bottom:16px">
        <h3 style="font-size:15px;font-weight:600;margin-bottom:12px">默认邮件渠道</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php foreach ($defs as $key => $def): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:var(--muted)">
            <input type="radio" name="default_channel" value="<?=$key?>" <?=(($channels['_default'] ?? 'smtp') === $key)?'checked':''?> style="width:16px;height:16px">
            <?=htmlspecialchars($def['label'])?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-p">保存邮件设置</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
