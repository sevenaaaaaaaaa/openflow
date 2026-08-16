<?php
/**
 * 分享传播链追踪 — 谁分享、谁带来访问/转化、潜在 KOL 识别
 *
 * 存储：SQLite (data/db/openflow.db)
 * 表：
 *   share_events   每次分享（生成 share_key）
 *   share_visits   每个分享带来的访问（含 referrer / 访问者）
 *   share_conversions 分享带来的转化（注册/购买）
 *
 * 流程：
 *   1. 用户点分享 → share_track_create() 生成 share_key → URL 拼 ?ref={share_key}
 *   2. 访问者点开 → article.php 检测 ref → share_track_visit() 记录访问
 *   3. 访问者注册/购买 → share_track_convert() 标记转化（反向关联到分享者）
 */

if (!function_exists('share_track_db')) {

function share_track_db(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DATA_DIR . '/db/openflow.db');
        $db->exec("CREATE TABLE IF NOT EXISTS share_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            share_key TEXT UNIQUE,
            article_slug TEXT,
            sharer_member_id TEXT DEFAULT '',
            channel TEXT DEFAULT '',
            created_at TEXT
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS share_visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            share_key TEXT,
            visitor_member_id TEXT DEFAULT '',
            visitor_ip TEXT DEFAULT '',
            visitor_ua TEXT DEFAULT '',
            created_at TEXT
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS share_conversions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            share_key TEXT,
            visitor_member_id TEXT,
            event TEXT,
            created_at TEXT
        )");
    }
    return $db;
}

// 1. 创建一次分享，返回 share_key
function share_track_create(string $slug, string $channel = '', string $memberId = ''): string {
    $db = share_track_db();
    $key = bin2hex(random_bytes(8)); // 16 hex chars
    $db->prepare("INSERT INTO share_events (share_key, article_slug, sharer_member_id, channel, created_at)
        VALUES (:k, :s, :m, :c, :now)")
        ->execute([':k' => $key, ':s' => $slug, ':m' => $memberId, ':c' => $channel, ':now' => date('Y-m-d H:i:s')]);
    return $key;
}

// 2. 记录一次由分享带来的访问
function share_track_visit(string $shareKey, string $visitorMemberId = '', string $ip = '', string $ua = ''): void {
    $db = share_track_db();
    $db->prepare("INSERT INTO share_visits (share_key, visitor_member_id, visitor_ip, visitor_ua, created_at)
        VALUES (:k, :m, :ip, :ua, :now)")
        ->execute([':k' => $shareKey, ':m' => $visitorMemberId, ':ip' => $ip, ':ua' => mb_substr($ua, 0, 500), ':now' => date('Y-m-d H:i:s')]);
}

// 3. 记录一次转化（注册/购买/订阅等），关联到分享者
function share_track_convert(string $shareKey, string $visitorMemberId, string $event): void {
    $db = share_track_db();
    $db->prepare("INSERT INTO share_conversions (share_key, visitor_member_id, event, created_at)
        VALUES (:k, :m, :e, :now)")
        ->execute([':k' => $shareKey, ':m' => $visitorMemberId, ':e' => $event, ':now' => date('Y-m-d H:i:s')]);
}

// 校验 share_key 是否有效（存在）
function share_track_valid(string $shareKey): bool {
    $db = share_track_db();
    $stmt = $db->prepare("SELECT 1 FROM share_events WHERE share_key = :k");
    $stmt->execute([':k' => $shareKey]);
    return (bool)$stmt->fetchColumn();
}

// ─── 统计查询 ───

// 单篇文章的传播统计
function share_track_article_stats(string $slug): array {
    $db = share_track_db();
    $stmt = $db->prepare("SELECT
        (SELECT COUNT(*) FROM share_events WHERE article_slug = :s) AS shares,
        (SELECT COUNT(*) FROM share_visits v JOIN share_events e ON v.share_key = e.share_key WHERE e.article_slug = :s) AS visits,
        (SELECT COUNT(*) FROM share_conversions c JOIN share_events e ON c.share_key = e.share_key WHERE e.article_slug = :s) AS conversions");
    $stmt->execute([':s' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ['shares' => (int)$row['shares'], 'visits' => (int)$row['visits'], 'conversions' => (int)$row['conversions']];
}

// KOL 排行：按带来的访问/转化排序
function share_track_kols(int $limit = 20): array {
    $db = share_track_db();
    $rows = $db->query("
        SELECT e.sharer_member_id,
            COUNT(DISTINCT e.share_key) AS share_count,
            COUNT(DISTINCT v.id) AS visit_count,
            COUNT(DISTINCT c.id) AS conversion_count
        FROM share_events e
        LEFT JOIN share_visits v ON v.share_key = e.share_key
        LEFT JOIN share_conversions c ON c.share_key = e.share_key
        WHERE e.sharer_member_id != ''
        GROUP BY e.sharer_member_id
        ORDER BY visit_count DESC, conversion_count DESC
        LIMIT " . (int)$limit)->fetchAll(PDO::FETCH_ASSOC);

    // 关联会员名
    $members = [];
    foreach (json_read(DATA_DIR . '/members/index.json') as $m) {
        $members[$m['id']] = $m['name'] ?? $m['email'] ?? $m['id'];
    }
    foreach ($rows as &$r) {
        $r['name'] = $members[$r['sharer_member_id']] ?? '匿名';
        $r['convert_rate'] = $r['visit_count'] > 0 ? round($r['conversion_count'] / $r['visit_count'] * 100, 1) . '%' : '0%';
    }
    return $rows;
}

// 热门文章排行（按分享/访问）
function share_track_hot_articles(int $limit = 20): array {
    $db = share_track_db();
    return $db->query("
        SELECT e.article_slug,
            COUNT(DISTINCT e.share_key) AS share_count,
            COUNT(DISTINCT v.id) AS visit_count,
            COUNT(DISTINCT c.id) AS conversion_count
        FROM share_events e
        LEFT JOIN share_visits v ON v.share_key = e.share_key
        LEFT JOIN share_conversions c ON c.share_key = e.share_key
        GROUP BY e.article_slug
        ORDER BY visit_count DESC, share_count DESC
        LIMIT " . (int)$limit)->fetchAll(PDO::FETCH_ASSOC);
}

// 单次分享的完整传播链（该分享带来的所有访问+转化）
function share_track_chain(string $shareKey): array {
    $db = share_track_db();
    $stmt = $db->prepare("SELECT * FROM share_events WHERE share_key = :k");
    $stmt->execute([':k' => $shareKey]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) return [];

    $v = $db->prepare("SELECT * FROM share_visits WHERE share_key = :k ORDER BY id");
    $v->execute([':k' => $shareKey]);
    $visits = $v->fetchAll(PDO::FETCH_ASSOC);

    $c = $db->prepare("SELECT * FROM share_conversions WHERE share_key = :k ORDER BY id");
    $c->execute([':k' => $shareKey]);
    $conversions = $c->fetchAll(PDO::FETCH_ASSOC);

    $event['visits'] = $visits;
    $event['conversions'] = $conversions;
    return $event;
}

} // end if function_exists
