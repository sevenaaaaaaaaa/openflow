<?php
/**
 * SEO 站长工具接入 — Google Search Console / Bing Webmaster / 百度站长
 * + 公开 SEO 看板 + 广告平台回传
 */

function seo_console_file(): string { return DATA_DIR . '/seo-console.json'; }

function seo_console_settings(): array {
    return array_merge([
        'gsc_email' => '',           // GSC Service Account Email
        'gsc_key' => '',             // GSC Service Account JSON Key（填路径或内容）
        'gsc_property' => '',        // GSC 属性（如 sc-domain:example.com）
        'bing_api_key' => '',        // Bing Webmaster API Key
        'bing_site' => '',           // Bing 站点 URL
        'baidu_token' => '',         // 百度站长 Token
        'baidu_site' => '',          // 百度站点
        'public_enabled' => false,   // 公开看板开关
        'public_slug' => 'seo-board',// 公开看板 slug
        'ad_platforms' => [],        // 广告平台回传配置 [{platform, endpoint, token}]
    ], json_read(seo_console_file()));
}
function seo_console_save(array $s): bool {
    if (!is_dir(dirname(seo_console_file()))) mkdir(dirname(seo_console_file()), 0755, true);
    return json_write(seo_console_file(), $s);
}

// ─── 数据缓存 ───
function seo_cache_file(): string { return DATA_DIR . '/seo-console-cache.json'; }
function seo_cache(): array { return json_read(seo_cache_file()); }
function seo_cache_save(array $data): bool { return json_write(seo_cache_file(), $data); }

// ─── 拉取 GSC 数据（Search Analytics）───
function seo_fetch_gsc(): array {
    $s = seo_console_settings();
    if (empty($s['gsc_email']) || empty($s['gsc_key']) || empty($s['gsc_property'])) return [];

    // 用 JWT 生成 OAuth token（简化：需 Google API Client，这里用本地实现）
    $jwt = seo_make_jwt($s['gsc_email'], $s['gsc_key']);
    if (!$jwt) return [];
    $token = seo_gsc_token($jwt);
    if (!$token) return [];

    $endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . urlencode($s['gsc_property']) . '/searchAnalytics/query';
    $payload = [
        'startDate' => date('Y-m-d', strtotime('-28 days')),
        'endDate' => date('Y-m-d'),
        'dimensions' => ['query', 'page'],
        'rowLimit' => 25,
    ];
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false,
    ]);
    $resp = json_decode(curl_exec($ch), true);

    $rows = [];
    foreach (($resp['rows'] ?? []) as $r) {
        $rows[] = [
            'query' => $r['keys'][0] ?? '', 'page' => $r['keys'][1] ?? '',
            'clicks' => $r['clicks'] ?? 0, 'impressions' => $r['impressions'] ?? 0,
            'ctr' => round(($r['ctr'] ?? 0) * 100, 2), 'position' => round($r['position'] ?? 0, 1),
        ];
    }
    return $rows;
}

// GSC Service Account JWT
function seo_make_jwt(string $email, string $keyJson): ?string {
    $keyData = json_decode($keyJson, true);
    if (!$keyData || empty($keyData['private_key'])) return null;
    $now = time();
    $header = base64_encode(json_encode(['alg'=>'RS256','typ'=>'JWT']));
    $claims = base64_encode(json_encode([
        'iss'=>$keyData['client_email'] ?? $email, 'scope'=>'https://www.googleapis.com/auth/webmasters.readonly',
        'aud'=>'https://oauth2.googleapis.com/token', 'iat'=>$now, 'exp'=>$now + 3600,
    ]));
    $signingInput = $header . '.' . $claims;
    $signature = '';
    openssl_sign($signingInput, $signature, $keyData['private_key'], 'sha256');
    if (empty($signature)) return null;
    return $signingInput . '.' . base64_encode($signature);
}

function seo_gsc_token(string $jwt): ?string {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query([
        'grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion'=>$jwt,
    ]), CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false]);
    $resp = json_decode(curl_exec($ch), true);
    return $resp['access_token'] ?? null;
}

// ─── 拉取 Bing 数据 ───
function seo_fetch_bing(): array {
    $s = seo_console_settings();
    if (empty($s['bing_api_key']) || empty($s['bing_site'])) return [];
    $url = 'https://ssl.bing.com/webmaster/api.svc/json/GetPageTrafficStats?siteUrl=' . urlencode($s['bing_site']) . '&apikey=' . $s['bing_api_key'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false]);
    $resp = json_decode(curl_exec($ch), true);
    return is_array($resp) ? $resp : [];
}

// ─── 拉取百度数据 ───
function seo_fetch_baidu(): array {
    $s = seo_console_settings();
    if (empty($s['baidu_token']) || empty($s['baidu_site'])) return [];
    $url = 'https://data.zz.baidu.com/searchdata?site=' . urlencode($s['baidu_site']) . '&token=' . $s['baidu_token'] . '&datatype=visitor';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false]);
    $resp = json_decode(curl_exec($ch), true);
    return is_array($resp) ? $resp : [];
}

// ─── 统一拉取 + 缓存 ───
function seo_console_pull(): array {
    $data = ['fetched_at'=>date('Y-m-d H:i:s')];
    $data['gsc'] = seo_fetch_gsc();
    $data['bing'] = seo_fetch_bing();
    $data['baidu'] = seo_fetch_baidu();
    seo_cache_save($data);
    return $data;
}

// ─── 广告平台回传 ───
// 用于投放平台（巨量/腾讯广告/Google Ads）API 转化回传
function seo_ad_webhook(array $payload): void {
    $s = seo_console_settings();
    foreach (($s['ad_platforms'] ?? []) as $ad) {
        if (empty($ad['endpoint'])) continue;
        $ch = curl_init($ad['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode(array_merge($payload, ['platform'=>$ad['platform'] ?? ''])),
            CURLOPT_HTTPHEADER=>array_filter(['Content-Type: application/json', !empty($ad['token']) ? 'Authorization: Bearer '.$ad['token'] : null]),
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
        ]);
        curl_exec($ch);
    }
}
