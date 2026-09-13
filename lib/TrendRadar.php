<?php
/**
 * TrendRadar — 社媒/多源热点雷达
 *
 * 多源发现（Hacker News / GitHub / RSS）→ 去重 → AI 聚类成"热点话题"并给热度 →
 * 一键转成 GEO 选题，接进内容链路（GEO → 创作台 → 自动装配）。
 *
 * 只依赖免鉴权公开源（Algolia HN / GitHub search / RSS），不需要外部凭据。
 * AI 未配置时退化为"按来源取热度最高的条目"，Loop 不断。
 */

function trend_dir(): string { return DATA_DIR . '/trends'; }
function trend_radar_file(): string { return trend_dir() . '/radar.json'; }
function trend_seen_file(): string { return trend_dir() . '/seen.json'; }
function trend_settings_file(): string { return trend_dir() . '/settings.json'; }

function trend_settings(): array {
    $d = json_read(trend_settings_file());
    return array_merge([
        'keywords' => ['AI', 'Agent', 'growth', 'SEO', 'marketing', 'automation', 'creator', 'open source'],
        'min_points' => 30,
        'max_age_days' => 7,
        'rss' => [],
        'enabled' => true,
    ], is_array($d) ? $d : []);
}
function trend_save_settings(array $s): void {
    // 关键词/来源允许传字符串（逗号或换行分隔）或数组
    $split = function ($v): array {
        if (is_string($v)) $v = preg_split('/[,，\n]/u', $v);
        return array_values(array_filter(array_map('trim', (array)$v), fn($x) => $x !== ''));
    };
    $s['keywords'] = $split($s['keywords'] ?? []);
    $s['rss'] = $split($s['rss'] ?? []);
    $s['min_points'] = max(0, (int)($s['min_points'] ?? 30));
    $s['max_age_days'] = max(1, min(30, (int)($s['max_age_days'] ?? 7)));
    json_write(trend_settings_file(), $s);
}

function trend_seen(): array { $d = json_read(trend_seen_file()); return is_array($d) ? $d : []; }
function trend_seen_key(string $url): string { return substr(md5(mb_strtolower(preg_replace('#\?.*$#', '', $url))), 0, 16); }
function trend_mark_seen(array $items): void {
    $seen = trend_seen();
    foreach ($items as $it) if (!empty($it['url'])) $seen[trend_seen_key($it['url'])] = date('Y-m-d');
    if (count($seen) > 5000) $seen = array_slice($seen, -5000, null, true);
    json_write(trend_seen_file(), $seen);
}

/** Hacker News（Algolia，免鉴权） */
function trend_fetch_hn(array $keywords, int $minPoints, int $days): array {
    $out = [];
    $since = time() - $days * 86400;
    foreach (array_slice($keywords, 0, 6) as $kw) {
        $url = 'https://hn.algolia.com/api/v1/search?query=' . urlencode($kw)
             . '&tags=story&hitsPerPage=15&numericFilters=' . urlencode("created_at_i>{$since},points>{$minPoints}");
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_USERAGENT => 'OpenFlow-TrendRadar']);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if ($code !== 200 || !$resp) continue;
        foreach ((array)(json_decode($resp, true)['hits'] ?? []) as $h) {
            $title = (string)($h['title'] ?? '');
            if ($title === '') continue;
            $out[] = ['title' => $title, 'url' => (string)($h['url'] ?? ('https://news.ycombinator.com/item?id=' . ($h['objectID'] ?? ''))), 'source' => 'HackerNews', 'points' => (int)($h['points'] ?? 0), 'comments' => (int)($h['num_comments'] ?? 0), 'published' => date('Y-m-d', (int)($h['created_at_i'] ?? time()))];
        }
    }
    return $out;
}

/** GitHub 新晋高星仓库 */
function trend_fetch_github(array $keywords, int $days): array {
    $out = [];
    $since = date('Y-m-d', time() - $days * 86400);
    foreach (array_slice($keywords, 0, 4) as $kw) {
        $q = urlencode($kw . ' created:>' . $since . ' stars:>50');
        $url = "https://api.github.com/search/repositories?q={$q}&sort=stars&order=desc&per_page=8";
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_USERAGENT => 'OpenFlow-TrendRadar', CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json']]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if ($code !== 200 || !$resp) continue;
        foreach ((array)(json_decode($resp, true)['items'] ?? []) as $r) {
            $out[] = ['title' => (string)($r['full_name'] ?? '') . '：' . mb_substr((string)($r['description'] ?? ''), 0, 80), 'url' => (string)($r['html_url'] ?? ''), 'source' => 'GitHub', 'points' => (int)($r['stargazers_count'] ?? 0), 'comments' => 0, 'published' => substr((string)($r['created_at'] ?? ''), 0, 10)];
        }
    }
    return $out;
}

/** RSS（复用 GEO 的解析） */
function trend_fetch_rss(array $urls): array {
    if (!function_exists('geo_fetch_rss')) require_once __DIR__ . '/GeoSystem.php';
    $out = [];
    foreach ($urls as $u) {
        foreach (geo_fetch_rss($u, 15) as $it) {
            $out[] = ['title' => (string)($it['title'] ?? ''), 'url' => (string)($it['link'] ?? $it['url'] ?? ''), 'source' => 'RSS', 'points' => 0, 'comments' => 0, 'published' => (string)($it['date'] ?? date('Y-m-d'))];
        }
    }
    return $out;
}

/** 聚合全部来源 + 去重（含历史 seen） */
function trend_fetch_all(): array {
    $s = trend_settings();
    $items = array_merge(
        trend_fetch_hn($s['keywords'], (int)$s['min_points'], (int)$s['max_age_days']),
        trend_fetch_github($s['keywords'], (int)$s['max_age_days']),
        trend_fetch_rss((array)$s['rss'])
    );
    $seen = trend_seen();
    $uniq = [];
    foreach ($items as $it) {
        if (empty($it['url']) || empty($it['title'])) continue;
        $k = trend_seen_key($it['url']);
        if (isset($seen[$k]) || isset($uniq[$k])) continue;
        $uniq[$k] = $it;
    }
    $out = array_values($uniq);
    usort($out, fn($a, $b) => (int)($b['points'] ?? 0) <=> (int)($a['points'] ?? 0));
    return $out;
}

/** AI 聚类成热点话题；AI 不可用则按来源取 Top */
function trend_cluster(array $items): array {
    if (!$items) return [];
    if (class_exists('AiCenter')) {
        $r = trend_cluster_ai($items);
        if ($r) return $r;
    }
    // 兜底：每个来源取热度最高的若干条，各自成"话题"
    $bySource = [];
    foreach ($items as $it) $bySource[$it['source']][] = $it;
    $clusters = [];
    foreach ($bySource as $src => $list) {
        foreach (array_slice($list, 0, 4) as $it) {
            $clusters[] = ['title' => mb_substr($it['title'], 0, 50), 'keywords' => [], 'angle' => '', 'why' => $src . ' 热度 ' . (int)$it['points'], 'item_urls' => [$it['url']], 'heat' => min(100, (int)$it['points'] / 5)];
        }
    }
    return $clusters;
}

function trend_cluster_ai(array $items): ?array {
    if (!class_exists('AiCenter')) require_once __DIR__ . '/AiCenter.php';
    if (!AiCenter::isConfigured()) return null;
    $compact = [];
    foreach (array_slice($items, 0, 60) as $it) $compact[] = ['title' => mb_substr($it['title'], 0, 120), 'source' => $it['source'], 'points' => (int)$it['points'], 'url' => $it['url']];
    $system = "你是内容趋势分析师。下面是从多源抓取的热点条目(JSON)。把**同一事件/话题**的条目聚在一起，输出 3-8 个热点话题。\n"
        . "严格输出 JSON：{\"clusters\":[{\"title\":\"话题(≤30字)\",\"keywords\":[\"关键词\"],\"angle\":\"内容切入角度(≤40字)\",\"why\":\"为什么现在值得做(≤60字)\",\"item_urls\":[\"只能引用给定的 url\"]}]}\n"
        . "规则：只引用给定 url；合并同义/相邻话题；热度高的多留。";
    try {
        $r = AiCenter::json($system, json_encode($compact, JSON_UNESCAPED_UNICODE), ['max_tokens' => 2200, 'feature' => 'trend_cluster', 'tier' => 'admin']);
    } catch (\Throwable $e) { return null; }
    if (empty($r['ok']) || empty($r['data']['clusters'])) return null;
    $valid = array_flip(array_map(fn($i) => (string)$i['url'], $items));
    $heatByUrl = []; foreach ($items as $i) $heatByUrl[(string)$i['url']] = (int)$i['points'];
    $out = [];
    foreach ((array)$r['data']['clusters'] as $c) {
        $urls = array_values(array_filter((array)($c['item_urls'] ?? []), fn($u) => isset($valid[(string)$u])));
        if (empty($urls)) continue;
        $maxP = 0; $srcs = []; foreach ($urls as $u) { $maxP = max($maxP, $heatByUrl[$u] ?? 0); }
        foreach ($items as $i) if (in_array($i['url'], $urls, true)) $srcs[$i['source']] = true;
        $out[] = ['title' => mb_substr(trim((string)($c['title'] ?? '')), 0, 50), 'keywords' => array_slice(array_map('strval', (array)($c['keywords'] ?? [])), 0, 5), 'angle' => mb_substr((string)($c['angle'] ?? ''), 0, 60), 'why' => mb_substr((string)($c['why'] ?? ''), 0, 120), 'item_urls' => $urls, 'heat' => min(100, (int)round(count($urls) * 12 + count($srcs) * 8 + $maxP / 8))];
    }
    if (!$out) return null;
    usort($out, fn($a, $b) => $b['heat'] <=> $a['heat']);
    return $out;
}

/** 跑一次雷达：抓取 → 聚类 → 存盘 + 标记已见 */
function trend_run(bool $force = false): array {
    $s = trend_settings();
    if (empty($s['enabled'])) return ['ok' => false, 'error' => '雷达未启用'];
    $items = trend_fetch_all();
    if (!$items) return ['ok' => false, 'error' => '未抓取到新条目（可能都已见过）'];
    $clusters = trend_cluster($items);
    $radar = ['generated_at' => date('Y-m-d H:i:s'), 'item_count' => count($items), 'clusters' => $clusters, 'items' => array_slice($items, 0, 80)];
    json_write(trend_radar_file(), $radar);
    trend_mark_seen($items);
    return ['ok' => true, 'clusters' => count($clusters), 'items' => count($items), 'generated_at' => $radar['generated_at']];
}

function trend_radar(): array { $d = json_read(trend_radar_file()); return array_merge(['generated_at' => '', 'clusters' => [], 'items' => [], 'item_count' => 0], is_array($d) ? $d : []); }

/** 把热点话题转成 GEO 选题（进入内容链路） */
function trend_promote(string $title, string $angle = '', string $why = ''): array {
    if (trim($title) === '') return ['ok' => false, 'error' => '标题为空'];
    if (!function_exists('geo_add_topic')) require_once __DIR__ . '/GeoSystem.php';
    geo_add_topic(['topic' => mb_substr($title, 0, 80), 'angle' => $angle, 'why' => $why, 'source' => 'radar']);
    return ['ok' => true];
}
