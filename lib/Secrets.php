<?php
/**
 * 秘钥落盘加密 —— 开放能力的地基（2026-09-04）
 *
 * 【为什么现在做】全站所有外部服务的凭据——百度站长 token、广告平台 token、
 * Mautic / Notion / Cloudflare 的密钥——一直是**明文**躺在 data/*.json 里。
 * 而 data/ 的访问保护只在一个 gitignored 的 data/.htaccess 里，全新部署时并不存在，
 * 得等有人跑一次健康检查再点修复。健康检查自己都把它标成了 weight=3 的 fail。
 *
 * 开放能力这条主线会让系统里的凭据成倍增加。把这些也明文堆进去，
 * 等于把一个已知的洞挖大十倍。所以先把地基打了：
 *   1. 根 .htaccess 里直接拒绝 data/（那个文件在仓库里，部署即生效）
 *   2. 秘钥落盘一律加密——就算 data/ 还是被读走了，拿到的也是密文
 *
 * 【实现】libsodium secretbox（PHP ≥ 7.2 内置），没有的话退到 openssl AES-256-GCM。
 * 安装级密钥放 data/.secret-key，首次用到时自动生成、权限 0600。
 * 密钥丢了，所有密文都不可恢复——这是设计使然，不是 bug。备份 data/ 时把它一起备。
 *
 * 密文格式：`enc1:<base64 nonce+cipher>`（sodium）/ `enc2:<base64 iv+tag+cipher>`（openssl）。
 * 前缀让解密方知道用哪种算法，也让「这个值是不是已经加密了」一眼可辨——
 * 老数据里的明文不带前缀，读到时原样返回，迁移可以渐进做。
 */

if (!function_exists('secret_key_file')) {

function secret_key_file(): string { return DATA_DIR . '/.secret-key'; }

/** 取安装级密钥；没有就生成。32 字节。 */
function secret_key(): string {
    static $key = null;
    if ($key !== null) return $key;
    $f = secret_key_file();
    if (is_file($f)) {
        $raw = trim((string)file_get_contents($f));
        $bin = base64_decode($raw, true);
        if ($bin !== false && strlen($bin) === 32) return $key = $bin;
        // 文件坏了：不能悄悄换一把新钥匙，那会让所有已加密的东西静默变成垃圾
        throw new RuntimeException('data/.secret-key 内容无效，请从备份恢复；不要删除重建，否则已加密的凭据全部作废');
    }
    $key = random_bytes(32);
    $dir = dirname($f);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (@file_put_contents($f, base64_encode($key) . "\n", LOCK_EX) === false) {
        throw new RuntimeException('无法写入 data/.secret-key，请检查 data/ 目录权限');
    }
    @chmod($f, 0600);
    return $key;
}

/** 这个值是不是已经是密文 */
function secret_is_encrypted(?string $v): bool {
    return is_string($v) && (str_starts_with($v, 'enc1:') || str_starts_with($v, 'enc2:'));
}

/** 加密。空串不加密（空就是空，没必要变成一段密文）。已经是密文的不重复加密。 */
function secret_encrypt(string $plain): string {
    if ($plain === '' || secret_is_encrypted($plain)) return $plain;
    $key = secret_key();
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $c = sodium_crypto_secretbox($plain, $nonce, $key);
        return 'enc1:' . base64_encode($nonce . $c);
    }
    $iv = random_bytes(12); $tag = '';
    $c = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($c === false) throw new RuntimeException('加密失败');
    return 'enc2:' . base64_encode($iv . $tag . $c);
}

/**
 * 解密。不是密文的原样返回（兼容老的明文数据）。
 * 解不开（密钥换了、数据被改）返回空串而不是抛异常——调用方看到的是「没有凭据」，
 * 会走正常的「请重新填写」路径，而不是整页白屏。
 */
function secret_decrypt(?string $v): string {
    if (!is_string($v) || $v === '') return '';
    if (!secret_is_encrypted($v)) return $v;
    try {
        $key = secret_key();
        $bin = base64_decode(substr($v, 5), true);
        if ($bin === false) return '';
        if (str_starts_with($v, 'enc1:')) {
            if (!function_exists('sodium_crypto_secretbox_open')) return '';
            $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            if (strlen($bin) <= $n) return '';
            $out = sodium_crypto_secretbox_open(substr($bin, $n), substr($bin, 0, $n), $key);
            return $out === false ? '' : $out;
        }
        if (strlen($bin) <= 28) return '';
        $iv = substr($bin, 0, 12); $tag = substr($bin, 12, 16); $c = substr($bin, 28);
        $out = openssl_decrypt($c, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $out === false ? '' : $out;
    } catch (Throwable $e) {
        return '';
    }
}

/** 给界面看的：只露最后 4 位。空的就说空。 */
function secret_mask(?string $v): string {
    $p = secret_decrypt($v);
    if ($p === '') return '（未设置）';
    $n = mb_strlen($p);
    return $n <= 4 ? str_repeat('•', $n) : str_repeat('•', min(12, $n - 4)) . mb_substr($p, -4);
}

/** 从日志/错误信息里抹掉所有已知秘钥。传入要抹的明文列表。 */
function secret_redact(string $text, array $plains): string {
    foreach ($plains as $p) {
        $p = (string)$p;
        if (strlen($p) >= 6) $text = str_replace($p, '[已脱敏]', $text);
    }
    return $text;
}

/** 密钥文件的健康状况（给健康检查用） */
function secret_health(): array {
    $f = secret_key_file();
    if (!is_file($f)) return ['ok' => true, 'note' => '尚未生成（首次保存凭据时自动生成）'];
    $perm = substr(sprintf('%o', fileperms($f)), -3);
    $ok = in_array($perm, ['600', '400'], true);
    return ['ok' => $ok, 'note' => $ok ? '已生成，权限 ' . $perm : '权限过宽（' . $perm . '），建议 chmod 600'];
}

}
