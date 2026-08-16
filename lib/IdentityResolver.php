<?php
/**
 * 身份解析 Identity Resolution
 * 跨设备/跨渠道合并同一用户：
 *   匿名访客(visitor_id) → 注册(member_id) → 多设备(多个 visitor_id)
 * 通过身份标识 (email / phone / 微信 openid / member_id) 建立「身份图谱」并合并画像
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/CdpSystem.php';

class IdentityResolver {
    private static string $graphFile = DATA_DIR . '/cdp/identity.json';
    private static string $aliasFile = DATA_DIR . '/cdp/aliases.json';

    // ─── 身份图谱结构 ───
    // {
    //   "canonical_id": "usr_xxx",                 // 主身份 ID（指向合并后的画像）
    //   "identities": {                            // 已知身份标识 → 主身份
    //     "visitor:abc123": "usr_xxx",
    //     "member:u_001": "usr_xxx",
    //     "email:foo@bar.com": "usr_xxx",
    //     "openid:wx_xxx": "usr_xxx"
    //   },
    //   "profile": {                               // 合并后的统一画像（引用 CdpSystem profile 冗余）
    //     "member_id": "u_001",
    //     "email": "foo@bar.com",
    //     "phone": "138...",
    //     "visitors": ["abc123", "def456"],
    //     "first_seen": "...", "last_seen": "...",
    //     "tags": [...], "properties": {...},
    //     "merge_count": 2, "merged_at": "..."
    //   }
    // }

    private static function graph(): array {
        return json_read(self::$graphFile);
    }

    private static function saveGraph(array $g): void {
        json_write(self::$graphFile, $g);
    }

    /**
     * 已知标识列表（用于合并）
     * 返回形如 ['visitor:abc'=>true, 'member:u_001'=>true, 'email:x@y.com'=>true]
     */
    public static function knownKeys(string $visitorId = '', string $memberId = '', string $email = '', string $phone = '', string $openid = ''): array {
        $keys = [];
        if ($visitorId) $keys['visitor:' . $visitorId] = true;
        if ($memberId) $keys['member:' . $memberId] = true;
        if ($email) $keys['email:' . strtolower(trim($email))] = true;
        if ($phone) $keys['phone:' . preg_replace('/[^0-9]/', '', $phone)] = true;
        if ($openid) $keys['openid:' . $openid] = true;
        return $keys;
    }

    /**
     * 解析身份：根据任意已知标识返回主身份 canonical_id
     */
    public static function resolve(string $visitorId = '', string $memberId = '', string $email = '', string $phone = '', string $openid = ''): ?string {
        $g = self::graph();
        $keys = self::knownKeys($visitorId, $memberId, $email, $phone, $openid);
        foreach ($keys as $k => $_) {
            if (isset($g['identities'][$k])) {
                return $g['identities'][$k];
            }
        }
        return null;
    }

    /**
     * 注册/合并身份：把一组标识归并到同一个 canonical_id
     * 若这些标识已属于不同主身份，则合并它们（图谱合并）
     */
    public static function merge(string $visitorId = '', string $memberId = '', string $email = '', string $phone = '', string $openid = ''): string {
        $g = self::graph();
        if (!isset($g['identities'])) $g['identities'] = [];
        if (!isset($g['profile'])) $g['profile'] = [];

        $keys = self::knownKeys($visitorId, $memberId, $email, $phone, $openid);
        if (empty($keys)) {
            // 无任何标识：以 visitor 为准
            if ($visitorId) $keys = ['visitor:' . $visitorId => true];
            else return '';
        }

        // 找出涉及的所有已有 canonical
        $foundCanonicals = [];
        foreach ($keys as $k => $_) {
            if (isset($g['identities'][$k]) && !in_array($g['identities'][$k], $foundCanonicals, true)) {
                $foundCanonicals[] = $g['identities'][$k];
            }
        }

        // 决定 canonical_id：优先已存在且关联了 member 的那个，否则新建
        $canonical = '';
        foreach ($foundCanonicals as $cid) {
            $p = $g['profile'][$cid] ?? [];
            if (!empty($p['member_id'])) { $canonical = $cid; break; }
        }
        if (!$canonical && $foundCanonicals) $canonical = $foundCanonicals[0];
        if (!$canonical) {
            $canonical = 'usr_' . bin2hex(random_bytes(6));
            $g['profile'][$canonical] = [
                'member_id' => '', 'email' => '', 'phone' => '',
                'visitors' => [], 'tags' => [], 'properties' => [],
                'first_seen' => date('Y-m-d H:i:s'), 'last_seen' => date('Y-m-d H:i:s'),
                'merge_count' => 1, 'merged_at' => '',
            ];
        }

        // 合并多个 canonical → 全部指向主 canonical
        foreach ($foundCanonicals as $cid) {
            if ($cid === $canonical) continue;
            self::absorbProfile($g, $canonical, $cid);
        }

        // 写入所有 keys → canonical
        foreach ($keys as $k => $_) {
            $g['identities'][$k] = $canonical;
        }

        // 更新主画像冗余字段
        $p = &$g['profile'][$canonical];
        if ($memberId && empty($p['member_id'])) $p['member_id'] = $memberId;
        if ($email) $p['email'] = $email;
        if ($phone) $p['phone'] = $phone;
        if ($visitorId && !in_array($visitorId, $p['visitors'], true)) $p['visitors'][] = $visitorId;
        $p['last_seen'] = date('Y-m-d H:i:s');

        self::saveGraph($g);

        // 同步到 CdpSystem 画像：把匿名访客数据合并到主身份
        self::syncCdpProfiles($canonical, $g);

        return $canonical;
    }

    /**
     * 把被合并 canonical 的画像吸收到主 canonical
     */
    private static function absorbProfile(array &$g, string $main, string $sub): void {
        $sp = $g['profile'][$sub] ?? [];
        $mp = &$g['profile'][$main];

        foreach ($sp['visitors'] ?? [] as $v) {
            if (!in_array($v, $mp['visitors'] ?? [], true)) $mp['visitors'][] = $v;
        }
        foreach ($sp['tags'] ?? [] as $t) {
            if (!in_array($t, $mp['tags'] ?? [], true)) $mp['tags'][] = $t;
        }
        foreach ($sp['properties'] ?? [] as $k => $v) {
            if (!isset($mp['properties'][$k]) || empty($mp['properties'][$k])) $mp['properties'][$k] = $v;
        }
        if (empty($mp['member_id']) && !empty($sp['member_id'])) $mp['member_id'] = $sp['member_id'];
        if (empty($mp['email']) && !empty($sp['email'])) $mp['email'] = $sp['email'];
        if (empty($mp['phone']) && !empty($sp['phone'])) $mp['phone'] = $sp['phone'];
        if (empty($mp['first_seen']) || ($sp['first_seen'] ?? '') < $mp['first_seen']) $mp['first_seen'] = $sp['first_seen'] ?? $mp['first_seen'];
        if (($sp['last_seen'] ?? '') > $mp['last_seen']) $mp['last_seen'] = $sp['last_seen'];

        $mp['merge_count'] = (int)($mp['merge_count'] ?? 1) + (int)($sp['merge_count'] ?? 1);
        $mp['merged_at'] = date('Y-m-d H:i:s');
        $mp['merged_from'][] = $sub;

        unset($g['profile'][$sub]);
        // 把 sub 的 identities 重指向 main（由其调用方处理 keys 写入）
    }

    /**
     * 同步到 CdpSystem：把 identity 图谱主画像合并进 CDP 画像
     */
    private static function syncCdpProfiles(string $canonical, array $g): void {
        $p = $g['profile'][$canonical] ?? [];
        $profiles = CdpSystem::allProfiles();

        // 1. 主画像已存在则更新
        if (isset($profiles[$canonical])) {
            $profiles[$canonical]['member_id'] = $p['member_id'] ?? $profiles[$canonical]['member_id'] ?? '';
            $profiles[$canonical]['last_seen'] = $p['last_seen'] ?? $profiles[$canonical]['last_seen'] ?? '';
            foreach (['email', 'name', 'phone', 'company', 'city'] as $k) {
                if (!empty($p['properties'][$k])) $profiles[$canonical]['properties'][$k] = $p['properties'][$k];
            }
        } else {
            $profiles[$canonical] = [
                'visitor_id' => $canonical,
                'member_id' => $p['member_id'] ?? '',
                'first_seen' => $p['first_seen'] ?? date('Y-m-d H:i:s'),
                'last_seen' => $p['last_seen'] ?? date('Y-m-d H:i:s'),
                'properties' => $p['properties'] ?? [],
                'events_count' => 0,
                'tags' => $p['tags'] ?? [],
            ];
        }

        // 2. 把被合并的匿名 visitor 画像数据并入主画像，并删除
        foreach ($p['visitors'] ?? [] as $vid) {
            if ($vid === $canonical) continue;
            if (isset($profiles[$vid])) {
                $vp = $profiles[$vid];
                foreach ($vp['tags'] ?? [] as $t) {
                    if (!in_array($t, $profiles[$canonical]['tags'], true)) $profiles[$canonical]['tags'][] = $t;
                }
                foreach ($vp['properties'] ?? [] as $k => $v) {
                    if (empty($profiles[$canonical]['properties'][$k])) $profiles[$canonical]['properties'][$k] = $v;
                }
                $profiles[$canonical]['events_count'] = (int)($profiles[$canonical]['events_count'] ?? 0) + (int)($vp['events_count'] ?? 0);
                unset($profiles[$vid]);
            }
        }

        CdpSystem::saveProfiles($profiles);
    }

    /**
     * 获取统一用户画像（主身份 + 各匿名子画像合并视图）
     */
    public static function unifiedProfile(string $visitorId = '', string $memberId = '', string $email = ''): ?array {
        $canonical = self::resolve($visitorId, $memberId, $email);
        if (!$canonical) return null;
        $g = self::graph();
        $p = $g['profile'][$canonical] ?? null;
        if (!$p) return null;

        $profiles = CdpSystem::allProfiles();
        $cdp = $profiles[$canonical] ?? [];
        return [
            'canonical_id' => $canonical,
            'identity' => $p,
            'cdp_profile' => $cdp,
        ];
    }

    /**
     * 按 member 或 email 获取所有关联 visitor（多设备）
     */
    public static function linkedVisitors(string $memberId = ''): array {
        $g = self::graph();
        $out = [];
        foreach ($g['profile'] ?? [] as $cid => $p) {
            if ($memberId && ($p['member_id'] ?? '') === $memberId) {
                $out[$cid] = $p['visitors'] ?? [];
            }
        }
        return $out;
    }

    /**
     * 全量统计
     */
    public static function stats(): array {
        $g = self::graph();
        $profiles = $g['profile'] ?? [];
        $merged = 0;
        foreach ($profiles as $p) $merged += max(0, (int)($p['merge_count'] ?? 1) - 1);
        return [
            'canonical_profiles' => count($profiles),
            'known_identities' => count($g['identities'] ?? []),
            'merged_events' => $merged,
            'with_member' => count(array_filter($profiles, fn($p) => !empty($p['member_id']))),
        ];
    }
}
