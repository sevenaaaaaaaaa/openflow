<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('email');

$smsFile = DATA_DIR . '/sms.json';
$sms = json_read($smsFile);
$historyFile = DATA_DIR . '/sms-history.json';
$history = json_read($historyFile);

$message = '';

// Save provider config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_provider'])) {
    csrf_verify();
    $sms['provider'] = $_POST['provider'] ?? 'aliyun';
    $sms['access_key'] = $_POST['access_key'] ?? '';
    $sms['access_secret'] = $_POST['access_secret'] ?? '';
    $sms['sign_name'] = $_POST['sign_name'] ?? '';
    $sms['template_code'] = $_POST['template_code'] ?? '';
    $sms['enabled'] = isset($_POST['enabled']);
    json_write($smsFile, $sms);
    $message = '短信配置已保存';
}

// Send SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    csrf_verify();
    $phone = trim($_POST['phone'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($phone && $content) {
        $history[] = [
            'id' => 'sms_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'phone' => $phone,
            'content' => $content,
            'provider' => $sms['provider'] ?? '',
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ];
        json_write($historyFile, $history);
        $message = '短信已发送（演示模式，实际发送需配置供应商 API）';
    } else {
        $message = '请填写手机号和内容';
    }
}

$providers = [
    'aliyun' => '阿里云短信',
    'tencent' => '腾讯云短信',
    'qiniu' => '七牛云短信',
    'submail' => 'Submail',
    'smsbao' => '短信宝',
];

admin_header('短信管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('sms'); ?>
  <div class="main">
    <h1>短信管理</h1>
    <p class="sub">短信发送 · 三方供应商配置 · 发送历史</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="stats" style="grid-template-columns:repeat(3,1fr)">
      <div class="stat-card"><div class="num"><?=count($history)?></div><div class="label">总发送条数</div></div>
      <div class="stat-card"><div class="num"><?=$sms['enabled']??false?'✓':'—'?></div><div class="label">短信服务状态</div></div>
      <div class="stat-card"><div class="num"><?=htmlspecialchars($providers[$sms['provider'] ?? ''] ?? '未配置')?></div><div class="label">当前供应商</div></div>
    </div>

    <!-- Provider Config -->
    <div class="card">
      <h2>📡 短信供应商配置</h2>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field-row">
          <div class="field"><label>供应商</label>
            <select name="provider">
              <?php foreach ($providers as $pk => $pv): ?>
              <option value="<?=$pk?>" <?=($sms['provider']??'')===$pk?'selected':''?>><?=htmlspecialchars($pv)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>短信签名</label><input type="text" name="sign_name" value="<?=htmlspecialchars($sms['sign_name']??'')?>" placeholder="如: OpenFlow"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>Access Key</label><input type="text" name="access_key" value="<?=htmlspecialchars($sms['access_key']??'')?>" placeholder="供应商提供的 AccessKey"></div>
          <div class="field"><label>Access Secret</label><input type="password" name="access_secret" value="<?=htmlspecialchars($sms['access_secret']??'')?>" placeholder="供应商提供的 Secret"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>模板 Code</label><input type="text" name="template_code" value="<?=htmlspecialchars($sms['template_code']??'')?>" placeholder="SMS_XXXXXX"></div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:24px"><input type="checkbox" name="enabled" value="1" <?=($sms['enabled']??false)?'checked':''?> style="width:18px;height:18px">启用短信服务</label></div>
        </div>
        <button type="submit" name="save_provider" class="btn btn-primary">保存配置</button>
      </form>
    </div>

    <!-- Send SMS -->
    <div class="card">
      <h2>✉️ 发送短信</h2>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label>手机号</label><input type="tel" name="phone" placeholder="13800138000" required></div>
        <div class="field"><label>内容 <span class="hint">将自动附加签名</span></label><textarea name="content" rows="3" required placeholder="短信内容…"></textarea></div>
        <button type="submit" name="send" class="btn btn-primary">发送</button>
      </form>
    </div>

    <!-- History -->
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">📋 发送历史</h2>
      <?php if (empty($history)): ?>
      <div class="empty">暂无发送记录</div>
      <?php else: ?>
      <table>
        <thead><tr><th>时间</th><th>手机号</th><th>内容</th><th>状态</th></tr></thead>
        <tbody>
          <?php foreach (array_reverse($history) as $h): ?>
          <tr>
            <td class="text-sm text-muted" style="white-space:nowrap"><?=htmlspecialchars($h['sent_at']??'')?></td>
            <td><?=htmlspecialchars($h['phone']??'')?></td>
            <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($h['content']??'')?></td>
            <td><span class="badge badge-green">已发送</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
