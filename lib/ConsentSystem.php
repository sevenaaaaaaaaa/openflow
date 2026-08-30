<?php
/**
 * ConsentSystem —— 同意管理 + 数据保留（AUDIT-02 / BACKLOG T1-5）
 *
 * 【为什么】出海与个保法/GDPR 刚需：没拿到同意就建画像是合规风险；采集了也不能永久留。
 * 本模块把「同意状态」接进采集链路（未同意→只留匿名/不建画像），并给「保留期」策略
 * （过期事件与画像自动清理）。
 *
 * 【模式】data/settings.json 的 consent.mode：
 *   off      —— 不启用同意门（默认，行为不变）
 *   implied  —— 默认视为同意，访客可拒绝（opt-out）
 *   explicit —— 必须明确同意才采集（opt-in，GDPR 式）
 * 同意状态存 cookie of_consent=granted|denied，由前端横幅写入。
 *
 * 保留期 consent.retention_days（0=不清理）。清理由 cron 调 consent_purge_expired()。
 */

if (!function_exists('consent_settings')) {

    function consent_settings(): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $c = is_array($s['consent'] ?? null) ? $s['consent'] : [];
        return array_merge([
            'mode' => 'off',
            'retention_days' => 0,
            'banner_text' => '我们使用 Cookie 与行为数据来改进内容与体验。',
        ], $c);
    }

    function consent_save(array $data): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $mode = in_array(($data['mode'] ?? 'off'), ['off', 'implied', 'explicit'], true) ? $data['mode'] : 'off';
        $s['consent'] = [
            'mode' => $mode,
            'retention_days' => max(0, (int)($data['retention_days'] ?? 0)),
            'banner_text' => trim((string)($data['banner_text'] ?? '')) ?: '我们使用 Cookie 与行为数据来改进内容与体验。',
        ];
        if (function_exists('json_write')) json_write(DATA_DIR . '/settings.json', $s);
        return $s['consent'];
    }

    /** 当前访客是否允许采集（$cookie 可注入，便于测试）。 */
    function consent_granted(?array $cookie = null): bool {
        $cookie = $cookie ?? $_COOKIE;
        $mode = consent_settings()['mode'];
        if ($mode === 'off') return true;                                  // 未启用同意门
        $v = (string)($cookie['of_consent'] ?? '');
        if ($v === 'denied') return false;                                 // 明确拒绝，一律不采
        if ($mode === 'implied') return true;                              // 默认同意（未表态也采）
        return $v === 'granted';                                           // explicit：必须明确同意
    }

    /** 是否该建画像（未同意时只允许匿名计数，不落画像）。 */
    function consent_allow_profile(?array $cookie = null): bool { return consent_granted($cookie); }

    /**
     * 数据保留清理：删除超过保留期的事件与长期未活跃画像。
     * $now 可注入（测试）。返回 ['events'=>删除数,'profiles'=>删除数]。
     */
    function consent_purge_expired(?int $now = null): array {
        $days = (int)consent_settings()['retention_days'];
        $out = ['events' => 0, 'profiles' => 0, 'skipped' => true];
        if ($days <= 0) return $out;
        $out['skipped'] = false;
        $now = $now ?? time();
        $cutoff = date('Y-m-d H:i:s', $now - $days * 86400);

        // 事件（SQLite）
        try {
            if (class_exists('Database')) {
                $out['events'] = Database::execute("DELETE FROM events WHERE created_at < ?", [$cutoff]);
            }
        } catch (\Throwable $e) {}

        // 画像（SQLite，T0-1 后）：按 updated_at 过期清
        try {
            if (function_exists('cdp_profile_ensure')) {
                cdp_profile_ensure();
                $out['profiles'] = Database::execute("DELETE FROM cdp_profiles WHERE updated_at <> '' AND updated_at < ?", [$cutoff]);
            }
        } catch (\Throwable $e) {}

        return $out;
    }

    /** 前端横幅所需配置（供 API 下发）。 */
    function consent_banner_config(): array {
        $c = consent_settings();
        return ['mode' => $c['mode'], 'text' => $c['banner_text'], 'need' => $c['mode'] !== 'off'];
    }
}
