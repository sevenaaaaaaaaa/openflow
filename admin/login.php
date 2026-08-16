<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    header("Location: /admin/workspace.php");
    exit;
}

// ── CAPTCHA generation ──
if (!isset($_SESSION['captcha_answer'])) {
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    $_SESSION['captcha_answer'] = $a + $b;
    $_SESSION['captcha_text'] = "$a + $b = ?";
}
$captchaText = $_SESSION['captcha_text'];

$error = '';

// ── IP Rate Limiting ──
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$rateFile = DATA_DIR . '/login_rate.json';
$rateData = json_read($rateFile);
$now = time();
$ipAttempts = $rateData[$ip] ?? ['count' => 0, 'last' => 0];

// Reset if more than 15 minutes passed
if ($now - $ipAttempts['last'] > 900) {
    $ipAttempts = ['count' => 0, 'last' => $now];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $users = get_users();
    // Check rate limit (5 attempts per 15 minutes)
    if ($ipAttempts['count'] >= 5) {
        $error = '登录尝试次数过多，请 15 分钟后再试。';
    } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captchaInput = trim($_POST['captcha'] ?? '');

    // CAPTCHA verification
    $captchaOk = (int)$captchaInput === (int)($_SESSION['captcha_answer'] ?? -1);
    // Regenerate on wrong answer
    if (!$captchaOk) {
        $a = random_int(2, 9); $b = random_int(2, 9);
        $_SESSION['captcha_answer'] = $a + $b;
        $_SESSION['captcha_text'] = "$a + $b = ?";
        $captchaText = $_SESSION['captcha_text'];
        $error = '验证码错误，请重试。';
    }

    if ($captchaOk) {
        if (isset($users[$username]) && password_verify($password, $users[$username]['password_hash'])) {
            // Reset rate limit on success
            unset($rateData[$ip]);
            json_write($rateFile, $rateData);

            session_regenerate_id(true);
            $_SESSION['admin_login'] = true;
            $_SESSION['admin_user'] = $username;
            $_SESSION['admin_role'] = $users[$username]['role'];
            $_SESSION['admin_name'] = $users[$username]['name'];
            session_write_close();
            header("Location: /admin/workspace.php");
            exit;
        }
        // Increment rate limit on failure
        $ipAttempts['count']++;
        $ipAttempts['last'] = $now;
        $rateData[$ip] = $ipAttempts;
        json_write($rateFile, $rateData);

        $error = '用户名或密码错误。';
        // Regenerate captcha on failed login too
        $a = random_int(2, 9); $b = random_int(2, 9);
        $_SESSION['captcha_answer'] = $a + $b;
        $_SESSION['captcha_text'] = "$a + $b = ?";
        $captchaText = $_SESSION['captcha_text'];
    }
    } // End rate limit check
}

admin_header('登录');
?>
<div class="login-page">
  <div class="login-box">
    <h1>OpenFlow</h1>
    <p class="sub">管理后台登录</p>
    <?php if ($error): ?><div class="msg msg-error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="fld">
        <label>用户名</label>
        <input type="text" name="username" class="inp" placeholder="输入用户名" required autofocus autocomplete="username">
      </div>
      <div class="fld">
        <label>密码</label>
        <input type="password" name="password" class="inp" placeholder="输入密码" required autocomplete="current-password">
      </div>
      <div class="fld">
        <label>验证码 · <?=htmlspecialchars($captchaText)?></label>
        <input type="text" name="captcha" class="inp" placeholder="输入计算结果" required inputmode="numeric" autocomplete="off">
      </div>
      <button type="submit" class="btn primary" style="width:100%;margin-top:8px">登录</button>
    </form>
  </div>
</div>
<?php admin_footer();
