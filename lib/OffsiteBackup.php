<?php
declare(strict_types=1);
/**
 * 异地备份（把本地备份推到 S3 / R2 兼容对象存储）
 *
 * 【为什么必须有】此前的自动备份写在 data/backups/，和站点同一块盘：
 * 整机故障时备份和数据一起没。"有备份"和"备份在别处"是两件事。
 *
 * 【为什么自己签名而不是用 SDK】项目的底线是零构建链、不引运行时依赖。
 * SigV4 签名本身只有几十行，实现在这里比引一个 SDK 更可控，也更好测。
 *
 * 【凭据从不入库】优先读环境变量 OF_OFFSITE_KEY / OF_OFFSITE_SECRET；
 * 没有再读 data/offsite-backup.json（data/ 已被 .gitignore 和 .htaccess 挡住）。
 *
 * 用法：
 *   php scripts/offsite-backup.php --test      # 只传一个探针对象，验证配置是否可用
 *   php scripts/offsite-backup.php --latest    # 把最近一次自动备份打包并上传
 *   自动：backup_run_if_due() 做完备份后会顺手调 offsite_sync()
 */

function offsite_cfg_file(): string
{
    return (defined('DATA_DIR') ? DATA_DIR : dirname(__DIR__) . '/data') . '/offsite-backup.json';
}

function offsite_status_file(): string
{
    return (defined('DATA_DIR') ? DATA_DIR : dirname(__DIR__) . '/data') . '/offsite-backup-status.json';
}

/**
 * @return array{enabled:bool, endpoint:string, bucket:string, region:string, prefix:string,
 *               access_key:string, secret_key:string}
 */
function offsite_config(): array
{
    $d = [];
    if (is_file(offsite_cfg_file())) {
        $tmp = json_decode((string) @file_get_contents(offsite_cfg_file()), true);
        if (is_array($tmp)) $d = $tmp;
    }
    // 环境变量优先：服务器上用 env 注入，配置文件里可以完全不写密钥
    $key = (string) (getenv('OF_OFFSITE_KEY') ?: ($d['access_key'] ?? ''));
    $sec = (string) (getenv('OF_OFFSITE_SECRET') ?: ($d['secret_key'] ?? ''));
    return [
        'enabled'    => (bool) ($d['enabled'] ?? false),
        'endpoint'   => rtrim((string) ($d['endpoint'] ?? ''), '/'),
        'bucket'     => trim((string) ($d['bucket'] ?? ''), '/'),
        'region'     => (string) ($d['region'] ?? 'auto'),
        'prefix'     => trim((string) ($d['prefix'] ?? 'openflow-backups'), '/'),
        'access_key' => $key,
        'secret_key' => $sec,
    ];
}

/** 保存配置。密钥留空表示"不改"，避免后台表单把 env 注入的密钥覆盖成空。 */
function offsite_config_set(array $in): array
{
    $cur = offsite_config();
    $raw = [];
    if (is_file(offsite_cfg_file())) {
        $tmp = json_decode((string) @file_get_contents(offsite_cfg_file()), true);
        if (is_array($tmp)) $raw = $tmp;
    }
    $next = [
        'enabled'  => !empty($in['enabled']),
        'endpoint' => rtrim((string) ($in['endpoint'] ?? $cur['endpoint']), '/'),
        'bucket'   => trim((string) ($in['bucket'] ?? $cur['bucket']), '/'),
        'region'   => (string) ($in['region'] ?? $cur['region']) ?: 'auto',
        'prefix'   => trim((string) ($in['prefix'] ?? $cur['prefix']), '/'),
        'access_key' => (string) ($in['access_key'] ?? '') !== '' ? (string) $in['access_key'] : (string) ($raw['access_key'] ?? ''),
        'secret_key' => (string) ($in['secret_key'] ?? '') !== '' ? (string) $in['secret_key'] : (string) ($raw['secret_key'] ?? ''),
    ];
    $dir = dirname(offsite_cfg_file());
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents(offsite_cfg_file(), (string) json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    @chmod(offsite_cfg_file(), 0600);
    return offsite_config();
}

/** 配置是否齐全到可以尝试上传 @return string '' 表示可用，否则是缺什么 */
function offsite_config_problem(array $cfg): string
{
    if ($cfg['endpoint'] === '') return '缺少 endpoint';
    if ($cfg['bucket'] === '') return '缺少 bucket';
    if ($cfg['access_key'] === '' || $cfg['secret_key'] === '') return '缺少访问密钥（设 OF_OFFSITE_KEY / OF_OFFSITE_SECRET 环境变量，或在后台填写）';
    if (!preg_match('#^https?://#i', $cfg['endpoint'])) return 'endpoint 必须以 http(s):// 开头';
    return '';
}

/** 路径分段编码：'/' 要保留，其余按 RFC3986 编码（空格是 %20，不是 +） */
function offsite_uri_encode_path(string $path): string
{
    $parts = explode('/', ltrim($path, '/'));
    return '/' . implode('/', array_map('rawurlencode', $parts));
}

/**
 * AWS SigV4 签名（纯函数，方便测试）。
 * @return array<string,string> 要发出去的请求头
 */
function offsite_sign(array $cfg, string $method, string $objectPath, string $payloadHash, string $amzDate): array
{
    $host = (string) parse_url($cfg['endpoint'], PHP_URL_HOST);
    $port = parse_url($cfg['endpoint'], PHP_URL_PORT);
    if ($port !== null && !in_array((int) $port, [80, 443], true)) $host .= ':' . (int) $port;

    $canonPath = offsite_uri_encode_path('/' . $cfg['bucket'] . '/' . ltrim($objectPath, '/'));
    $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
    $canonHeaders = "host:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$amzDate}\n";
    $canonRequest = strtoupper($method) . "\n{$canonPath}\n\n{$canonHeaders}\n{$signedHeaders}\n{$payloadHash}";

    $date = substr($amzDate, 0, 8);
    $scope = "{$date}/{$cfg['region']}/s3/aws4_request";
    $sts = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonRequest);

    $k = hash_hmac('sha256', $date, 'AWS4' . $cfg['secret_key'], true);
    $k = hash_hmac('sha256', $cfg['region'], $k, true);
    $k = hash_hmac('sha256', 's3', $k, true);
    $k = hash_hmac('sha256', 'aws4_request', $k, true);
    $signature = hash_hmac('sha256', $sts, $k);

    return [
        'Host' => $host,
        'x-amz-date' => $amzDate,
        'x-amz-content-sha256' => $payloadHash,
        'Authorization' => "AWS4-HMAC-SHA256 Credential={$cfg['access_key']}/{$scope}, "
                         . "SignedHeaders={$signedHeaders}, Signature={$signature}",
    ];
}

/** 上传一个本地文件（流式，不读进内存） @return array{ok:bool, status:int, key:string, bytes:int, error:string} */
function offsite_put_file(string $localFile, string $objectKey, ?array $cfg = null): array
{
    $cfg = $cfg ?? offsite_config();
    $out = ['ok' => false, 'status' => 0, 'key' => $objectKey, 'bytes' => 0, 'error' => ''];
    $problem = offsite_config_problem($cfg);
    if ($problem !== '') { $out['error'] = $problem; return $out; }
    if (!is_file($localFile)) { $out['error'] = '本地文件不存在：' . $localFile; return $out; }

    $size = (int) filesize($localFile);
    $hash = hash_file('sha256', $localFile);
    if ($hash === false) { $out['error'] = '无法计算文件摘要'; return $out; }
    $out['bytes'] = $size;

    $headers = offsite_sign($cfg, 'PUT', $objectKey, $hash, gmdate('Ymd\THis\Z'));
    $hdr = ['Expect:'];                       // 关掉 100-continue，省一个往返也避免部分网关行为差异
    foreach ($headers as $k => $v) $hdr[] = "{$k}: {$v}";

    $fh = fopen($localFile, 'rb');
    if ($fh === false) { $out['error'] = '无法读取本地文件'; return $out; }
    $url = $cfg['endpoint'] . offsite_uri_encode_path('/' . $cfg['bucket'] . '/' . ltrim($objectKey, '/'));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $fh,
        CURLOPT_INFILESIZE => $size,
        CURLOPT_HTTPHEADER => $hdr,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 900,
    ]);
    $body = curl_exec($ch);
    $out['status'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) $out['error'] = '网络错误：' . curl_error($ch);
    curl_close($ch);
    fclose($fh);

    if ($out['error'] === '') {
        $out['ok'] = $out['status'] >= 200 && $out['status'] < 300;
        if (!$out['ok']) {
            // 对象存储的错误正文是 XML，取 <Message> 就够定位问题了
            $msg = '';
            if (is_string($body) && preg_match('#<Message>(.*?)</Message>#s', $body, $m)) $msg = trim($m[1]);
            $out['error'] = "HTTP {$out['status']}" . ($msg !== '' ? "：{$msg}" : '');
        }
    }
    return $out;
}

/** @return array{last_attempt:string,last_ok:string,key:string,bytes:int,error:string} */
function offsite_status(): array
{
    $d = [];
    if (is_file(offsite_status_file())) {
        $tmp = json_decode((string) @file_get_contents(offsite_status_file()), true);
        if (is_array($tmp)) $d = $tmp;
    }
    return [
        'last_attempt' => (string) ($d['last_attempt'] ?? ''),
        'last_ok'      => (string) ($d['last_ok'] ?? ''),
        'key'          => (string) ($d['key'] ?? ''),
        'bytes'        => (int) ($d['bytes'] ?? 0),
        'error'        => (string) ($d['error'] ?? ''),
    ];
}

function offsite_status_set(array $r): void
{
    $cur = offsite_status();
    $next = [
        'last_attempt' => date('Y-m-d H:i:s'),
        'last_ok'      => !empty($r['ok']) ? date('Y-m-d H:i:s') : $cur['last_ok'],
        'key'          => (string) ($r['key'] ?? $cur['key']),
        'bytes'        => (int) ($r['bytes'] ?? 0),
        'error'        => (string) ($r['error'] ?? ''),
    ];
    $dir = dirname(offsite_status_file());
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents(offsite_status_file(), (string) json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 把一份本地备份打包并推到异地。
 * @return array{status:string, key?:string, bytes?:int, detail?:string}
 */
function offsite_sync(string $backupName): array
{
    $cfg = offsite_config();
    if (!$cfg['enabled']) return ['status' => 'disabled'];
    $problem = offsite_config_problem($cfg);
    if ($problem !== '') {
        offsite_status_set(['ok' => false, 'error' => $problem]);
        return ['status' => 'misconfigured', 'detail' => $problem];
    }

    require_once __DIR__ . '/BackupSystem.php';
    $zip = BackupSystem::createZip($backupName);
    if ($zip === null || !is_file($zip)) {
        offsite_status_set(['ok' => false, 'error' => "打包失败：{$backupName}"]);
        return ['status' => 'error', 'detail' => '打包失败'];
    }

    $key = ($cfg['prefix'] !== '' ? $cfg['prefix'] . '/' : '') . date('Y/m') . '/' . basename($zip);
    $r = offsite_put_file($zip, $key, $cfg);
    @unlink($zip);                               // 本地 zip 只是传输中间产物，不留在盘上占空间
    offsite_status_set($r);

    return $r['ok']
        ? ['status' => 'done', 'key' => $key, 'bytes' => $r['bytes']]
        : ['status' => 'error', 'detail' => $r['error'], 'key' => $key];
}

/** 连通性探针：传一个几十字节的对象，用来验证 endpoint/bucket/密钥是否配对 */
function offsite_probe(?array $cfg = null): array
{
    $cfg = $cfg ?? offsite_config();
    $problem = offsite_config_problem($cfg);
    if ($problem !== '') return ['ok' => false, 'error' => $problem, 'status' => 0, 'key' => '', 'bytes' => 0];
    $tmp = tempnam(sys_get_temp_dir(), 'of-probe');
    if ($tmp === false) return ['ok' => false, 'error' => '无法创建临时文件', 'status' => 0, 'key' => '', 'bytes' => 0];
    file_put_contents($tmp, 'openflow offsite probe ' . date('c') . "\n");
    $key = ($cfg['prefix'] !== '' ? $cfg['prefix'] . '/' : '') . '_probe/' . date('Ymd_His') . '.txt';
    $r = offsite_put_file($tmp, $key, $cfg);
    @unlink($tmp);
    return $r;
}
