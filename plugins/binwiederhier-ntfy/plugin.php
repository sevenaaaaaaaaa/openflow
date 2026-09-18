<?php
declare(strict_types=1);
/**
 * 触达/通知通道适配器
 * 上游：binwiederhier/ntfy v2.28.0 (apache-2.0)
 * 官方接口：POST {endpoint}/{topic}  发布消息（JSON 或纯文本）
 * 认证：Bearer Token（可选，仅当上游开启鉴权时）
 * @adapter-suggest: hook 上游支持 Webhook/事件回调，但骨架未声明该落点，交人审决定
 * @adapter-suggest: schedule 上游支持定时消息（X-Delay），但骨架未声明该落点，交人审决定
 */

function binwiederhier_ntfy_config(): array {
    if (function_exists('plugin_config')) return (array) plugin_config('binwiederhier-ntfy');
    return [];
}

/** 出站：把消息推到上游（ntfy 发布接口） */
PluginSystem::register_api_route('binwiederhier-ntfy', 'POST', 'send', function (array $req): array {
    $cfg = binwiederhier_ntfy_config();
    $text = (string) ($req['body']['text'] ?? '');
    if ($text === '') return ['ok' => false, 'error' => 'text 不能为空'];

    $endpoint = rtrim((string) ($cfg['endpoint'] ?? ''), '/');
    if ($endpoint === '') return ['ok' => false, 'error' => '未配置 endpoint'];

    $topic = trim((string) ($cfg['topic'] ?? ''), '/');
    if ($topic === '') return ['ok' => false, 'error' => '未配置 topic'];

    $url = $endpoint . '/' . rawurlencode($topic);

    $payload = ['message' => $text];
    $title = (string) ($req['body']['title'] ?? ($cfg['title'] ?? ''));
    if ($title !== '') $payload['title'] = $title;
    if (isset($req['body']['priority'])) $payload['priority'] = (int) $req['body']['priority'];
    if (isset($req['body']['tags']) && is_array($req['body']['tags'])) {
        $payload['tags'] = array_values(array_map('strval', $req['body']['tags']));
    }
    if (isset($req['body']['click'])) $payload['click'] = (string) $req['body']['click'];

    $headers = ['Content-Type: application/json'];
    $token = (string) ($cfg['token'] ?? '');
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;

    $timeout = (int) ($cfg['timeout'] ?? 10);
    if ($timeout < 1 || $timeout > 60) $timeout = 10;

    // 重试：最多 2 次额外尝试，仅对网络错误/5xx/429 退避重试（0.5s、1.5s），4xx 直接失败不重试
    $attempts = 0;
    $maxAttempts = 3;
    $lastError = '';

    while ($attempts < $maxAttempts) {
        $attempts++;
        $ch = curl_init($url);
        if ($ch === false) return ['ok' => false, 'error' => 'curl 初始化失败'];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $lastError = '网络错误: ' . $errmsg;
            $retryable = true;
        } elseif ($status >= 200 && $status < 300) {
            $decoded = is_string($body) ? json_decode($body, true) : null;
            return [
                'ok' => true,
                'status' => $status,
                'id' => is_array($decoded) ? ($decoded['id'] ?? null) : null,
                'attempts' => $attempts,
            ];
        } elseif ($status === 429 || $status >= 500) {
            $lastError = '上游返回 HTTP ' . $status;
            $retryable = true;
        } else {
            $lastError = '上游返回 HTTP ' . $status . '（不可重试）';
            $retryable = false;
        }

        if (!$retryable || $attempts >= $maxAttempts) break;

        // 退避：0.5s -> 1.5s
        usleep((int) (500000 * $attempts * ($attempts === 1 ? 1 : 3)));
    }

    return ['ok' => false, 'error' => $lastError, 'attempts' => $attempts];
}, ['auth' => 'token']);