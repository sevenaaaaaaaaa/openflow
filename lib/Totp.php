<?php
/**
 * TOTP —— 基于时间的一次性密码（RFC 6238），零依赖。
 *
 * 用于后台管理员两步验证。与 Google Authenticator / 1Password / 微软
 * Authenticator 等标准 App 兼容（SHA1 / 6 位 / 30 秒）。
 *
 * 只做三件事：生成密钥、拼 otpauth:// URI（给 App 扫码）、校验验证码。
 */
class Totp
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALGO   = 'sha1';

    /** 生成一个新的 base32 密钥（默认 160 bit，与主流 App 一致）。 */
    public static function secret(int $bytes = 20): string
    {
        return self::base32encode(random_bytes($bytes));
    }

    /**
     * otpauth:// URI，供认证器扫码。
     * @param string $secret base32 密钥
     * @param string $account 账号标识（如用户名）
     * @param string $issuer 站点名
     */
    public static function uri(string $secret, string $account, string $issuer = 'OpenFlow'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        $q = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper(self::ALGO),
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$q}";
    }

    /**
     * 校验用户输入的验证码。
     * @param int $window 容忍前后各多少个 30 秒窗口（默认 1，容忍时钟漂移）
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) return false;
        $key = self::base32decode($secret);
        if ($key === '') return false;
        $counter = intdiv(time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::hotp($key, $counter + $i), $code)) return true;
        }
        return false;
    }

    /** 当前验证码（测试 / 自检用）。 */
    public static function now(string $secret): string
    {
        return self::hotp(self::base32decode($secret), intdiv(time(), self::PERIOD));
    }

    /** 生成一组一次性恢复码（丢了手机时用）。 */
    public static function recoveryCodes(int $n = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $n; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(5)));           // 10 hex
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5); // XXXXX-XXXXX
        }
        return $codes;
    }

    // ── 内部 ──────────────────────────────────────────

    private static function hotp(string $key, int $counter): string
    {
        $bin = pack('N*', 0) . pack('N*', $counter);   // 8 字节大端计数器
        $hash = hash_hmac(self::ALGO, $bin, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $part = (ord($hash[$offset]) & 0x7f) << 24
              | (ord($hash[$offset + 1]) & 0xff) << 16
              | (ord($hash[$offset + 2]) & 0xff) << 8
              | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string)($part % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = ''; $bits = 0; $val = 0;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $val = ($val << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $out .= $alphabet[($val >> ($bits - 5)) & 31];
                $bits -= 5;
            }
        }
        if ($bits > 0) $out .= $alphabet[($val << (5 - $bits)) & 31];
        return $out;
    }

    private static function base32decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        if ($b32 === '') return '';
        $out = ''; $bits = 0; $val = 0;
        for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
            $val = ($val << 5) | strpos($alphabet, $b32[$i]);
            $bits += 5;
            if ($bits >= 8) {
                $out .= chr(($val >> ($bits - 8)) & 0xff);
                $bits -= 8;
            }
        }
        return $out;
    }
}
