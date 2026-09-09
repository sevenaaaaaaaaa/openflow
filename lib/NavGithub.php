<?php
/**
 * NavGithub — 导航站条目的 GitHub 仓库元数据
 *
 * 对 url 指向 github.com/{owner}/{repo} 的导航站点，拉取 stars / forks / 语言 /
 * topics / 简介 / 最近推送时间，缓存到 data/nav-github.json（默认 24h TTL），
 * 详情页与对比页直接读缓存；未命中时同步拉一次（4s 超时），失败做负缓存。
 *
 *   NavGithub::repoFromUrl($url)        → 'owner/repo' | null
 *   NavGithub::repoMeta('owner/repo')   → ['stars'=>…,'language'=>…,…] | null
 *   NavGithub::syncAll()                → 后台/定时任务批量刷新，返回统计
 */
class NavGithub {

    const TTL = 86400;          // 正常缓存 24h
    const ERR_TTL = 3600;       // 失败负缓存 1h
    const API = 'https://api.github.com/repos/';

    /** 从站点 URL 解析 owner/repo（只认仓库主页，不认子路径/组织页） */
    public static function repoFromUrl(string $url): ?string {
        if (!preg_match('#^https?://(?:www\.)?github\.com/([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+)/?$#', trim($url), $m)) return null;
        $repo = rtrim($m[1], '/');
        // 去掉 .git 后缀
        return preg_replace('/\.git$/i', '', $repo);
    }

    private static function cacheFile(): string {
        return DATA_DIR . '/nav-github.json';
    }

    private static function cacheRead(): array {
        $f = self::cacheFile();
        return is_file($f) ? (json_read($f) ?: []) : [];
    }

    private static function cacheWrite(array $cache): void {
        json_write(self::cacheFile(), $cache);
    }

    /**
     * 读仓库元数据。命中缓存直接返回；未命中/过期则同步拉取。
     * 返回字段：stars forks watchers language topics description homepage open_issues
     *          license pushed_at repo_created_at fetched_at
     */
    public static function repoMeta(string $repo, bool $refresh = false): ?array {
        $cache = self::cacheRead();
        $hit = $cache[$repo] ?? null;
        $now = time();
        if (!$refresh && $hit) {
            $age = $now - (int)($hit['fetched_at'] ?? 0);
            $ttl = isset($hit['error']) ? self::ERR_TTL : self::TTL;
            if ($age < $ttl) return isset($hit['error']) ? null : $hit;
        }
        $meta = self::fetch($repo);
        if ($meta === null) {
            // 负缓存：保留旧数据（如有），只记失败时间
            if ($hit && !isset($hit['error'])) {
                $cache[$repo]['fetched_at'] = $now; // 旧数据续命，避免每次访问都回源
                self::cacheWrite($cache);
                return $hit;
            }
            $cache[$repo] = ['error' => true, 'fetched_at' => $now];
            self::cacheWrite($cache);
            return null;
        }
        $cache[$repo] = $meta;
        self::cacheWrite($cache);
        return $meta;
    }

    private static function fetch(string $repo): ?array {
        $ch = curl_init(self::API . $repo);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'OpenFlow-Nav',
            CURLOPT_TIMEOUT => 4,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$resp) return null;
        $d = json_decode($resp, true);
        if (!is_array($d) || isset($d['message'])) return null;
        return [
            'stars'            => (int)($d['stargazers_count'] ?? 0),
            'forks'            => (int)($d['forks_count'] ?? 0),
            'watchers'         => (int)($d['subscribers_count'] ?? 0),
            'open_issues'      => (int)($d['open_issues_count'] ?? 0),
            'language'         => (string)($d['language'] ?? ''),
            'topics'           => array_values(array_slice((array)($d['topics'] ?? []), 0, 8)),
            'description'      => (string)($d['description'] ?? ''),
            'homepage'         => (string)($d['homepage'] ?? ''),
            'license'          => (string)($d['license']['spdx_id'] ?? ''),
            'pushed_at'        => (string)($d['pushed_at'] ?? ''),
            'repo_created_at'  => (string)($d['created_at'] ?? ''),
            'fetched_at'       => time(),
        ];
    }

    /** 批量刷新导航站里所有 GitHub 仓库（后台手动触发 / cron） */
    public static function syncAll(bool $force = false): array {
        $nav = json_read(DATA_DIR . '/navigation.json');
        $repos = [];
        foreach ($nav['sites'] ?? [] as $s) {
            $r = self::repoFromUrl($s['url'] ?? '');
            if ($r) $repos[$r] = true;
        }
        $ok = 0; $fail = 0; $skip = 0;
        foreach (array_keys($repos) as $repo) {
            $cache = self::cacheRead();
            $hit = $cache[$repo] ?? null;
            if (!$force && $hit && !isset($hit['error']) && (time() - (int)$hit['fetched_at'] < self::TTL)) { $skip++; continue; }
            $meta = self::repoMeta($repo, true);
            $meta ? $ok++ : $fail++;
            usleep(300000); // 0.3s 间隔，对未认证配额友好
        }
        return ['total' => count($repos), 'fetched' => $ok, 'failed' => $fail, 'skipped' => $skip];
    }

    /** 紧凑数字：12.3k / 1.2w */
    public static function compactNum(int $n): string {
        if ($n >= 10000) return round($n / 10000, 1) . 'w';
        if ($n >= 1000) return round($n / 1000, 1) . 'k';
        return (string)$n;
    }

    /** 「3 天前」式相对时间 */
    public static function relativeTime(string $iso): string {
        $t = strtotime($iso);
        if (!$t) return '';
        $diff = time() - $t;
        if ($diff < 3600) return max(1, (int)($diff / 60)) . ' 分钟前';
        if ($diff < 86400) return (int)($diff / 3600) . ' 小时前';
        if ($diff < 2592000) return (int)($diff / 86400) . ' 天前';
        if ($diff < 31536000) return (int)($diff / 2592000) . ' 个月前';
        return (int)($diff / 31536000) . ' 年前';
    }
}
