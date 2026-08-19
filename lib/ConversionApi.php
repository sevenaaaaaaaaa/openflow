<?php
/**
 * 转化回传闭环（CAPI，对标 Meta/Google 转化 API）
 * 支付/线索等关键转化事件 → PII 哈希 + 点击ID → 回传广告平台，幂等+重试
 */

// PII 哈希（Meta/Google 规范：小写去空格 → SHA256 hex）
function conv_hash_email(string $email): string {
    return hash('sha256', strtolower(trim($email)));
}
function conv_hash_phone(string $phone): string {
    return hash('sha256', preg_replace('/[^0-9]/', '', $phone));
}

// 记录转化事件（支付/线索/注册等转化后调用）
function conv_track(array $data): void {
    $file = DATA_DIR . '/conversion_events.json';
    $list = json_read($file);
    // Meta 标准：自动捕获 _fbp/_fbc（广告点击ID）
    $fbp = $data['fbp'] ?? ($_COOKIE['_fbp'] ?? '');
    $fbc = $data['fbc'] ?? ($_COOKIE['_fbc'] ?? '');
    $list[] = [
        'id' => $data['id'] ?? ('conv_' . date('YmdHis') . substr(bin2hex(random_bytes(4)), 0, 6)),
        'event_name' => $data['event_name'] ?? 'purchase',
        'user_id' => $data['user_id'] ?? '',
        'order_id' => $data['order_id'] ?? '',
        'click_id' => $data['click_id'] ?? ($_COOKIE['fc_utm_click_id'] ?? ''),
        'fbp' => $fbp,
        'fbc' => $fbc,
        'email_hash' => !empty($data['email']) ? conv_hash_email($data['email']) : '',
        'phone_hash' => !empty($data['phone']) ? conv_hash_phone($data['phone']) : '',
        'value' => (float)($data['value'] ?? 0),
        'currency' => $data['currency'] ?? 'CNY',
        'event_time' => time(),
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_write($file, array_slice($list, -500));
}

// 回传 worker（cron 调用）：处理 pending 转化，POST 到广告平台，重试/标记
function conv_process(): array {
    $file = DATA_DIR . '/conversion_events.json';
    $list = json_read($file);
    $configs = json_read(DATA_DIR . '/ad_platforms.json');
    if (!is_array($configs)) $configs = [];
    $sent = 0; $failed = 0;

    foreach ($list as &$c) {
        if (($c['status'] ?? '') !== 'pending') continue;
        // 幂等：按事件ID，重试不重复
        foreach ($configs as $plat) {
            if (empty($plat['enabled']) || empty($plat['endpoint'])) continue;
            $payload = [
                'event_name' => $c['event_name'],
                'event_time' => (int)$c['event_time'],
                'user_data' => [
                    'em' => $c['email_hash'] ? [$c['email_hash']] : [],
                    'ph' => $c['phone_hash'] ? [$c['phone_hash']] : [],
                    'fbp' => $c['fbp'] ?? '',
                    'fbc' => $c['fbc'] ?? '',
                ],
                'custom_data' => [
                    'order_id' => $c['order_id'],
                    'value' => $c['value'],
                    'currency' => $c['currency'],
                ],
                'event_id' => $c['id'],
                'event_source_url' => $plat['event_source_url'] ?? '',
                'action_source' => 'website',
            ];
            if ($c['click_id']) $payload['click_id'] = $c['click_id'];
            $ok = conv_post($plat['endpoint'], $plat['token'] ?? '', $payload);
            if ($ok) $sent++; else $failed++;
        }
        $c['attempts'] = ($c['attempts'] ?? 0) + 1;
        $c['status'] = $failed === 0 ? 'sent' : (($c['attempts'] >= 3) ? 'failed' : 'pending');
        $c['last_attempt'] = date('Y-m-d H:i:s');
    }
    json_write($file, $list);
    return ['sent' => $sent, 'failed' => $failed];
}

// POST 到广告平台
function conv_post(string $url, string $token, array $payload): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}
