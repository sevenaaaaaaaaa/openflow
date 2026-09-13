<?php
/**
 * PublishAdapters — 全平台发布适配器
 *
 * 统一「一键发全平台」的落地层：
 *   - api 适配器：有凭据即真发（通用 webhook / Telegram / Discord / Mastodon / WordPress；公众号·邮件复用 SocialPublisher）
 *   - manual 适配器：无开放 API 的平台（知乎/小红书/LinkedIn/X/B站/视频号/抖音）→ 生成平台原生文案 + 去发布链接，明确「需手动」
 *
 * 通用 webhook 是关键：任何外部发布器（n8n / Make / 自建 / 第三方）只要接一个 POST，就能作为平台接入。
 * 未配置凭据 → 明确「未就绪」，绝不假成功。
 */

function pub_dir(): string { return DATA_DIR . '/publish'; }
function pub_adapters_file(): string { return pub_dir() . '/adapters.json'; }
function pub_adapter_settings(): array { $d = json_read(pub_adapters_file()); return is_array($d) ? $d : []; }
function pub_adapter_save(string $id, array $vals): void {
    $all = pub_adapter_settings();
    $all[$id] = array_merge((array)($all[$id] ?? []), $vals);
    json_write(pub_adapters_file(), $all);
}
function pub_adapter_cfg(string $id): array { return (array)(pub_adapter_settings()[$id] ?? []); }

/** 适配器注册表 */
function pub_adapters(): array {
    return [
        'webhook' => ['name' => '通用 Webhook（对接任意外部发布器）', 'mode' => 'api',
            'fields' => ['url' => '回调 URL（POST JSON）', 'token' => '鉴权 Token（可选，Bearer）'],
            'ready' => fn($c) => !empty($c['url']),
            'publish' => fn($a, $c) => pub_ad_webhook($a, $c)],
        'telegram' => ['name' => 'Telegram', 'mode' => 'api',
            'fields' => ['bot_token' => 'Bot Token', 'chat_id' => 'Chat/Channel ID'],
            'ready' => fn($c) => !empty($c['bot_token']) && !empty($c['chat_id']),
            'publish' => fn($a, $c) => pub_ad_telegram($a, $c)],
        'discord' => ['name' => 'Discord', 'mode' => 'api',
            'fields' => ['webhook_url' => '频道 Webhook URL'],
            'ready' => fn($c) => !empty($c['webhook_url']),
            'publish' => fn($a, $c) => pub_ad_discord($a, $c)],
        'mastodon' => ['name' => 'Mastodon', 'mode' => 'api',
            'fields' => ['instance' => '实例地址（https://mastodon.social）', 'token' => 'Access Token'],
            'ready' => fn($c) => !empty($c['instance']) && !empty($c['token']),
            'publish' => fn($a, $c) => pub_ad_mastodon($a, $c)],
        'wordpress' => ['name' => 'WordPress（外部站点）', 'mode' => 'api',
            'fields' => ['site_url' => '站点地址', 'username' => '用户名', 'app_password' => '应用密码'],
            'ready' => fn($c) => !empty($c['site_url']) && !empty($c['username']) && !empty($c['app_password']),
            'publish' => fn($a, $c) => pub_ad_wordpress($a, $c)],
        'wechat' => ['name' => '微信公众号', 'mode' => 'api',
            'fields' => [], 'ready' => fn($c) => !empty((\WechatMp::config())['appid']),
            'publish' => fn($a, $c) => \SocialPublisher::publish($a, 'wechat')],
        'email' => ['name' => '邮件推送', 'mode' => 'api',
            'fields' => [], 'ready' => fn($c) => (bool)\BillionMail::fromConfig(),
            'publish' => fn($a, $c) => \SocialPublisher::publish($a, 'email')],
        // 手动平台（无开放 API）：生成原生文案 + 发布入口
        'zhihu' => ['name' => '知乎', 'mode' => 'manual', 'fields' => []],
        'xiaohongshu' => ['name' => '小红书', 'mode' => 'manual', 'fields' => []],
        'linkedin' => ['name' => 'LinkedIn', 'mode' => 'manual', 'fields' => []],
        'twitter' => ['name' => 'X (Twitter)', 'mode' => 'manual', 'fields' => []],
        'facebook' => ['name' => 'Facebook', 'mode' => 'manual', 'fields' => []],
        'bilibili' => ['name' => 'B站', 'mode' => 'manual', 'fields' => []],
        'video' => ['name' => '视频号', 'mode' => 'manual', 'fields' => []],
        'douyin' => ['name' => '抖音', 'mode' => 'manual', 'fields' => []],
    ];
}

function pub_adapter_ready(string $id): bool {
    $ad = pub_adapters()[$id] ?? null;
    if (!$ad) return false;
    if (($ad['mode'] ?? '') === 'manual') return true;   // 手动平台永远"可用"（生成文案）
    return !empty($ad['ready']) && ($ad['ready'])(pub_adapter_cfg($id));
}

/** 统一发布入口：返回 ['ok','message','platform_id','url','manual'?,'variant'?,'publish_url'?] */
function pub_adapter_publish(string $id, array $article, array $opts = []): array {
    $ad = pub_adapters()[$id] ?? null;
    if (!$ad) return ['ok' => false, 'message' => '未知平台：' . $id];
    if (($ad['mode'] ?? '') === 'manual') {
        $var = \SocialPublisher::variantFor($article, $id);
        return ['ok' => false, 'manual' => true, 'message' => '已生成「' . $ad['name'] . '」文案，复制后到创作中心手动发布', 'platform_id' => 'manual_' . substr(bin2hex(random_bytes(4)), 0, 6), 'variant' => $var, 'publish_url' => \SocialPublisher::platformHome($id)];
    }
    if (!pub_adapter_ready($id)) return ['ok' => false, 'message' => $ad['name'] . ' 未配置凭据（到「内容分发」里填）'];
    try { return ($ad['publish'])($article, pub_adapter_cfg($id)); }
    catch (\Throwable $e) { return ['ok' => false, 'message' => $e->getMessage()]; }
}

/* ── API 适配器实现 ── */

function pub_ad_webhook(array $a, array $c): array {
    $url = (string)($c['url'] ?? '');
    $payload = ['title' => (string)($a['title'] ?? ''), 'excerpt' => (string)($a['excerpt'] ?? ''), 'url' => pub_article_url($a), 'content' => (string)($a['content'] ?? ''), 'article_id' => (string)($a['id'] ?? '')];
    $headers = [];
    if (!empty($c['token'])) $headers[] = 'Authorization: Bearer ' . $c['token'];
    $r = pub_http_json($url, $payload, $headers);
    return ['ok' => $r['ok'], 'message' => $r['ok'] ? '已推送到自定义端点' : ('失败：' . $r['detail']), 'platform_id' => '', 'url' => pub_article_url($a)];
}
function pub_ad_telegram(array $a, array $c): array {
    $url = 'https://api.telegram.org/bot' . $c['bot_token'] . '/sendMessage';
    $text = (string)($a['title'] ?? '') . "\n" . mb_substr((string)($a['excerpt'] ?? ''), 0, 200) . "\n" . pub_article_url($a);
    $r = pub_http_json($url, ['chat_id' => $c['chat_id'], 'text' => $text, 'disable_web_page_preview' => false]);
    return ['ok' => $r['ok'], 'message' => $r['ok'] ? '已发送到 Telegram' : ('失败：' . $r['detail']), 'platform_id' => (string)($r['data']['result']['message_id'] ?? ''), 'url' => pub_article_url($a)];
}
function pub_ad_discord(array $a, array $c): array {
    $r = pub_http_json((string)$c['webhook_url'], ['content' => (string)($a['title'] ?? '') . "\n" . pub_article_url($a)]);
    return ['ok' => $r['ok'], 'message' => $r['ok'] ? '已发送到 Discord' : ('失败：' . $r['detail']), 'platform_id' => '', 'url' => pub_article_url($a)];
}
function pub_ad_mastodon(array $a, array $c): array {
    $url = rtrim((string)$c['instance'], '/') . '/api/v1/statuses';
    $r = pub_http_json($url, ['status' => (string)($a['title'] ?? '') . "\n" . pub_article_url($a)], ['Authorization: Bearer ' . $c['token']]);
    return ['ok' => $r['ok'], 'message' => $r['ok'] ? '已发布到 Mastodon' : ('失败：' . $r['detail']), 'platform_id' => (string)($r['data']['id'] ?? ''), 'url' => (string)($r['data']['url'] ?? pub_article_url($a))];
}
function pub_ad_wordpress(array $a, array $c): array {
    $url = rtrim((string)$c['site_url'], '/') . '/wp-json/wp/v2/posts';
    $r = pub_http_json($url, ['title' => (string)($a['title'] ?? ''), 'content' => (string)($a['content'] ?? ''), 'status' => 'draft'], ['Authorization: Basic ' . base64_encode($c['username'] . ':' . $c['app_password'])]);
    return ['ok' => $r['ok'], 'message' => $r['ok'] ? '已推送到 WordPress（草稿）' : ('失败：' . $r['detail']), 'platform_id' => (string)($r['data']['id'] ?? ''), 'url' => (string)($r['data']['link'] ?? pub_article_url($a))];
}

function pub_article_url(array $a): string {
    $base = function_exists('site_config_get') ? rtrim((string)site_config_get('site_url'), '/') : '';
    return $base . '/article/' . urlencode((string)($a['slug'] ?? ($a['id'] ?? '')));
}

/** 带超时的 JSON POST/GET */
function pub_http_json(string $url, array $payload, array $headers = [], string $method = 'POST'): array {
    $ch = curl_init($url);
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers)];
    if ($method === 'POST') { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE); }
    curl_setopt_array($ch, $opt);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    unset($ch);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'data' => json_decode((string)$resp, true) ?: [], 'detail' => $err ?: ('HTTP ' . $code)];
}
