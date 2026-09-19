#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * R2 单文件上传（AWS SigV4，零依赖）—— sync-r2.py 的兜底
 *
 *   R2_KEY=... R2_SECRET=... php scripts/r2-put.php <本地文件> <对象键>
 *   例：php scripts/r2-put.php assets/admin-ui.css assets/admin-ui.css
 *
 * 【为什么需要它】sync-r2.py 依赖 boto3 + 本机 DNS 能解析 R2 域名。
 * 一旦本机解析不到（或环境里装不了 boto3），assets 就传不上去：
 * 而 CDN 命中 R2 里的旧副本时不会回源，页面就会拿到旧样式。
 * 这个脚本只用 PHP 的 curl + hash_hmac，能在任何能连 R2 的机器上跑（比如服务器）。
 */

$local = $argv[1] ?? '';
$key = $argv[2] ?? '';
if ($local === '' || $key === '' || !is_file($local)) {
    fwrite(STDERR, "用法：R2_KEY=... R2_SECRET=... php scripts/r2-put.php <本地文件> <对象键>\n");
    exit(2);
}
$access = (string) (getenv('R2_KEY') ?: '');
$secret = (string) (getenv('R2_SECRET') ?: '');
$bucket = (string) (getenv('R2_BUCKET') ?: 'nownexts-static');
$endpoint = rtrim((string) (getenv('R2_ENDPOINT') ?: 'https://00d02a54a3c0f7a3f6c3fc75068e29c5.r2.cloudflarestorage.com'), '/');
if ($access === '' || $secret === '') {
    fwrite(STDERR, "缺少 R2_KEY / R2_SECRET\n");
    exit(2);
}

$host = (string) parse_url($endpoint, PHP_URL_HOST);
$region = 'auto';
$payload = (string) file_get_contents($local);
$payloadHash = hash('sha256', $payload);
$amzDate = gmdate('Ymd\THis\Z');
$dateStamp = gmdate('Ymd');

$ext = strtolower((string) pathinfo($local, PATHINFO_EXTENSION));
$contentType = match ($ext) {
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'json' => 'application/json',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'woff2' => 'font/woff2',
    'woff' => 'font/woff',
    default => 'application/octet-stream',
};
// 与 sync-r2.py 一致的缓存策略：静态资源 7 天、图片/字体 30 天
$isImg = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'ico', 'webp', 'svg', 'woff', 'woff2'], true);
$cache = $isImg ? 'public, max-age=2592000, immutable' : 'public, max-age=604800, immutable';

// 路径必须逐段 URI 编码（'/' 保留）
$uri = '/' . $bucket . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
$canonicalHeaders = "cache-control:$cache\ncontent-type:$contentType\nhost:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$amzDate\n";
$signedHeaders = 'cache-control;content-type;host;x-amz-content-sha256;x-amz-date';
$canonicalRequest = "PUT\n{$uri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
$scope = "{$dateStamp}/{$region}/s3/aws4_request";
$stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonicalRequest);
$kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secret, true);
$kRegion = hash_hmac('sha256', $region, $kDate, true);
$kService = hash_hmac('sha256', 's3', $kRegion, true);
$kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
$signature = hash_hmac('sha256', $stringToSign, $kSigning);
$authorization = "AWS4-HMAC-SHA256 Credential={$access}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

$ch = curl_init($endpoint . $uri);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $authorization,
        'Content-Type: ' . $contentType,
        'Cache-Control: ' . $cache,
        'x-amz-content-sha256: ' . $payloadHash,
        'x-amz-date: ' . $amzDate,
        'Host: ' . $host,
    ],
]);
$body = (string) curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$err = (string) curl_error($ch);
if ($code >= 200 && $code < 300) {
    printf("✅ %s → %s（%d 字节，HTTP %d）\n", $local, $key, strlen($payload), $code);
    exit(0);
}
fwrite(STDERR, sprintf("✗ 上传失败 HTTP %d %s\n%s\n", $code, $err, substr($body, 0, 400)));
exit(1);
