<?php
/**
 * CdpIdentityStore —— CDP 身份图谱的 SQLite 存储层（P1-1）
 *
 * 【为什么】IdentityResolver 原先整份读写 data/cdp/identity.json：
 *   每次 merge()「读全量图 → 改 → 写全量」，身份合并频繁时写放大。
 *   本层把身份图谱迁到 SQLite：cdp_identities（身份标识→主身份，一行一 key）
 *   + cdp_canonical（主身份合并画像，一行一 canonical），resolve/merge 都按行，
 *   不再动全量。
 *
 * 【迁移】首次访问把老 identity.json 一次性导入表（事务 + INSERT OR REPLACE 幂等），
 *   留 marker 防重导；**保留 identity.json 不删**，作回滚备份（与 CdpProfileStore 同哲学）。
 *
 * 依赖仅 Database + DATA_DIR（不 require config），便于隔离测试。
 * 核心方法供 IdentityResolver 走 SQLite；原 JSON 图逻辑保留作 fallback。
 */

require_once __DIR__ . '/Database.php';

if (!function_exists('cid_identity_ensure')) {

    function cid_identity_ensure(): void {
        static $ready = false;
        if ($ready) return;
        Database::execute("CREATE TABLE IF NOT EXISTS cdp_identities (
            identity_key TEXT PRIMARY KEY,
            canonical_id TEXT NOT NULL DEFAULT '',
            member_id    TEXT DEFAULT '',
            updated_at   TEXT DEFAULT ''
        )");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_cdp_identities_canonical ON cdp_identities(canonical_id)");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_cdp_identities_member ON cdp_identities(member_id)");
        Database::execute("CREATE TABLE IF NOT EXISTS cdp_canonical (
            canonical_id TEXT PRIMARY KEY,
            profile      TEXT DEFAULT '{}',
            updated_at   TEXT DEFAULT ''
        )");

        // 一次性迁移：老 identity.json → 表（保留原文件作回滚备份）
        $marker = DATA_DIR . '/cdp/.identities_migrated';
        if (!is_file($marker)) {
            $file = DATA_DIR . '/cdp/identity.json';
            $legacy = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
            if (is_array($legacy) && $legacy) {
                $conn = Database::conn();
                $own = !$conn->inTransaction();
                if ($own) $conn->beginTransaction();
                try {
                    foreach ((array)($legacy['identities'] ?? []) as $key => $canonical) {
                        cid_identity_put((string)$key, (string)$canonical, '');
                    }
                    foreach ((array)($legacy['profile'] ?? []) as $cid => $p) {
                        cid_canonical_put((string)$cid, is_array($p) ? $p : []);
                    }
                    if ($own) $conn->commit();
                } catch (\Throwable $e) {
                    if ($own && $conn->inTransaction()) $conn->rollBack();
                    $ready = true;
                    return;
                }
            }
            @mkdir(dirname($marker), 0755, true);
            @file_put_contents($marker, date('c'));
        }
        $ready = true;
    }

    /** 身份标识 → 主身份 写入（幂等 upsert） */
    function cid_identity_put(string $identityKey, string $canonicalId, string $memberId = ''): void {
        Database::execute(
            "INSERT OR REPLACE INTO cdp_identities (identity_key, canonical_id, member_id, updated_at) VALUES (?,?,?,?)",
            [$identityKey, $canonicalId, $memberId, date('Y-m-d H:i:s')]
        );
    }

    /** 按 identity_key 查 canonical（点查，热路径） */
    function cid_identity_get(string $identityKey): ?string {
        cid_identity_ensure();
        $rows = Database::query("SELECT canonical_id FROM cdp_identities WHERE identity_key = ?", [$identityKey]);
        if (!$rows) return null;
        $c = (string)($rows[0]['canonical_id'] ?? '');
        return $c !== '' ? $c : null;
    }

    /** 批量查多个 identity_key 的 canonical（同时适配值数组与 assoc[identity=>true]） */
    function cid_identity_get_many(array $keys): array {
        if (!$keys) return [];
        cid_identity_ensure();
        $vals = [];
        foreach ($keys as $k => $v) {
            $val = is_int($k) ? $v : $k;   // 值数组用 value，assoc 用 key
            $val = trim((string)$val);
            if ($val !== '') $vals[] = $val;
        }
        $vals = array_values(array_unique($vals));
        if (!$vals) return [];
        $place = implode(',', array_fill(0, count($vals), '?'));
        $rows = Database::query("SELECT identity_key, canonical_id FROM cdp_identities WHERE identity_key IN ($place)", $vals);
        $out = [];
        foreach ($rows as $r) $out[(string)$r['identity_key']] = (string)$r['canonical_id'];
        return $out;
    }

    /** 把某 canonical 的所有身份重指到另一 canonical（合并用） */
    function cid_identity_repoint(string $fromCanonical, string $toCanonical): void {
        Database::execute("UPDATE cdp_identities SET canonical_id = ?, updated_at = ? WHERE canonical_id = ?", [$toCanonical, date('Y-m-d H:i:s'), $fromCanonical]);
    }

    /** 主身份合并画像写入（profile 为 json 字符串） */
    function cid_canonical_put(string $canonicalId, array $profile): void {
        Database::execute(
            "INSERT OR REPLACE INTO cdp_canonical (canonical_id, profile, updated_at) VALUES (?,?,?)",
            [$canonicalId, json_encode($profile, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s')]
        );
    }

    function cid_canonical_get(string $canonicalId): ?array {
        cid_identity_ensure();
        $rows = Database::query("SELECT profile FROM cdp_canonical WHERE canonical_id = ?", [$canonicalId]);
        if (!$rows) return null;
        return json_decode((string)($rows[0]['profile'] ?? '{}'), true) ?: [];
    }

    function cid_canonical_delete(string $canonicalId): void {
        Database::execute("DELETE FROM cdp_canonical WHERE canonical_id = ?", [$canonicalId]);
    }

    /** 按 member_id 查所有 canonical 画像 */
    function cid_canonical_by_member(string $memberId): array {
        cid_identity_ensure();
        $rows = Database::query("SELECT canonical_id, profile FROM cdp_canonical WHERE profile LIKE ?", ['%"member_id":"' . $memberId . '"%']);
        $out = [];
        foreach ($rows as $r) { $cid = (string)$r['canonical_id']; $out[$cid] = json_decode((string)$r['profile'], true) ?: []; }
        return $out;
    }

    /** 统计 */
    function cid_stats(): array {
        cid_identity_ensure();
        $ids = Database::query("SELECT COUNT(*) n FROM cdp_identities");
        $can = Database::query("SELECT COUNT(*) n FROM cdp_canonical");
        $mem = Database::query("SELECT COUNT(*) n FROM cdp_canonical WHERE profile LIKE '%\"member_id\":\"%\"%'");
        $merged = Database::query("SELECT COUNT(*) n FROM cdp_canonical WHERE profile LIKE '%\"merged_from\"%'");
        return [
            'canonical_profiles' => (int)($can[0]['n'] ?? 0),
            'known_identities'   => (int)($ids[0]['n'] ?? 0),
            'merged_events'      => (int)($merged[0]['n'] ?? 0),
            'with_member'        => (int)($mem[0]['n'] ?? 0),
        ];
    }

}
