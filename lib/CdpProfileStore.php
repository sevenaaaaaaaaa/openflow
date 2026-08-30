<?php
/**
 * CdpProfileStore —— CDP 画像的 SQLite 存储层（AUDIT-02 P0 / BACKLOG T0-1）
 *
 * 【为什么】画像原先存 data/cdp/profiles.json，一个大字典整存整取：每来一个事件，
 * updateProfile 都要「读全量 → 改一条 → 写全量」，写放大随用户数平方级恶化，
 * 是 CDP 上量的第一块地基债。本层把画像迁到 SQLite：一画像一行，
 * visitor_id 主键 + member_id 索引；读写都按行，updateProfile 不再动全量。
 *
 * 【迁移】首次访问自动把老 profiles.json 一次性导入表（事务内、INSERT OR REPLACE
 * 幂等），并留 marker 防重导；**保留原 profiles.json 不删**，作回滚备份。
 *
 * 依赖仅 Database + DATA_DIR（不 require config），便于隔离测试。
 * 只负责画像的落库；画像字段计算仍在 CdpSystem，分群洞察在 SegmentEngine/CdpInsight。
 */

require_once __DIR__ . '/Database.php';

if (!function_exists('cdp_profile_ensure')) {

    function cdp_profile_ensure(): void {
        static $ready = false;
        if ($ready) return;
        Database::execute("CREATE TABLE IF NOT EXISTS cdp_profiles (
            visitor_id TEXT PRIMARY KEY,
            member_id  TEXT DEFAULT '',
            updated_at TEXT DEFAULT '',
            data       TEXT DEFAULT '{}'
        )");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_cdp_profiles_member ON cdp_profiles(member_id)");

        // 一次性迁移：老 profiles.json → 表（保留原文件作回滚备份）
        $marker = DATA_DIR . '/cdp/.profiles_migrated';
        if (!is_file($marker)) {
            $file = DATA_DIR . '/cdp/profiles.json';
            $legacy = _cdp_profile_read_json($file);
            if (is_array($legacy) && $legacy) {
                $conn = Database::conn();
                $own = !$conn->inTransaction();
                if ($own) $conn->beginTransaction();
                try {
                    foreach ($legacy as $vid => $p) {
                        if (is_array($p)) cdp_profile_put((string)$vid, $p);
                    }
                    if ($own) $conn->commit();
                } catch (\Throwable $e) {
                    if ($own && $conn->inTransaction()) $conn->rollBack();
                    $ready = true;   // 别卡死请求；下次再试导入
                    return;
                }
            }
            @mkdir(dirname($marker), 0755, true);
            @file_put_contents($marker, date('c'));
        }
        $ready = true;
    }

    /** 单画像写入（幂等 upsert）。member_id 抽成列便于按会员查。 */
    function cdp_profile_put(string $visitorId, array $profile): void {
        Database::execute(
            "INSERT OR REPLACE INTO cdp_profiles (visitor_id, member_id, updated_at, data) VALUES (?,?,?,?)",
            [
                $visitorId,
                (string)($profile['member_id'] ?? ''),
                date('Y-m-d H:i:s'),
                json_encode($profile, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /** 按 visitor_id 取一条（按行读，不碰全量）。 */
    function cdp_profile_get(string $visitorId): ?array {
        cdp_profile_ensure();
        $rows = Database::query("SELECT data FROM cdp_profiles WHERE visitor_id = ?", [$visitorId]);
        if (!$rows) return null;
        $d = json_decode($rows[0]['data'] ?? 'null', true);
        return is_array($d) ? $d : null;
    }

    /** 取全部，返回 {visitor_id => profile} 字典（与老 allProfiles 同形）。 */
    function cdp_profile_all(): array {
        cdp_profile_ensure();
        $out = [];
        foreach (Database::query("SELECT visitor_id, data FROM cdp_profiles") as $r) {
            $d = json_decode($r['data'] ?? 'null', true);
            if (is_array($d)) $out[(string)$r['visitor_id']] = $d;
        }
        return $out;
    }

    /**
     * 全量写回：让表精确等于给定集合——集合内 upsert、集合外删除。
     * 忠实翻译老 saveProfiles(json_write 整份)：身份合并 unset 掉的画像会被删掉。
     * 只在身份合并/数据导入时被调用，非逐事件热点。
     */
    function cdp_profile_save_all(array $profiles): void {
        cdp_profile_ensure();
        $conn = Database::conn();
        $existing = array_map('strval', array_column(Database::query("SELECT visitor_id FROM cdp_profiles"), 'visitor_id'));
        $keep = array_map('strval', array_keys($profiles));
        $toDelete = array_values(array_diff($existing, $keep));

        $own = !$conn->inTransaction();
        if ($own) $conn->beginTransaction();
        try {
            foreach (array_chunk($toDelete, 200) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                Database::execute("DELETE FROM cdp_profiles WHERE visitor_id IN ($ph)", $chunk);
            }
            foreach ($profiles as $vid => $p) {
                if (is_array($p)) cdp_profile_put((string)$vid, $p);
            }
            if ($own) $conn->commit();
        } catch (\Throwable $e) {
            if ($own && $conn->inTransaction()) $conn->rollBack();
            throw $e;
        }
    }

    /** 删一条画像（供未来身份合并显式删除用）。 */
    function cdp_profile_delete(string $visitorId): void {
        Database::execute("DELETE FROM cdp_profiles WHERE visitor_id = ?", [$visitorId]);
    }

    function _cdp_profile_read_json(string $f): array {
        if (function_exists('json_read')) return json_read($f);
        if (!is_file($f)) return [];
        $d = json_decode((string)file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }
}
