<?php
/**
 * 两步验证（2FA）验收
 *
 *   php tests/totp_test.php
 *
 * 核心是 TOTP 算法必须与标准 App 完全一致——用 RFC 4226 官方测试向量
 * 校验，而不是自证。再覆盖窗口容忍、恢复码、以及登录/设置页的接线。
 */

require_once __DIR__ . '/../lib/Totp.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

echo "\n── 1. RFC 4226 官方向量（保证与认证器 App 一致）──\n";
// secret = ASCII "12345678901234567890" → base32
$b32 = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
$expected = ['755224','287082','359152','969429','338314','254676','287922','162583','399871','520489'];
$hotp = new ReflectionMethod('Totp', 'hotp'); $hotp->setAccessible(true);
$b32d = new ReflectionMethod('Totp', 'base32decode'); $b32d->setAccessible(true);
$key = $b32d->invoke(null, $b32);
check('base32 解码回原始 ASCII', $key === '12345678901234567890');
$allok = true;
foreach ($expected as $c => $exp) {
    if ($hotp->invoke(null, $key, $c) !== $exp) { $allok = false; break; }
}
check('10 个计数器全部命中官方向量', $allok);

echo "\n── 2. base32 编解码往返 ──\n";
$b32e = new ReflectionMethod('Totp', 'base32encode'); $b32e->setAccessible(true);
$raw = random_bytes(20);
check('encode→decode 还原', $b32d->invoke(null, $b32e->invoke(null, $raw)) === $raw);
check('密钥长度 32 字符（160bit）', strlen(Totp::secret()) === 32);

echo "\n── 3. 验证码校验与时钟窗口 ──\n";
$s = Totp::secret();
check('当前码通过', Totp::verify($s, Totp::now($s)));
check('错误码被拒', !Totp::verify($s, '000000'));
check('空码被拒', !Totp::verify($s, ''));
check('非 6 位被拒', !Totp::verify($s, '12345'));
check('带空格/横线也能容错解析', is_bool(Totp::verify($s, '123 456')));
// 窗口：上一个 30 秒的码在 window=1 下仍接受
$prevCounter = intdiv(time(), 30) - 1;
$prevCode = $hotp->invoke(null, $b32d->invoke(null, $s), $prevCounter);
check('前一个窗口的码（window=1）接受', Totp::verify($s, $prevCode, 1));
check('两个窗口前的码（window=1）拒绝', !Totp::verify($s, $hotp->invoke(null, $b32d->invoke(null, $s), $prevCounter - 1), 1));

echo "\n── 4. 恢复码 ──\n";
$codes = Totp::recoveryCodes();
check('默认生成 8 个', count($codes) === 8);
check('格式 XXXXX-XXXXX', (bool)preg_match('/^[0-9A-F]{5}-[0-9A-F]{5}$/', $codes[0]), $codes[0]);
check('互不重复', count(array_unique($codes)) === 8);

echo "\n── 5. otpauth URI ──\n";
$uri = Totp::uri($s, 'root@site', 'OpenFlow');
check('是 otpauth://totp/', strpos($uri, 'otpauth://totp/') === 0);
check('带 secret', strpos($uri, 'secret=' . $s) !== false);
check('带 issuer', strpos($uri, 'issuer=OpenFlow') !== false);
check('账号做了 URL 编码', strpos($uri, 'root%40site') !== false);

echo "\n── 6. 登录与设置页接线 ──\n";
$login = file_get_contents(__DIR__ . '/../admin/login.php');
check('登录走两步（pending_2fa）', strpos($login, "pending_2fa") !== false);
check('登录校验 TOTP', strpos($login, 'Totp::verify') !== false);
check('支持恢复码登录', strpos($login, "recovery") !== false && strpos($login, 'hash_equals') !== false);
check('第一步验密码后不直接建会话', strpos($login, "\$_SESSION['pending_2fa'] = ['user'") !== false);
$sec = file_get_contents(__DIR__ . '/../admin/security.php');
check('设置页开启需确认验证码', strpos($sec, "'action') === 'enable'") !== false || strpos($sec, "=== 'enable'") !== false);
check('关闭 2FA 需再验一次', strpos($sec, "=== 'disable'") !== false && strpos($sec, 'Totp::verify') !== false);
check('开启/关闭都留审计', substr_count($sec, "audit(") >= 3);
$cfg = file_get_contents(__DIR__ . '/../admin/config.php');
check('侧栏有账号安全入口', strpos($cfg, '/xmp/security') !== false);

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
