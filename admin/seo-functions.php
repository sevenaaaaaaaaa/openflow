<?php
/**
 * IndexNow: notify search engines when content changes
 *
 * 零配置设计：首次调用自动生成 32 位 key 并落盘，key 验证文件
 * 由 .htaccess 路由到 api/indexnow-key.php 动态输出，部署不丢。
 */
function indexnow_config(): array {
    $cfg = json_read(DATA_DIR . '/indexnow.json');
    if (empty($cfg['key'])) {
        $cfg['key'] = bin2hex(random_bytes(16));
        $cfg['created_at'] = date('Y-m-d H:i:s');
    }
    if (empty($cfg['host'])) {
        $siteUrl = function_exists('site_config_get') ? site_config_get('site_url', '') : '';
        $cfg['host'] = $siteUrl ? (parse_url($siteUrl, PHP_URL_HOST) ?: '') : ($_SERVER['HTTP_HOST'] ?? '');
    }
    if (!empty($cfg['key']) && !empty($cfg['host'])) {
        // 幂等落盘（每次读都写代价可忽略，保证自动生成后持久化）
        static $saved = false;
        if (!$saved) { json_write(DATA_DIR . '/indexnow.json', $cfg); $saved = true; }
    }
    return $cfg;
}

function indexnow_save_config(array $data): bool {
    return json_write(DATA_DIR . '/indexnow.json', $data);
}

// ─── 收录推送日志（最近 50 条，后台「快速收录」卡展示）───
function index_log(string $engine, string $url, bool $ok, string $note = ''): void {
    $file = DATA_DIR . '/index-log.json';
    $log = json_read($file);
    if (!is_array($log)) $log = [];
    $log[] = ['engine' => $engine, 'url' => $url, 'ok' => $ok, 'note' => $note, 'at' => date('Y-m-d H:i:s')];
    json_write($file, array_slice($log, -50));
}
function index_log_get(): array { return array_reverse(json_read(DATA_DIR . '/index-log.json') ?: []); }

function indexnow_ping(string $url): bool {
    $cfg = indexnow_config();
    $key = $cfg['key'] ?? '';
    $host = $cfg['host'] ?? ($_SERVER['HTTP_HOST'] ?? '');
    if (empty($key) || empty($host) || empty($url)) return false;

    $apiUrl = "https://api.indexnow.org/indexnow";
    $payload = json_encode([
        'host' => $host,
        'key' => $key,
        'keyLocation' => "https://{$host}/{$key}.txt",
        'urlList' => [$url],
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $ok = $http >= 200 && $http < 300;
    index_log('IndexNow', $url, $ok, 'HTTP ' . $http);
    return $ok;
}

// ─── 百度站长主动推送（实时接口，token 在 SEO 中心 → 站长工具里配）───
function baidu_push_urls(array $urls): array {
    if (!$urls) return ['ok' => false, 'note' => '空 URL 列表'];
    require_once __DIR__ . '/../lib/SeoConsole.php';
    $s = seo_console_settings();
    $site = trim($s['baidu_site'] ?? '');
    $token = trim($s['baidu_token'] ?? '');
    if ($site === '' || $token === '') return ['ok' => false, 'note' => '未配置百度站点/Token'];
    $api = 'http://data.zz.baidu.com/urls?site=' . urlencode($site) . '&token=' . urlencode($token);
    $ch = curl_init($api);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => implode("\n", $urls),
        CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = json_decode((string)curl_exec($ch), true);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // 成功返回 {"success":N,"remain":M}
    $ok = $http === 200 && isset($resp['success']);
    $note = $ok ? "成功 {$resp['success']} 条，今日余量 " . ($resp['remain'] ?? '?') : ('HTTP ' . $http . ' ' . ($resp['message'] ?? ''));
    foreach ($urls as $u) index_log('百度', $u, $ok, $note);
    return ['ok' => $ok, 'note' => $note];
}

// ─── 统一收录推送：IndexNow（Bing/Yandex/Google 逐步接入）+ 百度 ───
function seo_submit_url(string $url): array {
    $r = ['indexnow' => false, 'baidu' => null];
    $r['indexnow'] = indexnow_ping($url);
    $b = baidu_push_urls([$url]);
    $r['baidu'] = $b['ok'];
    $r['baidu_note'] = $b['note'];
    return $r;
}

// ─── 301 Redirects ──────────────────────────────
function get_redirects(): array {
    return json_read(DATA_DIR . '/redirects.json');
}

function save_redirects(array $data): bool {
    return json_write(DATA_DIR . '/redirects.json', $data);
}

function add_redirect(string $from, string $to): bool {
    $r = get_redirects();
    $r[] = ['from' => trim($from, '/'), 'to' => $to, 'created' => date('Y-m-d H:i:s')];
    return save_redirects($r);
}

function remove_redirect(string $from): bool {
    $r = get_redirects();
    $r = array_values(array_filter($r, fn($v) => trim($v['from'], '/') !== trim($from, '/')));
    return save_redirects($r);
}

// ─── Structured Data ────────────────────────────
function get_structured_data(string $type, string $id): array {
    $file = DATA_DIR . '/structured/' . $type . '/' . $id . '.json';
    return json_read($file);
}

function save_structured_data(string $type, string $id, array $data): bool {
    $dir = DATA_DIR . '/structured/' . $type;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return json_write($dir . '/' . $id . '.json', $data);
}

// ─── Topics (专题) ──────────────────────────────
function get_topics(): array {
    return json_read(DATA_DIR . '/topics.json');
}

function save_topics(array $data): bool {
    return json_write(DATA_DIR . '/topics.json', $data);
}

// ─── Landing Pages (聚合页) ─────────────────────
function get_landing_pages(): array {
    return json_read(DATA_DIR . '/landing-pages.json');
}

function save_landing_pages(array $data): bool {
    return json_write(DATA_DIR . '/landing-pages.json', $data);
}

// ─── Internal Link Suggestions ───────────────────
function scan_internal_links(string $content, string $excludeId = ''): array {
    $articles = get_articles();
    $suggestions = [];
    $contentLower = mb_strtolower($content);

    foreach ($articles as $a) {
        if ($a['id'] === $excludeId || ($a['status'] ?? 'draft') !== 'published') continue;
        $title = $a['title'] ?? '';
        if (empty($title)) continue;

        // Check if title appears in content
        $titleLower = mb_strtolower($title);
        $pos = mb_strpos($contentLower, $titleLower);
        if ($pos !== false) {
            $slug = $a['slug'] ?? '';
            $suggestions[] = [
                'title' => $title,
                'slug' => $slug,
                'url' => '/article/' . $slug,
                'position' => $pos,
                'matched_text' => mb_substr($title, 0, 40),
            ];
        }

        // Also check article tags
        foreach ($a['tags'] ?? [] as $tag) {
            $tagLower = mb_strtolower($tag);
            if (mb_strlen($tagLower) >= 2 && mb_strpos($contentLower, $tagLower) !== false) {
                // Avoid duplicate suggestions for same URL
                $exists = false;
                foreach ($suggestions as $s) {
                    if (($s['slug'] ?? '') === $slug) { $exists = true; break; }
                }
                if (!$exists) {
                    $suggestions[] = [
                        'title' => $title,
                        'slug' => $slug,
                        'url' => '/article/' . $slug,
                        'matched_text' => $tag,
                        'from_tag' => true,
                    ];
                }
            }
        }
    }

    // Sort by position (earliest match first)
    usort($suggestions, fn($a, $b) => ($a['position'] ?? 9999) - ($b['position'] ?? 9999));
    return array_slice($suggestions, 0, 15);
}
