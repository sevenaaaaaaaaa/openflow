<?php
/**
 * CDP 客户同步层
 * 提供 cdp_customers 表 + cdp_* 辅助函数（cdp_find / cdp_get_or_create / cdp_add_tag / cdp_set_score / cdp_add_ltv / cdp_merge_on_login 等）
 * 数据源：CDP JSON 画像（data/cdp/profiles.json）+ 增量写入 SQLite
 *
 * ── CDP 三层架构：第 3 层「存储同步」 ──
 * 本文件只做「客户主数据的落库」：把 CdpSystem 的画像同步到 SQLite 的 cdp_customers 表。
 * 依赖：CdpSystem（画像）+ Database（SQLite）。
 * 加代码指引：客户表结构、标签/评分/ltv 的落库、登录合并逻辑加这里，
 *            不要在这里算画像（归 CdpSystem）或生成洞察（归 CdpInsight）。
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CdpSystem.php';

if (!function_exists('cdp_ensure_table')) {
    function cdp_ensure_table(): void {
        Database::execute("CREATE TABLE IF NOT EXISTS cdp_customers (
            id TEXT PRIMARY KEY,
            member_id TEXT DEFAULT '',
            visitor_id TEXT DEFAULT '',
            email TEXT DEFAULT '',
            name TEXT DEFAULT '',
            phone TEXT DEFAULT '',
            tags TEXT DEFAULT '[]',
            score INTEGER DEFAULT 0,
            lifetime_value REAL DEFAULT 0,
            first_seen TEXT DEFAULT '',
            last_seen TEXT DEFAULT '',
            channel TEXT DEFAULT '',
            props TEXT DEFAULT '{}',
            created_at TEXT DEFAULT ''
        )");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_cdp_member ON cdp_customers(member_id)");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_cdp_email ON cdp_customers(email)");
    }
}

if (!function_exists('cdp_find')) {
    /**
     * 按 email / member_id / visitor_id 查找客户
     */
    function cdp_find(string $email, string $memberId = '', string $uid = ''): ?array {
        cdp_ensure_table();
        if ($email) {
            $rows = Database::query("SELECT * FROM cdp_customers WHERE email = ? LIMIT 1", [$email]);
            if ($rows) return $rows[0];
        }
        if ($memberId) {
            $rows = Database::query("SELECT * FROM cdp_customers WHERE member_id = ? LIMIT 1", [$memberId]);
            if ($rows) return $rows[0];
        }
        if ($uid) {
            $rows = Database::query("SELECT * FROM cdp_customers WHERE visitor_id = ? LIMIT 1", [$uid]);
            if ($rows) return $rows[0];
        }
        return null;
    }
}

if (!function_exists('cdp_get_by_id')) {
    function cdp_get_by_id(string $id): ?array {
        cdp_ensure_table();
        $rows = Database::query("SELECT * FROM cdp_customers WHERE id = ?", [$id]);
        return $rows[0] ?? null;
    }
}

if (!function_exists('cdp_get_or_create')) {
    /**
     * 获取或创建客户记录
     */
    function cdp_get_or_create(string $uid, string $memberId = '', string $email = '', string $phone = ''): ?array {
        cdp_ensure_table();
        $existing = cdp_find($email, $memberId, $uid);
        if ($existing) {
            $updates = ['last_seen' => date('Y-m-d H:i:s')];
            if ($memberId && empty($existing['member_id'])) $updates['member_id'] = $memberId;
            if ($email && empty($existing['email'])) $updates['email'] = $email;
            $sets = [];
            foreach ($updates as $k => $v) $sets[] = "{$k} = ?";
            if ($sets) Database::execute("UPDATE cdp_customers SET " . implode(', ', $sets) . " WHERE id = ?", array_merge(array_values($updates), [$existing['id']]));
            return cdp_get_by_id($existing['id']);
        }
        $id = $uid ?: 'u_' . bin2hex(random_bytes(8));
        $now = date('Y-m-d H:i:s');
        Database::insert('cdp_customers', [
            'id' => $id,
            'member_id' => $memberId,
            'visitor_id' => $uid,
            'email' => $email,
            'phone' => $phone,
            'tags' => '[]',
            'score' => 0,
            'lifetime_value' => 0,
            'first_seen' => $now,
            'last_seen' => $now,
            'props' => '{}',
            'created_at' => $now,
        ]);
        return cdp_get_by_id($id);
    }
}

if (!function_exists('cdp_add_tag')) {
    function cdp_add_tag(string $id, string $tag): void {
        cdp_ensure_table();
        $c = cdp_get_by_id($id);
        if (!$c) return;
        $tags = json_decode($c['tags'] ?? '[]', true) ?: [];
        if (!in_array($tag, $tags, true)) {
            $tags[] = $tag;
            Database::execute("UPDATE cdp_customers SET tags = ? WHERE id = ?", [json_encode($tags, JSON_UNESCAPED_UNICODE), $id]);
        }
    }
}

if (!function_exists('cdp_set_score')) {
    function cdp_set_score(string $id, int $score): void {
        cdp_ensure_table();
        Database::execute("UPDATE cdp_customers SET score = ? WHERE id = ?", [$score, $id]);
    }
}

if (!function_exists('cdp_add_ltv')) {
    function cdp_add_ltv(string $id, float $amount): void {
        cdp_ensure_table();
        $c = cdp_get_by_id($id);
        if (!$c) return;
        $ltv = (float)($c['lifetime_value'] ?? 0) + $amount;
        Database::execute("UPDATE cdp_customers SET lifetime_value = ? WHERE id = ?", [$ltv, $id]);
    }
}

if (!function_exists('cdp_touch')) {
    function cdp_touch(string $uid, array $ctx = []): void {
        cdp_ensure_table();
        $memberId = $ctx['member_id'] ?? '';
        $email = $ctx['email'] ?? '';
        $c = cdp_get_or_create($uid, $memberId, $email);
        if ($c) Database::execute("UPDATE cdp_customers SET last_seen = ? WHERE id = ?", [date('Y-m-d H:i:s'), $c['id']]);
    }
}

if (!function_exists('cdp_score')) {
    function cdp_score(string $uid, int $points, string $reason = '', string $label = ''): void {
        cdp_ensure_table();
        $c = cdp_get_or_create($uid, '', '');
        if (!$c) return;
        $score = (int)$c['score'] + $points;
        cdp_set_score($c['id'], max(0, $score));
    }
}

if (!function_exists('cdp_merge_on_login')) {
    /**
     * 用户登录后合并匿名访客数据到会员
     */
    function cdp_merge_on_login(string $memberId, string $email = '', string $uid = ''): void {
        cdp_ensure_table();
        // 走完整身份解析：匿名访客 + 邮箱 + 会员 → 统一 canonical_id，跨设备合并
        require_once __DIR__ . '/IdentityResolver.php';
        try {
            IdentityResolver::merge($uid, $memberId, $email);
        } catch (Exception $e) {}
        // 兼容旧逻辑：cdp_customers 表内合并
        if ($uid) {
            $anon = cdp_find('', '', $uid);
            if ($anon && $anon['member_id'] === '') {
                Database::execute("UPDATE cdp_customers SET member_id = ?, email = COALESCE(NULLIF(email,''), ?) WHERE id = ?", [$memberId, $email, $anon['id']]);
            }
        }
        // 按 email 合并
        if ($email) {
            $byEmail = cdp_find($email, '', '');
            if ($byEmail && $byEmail['member_id'] !== $memberId) {
                Database::execute("UPDATE cdp_customers SET member_id = ? WHERE id = ?", [$memberId, $byEmail['id']]);
            }
        }
    }
}

if (!function_exists('cdp_profiles_export')) {
    /**
     * 将 CdpSystem JSON 画像同步到 cdp_customers 表
     */
    function cdp_profiles_export(): int {
        cdp_ensure_table();
        $profiles = CdpSystem::allProfiles();
        $count = 0;
        foreach ($profiles as $vid => $p) {
            $memberId = $p['member_id'] ?? '';
            $email = $p['properties']['email'] ?? $p['email'] ?? '';
            $existing = cdp_find('', $memberId, $vid);
            $now = date('Y-m-d H:i:s');
            $data = [
                'id' => $vid,
                'member_id' => $memberId,
                'visitor_id' => $vid,
                'email' => $email,
                'tags' => json_encode($p['tags'] ?? [], JSON_UNESCAPED_UNICODE),
                'first_seen' => $p['first_seen'] ?? $now,
                'last_seen' => $p['last_seen'] ?? $now,
                'props' => json_encode($p['properties'] ?? [], JSON_UNESCAPED_UNICODE),
                'created_at' => $p['created_at'] ?? $now,
            ];
            if ($existing) {
                $sets = [];
                foreach ($data as $k => $v) {
                    if ($k === 'id') continue;
                    $sets[] = "{$k} = COALESCE(NULLIF('" . str_replace("'", "''", (string)$v) . "',''), {$k})";
                }
                if ($sets) Database::execute("UPDATE cdp_customers SET " . implode(', ', $sets) . " WHERE id = ?", [$vid]);
            } else {
                Database::insert('cdp_customers', $data);
            }
            $count++;
        }
        return $count;
    }
}
