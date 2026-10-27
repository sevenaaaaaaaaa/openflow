<?php
declare(strict_types=1);
/**
 * SEO 站长工具接入 — Google Search Console / Bing Webmaster / 百度站长
 * + 公开 SEO 看板 + 广告平台回传
 */

function seo_console_file(): string { return DATA_DIR . '/seo-console.json'; }

function seo_console_settings(): array {
    return array_merge([
        'gsc_email' => '',           // GSC Service Account Email（旧方式，留作回退）
        'gsc_key' => '',             // GSC Service Account JSON Key（旧方式，留作回退）
        'gsc_property' => '',        // GSC 属性（如 sc-domain:example.com）
        'google_client_id' => '',    // Google OAuth Client ID（一次配置，GSC+GA4 共用）
        'google_client_secret' => '',// Google OAuth Client Secret
        'google_refresh_token' => '',// 授权后自动写入，无需手填
        'google_account' => '',      // 已授权的 Google 账号邮箱（展示用）
        'ga4_property' => '',        // GA4 属性 ID（数字，授权后从下拉选择自动带入）
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

// ─── Google OAuth 2.0（一次授权，GSC + GA4 共用）───
// 回调地址固定为 /xmp/seo-center?tab=console&google_callback=1（seo-console 被 301 到
// seo-center 嵌入渲染，直接打 seo-console 会在 301 中丢 query），需在 Google Cloud Console 的
// OAuth 客户端「已授权的重定向 URI」里登记完整地址
function seo_google_callback_url(): string {
    $base = function_exists('site_config_get') ? rtrim(site_config_get('site_url', ''), '/') : '';
    if ($base === '') $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    return $base . '/xmp/seo-center?tab=console&google_callback=1';
}

function seo_google_oauth_url(): string {
    $s = seo_console_settings();
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $s['google_client_id'],
        'redirect_uri' => seo_google_callback_url(),
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly email',
        'access_type' => 'offline',   // 要 refresh_token
        'prompt' => 'consent',        // 每次都给 refresh_token（重复授权也不丢）
    ]);
}

// 授权码 → tokens（成功返回 true 并落库 refresh_token + 账号邮箱）
function seo_google_oauth_exchange(string $code): bool {
    $s = seo_console_settings();
    if ($s['google_client_id'] === '' || $s['google_client_secret'] === '') return false;
    $resp = seo_http_post_json('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $s['google_client_id'],
        'client_secret' => $s['google_client_secret'],
        'redirect_uri' => seo_google_callback_url(),
        'grant_type' => 'authorization_code',
    ], null, true);
    if (empty($resp['refresh_token']) && empty($resp['access_token'])) return false;
    if (!empty($resp['refresh_token'])) $s['google_refresh_token'] = $resp['refresh_token'];
    // 记录授权账号邮箱（展示用）
    if (!empty($resp['access_token'])) {
        $ui = seo_http_get_json('https://www.googleapis.com/oauth2/v2/userinfo', $resp['access_token']);
        if (!empty($ui['email'])) $s['google_account'] = $ui['email'];
    }
    return seo_console_save($s);
}

// refresh_token → access_token（1 小时内存+文件缓存，避免每次拉取都刷新）
function seo_google_access_token(): ?string {
    $s = seo_console_settings();
    if ($s['google_refresh_token'] === '') return null;
    static $memo = null;
    if ($memo && ($memo['exp'] ?? 0) > time() + 60) return $memo['token'];
    $cache = seo_cache();
    if (!empty($cache['google_token']) && ($cache['google_token_exp'] ?? 0) > time() + 60) {
        $memo = ['token' => $cache['google_token'], 'exp' => $cache['google_token_exp']];
        return $memo['token'];
    }
    $resp = seo_http_post_json('https://oauth2.googleapis.com/token', [
        'client_id' => $s['google_client_id'],
        'client_secret' => $s['google_client_secret'],
        'refresh_token' => $s['google_refresh_token'],
        'grant_type' => 'refresh_token',
    ], null, true);
    if (empty($resp['access_token'])) return null;
    $cache['google_token'] = $resp['access_token'];
    $cache['google_token_exp'] = time() + (int)($resp['expires_in'] ?? 3600);
    seo_cache_save($cache);
    $memo = ['token' => $resp['access_token'], 'exp' => $cache['google_token_exp']];
    return $memo['token'];
}

// ─── HTTP 小助手（JSON POST / 带 Bearer 的 GET）───
function seo_http_post_json(string $url, array $payload, ?string $bearer = null, bool $form = false): array {
    $ch = curl_init($url);
    $headers = $form ? ['Content-Type: application/x-www-form-urlencoded'] : ['Content-Type: application/json'];
    if ($bearer) $headers[] = 'Authorization: Bearer ' . $bearer;
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $form ? http_build_query($payload) : json_encode($payload),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    return is_array($resp) ? $resp : [];
}
function seo_http_get_json(string $url, string $bearer): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $bearer],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    return is_array($resp) ? $resp : [];
}

// ─── 授权后自动发现：GSC 属性列表 / GA4 属性列表 ───
function seo_gsc_list_sites(): array {
    $token = seo_google_access_token();
    if (!$token) return [];
    $resp = seo_http_get_json('https://www.googleapis.com/webmasters/v3/sites', $token);
    $out = [];
    foreach ($resp['siteEntry'] ?? [] as $e) {
        if (($e['permissionLevel'] ?? '') === 'siteUnverifiedUser') continue;
        $out[] = $e['siteUrl'] ?? '';
    }
    return array_values(array_filter($out));
}

function seo_ga4_list_properties(): array {
    $token = seo_google_access_token();
    if (!$token) return [];
    // Admin API：先列账号，再列每个账号下的 GA4 属性
    $out = [];
    $accounts = seo_http_get_json('https://analyticsadmin.googleapis.com/v1beta/accounts?pageSize=200', $token);
    foreach ($accounts['accounts'] ?? [] as $acc) {
        $accName = $acc['name'] ?? ''; // accounts/123
        if ($accName === '') continue;
        $props = seo_http_get_json('https://analyticsadmin.googleapis.com/v1beta/properties?pageSize=200&filter=' . urlencode('parent:' . $accName), $token);
        foreach ($props['properties'] ?? [] as $p) {
            $id = str_replace('properties/', '', (string)($p['name'] ?? ''));
            if ($id !== '') $out[] = ['id' => $id, 'label' => ($p['displayName'] ?? $id) . '（' . ($acc['displayName'] ?? '') . '）'];
        }
    }
    return $out;
}

// ─── 拉取 GA4 数据（近 28 天：会话/浏览/热门页面）───
function seo_fetch_ga4(): array {
    $s = seo_console_settings();
    if (empty($s['ga4_property'])) return [];
    $token = seo_google_access_token();
    if (!$token) return [];
    $resp = seo_http_post_json(
        'https://analyticsdata.googleapis.com/v1beta/properties/' . $s['ga4_property'] . ':runReport',
        [
            'dateRanges' => [['startDate' => '28daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [['name' => 'sessions'], ['name' => 'screenPageViews'], ['name' => 'totalUsers']],
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
            'limit' => 25,
        ],
        $token
    );
    $rows = [];
    foreach ($resp['rows'] ?? [] as $r) {
        $rows[] = [
            'page' => $r['dimensionValues'][0]['value'] ?? '',
            'sessions' => (int)($r['metricValues'][0]['value'] ?? 0),
            'views' => (int)($r['metricValues'][1]['value'] ?? 0),
            'users' => (int)($r['metricValues'][2]['value'] ?? 0),
        ];
    }
    return $rows;
}



// ─── 趋势序列（驾驶舱用）───
// GSC 近 N 个自然周（周一–周日），只取已完整的周
function seo_fetch_gsc_weeks(int $weeks = 8): array {
    $out = [];
    $mon = strtotime('monday this week');
    for ($i = $weeks; $i >= 1; $i--) {
        $s = strtotime('-' . ($i * 7) . ' days', $mon);
        $e = strtotime('-' . (($i - 1) * 7) . ' days', $mon);
        $e = strtotime('-1 day', $e);
        $t = seo_gsc_totals(date('Y-m-d', $s), date('Y-m-d', $e));
        if (!$t) continue;
        $t['label'] = date('m/d', $s);
        $out[] = $t;
    }
    return $out;
}

// GA4 按日（会话/用户/浏览）
function seo_fetch_ga4_daily(int $days = 28): array {
    $s = seo_console_settings();
    if (empty($s['ga4_property'])) return [];
    $token = seo_google_access_token();
    if (!$token) return [];
    $resp = seo_http_post_json(
        'https://analyticsdata.googleapis.com/v1beta/properties/' . $s['ga4_property'] . ':runReport',
        [
            'dateRanges' => [['startDate' => $days . 'daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'date']],
            'metrics' => [['name' => 'sessions'], ['name' => 'totalUsers'], ['name' => 'screenPageViews']],
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
            'limit' => 90,
        ],
        $token
    );
    $out = [];
    foreach (($resp['rows'] ?? []) as $r) {
        $d = $r['dimensionValues'][0]['value'] ?? '';
        $out[] = [
            'date' => strlen($d) === 8 ? substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2) : $d,
            'sessions' => (int)($r['metricValues'][0]['value'] ?? 0),
            'users' => (int)($r['metricValues'][1]['value'] ?? 0),
            'views' => (int)($r['metricValues'][2]['value'] ?? 0),
        ];
    }
    return $out;
}

// GA4 渠道组（近 28 天）
function seo_fetch_ga4_groups(int $days = 28): array {
    $s = seo_console_settings();
    if (empty($s['ga4_property'])) return [];
    $token = seo_google_access_token();
    if (!$token) return [];
    $resp = seo_http_post_json(
        'https://analyticsdata.googleapis.com/v1beta/properties/' . $s['ga4_property'] . ':runReport',
        [
            'dateRanges' => [['startDate' => $days . 'daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
            'metrics' => [['name' => 'sessions'], ['name' => 'totalUsers']],
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
            'limit' => 12,
        ],
        $token
    );
    $out = [];
    foreach (($resp['rows'] ?? []) as $r) {
        $out[] = [
            'group' => $r['dimensionValues'][0]['value'] ?? '',
            'sessions' => (int)($r['metricValues'][0]['value'] ?? 0),
            'users' => (int)($r['metricValues'][1]['value'] ?? 0),
        ];
    }
    return $out;
}


// ─── 分档数据包（驾驶舱 7 / 28 / 90 天切换）───
// 每档一次打包：GSC 合计+明细、GA4 合计+渠道组+事件+落地页。
// 不复用 seo_fetch_*，因为那些把窗口写死在 28 天。
function seo_range_gsc(int $days, int $rowLimit = 25): array {
    $s = seo_console_settings();
    if (empty($s['gsc_property'])) return ['rows' => [], 'total' => []];
    $token = seo_google_access_token();
    if (!$token) return ['rows' => [], 'total' => []];
    $end = date('Y-m-d');
    $start = date('Y-m-d', strtotime('-' . $days . ' days'));
    $endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . urlencode($s['gsc_property']) . '/searchAnalytics/query';
    $post = function (array $payload) use ($endpoint, $token): array {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload),
            CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>25, CURLOPT_SSL_VERIFYPEER=>false,
        ]);
        $r = json_decode(curl_exec($ch), true);
        return is_array($r) ? $r : [];
    };
    $tot = $post(['startDate'=>$start, 'endDate'=>$end, 'dimensions'=>[], 'rowLimit'=>1, 'dataState'=>'all']);
    $r1 = $tot['rows'][0] ?? [];
    $list = $post(['startDate'=>$start, 'endDate'=>$end, 'dimensions'=>['query','page'], 'rowLimit'=>$rowLimit, 'dataState'=>'all']);
    $rows = [];
    foreach (($list['rows'] ?? []) as $r) {
        $rows[] = [
            'query' => $r['keys'][0] ?? '', 'page' => $r['keys'][1] ?? '',
            'clicks' => (int)($r['clicks'] ?? 0), 'impressions' => (int)($r['impressions'] ?? 0),
            'ctr' => round(((float)($r['ctr'] ?? 0)) * 100, 2), 'position' => round((float)($r['position'] ?? 0), 1),
        ];
    }
    return [
        'days' => $days, 'start' => $start, 'end' => $end,
        'total' => [
            'clicks' => (int)($r1['clicks'] ?? 0), 'impressions' => (int)($r1['impressions'] ?? 0),
            'ctr' => round(((float)($r1['ctr'] ?? 0)) * 100, 2), 'position' => round((float)($r1['position'] ?? 0), 1),
        ],
        'rows' => $rows,
    ];
}

function seo_range_ga4(int $days): array {
    $s = seo_console_settings();
    if (empty($s['ga4_property'])) return ['total' => [], 'groups' => [], 'events' => [], 'pages' => []];
    $token = seo_google_access_token();
    if (!$token) return ['total' => [], 'groups' => [], 'events' => [], 'pages' => []];
    $base = 'https://analyticsdata.googleapis.com/v1beta/properties/' . $s['ga4_property'] . ':runReport';
    $dr = [['startDate' => $days . 'daysAgo', 'endDate' => 'today']];

    $tot = seo_http_post_json($base, [
        'dateRanges' => $dr,
        'metrics' => [['name'=>'sessions'],['name'=>'totalUsers'],['name'=>'screenPageViews'],['name'=>'engagedSessions'],['name'=>'averageSessionDuration']],
    ], $token);
    $m = $tot['rows'][0]['metricValues'] ?? [];
    $sessions = (int)($m[0]['value'] ?? 0);
    $engaged = (int)($m[3]['value'] ?? 0);

    $grp = seo_http_post_json($base, [
        'dateRanges' => $dr,
        'dimensions' => [['name'=>'sessionDefaultChannelGroup']],
        'metrics' => [['name'=>'sessions'],['name'=>'totalUsers']],
        'orderBys' => [['metric'=>['metricName'=>'sessions'],'desc'=>true]], 'limit'=>10,
    ], $token);
    $groups = [];
    foreach (($grp['rows'] ?? []) as $r) {
        $groups[] = ['group'=>$r['dimensionValues'][0]['value'] ?? '', 'sessions'=>(int)($r['metricValues'][0]['value'] ?? 0), 'users'=>(int)($r['metricValues'][1]['value'] ?? 0)];
    }

    $ev = seo_http_post_json($base, [
        'dateRanges' => $dr,
        'dimensions' => [['name'=>'eventName']],
        'metrics' => [['name'=>'eventCount'],['name'=>'totalUsers']],
        'orderBys' => [['metric'=>['metricName'=>'eventCount'],'desc'=>true]], 'limit'=>20,
    ], $token);
    $events = [];
    foreach (($ev['rows'] ?? []) as $r) {
        $events[] = ['event'=>$r['dimensionValues'][0]['value'] ?? '', 'count'=>(int)($r['metricValues'][0]['value'] ?? 0), 'users'=>(int)($r['metricValues'][1]['value'] ?? 0)];
    }

    $pg = seo_http_post_json($base, [
        'dateRanges' => $dr,
        'dimensions' => [['name'=>'pagePath']],
        'metrics' => [['name'=>'sessions'],['name'=>'screenPageViews'],['name'=>'totalUsers']],
        'orderBys' => [['metric'=>['metricName'=>'sessions'],'desc'=>true]], 'limit'=>25,
    ], $token);
    $pages = [];
    foreach (($pg['rows'] ?? []) as $r) {
        $pages[] = ['page'=>$r['dimensionValues'][0]['value'] ?? '', 'sessions'=>(int)($r['metricValues'][0]['value'] ?? 0), 'views'=>(int)($r['metricValues'][1]['value'] ?? 0), 'users'=>(int)($r['metricValues'][2]['value'] ?? 0)];
    }

    return [
        'days' => $days,
        'total' => [
            'sessions' => $sessions, 'users' => (int)($m[1]['value'] ?? 0), 'views' => (int)($m[2]['value'] ?? 0),
            'engaged_sessions' => $engaged, 'engagement_rate' => $sessions > 0 ? round($engaged / $sessions * 100, 1) : 0,
            'avg_duration' => (int)round((float)($m[4]['value'] ?? 0)),
        ],
        'groups' => $groups, 'events' => $events, 'pages' => $pages,
    ];
}

function seo_fetch_ranges(array $ranges = [7, 28, 90]): array {
    $out = [];
    foreach ($ranges as $d) $out[(string)$d] = ['gsc' => seo_range_gsc($d), 'ga4' => seo_range_ga4($d)];
    return $out;
}

// GA4 事件计数（近 N 天）—— 漏斗断点用
function seo_fetch_ga4_events(int $days = 28): array {
    $s = seo_console_settings();
    if (empty($s['ga4_property'])) return [];
    $token = seo_google_access_token();
    if (!$token) return [];
    $resp = seo_http_post_json(
        'https://analyticsdata.googleapis.com/v1beta/properties/' . $s['ga4_property'] . ':runReport',
        [
            'dateRanges' => [['startDate' => $days . 'daysAgo', 'endDate' => 'today']],
            'dimensions' => [['name' => 'eventName']],
            'metrics' => [['name' => 'eventCount'], ['name' => 'totalUsers']],
            'orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => true]],
            'limit' => 20,
        ],
        $token
    );
    $out = [];
    foreach (($resp['rows'] ?? []) as $r) {
        $out[] = [
            'event' => $r['dimensionValues'][0]['value'] ?? '',
            'count' => (int)($r['metricValues'][0]['value'] ?? 0),
            'users' => (int)($r['metricValues'][1]['value'] ?? 0),
        ];
    }
    return $out;
}

// ─── GA4 汇总口径（不传 dimensions，一次返回一行合计）───
// 与 GSC 同理：Top20 明细行相加 ≠ 全量会话。这里补三档汇总。
function seo_ga4_totals(string $start, string $end): array {
    $s = seo_console_settings();
    if (empty($s['ga4_property'])) return [];
    $token = seo_google_access_token();
    if (!$token) return [];
    $resp = seo_http_post_json(
        'https://analyticsdata.googleapis.com/v1beta/properties/' . $s['ga4_property'] . ':runReport',
        [
            'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
            'metrics' => [
                ['name' => 'sessions'], ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'], ['name' => 'engagedSessions'],
                ['name' => 'averageSessionDuration'],
            ],
        ],
        $token
    );
    $m = $resp['rows'][0]['metricValues'] ?? [];
    $sessions = (int)($m[0]['value'] ?? 0);
    $engaged = (int)($m[3]['value'] ?? 0);
    return [
        'start' => $start, 'end' => $end,
        'sessions' => $sessions,
        'users' => (int)($m[1]['value'] ?? 0),
        'views' => (int)($m[2]['value'] ?? 0),
        'engaged_sessions' => $engaged,
        'engagement_rate' => $sessions > 0 ? round($engaged / $sessions * 100, 1) : 0,
        'avg_duration' => (int)round((float)($m[4]['value'] ?? 0)),
    ];
}

// GA4 渠道组（sessionDefaultChannelGroup）：本周 / 上周
function seo_ga4_channel_weekly(string $start, string $end): array {
    $s = seo_console_settings();
    if (empty($s['ga4_property'])) return [];
    $token = seo_google_access_token();
    if (!$token) return [];
    $resp = seo_http_post_json(
        'https://analyticsdata.googleapis.com/v1beta/properties/' . $s['ga4_property'] . ':runReport',
        [
            'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
            'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
            'metrics' => [['name' => 'sessions'], ['name' => 'totalUsers']],
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
            'limit' => 12,
        ],
        $token
    );
    $rows = [];
    foreach (($resp['rows'] ?? []) as $r) {
        $rows[] = [
            'group' => $r['dimensionValues'][0]['value'] ?? '',
            'sessions' => (int)($r['metricValues'][0]['value'] ?? 0),
            'users' => (int)($r['metricValues'][1]['value'] ?? 0),
        ];
    }
    return $rows;
}

// 三档：近 28 天 / 本周（周一至今）/ 上周（完整自然周）
function seo_fetch_ga4_totals(): array {
    $mon = strtotime('monday this week');
    $wS = date('Y-m-d', $mon); $wE = date('Y-m-d');
    $pS = date('Y-m-d', strtotime('-7 days', $mon)); $pE = date('Y-m-d', strtotime('-1 day', $mon));
    return [
        'd28' => seo_ga4_totals(date('Y-m-d', strtotime('-28 days')), date('Y-m-d')),
        'week' => seo_ga4_totals($wS, $wE),
        'prev_week' => seo_ga4_totals($pS, $pE),
        'channel_week' => seo_ga4_channel_weekly($wS, $wE),
        'channel_prev_week' => seo_ga4_channel_weekly($pS, $pE),
    ];
}

// ─── 拉取 GSC 数据（Search Analytics）───

// ─── GSC 汇总口径（不传 dimensions 时 API 返回一行合计）───
// 之前只有 Top25 明细，报告里把它们加起来当"28 天总量"是错的（25 行只占全量一部分）。
function seo_gsc_totals(string $start, string $end): array {
    $s = seo_console_settings();
    if (empty($s['gsc_property'])) return [];
    $token = seo_google_access_token();
    if (!$token) return [];
    $endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . urlencode($s['gsc_property']) . '/searchAnalytics/query';
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode(['startDate'=>$start, 'endDate'=>$end, 'dimensions'=>[], 'rowLimit'=>1, 'dataState'=>'all']),
        CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false,
    ]);
    $resp = json_decode(curl_exec($ch), true);
    $r = $resp['rows'][0] ?? null;
    return [
        'start' => $start, 'end' => $end,
        'clicks' => (int)($r['clicks'] ?? 0),
        'impressions' => (int)($r['impressions'] ?? 0),
        'ctr' => round(((float)($r['ctr'] ?? 0)) * 100, 2),
        'position' => round((float)($r['position'] ?? 0), 1),
    ];
}

// 三档口径：近 28 天 / 本周（周一至今）/ 上周（完整自然周）
function seo_fetch_gsc_totals(): array {
    $mon = strtotime('monday this week');
    return [
        'd28' => seo_gsc_totals(date('Y-m-d', strtotime('-28 days')), date('Y-m-d')),
        'week' => seo_gsc_totals(date('Y-m-d', $mon), date('Y-m-d')),
        'prev_week' => seo_gsc_totals(date('Y-m-d', strtotime('-7 days', $mon)), date('Y-m-d', strtotime('-1 day', $mon))),
    ];
}

function seo_fetch_gsc(): array {
    $s = seo_console_settings();
    if (empty($s['gsc_property'])) return [];

    // 优先 OAuth 授权（一键连接）；没有则回退 Service Account JWT（旧方式）
    $token = seo_google_access_token();
    if (!$token && !empty($s['gsc_email']) && !empty($s['gsc_key'])) {
        $jwt = seo_make_jwt($s['gsc_email'], $s['gsc_key']);
        if ($jwt) $token = seo_gsc_token($jwt);
    }
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
    // Bing Webmaster API 返回 {"d":[{"__type":"..","Query":"..","Clicks":0,...}]}，
    // 旧实现直接 return 整个响应体，前端 array_column 拿不到 clicks —— 等于配了也看不到数。
    $url = 'https://ssl.bing.com/webmaster/api.svc/json/GetQueryStats?siteUrl=' . urlencode($s['bing_site']) . '&apikey=' . urlencode($s['bing_api_key']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !is_string($raw)) return [];
    $resp = json_decode($raw, true);
    $rows = is_array($resp['d'] ?? null) ? $resp['d'] : (is_array($resp) ? $resp : []);
    // 按关键词聚合（Bing 按日期返回多行）
    $agg = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $q = (string)($r['Query'] ?? '');
        if ($q === '') continue;
        if (!isset($agg[$q])) $agg[$q] = ['query'=>$q, 'page'=>'', 'clicks'=>0, 'impressions'=>0, 'posSum'=>0, 'posN'=>0];
        $agg[$q]['clicks'] += (int)($r['Clicks'] ?? 0);
        $agg[$q]['impressions'] += (int)($r['Impressions'] ?? 0);
        $p = (float)($r['AvgImpressionPosition'] ?? 0);
        if ($p > 0) { $agg[$q]['posSum'] += $p; $agg[$q]['posN']++; }
    }
    $out = [];
    foreach ($agg as $a) {
        $out[] = [
            'query' => $a['query'],
            'page' => '',
            'clicks' => $a['clicks'],
            'impressions' => $a['impressions'],
            'ctr' => $a['impressions'] > 0 ? round($a['clicks'] / $a['impressions'] * 100, 2) : 0,
            'position' => $a['posN'] > 0 ? round($a['posSum'] / $a['posN'], 1) : '—',
        ];
    }
    usort($out, fn($x, $y) => [$y['impressions'], $y['clicks']] <=> [$x['impressions'], $x['clicks']]);
    return $out;
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

// ─── 拉取 Yandex Webmaster 数据 ───
function seo_fetch_yandex(): array {
    $s = seo_console_settings();
    $token = $s['yandex_token'] ?? '';
    $host = $s['yandex_host'] ?? '';
    if (empty($token) || empty($host)) return [];
    $url = 'https://api.webmaster.yandex.net/v4/user/' . ($s['yandex_user_id'] ?? 'self') . '/hosts/' . urlencode($host) . '/search-queries/popular?limit=25&query_indicator=ALL';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ["Authorization: OAuth $token", "Content-Type: application/json"],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    if (!is_array($resp)) return [];
    // 统一成 [{query, clicks, impressions, ctr, position}] 格式
    $rows = [];
    foreach ($resp['queries'] ?? [] as $q) {
        $rows[] = [
            'query' => $q['query_text'] ?? '',
            'clicks' => (int)($q['clicks'] ?? 0),
            'impressions' => (int)($q['impressions'] ?? 0),
            'ctr' => round(($q['ctr'] ?? 0) * 100, 2),
            'position' => round(($q['position'] ?? 0), 1),
        ];
    }
    return $rows;
}

// ─── OAuth 回调/断开处理（必须在任何 HTML 输出前调用；seo-center 嵌入时由宿主页提前调用）───
// 返回 [type, text] 用于页面顶部提示；若执行了跳转则直接 exit。
function seo_console_handle_google_actions(): ?array {
    if (isset($_GET['google_callback'])) {
        $code = trim((string)($_GET['code'] ?? ''));
        if ($code !== '' && seo_google_oauth_exchange($code)) {
            header('Location: ' . of_hub_url(['google_connected' => 1]));
            exit;
        }
        return ['error', 'Google 授权失败：' . (string)($_GET['error'] ?? '请检查 Client ID/Secret 与回调地址配置')];
    }
    if (isset($_GET['google_disconnect'])) {
        $s = seo_console_settings();
        $s['google_refresh_token'] = '';
        $s['google_account'] = '';
        seo_console_save($s);
        header('Location: ' . of_hub_url(['google_disconnected' => 1]));
        exit;
    }
    if (isset($_GET['google_connected'])) return ['success', 'Google 账号连接成功，GSC 属性与 GA4 属性已可自动选择'];
    if (isset($_GET['google_disconnected'])) return ['success', '已断开 Google 授权'];
    return null;
}

// ─── 统一拉取 + 缓存 ───
function seo_console_pull(): array {
    $data = ['fetched_at'=>date('Y-m-d H:i:s')];
    $data['gsc'] = seo_fetch_gsc();
    $data['gsc_totals'] = seo_fetch_gsc_totals();
    $data['ga4'] = seo_fetch_ga4();
    $data['ga4_totals'] = seo_fetch_ga4_totals();
    $data['gsc_weeks'] = seo_fetch_gsc_weeks(8);
    $data['ga4_daily'] = seo_fetch_ga4_daily(28);
    $data['ga4_groups_28d'] = seo_fetch_ga4_groups(28);
    $data['ga4_events'] = seo_fetch_ga4_events(28);
    $data['ranges'] = seo_fetch_ranges([7, 28, 90]);
    $data['bing'] = seo_fetch_bing();
    $data['baidu'] = seo_fetch_baidu();
    $data['yandex'] = seo_fetch_yandex();
    // 保留 token 缓存，避免被覆盖
    $old = seo_cache();
    foreach (['google_token','google_token_exp'] as $k) if (isset($old[$k])) $data[$k] = $old[$k];
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
