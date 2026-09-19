<?php
/**
 * 通知渠道 — 企业微信 / 飞书 / Slack / WhatsApp
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/NotifyChannels.php';
require_login();
require_perm('settings');

$channels = notify_channels();
$message = '';
$testMsg = '';

// 保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $channels = [
        'wecom' => ['enabled'=>isset($_POST['wecom_enabled']), 'webhook'=>trim($_POST['wecom_webhook'] ?? ''), 'name'=>'企业微信'],
        'feishu' => ['enabled'=>isset($_POST['feishu_enabled']), 'webhook'=>trim($_POST['feishu_webhook'] ?? ''), 'name'=>'飞书'],
        'slack' => ['enabled'=>isset($_POST['slack_enabled']), 'webhook'=>trim($_POST['slack_webhook'] ?? ''), 'channel'=>trim($_POST['slack_channel'] ?? ''), 'name'=>'Slack'],
        // 任务到期提醒的邮件收件人（负责人邮箱之外再抄送谁；留空=只发负责人）
        'task_email' => ['enabled'=>isset($_POST['te_enabled']), 'to'=>trim($_POST['te_to'] ?? ''), 'name'=>'任务到期邮件'],
        'whatsapp' => ['enabled'=>isset($_POST['wa_enabled']), 'webhook'=>trim($_POST['wa_webhook'] ?? ''), 'token'=>trim($_POST['wa_token'] ?? ''), 'to'=>trim($_POST['wa_to'] ?? ''), 'name'=>'WhatsApp'],
    ];
    notify_channels_save($channels);
    $message = '通知渠道已保存';
}

// 测试发送
if (isset($_GET['test'])) {
    notify_channels_send('测试通知', '这是一条测试消息', 'admin/notify-channels.php');
    $testMsg = '测试消息已发送到启用的渠道';
}

admin_header('通知渠道');
?>
<div class="admin-layout">
  <?php admin_sidebar('notify-channels'); ?>
  <div class="main">
    <h1>通知渠道</h1>
    <p class="sub">站内通知自动转发到企业微信 / 飞书 / Slack / WhatsApp</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($testMsg): ?><?=msg('success', $testMsg)?><?php endif; ?>

    <div class="card" style="background:linear-gradient(135deg,var(--surface),rgba(221,255,14,.08))">
      <h2 style="font-size:15px">💡 说明</h2>
      <p class="text-sm text-muted" style="font-size:13px">所有站内通知（线索、审核、社区、自动化流程等）会自动同步发送到下方启用的外部渠道，方便团队在 IM 中实时接收。</p>
      <div style="margin-top:10px"><a href="?test=1" class="btn btn-primary btn-sm">🧪 发送测试消息</a></div>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>💼 企业微信</h2>
        <p class="text-sm text-muted mb-4">群机器人 Webhook（企业微信群 → 添加群机器人 → 获取 Webhook 地址）</p>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:10px"><input type="checkbox" name="wecom_enabled" value="1" <?=!empty($channels['wecom']['enabled'])?'checked':''?> style="width:16px;height:16px"> 启用企业微信通知</label>
        <div class="field"><label>Webhook 地址</label><input type="text" name="wecom_webhook" value="<?=htmlspecialchars($channels['wecom']['webhook'] ?? '')?>" placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx"></div>
      </div>

      <div class="card">
        <h2>📘 飞书</h2>
        <p class="text-sm text-muted mb-4">群机器人 Webhook（飞书群 → 设置 → 群机器人 → 添加自定义机器人）</p>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:10px"><input type="checkbox" name="feishu_enabled" value="1" <?=!empty($channels['feishu']['enabled'])?'checked':''?> style="width:16px;height:16px"> 启用飞书通知</label>
        <div class="field"><label>Webhook 地址</label><input type="text" name="feishu_webhook" value="<?=htmlspecialchars($channels['feishu']['webhook'] ?? '')?>" placeholder="https://open.feishu.cn/open-apis/bot/v2/hook/xxx"></div>
      </div>

      <div class="card">
        <h2>⏰ 任务到期邮件</h2>
        <p class="text-sm text-muted mb-4">团队视角的任务到期/逾期时，发给<strong>任务负责人</strong>（邮箱在 <a href="/xmp/users">用户与权限</a> 里填）；下面的收件人再抄送一份，负责人没填邮箱时他们是唯一的收件人。</p>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:10px"><input type="checkbox" name="te_enabled" value="1" <?=(!array_key_exists('task_email', $channels) || !empty($channels['task_email']['enabled'])) ? 'checked' : ''?> style="width:16px;height:16px"> 启用任务到期邮件</label>
        <div class="field"><label>抄送收件人 <span class="hint">多个用逗号分隔；留空=只发负责人</span></label><input type="text" name="te_to" value="<?=htmlspecialchars($channels['task_email']['to'] ?? '')?>" placeholder="ops@example.com, lead@example.com"></div>
        <p class="text-xs text-muted" style="margin-top:8px">需要先在 <a href="/xmp/mail-settings">邮件设置</a> 里启用一个发信渠道；没有可用渠道时提醒不会发送，也不会被标记为已提醒（配好后照常发）。</p>
      </div>

      <div class="card">
        <h2>🧵 Slack</h2>
        <p class="text-sm text-muted mb-4">Incoming Webhook（Slack → Apps → Incoming Webhooks → 复制 Webhook URL）</p>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:10px"><input type="checkbox" name="slack_enabled" value="1" <?=!empty($channels['slack']['enabled'])?'checked':''?> style="width:16px;height:16px"> 启用 Slack 通知</label>
        <div class="field-row">
          <div class="field"><label>Webhook 地址</label><input type="text" name="slack_webhook" value="<?=htmlspecialchars($channels['slack']['webhook'] ?? '')?>" placeholder="https://hooks.slack.com/services/xxx"></div>
          <div class="field"><label>频道（可留空）</label><input type="text" name="slack_channel" value="<?=htmlspecialchars($channels['slack']['channel'] ?? '')?>" placeholder="#ops"></div>
        </div>
      </div>

      <div class="card">
        <h2>💬 WhatsApp</h2>
        <p class="text-sm text-muted mb-4">WhatsApp Business Cloud API 或第三方网关</p>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:10px"><input type="checkbox" name="wa_enabled" value="1" <?=!empty($channels['whatsapp']['enabled'])?'checked':''?> style="width:16px;height:16px"> 启用 WhatsApp 通知</label>
        <div class="field-row">
          <div class="field"><label>API 端点</label><input type="text" name="wa_webhook" value="<?=htmlspecialchars($channels['whatsapp']['webhook'] ?? '')?>" placeholder="Graph API 或第三方端点"></div>
          <div class="field"><label>Token</label><input type="password" name="wa_token" value="<?=htmlspecialchars($channels['whatsapp']['token'] ?? '')?>" placeholder="访问令牌"></div>
          <div class="field"><label>接收号码</label><input type="text" name="wa_to" value="<?=htmlspecialchars($channels['whatsapp']['to'] ?? '')?>" placeholder="+8613800000000"></div>
        </div>
      </div>

      <button type="submit" name="save" class="btn btn-primary">保存配置</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
