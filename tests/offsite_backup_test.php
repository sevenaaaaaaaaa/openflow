<?php
declare(strict_types=1);
/**
 * 异地备份契约测试
 *
 *   php tests/offsite_backup_test.php
 *
 * 不碰网络。验证四件事：
 *   1) 配置读写：环境变量优先、留空不覆盖已存密钥、缺什么说得清楚
 *   2) SigV4 签名：格式正确、确定性、任一输入变化签名必变（防止签名被改坏还静默通过）
 *   3) 路径编码：'/' 保留、空格是 %20（S3 对这个很敏感，编错就是 403）
 *   4) 失败路径：没配置/未开启/文件不存在时，返回错误而不是抛异常、也不发请求
 */

$tmp = sys_get_temp_dir() . '/of-offsite-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_OFFSITE_KEY=');
putenv('OF_OFFSITE_SECRET=');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
define('OF_NO_AUTO_CSRF', true);
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/OffsiteBackup.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $n, string $d = ''): void
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "异地备份\n";

// ── 1. 配置 ──
$c = offsite_config();
ok($c['enabled'] === false, '默认不开启（不能悄悄往外传数据）');
ok(offsite_config_problem($c) !== '', '空配置会说明缺什么');

offsite_config_set(['enabled' => true, 'endpoint' => 'https://acc.r2.cloudflarestorage.com/',
                    'bucket' => 'of-backups', 'region' => 'auto', 'prefix' => 'p/',
                    'access_key' => 'AK1', 'secret_key' => 'SK1']);
$c = offsite_config();
ok($c['enabled'] === true, '开关可保存');
ok($c['endpoint'] === 'https://acc.r2.cloudflarestorage.com', 'endpoint 结尾的 / 被去掉');
ok($c['prefix'] === 'p', 'prefix 两端的 / 被去掉');
ok(offsite_config_problem($c) === '', '配置完整时无问题');

offsite_config_set(['enabled' => true, 'endpoint' => $c['endpoint'], 'bucket' => 'of-backups',
                    'access_key' => '', 'secret_key' => '']);
ok(offsite_config()['access_key'] === 'AK1', '密钥留空 = 不修改（不会被表单清空）');

putenv('OF_OFFSITE_KEY=ENVKEY');
putenv('OF_OFFSITE_SECRET=ENVSEC');
ok(offsite_config()['access_key'] === 'ENVKEY', '环境变量优先于配置文件');
putenv('OF_OFFSITE_KEY=');
putenv('OF_OFFSITE_SECRET=');

$bad = offsite_config();
$bad['endpoint'] = 'acc.r2.cloudflarestorage.com';
ok(str_contains(offsite_config_problem($bad), 'http'), 'endpoint 缺协议会被指出来');

// ── 2. 签名 ──
$cfg = ['endpoint' => 'https://acc.r2.cloudflarestorage.com', 'bucket' => 'of-backups',
        'region' => 'auto', 'access_key' => 'AKID', 'secret_key' => 'SECRET'];
$h = offsite_sign($cfg, 'PUT', 'a/b.zip', str_repeat('0', 64), '20260920T000000Z');
ok($h['Host'] === 'acc.r2.cloudflarestorage.com', 'Host 从 endpoint 推导');
ok($h['x-amz-date'] === '20260920T000000Z', 'x-amz-date 原样带上');
ok(str_starts_with($h['Authorization'], 'AWS4-HMAC-SHA256 Credential=AKID/20260920/auto/s3/aws4_request'),
   'Credential scope 正确', $h['Authorization']);
ok(str_contains($h['Authorization'], 'SignedHeaders=host;x-amz-content-sha256;x-amz-date'), 'SignedHeaders 正确');
preg_match('/Signature=([0-9a-f]+)$/', $h['Authorization'], $m);
ok(isset($m[1]) && strlen($m[1]) === 64, '签名是 64 位十六进制', $m[1] ?? '无');

$sig = static function (array $cfg, string $method, string $path, string $hash, string $date): string {
    preg_match('/Signature=([0-9a-f]+)$/', offsite_sign($cfg, $method, $path, $hash, $date)['Authorization'], $mm);
    return $mm[1] ?? '';
};
$base = $sig($cfg, 'PUT', 'a/b.zip', str_repeat('0', 64), '20260920T000000Z');
ok($base === $sig($cfg, 'PUT', 'a/b.zip', str_repeat('0', 64), '20260920T000000Z'), '同样输入 → 同样签名');
ok($base !== $sig($cfg, 'GET', 'a/b.zip', str_repeat('0', 64), '20260920T000000Z'), '换方法 → 签名变');
ok($base !== $sig($cfg, 'PUT', 'a/c.zip', str_repeat('0', 64), '20260920T000000Z'), '换对象 → 签名变');
ok($base !== $sig($cfg, 'PUT', 'a/b.zip', str_repeat('1', 64), '20260920T000000Z'), '换内容摘要 → 签名变');
ok($base !== $sig($cfg, 'PUT', 'a/b.zip', str_repeat('0', 64), '20260921T000000Z'), '换日期 → 签名变');
$cfg2 = $cfg; $cfg2['secret_key'] = 'OTHER';
ok($base !== $sig($cfg2, 'PUT', 'a/b.zip', str_repeat('0', 64), '20260920T000000Z'), '换密钥 → 签名变');
$cfg3 = $cfg; $cfg3['bucket'] = 'other';
ok($base !== $sig($cfg3, 'PUT', 'a/b.zip', str_repeat('0', 64), '20260920T000000Z'), '换 bucket → 签名变');

// ── 2b. 黄金向量：与 AWS 官方 SDK（botocore S3SigV4Auth）逐字节比对过的签名 ──
// 【为什么钉死】签名是"要么全对要么 403"的东西，自己测自己没有意义。
// 这三组是用 botocore 独立实现交叉验证过的结果（含空格、非 ASCII、非标准端口、不同 region）。
// 如果改动签名实现后这里红了：不是把期望值改掉，是实现错了。
$golden = [
    [['endpoint' => 'https://acc.r2.cloudflarestorage.com', 'bucket' => 'of-backups', 'region' => 'auto',
      'access_key' => 'AKID', 'secret_key' => 'SECRET'], 'a/b.zip', '20260920T000000Z',
     'e6ad327d3017dc05365dc34b3ce9523bf1cba32ada715880c9a445b340c1ceda'],
    [['endpoint' => 'https://s3.us-east-1.amazonaws.com', 'bucket' => 'my-bucket', 'region' => 'us-east-1',
      'access_key' => 'AKIDEXAMPLE', 'secret_key' => 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY'],
     'p/2026/09/auto x.zip', '20150830T123600Z',
     'e8b468e7b28d955f649a76d29671db8074ba7df832bb5b48eacb6d29a7cdedc6'],
    [['endpoint' => 'https://minio.local:9000', 'bucket' => 'b2', 'region' => 'cn-north-1',
      'access_key' => 'K', 'secret_key' => 'S'], '中文/文件.zip', '20260101T101010Z',
     'abfc5ae65fd5d4d4896d5e0de9cf8b0812607d88f81fd353dadf11811d71cb8d'],
];
$payload = hash('sha256', 'hello world');
foreach ($golden as $i => [$gcfg, $gkey, $gdate, $want]) {
    preg_match('/Signature=([0-9a-f]+)$/', offsite_sign($gcfg, 'PUT', $gkey, $payload, $gdate)['Authorization'], $gm);
    ok(($gm[1] ?? '') === $want, '黄金向量 #' . ($i + 1) . '（与 AWS SDK 一致）', ($gm[1] ?? '无') . ' ≠ ' . $want);
}

// ── 3. 路径编码 ──
ok(offsite_uri_encode_path('/b/a b.zip') === '/b/a%20b.zip', '空格编成 %20 而不是 +');
ok(offsite_uri_encode_path('/b/x/y.zip') === '/b/x/y.zip', '斜杠保留为分隔符');
ok(offsite_uri_encode_path('/b/中文.zip') === '/b/' . rawurlencode('中文.zip'), '非 ASCII 被编码');

// ── 4. 失败路径不抛异常、不发请求 ──
$r = offsite_put_file('/definitely/not/here.zip', 'k.zip', array_merge($cfg, ['enabled' => true]));
ok($r['ok'] === false && str_contains($r['error'], '不存在'), '本地文件不存在 → 明确报错');
ok($r['status'] === 0, '没发出请求');

$noKey = $cfg; $noKey['access_key'] = '';
$r2 = offsite_put_file(__FILE__, 'k.zip', $noKey);
ok($r2['ok'] === false && str_contains($r2['error'], '密钥'), '缺密钥 → 在发请求前就拦下');

offsite_config_set(['enabled' => false, 'endpoint' => $cfg['endpoint'], 'bucket' => 'b']);
ok((offsite_sync('whatever')['status'] ?? '') === 'disabled', '未开启时 sync 直接返回 disabled');

// 状态落盘
offsite_status_set(['ok' => true, 'key' => 'p/2026/09/x.zip', 'bytes' => 123, 'error' => '']);
$st = offsite_status();
ok($st['last_ok'] !== '' && $st['key'] === 'p/2026/09/x.zip' && $st['bytes'] === 123, '成功状态被记录');
offsite_status_set(['ok' => false, 'error' => 'HTTP 403：SignatureDoesNotMatch']);
$st2 = offsite_status();
ok($st2['error'] !== '' && $st2['last_ok'] === $st['last_ok'], '失败不会抹掉"上次成功"时间');

// 配置文件里不应出现明文密钥以外的敏感泄漏路径：权限收紧
ok(is_file(offsite_cfg_file()) && (fileperms(offsite_cfg_file()) & 0077) === 0, '配置文件权限收敛到 0600');

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
@exec('rm -rf ' . escapeshellarg($tmp));
exit($fail === 0 ? 0 : 1);
