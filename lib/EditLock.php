<?php
/**
 * EditLock —— 轻量编辑锁（AUDIT-01 / BACKLOG T2-2）
 *
 * 【为什么】目标人群是一人公司，实时协同优先级低；但"两个标签页/两个人同时编辑
 * 同一篇，后保存的把先保存的覆盖掉"是真实会发生的数据丢失。做个便宜的编辑锁即可。
 *
 * 【设计】开锁即占用，带 TTL 自动过期（避免忘了关页面就永久锁死）；
 * 同一持有者可续期；他人可看到"谁在编、还剩多久"；提供强制接管（记录谁抢的）。
 * 存 data/edit-locks.json，依赖极小，可注入 now 便于测试。
 */

if (!function_exists('editlock_file')) {

    function editlock_file(): string { return DATA_DIR . '/edit-locks.json'; }
    function editlock_ttl(): int { return 15 * 60; }   // 15 分钟无续期即释放

    function editlock_all(): array {
        $d = function_exists('json_read') ? json_read(editlock_file()) : [];
        return is_array($d) ? $d : [];
    }
    function editlock_save_all(array $l): void {
        if (function_exists('json_write')) json_write(editlock_file(), $l);
    }

    /** 清掉过期锁并返回剩余。 */
    function editlock_gc(?int $now = null): array {
        $now = $now ?? time();
        $all = editlock_all();
        $keep = [];
        foreach ($all as $k => $v) {
            if (!is_array($v)) continue;
            if (($now - (int)($v['at'] ?? 0)) < editlock_ttl()) $keep[$k] = $v;
        }
        if (count($keep) !== count($all)) editlock_save_all($keep);
        return $keep;
    }

    /**
     * 尝试取锁。返回：
     *  ['ok'=>true,'own'=>true]                     取到（或本来就是自己的，已续期）
     *  ['ok'=>false,'holder'=>名字,'remaining'=>秒]  被别人占着
     */
    function editlock_acquire(string $resource, string $userId, string $userName = '', ?int $now = null): array {
        $now = $now ?? time();
        $resource = trim($resource); $userId = trim($userId);
        if ($resource === '' || $userId === '') return ['ok' => false, 'error' => 'resource/userId 必填'];
        $all = editlock_gc($now);
        $cur = $all[$resource] ?? null;

        if ($cur && (string)$cur['user_id'] !== $userId) {
            return ['ok' => false, 'holder' => (string)($cur['user_name'] ?? $cur['user_id']),
                    'remaining' => max(0, editlock_ttl() - ($now - (int)$cur['at']))];
        }
        $all[$resource] = ['user_id' => $userId, 'user_name' => $userName ?: $userId, 'at' => $now];
        editlock_save_all($all);
        return ['ok' => true, 'own' => true];
    }

    /** 续期（编辑过程中心跳调用）。只有持有者能续。 */
    function editlock_renew(string $resource, string $userId, ?int $now = null): bool {
        $now = $now ?? time();
        $all = editlock_gc($now);
        if (!isset($all[$resource])) return false;
        if ((string)$all[$resource]['user_id'] !== trim($userId)) return false;
        $all[$resource]['at'] = $now;
        editlock_save_all($all);
        return true;
    }

    /** 释放（保存或离开时调用）。只有持有者能释放。 */
    function editlock_release(string $resource, string $userId): bool {
        $all = editlock_all();
        if (!isset($all[$resource])) return false;
        if ((string)$all[$resource]['user_id'] !== trim($userId)) return false;
        unset($all[$resource]);
        editlock_save_all($all);
        return true;
    }

    /** 强制接管（人确认后才调；记录接管者，便于追溯）。 */
    function editlock_takeover(string $resource, string $userId, string $userName = '', ?int $now = null): array {
        $now = $now ?? time();
        $all = editlock_all();
        $prev = $all[$resource]['user_name'] ?? '';
        $all[$resource] = ['user_id' => trim($userId), 'user_name' => $userName ?: $userId, 'at' => $now, 'took_over_from' => $prev];
        editlock_save_all($all);
        return ['ok' => true, 'from' => $prev];
    }

    /** 查询当前占用状态（不改变锁）。 */
    function editlock_status(string $resource, ?int $now = null): ?array {
        $all = editlock_gc($now);
        if (!isset($all[$resource])) return null;
        $v = $all[$resource];
        return ['user_id' => (string)$v['user_id'], 'user_name' => (string)($v['user_name'] ?? ''),
                'remaining' => max(0, editlock_ttl() - (($now ?? time()) - (int)$v['at']))];
    }
}
