<?php
declare(strict_types=1);
/**
 * MediaMirror — uploads/ → R2 双写（媒体镜像，零依赖 SigV4）
 *
 * 上传仍以本地为真源（GD 压缩/变体/防病毒检查都基于本地文件），成功后把最终产物
 * （主文件 + 尺寸变体）原样 PUT 到 R2；推送失败绝不影响上传结果（fail-soft，只记日志）。
 *
 * 响应 URL 是否指向 CDN 由 media_mirror_base() 决定：只有主文件镜像成功才用
 * /assets/uploads/<路径>（经 CDN 直出 R2，实测探针 200 且内容一致），否则保持
 * /uploads/<路径>（源站直出，行为与历史一致）。
 *
 * 配置：data/media-mirror.json（600 权限、www 属主，密钥不入库）
 *   {"enabled":true,"endpoint":"https://<account>.r2.cloudflarestorage.com",
 *    "bucket":"nownexts-static","access_key":"...","secret_key":"..."}
 *
 * 对象键约定：<CDN URL 路径> 即 <对象键>（与 assets 同一映射）：
 *   uploads/articles/a.webp  →  assets/uploads/articles/a.webp
 *
 * CLI 回填存量：php deploy/uploads-r2-mirror.php
 */

function media_mirror_cfg_file(): string
{
    if (defined('DATA_DIR')) return DATA_DIR . '/media-mirror.json';
    $env = (string) getenv('OF_DATA_DIR');
    return ($env !== '' ? rtrim($env, '/') : dirname(__DIR__) . '/data') . '/media-mirror.json';
}

function media_mirror_cfg(): array
{
    $cfg = ['enabled' => false, 'endpoint' => '', 'bucket' => '', 'access_key' => '', 'secret_key' => ''];
    if (!is_file(media_mirror_cfg_file())) return $cfg;
    $d = json_decode((string) @file_get_contents(media_mirror_cfg_file()), true);
    if (!is_array($d)) return $cfg;
    foreach ($cfg as $k => $_) {
        if (isset($d[$k]) && $d[$k] !== '') $cfg[$k] = $d[$k];
    }
    $cfg['enabled'] = (bool) ($d['enabled'] ?? false);
    return $cfg;
}

/** 前台/编辑器里 uploads 的 URL 前缀：镜像启用才给 CDN 前缀。 */
function media_mirror_base(): string
{
    $c = media_mirror_cfg();
    return ($c['enabled'] && $c['access_key'] !== '' && $c['secret_key'] !== '') ? '/assets/uploads' : '/uploads';
}

/**
 * 相对 uploads/ 的路径 → R2 对象键（并做安全校验）。
 * 传 null/无效值返回空串；含 '..'、绝对路径越界一律拒绝。
 */
function media_mirror_key(?string $relUnderUploads): string
{
    if ($relUnderUploads === null) return '';
    $rel = ltrim(str_replace('\\', '/', $relUnderUploads), '/');
    if (str_starts_with($rel, 'uploads/')) $rel = substr($rel, 8);
    if ($rel === '' || str_contains($rel, '..')) return '';
    foreach (explode('/', $rel) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..') return '';
    }
    return 'assets/uploads/' . $rel;
}

/**
 * 把本地文件镜像到 R2。仅接受 UPLOAD_DIR 下的真实文件；任何失败返回 false（不抛异常）。
 */
function media_mirror_put(string $absPath): bool
{
    try {
        $cfg = media_mirror_cfg();
        if (!$cfg['enabled'] || $cfg['endpoint'] === '' || $cfg['bucket'] === ''
            || $cfg['access_key'] === '' || $cfg['secret_key'] === '') {
            return false;
        }
        if (!defined('UPLOAD_DIR') || !is_file($absPath)) return false;
        $root = realpath(UPLOAD_DIR);
        $real = realpath($absPath);
        if ($root === false || $real === false || !str_starts_with($real, $root . '/')) return false;
        $key = media_mirror_key(substr($real, strlen($root) + 1));
        if ($key === '') return false;

        $endpoint = rtrim($cfg['endpoint'], '/');
        $access = $cfg['access_key'];
        $secret = $cfg['secret_key'];
        $region = 'auto';

        $payload = (string) file_get_contents($real);
        $payloadHash = hash('sha256', $payload);
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');

        $ext = strtolower((string) pathinfo($real, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'mp3', 'm4a', 'wav', 'ogg' => 'audio/' . ($ext === 'm4a' ? 'mp4' : $ext),
            'mp4', 'webm', 'mov' => 'video/' . $ext,
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'txt', 'csv' => 'text/plain; charset=utf-8',
            default => 'application/octet-stream',
        };
        // 与 sync-r2.py / r2-put.php 一致的缓存策略：图片/字体 30 天，其余 7 天
        $isImg = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'ico', 'webp', 'svg', 'woff', 'woff2'], true);
        $cache = $isImg ? 'public, max-age=2592000, immutable' : 'public, max-age=604800, immutable';

        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $uri = '/' . $cfg['bucket'] . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
        $canonicalHeaders = "cache-control:{$cache}\ncontent-type:{$contentType}\nhost:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$amzDate}\n";
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
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = (string) curl_error($ch);
        curl_close($ch);

        if ($code >= 200 && $code < 300) return true;
        error_log("[media-mirror] PUT {$key} 失败 HTTP {$code} {$err}");
        return false;
    } catch (Throwable $e) {
        error_log('[media-mirror] 例外: ' . $e->getMessage());
        return false;
    }
}
