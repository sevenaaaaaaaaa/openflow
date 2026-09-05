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
    // P1-3：修复配置断链 —— 优先 ad_platforms.json，空则回退 seo-console.json::ad_platforms
    $configs = json_read(DATA_DIR . '/ad_platforms.json');
    if (empty($configs)) {
        $sc = json_read(DATA_DIR . '/seo-console.json');
        $configs = $sc['ad_platforms'] ?? [];
    }
    if (!is_array($configs)) $configs = [];
    $sent = 0; $failed = 0;

    foreach ($list as &$c) {
        if (($c['status'] ?? '') !== 'pending') continue;
        $platSent = 0; $platFailed = 0;
        foreach ($configs as $plat) {
            if (empty($plat['enabled'])) continue;
            $platform = (string)($plat['platform'] ?? $plat['provider'] ?? '');
            $ok = false;
            try {
                if ($platform === 'meta') $ok = conv_send_meta($plat, $c);
                elseif ($platform === 'google') $ok = conv_send_google($plat, $c);
                elseif ($platform === 'douyin') $ok = conv_send_douyin($plat, $c);
                elseif (!empty($plat['endpoint'])) $ok = conv_post($plat['endpoint'], $plat['token'] ?? '', conv_payload($plat, $c));
            } catch (\Throwable $e) {}
            if ($ok) $platSent++; else $platFailed++;
        }
        $c['attempts'] = ($c['attempts'] ?? 0) + 1;
        $c['status'] = $platFailed === 0 ? 'sent' : (($c['attempts'] >= 3) ? 'failed' : 'pending');
        $c['last_attempt'] = date('Y-m-d H:i:s');
    }
    json_write($file, $list);
    return ['sent' => $sent, 'failed' => $failed];
}

/** 通用 payload（保留老通用 POST 用） */
function conv_payload(array $plat, array $c): array {
    return [
        'event_name' => $c['event_name'],
        'event_time' => (int)$c['event_time'],
        'user_data' => ['em' => $c['email_hash'] ? [$c['email_hash']] : [], 'ph' => $c['phone_hash'] ? [$c['phone_hash']] : [], 'fbp' => $c['fbp'] ?? '', 'fbc' => $c['fbc'] ?? ''],
        'custom_data' => ['order_id' => $c['order_id'], 'value' => $c['value'], 'currency' => $c['currency']],
        'event_id' => $c['id'],
        'event_source_url' => $plat['event_source_url'] ?? '',
        'action_source' => 'website',
    ];
}

/** Meta CAPI：graph /{pixel_id}/events，token 放 query，映射标准事件名 */
function conv_send_meta(array $plat, array $c): bool {
    $pixel = (string)($plat['pixel_id'] ?? '');
    if ($pixel === '') return false;
    $map = (array)($plat['event_name_map'] ?? []);
    $eventName = (string)($map[$c['event_name']] ?? $c['event_name']);
    $token = (string)($plat['access_token'] ?? '');
    $url = 'https://graph.facebook.com/v19.0/' . $pixel . '/events?access_token=' . rawurlencode($token);
    $body = ['data' => [[
        'event_name' => $eventName,
        'event_time' => (int)$c['event_time'],
        'event_id' => $c['id'],
        'action_source' => 'website',
        'event_source_url' => $plat['event_source_url'] ?? '',
        'user_data' => ['em' => array_values(array_filter([$c['email_hash']])), 'ph' => array_values(array_filter([$c['phone_hash']])), 'fbp' => $c['fbp'] ?? '', 'fbc' => $c['fbc'] ?? ''],
        'custom_data' => ['order_id' => $c['order_id'], 'value' => (float)$c['value'], 'currency' => $c['currency']],
    ]]];
    return conv_http('POST', $url, ['Content-Type: application/json'], $body);
}

/** Google Ads Offline Conversions：customers/{id}/offlineUserDataJobs（简化：上报一次） */
function conv_send_google(array $plat, array $c): bool {
    $customer = (string)($plat['customer_id'] ?? '');
    if ($customer === '') return false;
    $url = 'https://googleads.googleapis.com/v16/customers/' . rawurlencode($customer) . '/offlineUserDataJobs:create';
    $headers = [
        'Authorization: Bearer ' . (string)($plat['access_token'] ?? ''),
        'developer-token: ' . (string)($plat['developer_token'] ?? ''),
        'Content-Type: application/json',
    ];
    $body = ['job' => [
        'type' => 'STORE_SALES_UPLOAD_FIRST_PARTY',
        'conversion_action' => 'customers/' . $customer . '/conversionActions/' . (string)($plat['conversion_action_id'] ?? ''),
        'conversion_date_time' => date('c', (int)$c['event_time']),
        'user_data' => [
            'user_identifiers' => [
                ['hashed_email' => base64_encode(hash('sha256', strtolower(trim($c['email_hash'] ?: '')), true))],
                ['hashed_phone_number' => base64_encode(hash('sha256', trim($c['phone_hash'] ?: ''), true))],
            ],
        ],
    ]];
    return conv_http('POST', $url, $headers, $body);
}

/** 巨量引擎 / TikTok for Business：business-api event/track */
function conv_send_douyin(array $plat, array $c): bool {
    $advertiser = (string)($plat['advertiser_id'] ?? '');
    if ($advertiser === '') return false;
    $url = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';
    $headers = ['Access-Token: ' . (string)($plat['access_token'] ?? ''), 'Content-Type: application/json'];
    $map = (array)($plat['event_name_map'] ?? []);
    $eventName = (string)($map[$c['event_name']] ?? $c['event_name']);
    $body = [
        'event_source' => 'web',
        'event_name' => $eventName,
        'event_time' => (int)$c['event_time'],
        'pixel_code' => (string)($plat['pixel_code'] ?? ''),
        'user' => ['external_id' => $c['user_id'] ?: '', 'phone' => $c['phone_hash'] ?: '', 'email' => $c['email_hash'] ?: ''],
        'properties' => ['order_id' => $c['order_id'], 'value' => (float)$c['value'], 'currency' => $c['currency']],
        'page' => ['url' => $plat['event_source_url'] ?? ''],
    ];
    return conv_http('POST', $url, $headers, $body);
}

/** 通用 HTTP（GET/POST/自定义 header），供各 provider 用；$GLOBALS['_CONV_HTTP_MOCK'] 存在则短路（测试注入） */
function conv_http(string $method, string $url, array $headers, array $body = []): bool {
    if (isset($GLOBALS['_CONV_HTTP_MOCK'])) {
        $GLOBALS['_CONV_HTTP_MOCK'][] = compact('method','url','headers','body');
        return true;
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($method === 'POST') { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE); }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

// POST 到广告平台（兼容老 endpoint 调用）
function conv_post(string $url, string $token, array $payload): bool {
    return conv_http('POST', $url, ['Content-Type: application/json', 'Authorization: Bearer ' . $token], $payload);
}
