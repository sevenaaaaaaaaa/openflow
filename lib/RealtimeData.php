<?php
/**
 * 实时数据采集层 RealtimeData
 * 提供：
 *  - 实时 SERP（搜索结果位置查询：Bing API / DuckDuckGo / 百度）
 *  - 实时 SEO 指标刷新（排名/收录/索引）
 *  - 实时舆情采集（多源搜索 + AI 摘要可选）
 *  - 本地实时数据（站点内事件/搜索量）
 * 带缓存（TTL）+ 多数据源回退
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/AiCenter.php';
require_once __DIR__ . '/MemberSystem.php';

class RealtimeData {
    private static string $cacheFile = DATA_DIR . '/realtime-cache.json';

    private static function cache(): array {
        return json_read(self::$cacheFile);
    }

    private static function saveCache(array $c): void {
        json_write(self::$cacheFile, $c);
    }

    private static function cacheGet(string $key): ?array {
        $c = self::cache();
        $entry = $c[$key] ?? null;
        if ($entry && ($entry['expires'] ?? 0) > time()) return $entry['data'];
        return null;
    }

    private static function cacheSet(string $key, $data, int $ttl = 300): void {
        $c = self::cache();
        $c[$key] = ['data' => $data, 'expires' => time() + $ttl];
        // 限制缓存大小
        if (count($c) > 200) $c = array_slice($c, -200);
        self::saveCache($c);
    }

    /**
     * 实时 SERP：查询关键词在搜索引擎的排名
     * @return array ['query'=>, 'engine'=>, 'results'=>[{title,url,snippet,position}], 'found'=>bool, 'rank'=>int, 'cached'=>bool]
     */
    public static function serp(string $query, string $engine = 'bing', int $ttl = 300): array {
        $cacheKey = 'serp_' . $engine . '_' . md5($query);
        $cached = self::cacheGet($cacheKey);
        if ($cached !== null) { $cached['cached'] = true; return $cached; }

        $results = [];
        if ($engine === 'bing') {
            $results = self::bingSearch($query, 20);
        } elseif ($engine === 'duckduckgo') {
            $results = self::ddgSearch($query, 20);
        } elseif ($engine === 'baidu') {
            $results = self::baiduSearch($query, 20);
        }

        $out = [
            'query' => $query,
            'engine' => $engine,
            'results' => $results,
            'found' => false,
            'rank' => 0,
            'cached' => false,
            'time' => date('Y-m-d H:i:s'),
        ];
        foreach ($results as $i => $r) {
            if (stripos($r['url'], self::siteHost()) !== false) {
                $out['found'] = true;
                $out['rank'] = $i + 1;
                break;
            }
        }
        if (!empty($results)) self::cacheSet($cacheKey, $out, $ttl);
        return $out;
    }

    private static function siteHost(): string {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!$host) {
            $url = parse_url(SITE_URL, PHP_URL_HOST);
            $host = $url ?: 'localhost';
        }
        return $host;
    }

    /**
     * 批量 SERP 监控（多关键词）
     */
    public static function serpBatch(array $queries, string $engine = 'bing', int $ttl = 300): array {
        $out = [];
        foreach ($queries as $q) $out[$q] = self::serp($q, $engine, $ttl);
        return $out;
    }

    /**
     * 实时舆情采集（多源）
     */
    public static function sentiment(string $topic, array $sources = ['bing', 'baidu'], int $ttl = 600): array {
        $cacheKey = 'sent_' . md5($topic . implode(',', $sources));
        $cached = self::cacheGet($cacheKey);
        if ($cached !== null) { $cached['cached'] = true; return $cached; }

        $results = [];
        foreach ($sources as $src) {
            if ($src === 'bing') $results = array_merge($results, self::bingSearch($topic, 15));
            elseif ($src === 'baidu') $results = array_merge($results, self::baiduSearch($topic, 15));
            elseif ($src === 'duckduckgo') $results = array_merge($results, self::ddgSearch($topic, 15));
        }
        // 去重
        $seen = [];
        $unique = [];
        foreach ($results as $r) {
            $k = md5($r['title'] ?? '');
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $unique[] = $r;
        }

        $out = [
            'topic' => $topic,
            'results' => $unique,
            'count' => count($unique),
            'cached' => false,
            'time' => date('Y-m-d H:i:s'),
        ];
        self::cacheSet($cacheKey, $out, $ttl);
        return $out;
    }

    /**
     * 舆情 AI 摘要（可选，需配置 AI）
     */
    public static function sentimentSummary(array $sentiment): array {
        if (empty($sentiment['results'])) return ['summary' => '暂无舆情数据', 'ai' => false];
        if (!AiCenter::isConfigured()) {
            $titles = array_slice(array_column($sentiment['results'], 'title'), 0, 8);
            return ['summary' => '采集到 ' . $sentiment['count'] . ' 条相关信息。主要提及：' . implode('；', array_slice($titles, 0, 3)), 'ai' => false];
        }
        $items = [];
        foreach (array_slice($sentiment['results'], 0, 15) as $r) {
            $items[] = "- {$r['title']} ({$r['url']})";
        }
        $user = "关于「{$sentiment['topic']}」的最新舆情信息：\n" . implode("\n", $items) . "\n\n请输出 JSON：{\"summary\":\"150字内总体概括\",\"tone\":\"正面|中性|负面\",\"hot_points\":[\"热点1\",\"热点2\"]}";
        $r = AiCenter::json('你是舆情分析师。基于给定信息输出简洁的舆情摘要。', $user, ['temperature' => 0.3]);
        if ($r['ok']) return ['summary' => $r['data']['summary'] ?? '', 'tone' => $r['data']['tone'] ?? '中性', 'hot_points' => $r['data']['hot_points'] ?? [], 'ai' => true];
        return ['summary' => 'AI 分析暂不可用', 'ai' => false];
    }

    /**
     * 本地实时数据（站点内实时指标）
     */
    public static function local(): array {
        $local = ['time' => date('Y-m-d H:i:s')];
        try {
            $local['events_24h'] = self::countEvents24h();
            $local['active_visitors_5min'] = self::activeVisitors(300);
            $local['new_members_24h'] = self::newMembers24h();
            $local['form_submissions_24h'] = self::submissions24h();
        } catch (Exception $e) {}
        return $local;
    }

    private static function countEvents24h(): int {
        $events = CdpSystem::allEvents();
        $cut = time() - 86400;
        $n = 0;
        foreach (array_slice($events, -500) as $e) {
            if (strtotime($e['timestamp'] ?? '') > $cut) $n++;
        }
        return $n;
    }

    private static function activeVisitors(int $window): int {
        $visitors = [];
        $events = CdpSystem::allEvents();
        $cut = time() - $window;
        foreach (array_slice($events, -500) as $e) {
            if (strtotime($e['timestamp'] ?? '') > $cut) $visitors[$e['visitor_id'] ?? ''] = true;
        }
        return count($visitors);
    }

    private static function newMembers24h(): int {
        try {
            $n = 0;
            $cut = time() - 86400;
            foreach (member_get_all() as $m) {
                if (!empty($m['created_at']) && strtotime($m['created_at']) > $cut) $n++;
            }
            return $n;
        } catch (Exception $e) { return 0; }
    }

    private static function submissions24h(): int {
        try {
            $subs = json_read(DATA_DIR . '/submissions/index.json');
            $cut = time() - 86400;
            $n = 0;
            foreach ($subs as $s) if (strtotime($s['created_at'] ?? '') > $cut) $n++;
            return $n;
        } catch (Exception $e) { return 0; }
    }

    // ─── 数据源实现 ───

    private static function bingSearch(string $query, int $count = 20): array {
        $key = self::settings()['bing_key'] ?? '';
        if (empty($key)) return [];
        $url = 'https://api.bing.microsoft.com/v7.0/search?q=' . urlencode($query) . '&count=' . $count . '&mkt=zh-CN';
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Ocp-Apim-Subscription-Key: '.$key], CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false]);
        $resp = json_decode(curl_exec($ch), true);
        $out = [];
        foreach (($resp['webPages']['value'] ?? []) as $r) {
            $out[] = ['title' => $r['name'] ?? '', 'url' => $r['url'] ?? '', 'snippet' => strip_tags($r['snippet'] ?? ''), 'date' => $r['dateLastCrawled'] ?? ''];
        }
        return $out;
    }

    private static function ddgSearch(string $query, int $count = 20): array {
        $url = 'https://html.duckduckgo.com/html/?q=' . urlencode($query);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; OpenFlow-Realtime)', CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false]);
        $html = curl_exec($ch);
        $out = [];
        if ($html && preg_match_all('/<a[^>]*class="result__a"[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/si', $html, $m)) {
            foreach ($m[1] as $i => $url) {
                if ($i >= $count) break;
                $out[] = ['title' => strip_tags($m[2][$i]), 'url' => html_entity_decode($url), 'snippet' => '', 'date' => ''];
            }
        }
        return $out;
    }

    private static function baiduSearch(string $query, int $count = 20): array {
        $url = 'https://www.baidu.com/s?wd=' . urlencode($query) . '&rn=' . $count;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; OpenFlow-Realtime)', CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false]);
        $html = curl_exec($ch);
        $out = [];
        if ($html && preg_match_all('/<h3[^>]*>.*?<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>.*?<\/h3>/si', $html, $m)) {
            foreach ($m[1] as $i => $url) {
                if ($i >= $count) break;
                $out[] = ['title' => strip_tags($m[2][$i]), 'url' => html_entity_decode($url), 'snippet' => '', 'date' => ''];
            }
        }
        return $out;
    }

    public static function settings(): array {
        $s = json_read(DATA_DIR . '/realtime.json');
        return $s ?: ['bing_key' => ''];
    }

    public static function saveSettings(array $data): bool {
        return json_write(DATA_DIR . '/realtime.json', $data);
    }

    public static function clearCache(): void {
        json_write(self::$cacheFile, []);
    }
}
