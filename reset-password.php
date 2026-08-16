<?php
/**
 * 密码重置 — 支持 token 验证 + 自助重置
 */
require_once __DIR__ . '/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $newPassword = trim($_POST['password'] ?? '');

    if (strlen($newPassword) < 8) {
        $error = '密码至少需要 8 位字符。';
    } else {
        $users = get_users();
        $found = null;
        foreach ($users as $username => $u) {
            if (!empty($u['reset_token']) && hash_equals($u['reset_token'], $token)) {
                if (($u['reset_token_expires'] ?? 0) > time()) {
                    $found = $username;
                } else {
                    $error = '重置链接已过期（有效时间 1 小时）。';
                }
                break;
            }
        }
        if ($found) {
            $result = user_reset_password($found, $newPassword);
            if ($result['ok']) {
                $message = "密码重置成功！请使用新密码登录。";
                $users = get_users(); unset($users[$found]['reset_token'], $users[$found]['reset_token_expires']);
                save_users($users);
            } else {
                $error = $result['error'] ?? '重置失败';
            }
        } elseif (!$error) {
            $error = '无效的重置链接。';
        }
    }
}

$token = trim($_GET['token'] ?? '');
$validToken = false;
if ($token) {
    $users = get_users();
    foreach ($users as $u) {
        if (!empty($u['reset_token']) && hash_equals($u['reset_token'], $token) && ($u['reset_token_expires'] ?? 0) > time()) {
            $validToken = true;
            break;
        }
    }
}

admin_header('密码重置');
?>
<style>
.reset-page{display:flex;align-items:center;justify-content:center;min-height:70vh}
.reset-box{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:36px;width:440px;max-width:90vw;box-shadow:var(--shadow);text-align:center}
.reset-box h1{font-size:22px;font-weight:800;margin-bottom:20px}
</style>
<div class="reset-page">
  <div class="reset-box">
    <h1>🔑 密码重置</h1>

    <?php if ($message): ?>
      <div class="msg msg-success"><?=$message?></div>
      <a href="<?=user_login_url($found ?? '')?>" class="btn primary" style="margin-top:12px">前往登录</a>
    <?php elseif ($error): ?>
      <div class="msg msg-error"><?=htmlspecialchars($error)?></div>
    <?php elseif ($validToken): ?>
      <p style="color:var(--muted);font-size:13px;margin-bottom:20px">请设置新密码（至少 8 位字符）</p>
      <form method="post">
        <input type="hidden" name="token" value="<?=htmlspecialchars($token)?>">
        <div class="fld"><input type="password" name="password" class="inp" placeholder="新密码" required minlength="8"></div>
        <button type="submit" class="btn primary" style="width:100%">重置密码</button>
      </form>
    <?php else: ?>
      <p style="color:var(--muted);font-size:13px;line-height:1.8">
        密码重置需要通过邮箱发送重置链接。<br>
        请联系管理员手动重置你的密码。<br>
        <small style="color:var(--faint)">管理员可在后台 → 权限管理 中重置任意用户密码。</small>
      </p>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer();