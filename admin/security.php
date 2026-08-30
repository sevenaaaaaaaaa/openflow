<?php
/**
 * 账号安全 —— 当前登录管理员的两步验证（2FA）设置
 *
 * 开启流程：生成密钥 → 认证器扫码 → 输入一次验证码确认 → 落库 + 发恢复码。
 * 关闭需要再验一次当前验证码，防止别人趁你离开工位关掉。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Totp.php';
require_login();

$me = $_SESSION['admin_user'] ?? '';
$users = get_users();
if (!isset($users[$me])) { http_response_code(403); die('账号不存在'); }

$enabled = !empty($users[$me]['totp_secret']);
$message = ''; $error = '';
$showRecovery = [];   // 刚生成/重置时一次性展示

// ── 开启：确认验证码 ──
if (($_POST['action'] ?? '') === 'enable') {
    $secret = trim($_POST['secret'] ?? '');
    $code   = trim($_POST['code'] ?? '');
    if ($enabled) {
        $error = '已经开启了两步验证。';
    } elseif ($secret === '' || !Totp::verify($secret, $code)) {
        $error = '验证码不正确，请确认认证器时间正确后重试。';
        $_SESSION['pending_secret'] = $secret;   // 保留本次密钥，避免重扫
    } else {
        $recovery = Totp::recoveryCodes();
        $users[$me]['totp_secret'] = $secret;
        $users[$me]['totp_recovery'] = $recovery;
        $users[$me]['totp_enabled_at'] = date('Y-m-d H:i:s');
        save_users($users);
        unset($_SESSION['pending_secret']);
        audit('开启两步验证', 'auth', ['user' => $me]);
        $enabled = true;
        $showRecovery = $recovery;
        $message = '两步验证已开启。请务必保存下面的恢复码——手机丢了时用它登录，且每个只能用一次。';
    }
}

// ── 关闭：验证当前码 ──
if (($_POST['action'] ?? '') === 'disable') {
    $code = trim($_POST['code'] ?? '');
    if (!$enabled) {
        $error = '尚未开启。';
    } elseif (!Totp::verify($users[$me]['totp_secret'], $code)) {
        $error = '验证码不正确，无法关闭。';
    } else {
        unset($users[$me]['totp_secret'], $users[$me]['totp_recovery'], $users[$me]['totp_enabled_at']);
        save_users($users);
        audit('关闭两步验证', 'auth', ['user' => $me]);
        $enabled = false;
        $message = '两步验证已关闭。';
    }
}

// ── 重新生成恢复码 ──
if (($_POST['action'] ?? '') === 'regen_recovery') {
    $code = trim($_POST['code'] ?? '');
    if (!$enabled) {
        $error = '尚未开启两步验证。';
    } elseif (!Totp::verify($users[$me]['totp_secret'], $code)) {
        $error = '验证码不正确。';
    } else {
        $recovery = Totp::recoveryCodes();
        $users[$me]['totp_recovery'] = $recovery;
        save_users($users);
        audit('重置恢复码', 'auth', ['user' => $me]);
        $showRecovery = $recovery;
        $message = '恢复码已重新生成，旧的立即失效。';
    }
}

// 未开启时准备一个待确认的密钥（本次会话内保持稳定，刷新不换）
$pendingSecret = '';
if (!$enabled) {
    $pendingSecret = $_SESSION['pending_secret'] ?? '';
    if ($pendingSecret === '') {
        $pendingSecret = Totp::secret();
        $_SESSION['pending_secret'] = $pendingSecret;
    }
}
$issuer = 'OpenFlow';
$otpUri = $pendingSecret ? Totp::uri($pendingSecret, $me, $issuer) : '';
$remaining = $enabled ? count($users[$me]['totp_recovery'] ?? []) : 0;

admin_header('账号安全');
?>
<div class="admin-layout">
  <?php admin_sidebar('security'); ?>
  <div class="main">
    <h1>账号安全 · 两步验证</h1>
    <p class="sub">开启后，登录时除了密码，还需要输入认证器 App 上的 6 位动态码。强烈建议管理员账号开启。</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <?php if ($showRecovery): ?>
      <div class="card" style="border:1px solid var(--warn)">
        <h2 style="margin-top:0;font-size:15px">🔑 恢复码（只显示这一次）</h2>
        <p class="sub">把它们抄到安全的地方。每个只能用一次，用于手机丢失时登录。</p>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;font-family:monospace;font-size:15px;margin-top:8px">
          <?php foreach ($showRecovery as $rc): ?><div style="padding:8px 12px;background:var(--surface-2);border-radius:8px;letter-spacing:1px"><?=htmlspecialchars($rc)?></div><?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($enabled): ?>
      <div class="card">
        <p style="margin:0 0 4px"><span class="badge ok">已开启</span> 开启于 <?=htmlspecialchars($users[$me]['totp_enabled_at'] ?? '—')?></p>
        <p class="sub" style="margin:6px 0 0">剩余可用恢复码：<b><?=$remaining?></b> 个<?php if ($remaining <= 2): ?>（偏少，建议重新生成）<?php endif; ?></p>
      </div>

      <div class="card">
        <h2 style="margin-top:0;font-size:15px">重新生成恢复码</h2>
        <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="regen_recovery">
          <div class="field" style="margin:0"><label>当前验证码</label><input type="text" name="code" placeholder="6 位数字" inputmode="numeric" maxlength="6" required></div>
          <button class="btn btn-ghost">重新生成</button>
        </form>
      </div>

      <div class="card" style="border-color:var(--danger)">
        <h2 style="margin-top:0;font-size:15px;color:var(--danger)">关闭两步验证</h2>
        <p class="sub">关闭后账号仅靠密码保护，安全性下降。</p>
        <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="disable">
          <div class="field" style="margin:0"><label>当前验证码</label><input type="text" name="code" placeholder="6 位数字" inputmode="numeric" maxlength="6" required></div>
          <button class="btn btn-danger">确认关闭</button>
        </form>
      </div>
    <?php else: ?>
      <div class="card">
        <h2 style="margin-top:0;font-size:15px">1 · 用认证器 App 扫码</h2>
        <p class="sub">Google Authenticator、Microsoft Authenticator、1Password 等都支持。</p>
        <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;margin-top:8px">
          <div id="qrbox" style="width:180px;height:180px;background:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center"></div>
          <div>
            <p class="sub" style="margin:0 0 4px">不能扫码？手动输入密钥：</p>
            <code style="font-size:15px;letter-spacing:1px;word-break:break-all;background:var(--surface-2);padding:8px 12px;border-radius:8px;display:inline-block"><?=htmlspecialchars($pendingSecret)?></code>
          </div>
        </div>
      </div>
      <div class="card">
        <h2 style="margin-top:0;font-size:15px">2 · 输入 App 显示的 6 位码确认</h2>
        <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="enable">
          <input type="hidden" name="secret" value="<?=htmlspecialchars($pendingSecret)?>">
          <div class="field" style="margin:0"><label>验证码</label><input type="text" name="code" placeholder="6 位数字" inputmode="numeric" maxlength="6" required autofocus></div>
          <button class="btn btn-primary">开启两步验证</button>
        </form>
      </div>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
      <script>
        (function () {
          var uri = <?=json_encode($otpUri)?>;
          try { new QRCode(document.getElementById('qrbox'), { text: uri, width: 168, height: 168 }); }
          catch (e) { document.getElementById('qrbox').textContent = '二维码加载失败，请用上面的密钥手动添加'; }
        })();
      </script>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
