<?php
/**
 * D1：CDP 事件表查询实测（先量后治）
 *
 *   php tests/events_index_bench.php [行数]
 *
 * 任务清单里 D1 写的是「加 Redis 分层缓存」，但这个前提在当前代码里
 * 已经不成立：CdpSystem::allEvents() 早就走 SQLite + LIMIT + 进程内
 * 缓存，不是把整表读进内存。真正的问题在 schema：events 是全库最大的
 * 表（生产约 52 万行），却只有一条 message_id 的部分唯一索引，
 * 而 coupons、addresses 这种几百行的小表都建了索引。
 *
 * 所以先量，不猜。本脚本建一张同构的临时表，灌进同量级数据，
 * 跑真实存在于代码里的查询形状，打印加索引前后的耗时与执行计划。
 *
 * 这个脚本是「证据」，不进 qa_full 的必跑集（灌 50 万行要几十秒）。
 */

$rows = (int)($argv[1] ?? 520000);
$dbFile = sys_get_temp_dir() . '/of-events-bench-' . getmypid() . '.sqlite';
@unlink($dbFile);

$db = new PDO('sqlite:' . $dbFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "OpenFlow · CDP 事件表查询实测\n" . str_repeat('=', 62) . "\n";
echo "样本行数：" . number_format($rows) . "\n";

// ── 与 lib/Database.php 完全一致的 schema ──
$db->exec("CREATE TABLE events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event TEXT, label TEXT, variant TEXT,
    page TEXT, uid TEXT,
    member_id TEXT, member_email TEXT, props TEXT,
    ip TEXT, created_at TEXT,
    session_id TEXT DEFAULT '', message_id TEXT DEFAULT '',
    ts INTEGER DEFAULT 0, event_category TEXT DEFAULT ''
)");
$db->exec("CREATE UNIQUE INDEX idx_events_message ON events(message_id) WHERE message_id != ''");

// ── 灌数据：事件名分布向 page_view 倾斜，贴近真实埋点 ──
$t0 = microtime(true);
$events = array_merge(array_fill(0, 70, 'page_view'), array_fill(0, 10, 'element_click'),
                      array_fill(0, 6, 'utm_landing'), array_fill(0, 5, 'scroll'),
                      array_fill(0, 4, 'form_submit'), array_fill(0, 3, 'purchase'),
                      array_fill(0, 2, 'register'));
$pages = ['/', '/about', '/capability', '/courses', '/article/a', '/article/b', '/pricing', '/contact'];

$db->exec('BEGIN');
$ins = $db->prepare("INSERT INTO events
    (event,label,page,uid,member_id,props,ip,created_at,session_id,message_id,ts,event_category)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
$base = strtotime('-365 days');
for ($i = 0; $i < $rows; $i++) {
    $ev  = $events[$i % count($events)];
    $uid = 'v_' . str_pad((string)($i % 45000), 6, '0', STR_PAD_LEFT);   // 约 4.5 万访客
    $ts  = $base + (int)($i / $rows * 365 * 86400) + ($i % 86400);
    $ins->execute([
        $ev, 'L' . ($i % 40), $pages[$i % count($pages)], $uid,
        ($i % 9 === 0) ? ('m_' . ($i % 5000)) : '',
        '{"referrer":"https://x.test/","utm_source":"s' . ($i % 12) . '"}',
        '10.0.' . ($i % 255) . '.1', date('Y-m-d H:i:s', $ts),
        'sess_' . ($i % 120000), '', $ts, ($ev === 'purchase' ? 'conversion' : 'behavior'),
    ]);
    if ($i % 50000 === 0 && $i > 0) { $db->exec('COMMIT'); $db->exec('BEGIN'); }
}
$db->exec('COMMIT');
printf("灌数据耗时：%.1fs\n\n", microtime(true) - $t0);

// ── 代码里真实存在的查询形状 ──
$d30 = date('Y-m-d', strtotime('-30 days'));
$d7  = date('Y-m-d', strtotime('-7 days'));
$queries = [
    '工作台 · 30 天 UV/PV（DashboardSystem:14）' =>
        ["SELECT COUNT(DISTINCT uid) uv, COUNT(*) pv FROM events WHERE event='page_view' AND created_at >= ?", [$d30]],
    '工作台 · DAU（DashboardSystem:234）' =>
        ["SELECT COUNT(DISTINCT uid) c FROM events WHERE created_at >= ?", [date('Y-m-d')]],
    '工作台 · MAU（DashboardSystem:238）' =>
        ["SELECT COUNT(DISTINCT uid) c FROM events WHERE created_at >= ?", [$d30]],
    '工作台 · 热门页面（DashboardSystem:259）' =>
        ["SELECT page, COUNT(*) c FROM events WHERE event='page_view' GROUP BY page ORDER BY c DESC LIMIT 10", []],
    '工作台 · 分时段（DashboardSystem:241）' =>
        ["SELECT substr(created_at,12,2) h, COUNT(*) c FROM events WHERE event='page_view' AND created_at >= ? GROUP BY h", [$d7]],
    '归因 · UTM 落地页（attribution.php:19）' =>
        ["SELECT label, COUNT(*) v FROM events WHERE event='utm_landing' GROUP BY label ORDER BY v DESC LIMIT 20", []],
    '热力图 · 点击事件（heatmap.php:17）' =>
        ["SELECT props, page, created_at FROM events WHERE event='element_click' AND created_at >= ? ORDER BY id DESC LIMIT 500", [$d7]],
    '画像 · 单访客事件流（ProfilingSystem:239）' =>
        ["SELECT event, label, page, props, created_at FROM events WHERE uid IN (?,?,?) ORDER BY id DESC LIMIT 200",
         ['v_000001', 'v_000002', 'v_000003']],
    '流程 · 事件名分布（flow.php:15）' =>
        ["SELECT event, COUNT(*) c FROM events GROUP BY event ORDER BY c DESC", []],
    '流程 · 已识别用户数（flow.php:20）' =>
        ["SELECT COUNT(*) c FROM events WHERE member_id != ''", []],
    'CDP · 最近事件分页（CdpSystem:124）' =>
        ["SELECT id, event, uid, props, created_at FROM events ORDER BY id DESC LIMIT 10000", []],
    '留存清理 · 过期删除（StorageSystem:109）' =>
        ["SELECT COUNT(*) c FROM events WHERE created_at < ?", [date('Y-m-d', strtotime('-180 days'))]],
];

function bench(PDO $db, string $sql, array $args, int $runs = 3): array {
    $best = INF;
    for ($i = 0; $i < $runs; $i++) {
        $t = microtime(true);
        $st = $db->prepare($sql); $st->execute($args); $st->fetchAll();
        $best = min($best, (microtime(true) - $t) * 1000);
    }
    $plan = [];
    foreach ($db->query('EXPLAIN QUERY PLAN ' . $sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $plan[] = $r['detail'] ?? '';
    }
    return ['ms' => $best, 'plan' => implode(' / ', $plan)];
}

echo "【加索引前】\n";
$before = [];
foreach ($queries as $name => [$sql, $args]) {
    $before[$name] = bench($db, $sql, $args);
    $scan = strpos($before[$name]['plan'], 'SCAN') !== false ? ' 全表扫描' : '';
    printf("  %-42s %8.1f ms%s\n", $name, $before[$name]['ms'], $scan);
}
clearstatcache(true, $dbFile);
$sizeBefore = filesize($dbFile);

// ── 按查询形状补索引 ──
//   1. (event, created_at)：绝大多数查询的谓词，最左前缀还能单独服务 event=?
//   2. (created_at)：DAU/WAU/MAU 与留存清理只按时间过滤
//   3. (uid, id)：画像按访客取事件流，且要按 id 倒序
echo "\n【建索引】\n";
$indexes = [
    'idx_events_event_created' => 'events(event, created_at)',
    'idx_events_created'       => 'events(created_at)',
    'idx_events_uid'           => 'events(uid, id)',
    // 热门页面是 event=? GROUP BY page，(event, created_at) 帮不上 GROUP BY，
    // 这条让分组直接走索引顺序，不用建临时 B 树
    'idx_events_event_page'    => 'events(event, page)',
    // member_id != '' 是不等值，普通索引没用；部分索引只收非空行，
    // 生产里约 1/9 的事件带 member_id，索引只有整表的 1/9 大
    'idx_events_member'        => "events(member_id) WHERE member_id != ''",
];
$t0 = microtime(true);
foreach ($indexes as $name => $def) {
    $t = microtime(true);
    $db->exec("CREATE INDEX IF NOT EXISTS {$name} ON {$def}");
    printf("  %-28s %s  (%.1fs)\n", $name, $def, microtime(true) - $t);
}
$db->exec('ANALYZE');
printf("  合计建索引耗时：%.1fs\n", microtime(true) - $t0);

echo "\n【加索引后】\n";
$worse = []; $win = 0;
foreach ($queries as $name => [$sql, $args]) {
    $after = bench($db, $sql, $args);
    $b = $before[$name]['ms'];
    $ratio = $after['ms'] > 0 ? $b / $after['ms'] : 0;
    $mark = $ratio >= 1.5 ? sprintf('快 %.1f×', $ratio)
          : ($after['ms'] > $b * 1.2 ? sprintf('慢 %.1f×', $after['ms'] / $b) : '持平');
    if ($ratio >= 1.5) $win++;
    if ($after['ms'] > $b * 1.2) $worse[] = $name;
    printf("  %-42s %8.1f ms  ← %8.1f ms  %s\n", $name, $after['ms'], $b, $mark);
    if (strpos($after['plan'], 'SCAN') !== false && strpos($after['plan'], 'USING') === false) {
        printf("      %s\n", $after['plan']);
    }
}

clearstatcache(true, $dbFile);
$sizeAfter = filesize($dbFile);
echo "\n" . str_repeat('=', 62) . "\n";
printf("明显变快：%d / %d 条查询\n", $win, count($queries));
if ($worse) echo "变慢的查询：" . implode('、', $worse) . "\n";
printf("库体积：%.1f MB → %.1f MB（索引占 %.1f MB，+%.0f%%）\n",
    $sizeBefore / 1048576, $sizeAfter / 1048576,
    ($sizeAfter - $sizeBefore) / 1048576,
    ($sizeAfter - $sizeBefore) / max(1, $sizeBefore) * 100);
echo "\n写入代价：每条 INSERT 多维护 3 棵 B 树。埋点是写多读少，\n";
echo "所以下面这条也要看——\n";

$t = microtime(true);
$db->exec('BEGIN');
$ins = $db->prepare("INSERT INTO events (event,page,uid,created_at,ts) VALUES (?,?,?,?,?)");
for ($i = 0; $i < 5000; $i++) $ins->execute(['page_view', '/', 'v_x' . $i, date('Y-m-d H:i:s'), time()]);
$db->exec('COMMIT');
printf("  有索引时写入 5000 条：%.0f ms（%.2f ms/条）\n",
    ($e = (microtime(true) - $t) * 1000), $e / 5000);

@unlink($dbFile);
echo "\n结论见 docs/PERFORMANCE.md。\n";
