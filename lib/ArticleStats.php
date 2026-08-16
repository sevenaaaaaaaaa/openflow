<?php
/**
 * 文章互动数据 — 阅读/点赞/收藏/分享 统计
 * 存储：SQLite (data/db/openflow.db) → article_stats 表
 *
 * 方法：
 *   art_stats_add($slug, $type)        记录一次互动 (view/like/favorite/share)
 *   art_stats_get($slug)               获取某文章统计
 *   art_stats_batch($slugs)            批量获取统计 map
 *   art_stats_toggle($slug, $memberId, $type)  点赞/收藏 切换（返回是否已开启）
 */

if (!function_exists('art_stats_get')) {

function art_stats_db(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DATA_DIR . '/db/openflow.db');
        $db->exec("CREATE TABLE IF NOT EXISTS article_stats (
            slug TEXT PRIMARY KEY,
            views INTEGER DEFAULT 0,
            likes INTEGER DEFAULT 0,
            favorites INTEGER DEFAULT 0,
            shares INTEGER DEFAULT 0,
            updated_at TEXT
        )");
    }
    return $db;
}

// 记录一次互动（幂等计数）
function art_stats_add(string $slug, string $type): void {
    if (!in_array($type, ['view', 'like', 'favorite', 'share'])) return;
    // 列名映射（单数 → 复数）
    $colMap = ['view' => 'views', 'like' => 'likes', 'favorite' => 'favorites', 'share' => 'shares'];
    $col = $colMap[$type];
    $db = art_stats_db();
    // 先确保行存在（不覆盖计数）
    $db->prepare("INSERT OR IGNORE INTO article_stats (slug, views, likes, favorites, shares, updated_at)
        VALUES (:slug, 0, 0, 0, 0, :now)")
        ->execute([':slug' => $slug, ':now' => date('Y-m-d H:i:s')]);
    // 再自增目标列
    $db->prepare("UPDATE article_stats SET $col = $col + 1, updated_at = :now WHERE slug = :slug")
        ->execute([':slug' => $slug, ':now' => date('Y-m-d H:i:s')]);
}

// 获取统计
function art_stats_get(string $slug): array {
    $db = art_stats_db();
    $stmt = $db->prepare("SELECT views, likes, favorites, shares FROM article_stats WHERE slug = :slug");
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['views' => 0, 'likes' => 0, 'favorites' => 0, 'shares' => 0];
}

// 批量获取
function art_stats_batch(array $slugs): array {
    if (empty($slugs)) return [];
    $out = [];
    $db = art_stats_db();
    $chunks = array_chunk(array_values(array_unique($slugs)), 200);
    foreach ($chunks as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $db->prepare("SELECT slug, views, likes, favorites, shares FROM article_stats WHERE slug IN ($in)");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['slug']] = ['views' => (int)$r['views'], 'likes' => (int)$r['likes'], 'favorites' => (int)$r['favorites'], 'shares' => (int)$r['shares']];
        }
    }
    return $out;
}

// 用户点赞/收藏状态表
function art_stats_toggle(string $slug, string $memberId, string $type): bool {
    if (!in_array($type, ['like', 'favorite'])) return false;
    $db = art_stats_db();
    $db->exec("CREATE TABLE IF NOT EXISTS article_user_stats (
        member_id TEXT, slug TEXT, type TEXT,
        created_at TEXT, PRIMARY KEY (member_id, slug, type)
    )");
    $stmt = $db->prepare("SELECT 1 FROM article_user_stats WHERE member_id = :m AND slug = :s AND type = :t");
    $stmt->execute([':m' => $memberId, ':s' => $slug, ':t' => $type]);
    $exists = (bool)$stmt->fetchColumn();

    if ($exists) {
        $db->prepare("DELETE FROM article_user_stats WHERE member_id = :m AND slug = :s AND type = :t")
            ->execute([':m' => $memberId, ':s' => $slug, ':t' => $type]);
        art_stats_add_signed($slug, $type, -1);
        return false; // 已取消
    }
    $db->prepare("INSERT INTO article_user_stats (member_id, slug, type, created_at) VALUES (:m, :s, :t, :now)")
        ->execute([':m' => $memberId, ':s' => $slug, ':t' => $type, ':now' => date('Y-m-d H:i:s')]);
    art_stats_add_signed($slug, $type, 1);
    return true; // 已开启
}

function art_stats_add_signed(string $slug, string $type, int $delta): void {
    if (!in_array($type, ['like', 'favorite'])) return;
    // 列名是复数形式
    $col = $type === 'like' ? 'likes' : 'favorites';
    $db = art_stats_db();
    $db->prepare("INSERT OR IGNORE INTO article_stats (slug, views, likes, favorites, shares, updated_at)
        VALUES (:slug, 0, 0, 0, 0, :now)")
        ->execute([':slug' => $slug, ':now' => date('Y-m-d H:i:s')]);
    $db->prepare("UPDATE article_stats SET $col = MAX(0, $col + :delta), updated_at = :now WHERE slug = :slug")
        ->execute([':slug' => $slug, ':delta' => $delta, ':now' => date('Y-m-d H:i:s')]);
}

// 用户是否已点赞/收藏
function art_stats_user_state(string $slug, string $memberId): array {
    if (!$memberId) return ['like' => false, 'favorite' => false];
    $db = art_stats_db();
    $state = ['like' => false, 'favorite' => false];
    $stmt = $db->prepare("SELECT type FROM article_user_stats WHERE member_id = :m AND slug = :s");
    $stmt->execute([':m' => $memberId, ':s' => $slug]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) $state[$t] = true;
    return $state;
}

} // end if function_exists
