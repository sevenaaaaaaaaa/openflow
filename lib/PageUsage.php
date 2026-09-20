<?php
declare(strict_types=1);
/**
 * 后台页面使用埋点（"他们到底在用哪些页"）
 *
 * 【为什么要有它】可用性诊断里 H2「多数人只用 5-10 个页面，其余从不打开」一直是**假设**：
 * 审计日志只记写操作（audit_write_verb() 对 GET 返回空），所以"谁打开过哪一页"从来没被记录过。
 * 没有这份数据，"裁剪导航"就只能靠猜——而按猜测重排导航，正是诊断里明确说不要做的事。
 *
 * 【设计取舍】
 * - **只按天聚合计数，不存单条访问**：一次请求只改一个很小的 JSON（页数量级 ~200 键），
 *   不会像 audit_log.json 那样每写一条就重写 5000 条记录。
 * - **只记页面 slug + 用户名 + 次数**：不记 IP、不记 URL 查询串、不记 referer。
 *   这是给站长看"哪些功能没人用"的运营数据，不是行为追踪。
 * - **绝不影响业务**：全程 try/catch，写不进去就当没埋点。
 *
 * 用法：
 *   page_usage_record();                 // 由 require_login() 自动调用
 *   page_usage_summary(30);              // 最近 30 天汇总
 *   page_usage_never_opened(30);         // 从没被打开过的页面（裁剪导航的依据）
 *   page_usage_prune();                  // 过期清理（cron 调用）
 */

require_once __DIR__ . '/AdminInventory.php';

function page_usage_dir(): string
{
    return (defined('DATA_DIR') ? DATA_DIR : dirname(__DIR__) . '/data') . '/page-usage';
}

/** @return array{enabled: bool, retain_days: int} */
function page_usage_config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $f = (defined('DATA_DIR') ? DATA_DIR : dirname(__DIR__) . '/data') . '/page-usage.json';
    $d = [];
    if (is_file($f)) {
        $raw = (string) @file_get_contents($f);
        $tmp = json_decode($raw, true);
        if (is_array($tmp)) $d = $tmp;
    }
    return $cfg = [
        'enabled' => !array_key_exists('enabled', $d) || (bool) $d['enabled'],   // 默认开
        'retain_days' => max(7, min(400, (int) ($d['retain_days'] ?? 90))),
    ];
}

function page_usage_config_set(array $in): array
{
    $cur = page_usage_config();
    $next = [
        'enabled' => array_key_exists('enabled', $in) ? !empty($in['enabled']) : $cur['enabled'],
        'retain_days' => max(7, min(400, (int) ($in['retain_days'] ?? $cur['retain_days']))),
    ];
    $base = defined('DATA_DIR') ? DATA_DIR : dirname(__DIR__) . '/data';
    if (!is_dir($base)) @mkdir($base, 0755, true);
    @file_put_contents($base . '/page-usage.json', (string) json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $next;
}

/**
 * 记一次页面访问。只在"后台真实页面的 GET 页面加载"上计数。
 * 同一次请求只记一次（子 tab include 会再次走 require_login()）。
 */
function page_usage_record(?string $uri = null, ?string $user = null, ?string $day = null): bool
{
    static $done = false;
    if ($done) return false;

    if (PHP_SAPI === 'cli' && $uri === null) return false;
    if (!page_usage_config()['enabled']) return false;
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') return false;
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') return false;
    foreach (['ajax', 'export', 'download', 'csv'] as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') return false;
    }
    if (($_GET['format'] ?? '') === 'json') return false;

    $slug = admin_page_slug_from_path($uri ?? (string) ($_SERVER['REQUEST_URI'] ?? ''));
    if ($slug === '') return false;

    $done = true;
    $user = $user ?? (string) ($_SESSION['admin_user'] ?? 'unknown');
    if ($user === '') $user = 'unknown';
    $user = mb_substr($user, 0, 64);

    return page_usage_bump($slug, $user, $day ?? date('Y-m-d'));
}

/** 原子自增（flock 串行化；失败静默——埋点不能反过来搞挂页面） */
function page_usage_bump(string $slug, string $user, string $day): bool
{
    try {
        $dir = page_usage_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return false;
        $file = $dir . '/' . $day . '.json';
        $fh = @fopen($file, 'c+');
        if ($fh === false) return false;
        try {
            if (!flock($fh, LOCK_EX)) return false;
            $raw = (string) stream_get_contents($fh);
            $d = $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($d)) $d = [];
            $pages = is_array($d['pages'] ?? null) ? $d['pages'] : [];
            $row = is_array($pages[$slug] ?? null) ? $pages[$slug] : ['pv' => 0, 'users' => []];
            $row['pv'] = (int) ($row['pv'] ?? 0) + 1;
            $users = is_array($row['users'] ?? null) ? $row['users'] : [];
            $users[$user] = (int) ($users[$user] ?? 0) + 1;
            $row['users'] = $users;
            $pages[$slug] = $row;
            $d['date'] = $day;
            $d['pages'] = $pages;
            $json = json_encode($d, JSON_UNESCAPED_UNICODE);
            if ($json === false) return false;              // 编码失败绝不落盘（否则清空整天数据）
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $json);
            fflush($fh);
            return true;
        } finally {
            @flock($fh, LOCK_UN);
            @fclose($fh);
        }
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 最近 N 天汇总。
 * @return array{days:int, from:string, to:string, days_with_data:int, total_pv:int,
 *               pages:array<string,array{pv:int,uv:int,last:string}>,
 *               daily:array<string,int>, users:array<string,int>}
 */
function page_usage_summary(int $days = 30): array
{
    $days = max(1, min(400, $days));
    $to = date('Y-m-d');
    $from = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
    $pages = [];
    $daily = [];
    $users = [];
    $withData = 0;
    $total = 0;

    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $daily[$day] = 0;
        $file = page_usage_dir() . '/' . $day . '.json';
        if (!is_file($file)) continue;
        $d = json_decode((string) @file_get_contents($file), true);
        if (!is_array($d) || !is_array($d['pages'] ?? null)) continue;
        $withData++;
        foreach ($d['pages'] as $slug => $row) {
            if (!is_string($slug) || !is_array($row)) continue;
            $pv = (int) ($row['pv'] ?? 0);
            $daily[$day] += $pv;
            $total += $pv;
            $cur = $pages[$slug] ?? ['pv' => 0, 'users' => [], 'last' => ''];
            $cur['pv'] += $pv;
            foreach ((array) ($row['users'] ?? []) as $u => $n) {
                $cur['users'][(string) $u] = ($cur['users'][(string) $u] ?? 0) + (int) $n;
                $users[(string) $u] = ($users[(string) $u] ?? 0) + (int) $n;
            }
            $cur['last'] = $day;
            $pages[$slug] = $cur;
        }
    }

    $out = [];
    foreach ($pages as $slug => $row) {
        $out[$slug] = ['pv' => (int) $row['pv'], 'uv' => count((array) $row['users']), 'last' => (string) $row['last']];
    }
    uasort($out, static fn(array $a, array $b): int => $b['pv'] <=> $a['pv']);
    arsort($users);

    return ['days' => $days, 'from' => $from, 'to' => $to, 'days_with_data' => $withData,
            'total_pv' => $total, 'pages' => $out, 'daily' => $daily, 'users' => $users];
}

/**
 * 从没被打开过的后台页（按 admin_page_inventory() 的真实页面清单比对）。
 * @return list<array{page:string,path:string}>
 */
function page_usage_never_opened(int $days = 30): array
{
    $seen = page_usage_summary($days)['pages'];
    $rows = [];
    foreach (admin_pages() as $slug => $path) {
        if (!isset($seen[$slug])) $rows[] = ['page' => $slug, 'path' => $path];
    }
    return $rows;
}

/** 删掉超出保留期的日文件；返回删除数量（cron 调用） */
function page_usage_prune(?int $retainDays = null): int
{
    $keep = $retainDays ?? page_usage_config()['retain_days'];
    $cut = date('Y-m-d', strtotime("-{$keep} days"));
    $n = 0;
    foreach (glob(page_usage_dir() . '/*.json') ?: [] as $f) {
        $day = basename($f, '.json');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 && $day < $cut) {
            if (@unlink($f)) $n++;
        }
    }
    return $n;
}
