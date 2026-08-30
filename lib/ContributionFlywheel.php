<?php
/**
 * ContributionFlywheel —— 贡献即复利（AUDIT-06 创新四 / BACKLOG T2-10）
 *
 * 【为什么】在位生态是"货架越长，单个创作者越淹没"（负外部性）；
 * OIA 生态该是"参与者越多，公共能力池越厚，每个人的 Agent 越强"（正外部性）。
 * 这是拆墙之后真正不可复制的护城河，但它不会自动发生——需要把
 * **贡献之间的互相调用与复用**记录下来、算出来、回馈给贡献者。
 *
 * 【三件事】
 *   ① 复用记账：A 的技能被 B 调用 / C 的内容被站点 Agent 引用 → 记一笔
 *   ② 复利分数：贡献者的影响力 = 直接使用 + 被他人贡献物引用的二阶传播
 *   ③ 公共能力池：把高复用的贡献物提为"平台公共能力"，人人可直接用
 */

if (!function_exists('flywheel_file')) {

    function flywheel_file(): string { return DATA_DIR . '/ecosystem/reuse-log.json'; }
    function flywheel_all(): array {
        $d = function_exists('json_read') ? json_read(flywheel_file()) : [];
        return is_array($d) ? $d : [];
    }
    function flywheel_save(array $l): void {
        if (function_exists('json_write')) { @mkdir(dirname(flywheel_file()), 0755, true); json_write(flywheel_file(), $l); }
    }

    /**
     * ① 记一次复用。
     * $from: 使用方（可为 'agent' / 'platform' / 某贡献物 uid）
     * $to:   被使用的贡献物 uid（kind:id）
     */
    function flywheel_record(string $to, string $from = 'agent', string $kind = 'call'): array {
        $to = trim($to); if ($to === '') return ['ok' => false, 'error' => '缺少被使用方'];
        $l = flywheel_all();
        $l[] = ['to' => $to, 'from' => trim($from) ?: 'agent',
                'kind' => in_array($kind, ['call','cite','install','remix'], true) ? $kind : 'call',
                'at' => date('Y-m-d H:i:s')];
        if (count($l) > 5000) $l = array_slice($l, -5000);
        flywheel_save($l);
        return ['ok' => true];
    }

    /** 某贡献物被用了多少次（按类型拆）。 */
    function flywheel_usage(string $uid, ?array $log = null): array {
        $log = $log ?? flywheel_all();
        $out = ['total' => 0, 'by_kind' => [], 'by_from' => []];
        foreach ($log as $r) {
            if (($r['to'] ?? '') !== $uid) continue;
            $out['total']++;
            $k = (string)($r['kind'] ?? 'call');
            $out['by_kind'][$k] = ($out['by_kind'][$k] ?? 0) + 1;
            $f = (string)($r['from'] ?? '');
            $out['by_from'][$f] = ($out['by_from'][$f] ?? 0) + 1;
        }
        return $out;
    }

    /**
     * ② 复利分数：直接使用 + 二阶传播。
     * 二阶＝"用了我的东西的那个贡献物，它自己又被用了多少次"——
     * 这正是复利：你的贡献让别人的贡献更有用，功劳有你一份。
     * $owners: uid => 贡献者id（用于按人聚合）
     */
    function flywheel_score(string $uid, ?array $log = null, float $secondWeight = 0.3): array {
        $log = $log ?? flywheel_all();
        $direct = flywheel_usage($uid, $log)['total'];

        // 找出"使用了我"的贡献物（from 是贡献物 uid，形如 kind:id）
        $consumers = [];
        foreach ($log as $r) {
            if (($r['to'] ?? '') !== $uid) continue;
            $f = (string)($r['from'] ?? '');
            if (strpos($f, ':') !== false) $consumers[$f] = true;
        }
        $second = 0;
        foreach (array_keys($consumers) as $c) $second += flywheel_usage($c, $log)['total'];

        return [
            'direct' => $direct,
            'second' => $second,
            'score' => round($direct + $second * $secondWeight, 2),
            'consumers' => array_keys($consumers),
        ];
    }

    /** 按贡献者聚合影响力（谁在让整个池子变厚）。 */
    function flywheel_leaderboard(array $owners, ?array $log = null, int $limit = 10): array {
        $log = $log ?? flywheel_all();
        $byOwner = [];
        foreach ($owners as $uid => $owner) {
            $s = flywheel_score((string)$uid, $log);
            $o = (string)$owner;
            if (!isset($byOwner[$o])) $byOwner[$o] = ['owner' => $o, 'score' => 0.0, 'direct' => 0, 'items' => 0];
            $byOwner[$o]['score'] += $s['score'];
            $byOwner[$o]['direct'] += $s['direct'];
            $byOwner[$o]['items']++;
        }
        $rows = array_values($byOwner);
        foreach ($rows as &$r) $r['score'] = round($r['score'], 2);
        unset($r);
        usort($rows, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($rows, 0, max(1, $limit));
    }

    /**
     * ③ 公共能力池：复用达阈值的贡献物提为平台公共能力（人人可直接用）。
     * 返回被提名的 uid 列表 —— 提名而非自动生效，仍走运营确认。
     */
    function flywheel_promote_candidates(array $uids, int $threshold = 10, ?array $log = null): array {
        $log = $log ?? flywheel_all();
        $out = [];
        foreach ($uids as $uid) {
            $s = flywheel_score((string)$uid, $log);
            if ($s['score'] >= $threshold) $out[] = ['uid' => (string)$uid, 'score' => $s['score'], 'direct' => $s['direct']];
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return $out;
    }

    /**
     * 飞轮健康度：参与者越多、池子越厚，人均可用能力应上升。
     * 这是判断"正外部性有没有真的发生"的指标。
     */
    function flywheel_health(int $contributors, int $artifacts, ?array $log = null): array {
        $log = $log ?? flywheel_all();
        $reuse = count($log);
        return [
            'contributors' => $contributors,
            'artifacts' => $artifacts,
            'reuse_events' => $reuse,
            'per_contributor' => $contributors > 0 ? round($artifacts / $contributors, 2) : 0.0,
            'reuse_per_artifact' => $artifacts > 0 ? round($reuse / $artifacts, 2) : 0.0,
            'healthy' => $artifacts > 0 && ($reuse / max(1, $artifacts)) >= 1.0,
        ];
    }
}
