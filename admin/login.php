<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    $ob = json_read(DATA_DIR . '/onboarding.json');
    header("Location: " . (empty($ob['completed']) ? "/xmp/onboarding" : "/xmp/workspace"));
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
$need2fa = false;   // 是否渲染「第二步：两步验证码」表单

require_once __DIR__ . '/../lib/Totp.php';

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

// 完成登录的公共收尾
$finishLogin = function (array $users, string $username) use (&$rateData, $ip, $rateFile) {
    unset($rateData[$ip]);
    json_write($rateFile, $rateData);
    session_regenerate_id(true);
    $_SESSION['admin_login'] = true;
    $_SESSION['admin_user'] = $username;
    $_SESSION['admin_role'] = $users[$username]['role'];
    $_SESSION['admin_name'] = $users[$username]['name'];
    audit('登录成功', 'auth', ['user' => $username, 'two_factor' => !empty($users[$username]['totp_secret'])]);
    session_write_close();
    $ob = json_read(DATA_DIR . '/onboarding.json');
    header("Location: " . (empty($ob['completed']) ? "/xmp/onboarding" : "/xmp/workspace"));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $users = get_users();

    // ── 忘记密码：用户名 + 绑定邮箱双因子核验 → 发重置邮件 ──
    // （此前 user_send_reset_email() 与 reset-password.php 均已存在，但登录页没有入口，
    //    令牌永远无法生成 —— 典型的「开发了没接通」。此处接通，并做防枚举与防滥发。）
    if (($_POST['forgot'] ?? '') === '1') {
        if ($ipAttempts['count'] >= 5) {
            $error = '尝试次数过多，请 15 分钟后再试。';
        } else {
            $fuser = trim($_POST['username'] ?? '');
            $femail = trim($_POST['email'] ?? '');
            $ipAttempts['count']++; $ipAttempts['last'] = $now;
            $rateData[$ip] = $ipAttempts; json_write($rateFile, $rateData);
            $matched = isset($users[$fuser]) && !empty($users[$fuser]['email'])
                && hash_equals(strtolower((string)$users[$fuser]['email']), strtolower($femail));
            if ($matched) {
                $r = user_send_reset_email($fuser);
                audit('请求密码重置', 'auth', ['user' => $fuser, 'sent' => !empty($r['ok'])]);
                if (!empty($r['ok'])) {
                    $resetMsg = '重置邮件已发送到该账号绑定的邮箱，1 小时内有效。';
                } elseif (!empty($r['token_url'])) {
                    // SMTP 未配置：用户名+邮箱已双因子核验通过，直接给链接（仅此情形势必直给）
                    $resetMsg = 'SMTP 未配置无法发邮件。身份已核验，请直接打开此链接重置（1 小时内有效）：' . $r['token_url'];
                } else {
                    $resetMsg = '发送失败：' . ($r['error'] ?? '未知错误');
                }
            } else {
                audit('密码重置请求被拒（用户名/邮箱不匹配）', 'auth', ['user' => $fuser]);
                $resetMsg = '如果用户名与绑定邮箱匹配，重置邮件将会发出。';   // 模糊提示防枚举
            }
        }
    }
    // ── 第二步：两步验证 ──
    // 密码已在上一步验过，用 session 里的 pending_2fa 承接，避免重输密码。
    $pending = $_SESSION['pending_2fa'] ?? null;
    if ($pending && (($_POST['totp'] ?? '') !== '' || ($_POST['recovery'] ?? '') !== '')) {
        $need2fa = true;
        $puser = $pending['user'] ?? '';
        if (($now - ($pending['ts'] ?? 0)) > 300) {
            unset($_SESSION['pending_2fa']);
            $error = '验证超时，请重新登录。';
            $need2fa = false;
        } elseif (isset($users[$puser]) && !empty($users[$puser]['totp_secret'])) {
            $u = $users[$puser];
            $totp = trim($_POST['totp'] ?? '');
            $recovery = strtoupper(trim($_POST['recovery'] ?? ''));
            $twoOk = false;
            if ($totp !== '' && Totp::verify($u['totp_secret'], $totp)) {
                $twoOk = true;
            } elseif ($recovery !== '' && !empty($u['totp_recovery'])) {
                foreach ($u['totp_recovery'] as $idx => $rc) {   // 恢复码一次性
                    if (hash_equals((string)$rc, $recovery)) {
                        $twoOk = true;
                        unset($users[$puser]['totp_recovery'][$idx]);
                        $users[$puser]['totp_recovery'] = array_values($users[$puser]['totp_recovery']);
                        save_users($users);
                        $_SESSION['_flash'] = ['type' => 'warning', 'text' => '本次用恢复码登录，请尽快在「账号安全」重新生成恢复码。'];
                        break;
                    }
                }
            }
            if ($twoOk) {
                unset($_SESSION['pending_2fa']);
                $finishLogin($users, $puser);
            }
            $ipAttempts['count']++; $ipAttempts['last'] = $now; $rateData[$ip] = $ipAttempts; json_write($rateFile, $rateData);
            audit('两步验证失败', 'auth', ['user' => $puser]);
            $error = '两步验证码不正确，请重试。';
        } else {
            unset($_SESSION['pending_2fa']);
            $error = '会话已失效，请重新登录。';
            $need2fa = false;
        }
    }
    // ── 第一步：用户名 + 密码 + 图形验证码 ──
    elseif ($ipAttempts['count'] >= 5) {
        $error = '登录尝试次数过多，请 15 分钟后再试。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $captchaInput = trim($_POST['captcha'] ?? '');
        $captchaOk = (int)$captchaInput === (int)($_SESSION['captcha_answer'] ?? -1);
        if (!$captchaOk) {
            $a = random_int(2, 9); $b = random_int(2, 9);
            $_SESSION['captcha_answer'] = $a + $b;
            $_SESSION['captcha_text'] = "$a + $b = ?";
            $captchaText = $_SESSION['captcha_text'];
            $error = '验证码错误，请重试。';
        }
        if ($captchaOk) {
            if (isset($users[$username]) && password_verify($password, $users[$username]['password_hash'])) {
                if (!empty($users[$username]['totp_secret'])) {
                    // 开了两步验证 → 进第二步，不在此刻建立登录态
                    $_SESSION['pending_2fa'] = ['user' => $username, 'ts' => $now];
                    $need2fa = true;
                } else {
                    $finishLogin($users, $username);
                }
            } else {
                $ipAttempts['count']++;
                $ipAttempts['last'] = $now;
                $rateData[$ip] = $ipAttempts;
                json_write($rateFile, $rateData);
                $error = '用户名或密码错误。';
                $a = random_int(2, 9); $b = random_int(2, 9);
                $_SESSION['captcha_answer'] = $a + $b;
                $_SESSION['captcha_text'] = "$a + $b = ?";
                $captchaText = $_SESSION['captcha_text'];
            }
        }
    }
}

admin_header('登录');
?>
<div class="login-page">
  <div class="login-box">
    <div style="display:flex;justify-content:center;margin-bottom:6px"><img src="/favicon.svg" alt="OpenFlow" width="56" height="56" style="border-radius:14px"></div>
    <h1>OpenFlow</h1>
    <p class="sub">管理后台登录</p>
    <?php if ($error): ?><div class="msg msg-error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if ($need2fa): ?>
    <!-- 第二步：两步验证 -->
    <p class="sub" style="margin-top:-6px">请输入认证器 App 上的 6 位动态验证码。</p>
    <form method="post">
      <?= csrf_field() ?>
      <div class="fld">
        <label>动态验证码</label>
        <input type="text" name="totp" class="inp" placeholder="6 位数字" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="6">
      </div>
      <details style="margin:8px 0">
        <summary style="cursor:pointer;font-size:13px;color:var(--muted)">手机丢了？用恢复码</summary>
        <div class="fld" style="margin-top:8px">
          <label>恢复码</label>
          <input type="text" name="recovery" class="inp" placeholder="XXXXX-XXXXX" autocomplete="off">
        </div>
      </details>
      <button type="submit" class="btn primary" style="width:100%;margin-top:8px">验证并登录</button>
      <a href="/xmp/login" class="sub" style="display:block;text-align:center;margin-top:10px;font-size:13px">← 重新登录</a>
    </form>
    <?php elseif (isset($_GET['forgot']) || isset($resetMsg)): ?>
    <!-- 忘记密码：用户名 + 绑定邮箱双因子核验 -->
    <p class="sub" style="margin-top:-6px">输入用户名和该账号绑定的邮箱，核验后发送重置链接。</p>
    <?php if (!empty($resetMsg)): ?><div class="msg msg-success" style="word-break:break-all"><?=htmlspecialchars($resetMsg)?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="forgot" value="1">
      <div class="fld">
        <label>用户名</label>
        <input type="text" name="username" class="inp" placeholder="输入用户名" required autofocus autocomplete="username">
      </div>
      <div class="fld">
        <label>绑定邮箱</label>
        <input type="email" name="email" class="inp" placeholder="账号资料里设置的邮箱" required autocomplete="email">
      </div>
      <button type="submit" class="btn primary" style="width:100%;margin-top:8px">发送重置邮件</button>
      <a href="/xmp/login" class="sub" style="display:block;text-align:center;margin-top:10px;font-size:13px">← 返回登录</a>
    </form>
    <?php else: ?>
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
      <a href="/xmp/login?forgot=1" class="sub" style="display:block;text-align:center;margin-top:10px;font-size:13px">忘记密码？</a>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer();
