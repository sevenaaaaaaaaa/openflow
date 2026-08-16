<?php
/**
 * IndexNow: notify search engines when content changes
 */
function indexnow_config(): array {
    return json_read(DATA_DIR . '/indexnow.json');
}

function indexnow_save_config(array $data): bool {
    return json_write(DATA_DIR . '/indexnow.json', $data);
}

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

    return $http >= 200 && $http < 300;
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
