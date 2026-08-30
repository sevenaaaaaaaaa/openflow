<?php
/**
 * GrowthGoal —— 共享增长目标（AUDIT-07 P1-5）
 *
 * 【它解决什么】P0-3 的大脑用固定优先级排提议，不知道"你此刻到底想要什么"。
 * 本模块让人用一句话设一个增长目标（指标 + 目标值 + 周期），存成一个各模块共享的
 * 目标对象；大脑据此给"离目标最近的动作"加权（growth_brain 消费），并算出实时进度。
 *
 * 【原则】纯本地 JSON、无外部依赖；进度计算按指标就地取数（成交额/成交单从 P0-2
 * 成交真相账本，会员/线索从计数），取不到就优雅显示 0。目标是"共享上下文"，
 * 不是又一套报表系统——够大脑用、够人一眼看进度即可。
 */

require_once __DIR__ . '/GrowthSignal.php';

if (!function_exists('growth_goal_file')) {
    function growth_goal_file(): string { return DATA_DIR . '/growth/goals.json'; }
}

if (!function_exists('growth_goal_metrics')) {
    /** 支持的指标 → 展示名 + 单位前后缀。 */
    function growth_goal_metrics(): array {
        return [
            'revenue' => ['label' => '成交额', 'prefix' => '¥', 'suffix' => ''],
            'won'     => ['label' => '成交单数', 'prefix' => '', 'suffix' => ' 单'],
            'members' => ['label' => '会员数', 'prefix' => '', 'suffix' => ' 人'],
            'leads'   => ['label' => '线索数', 'prefix' => '', 'suffix' => ' 条'],
        ];
    }
}

if (!function_exists('growth_goal_all')) {
    function growth_goal_all(): array {
        $f = growth_goal_file();
        if (!is_file($f)) return [];
        $d = json_decode((string)file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }
    function growth_goal_save_all(array $list): void {
        $f = growth_goal_file();
        @mkdir(dirname($f), 0777, true);
        @file_put_contents($f, json_encode(array_values($list), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

if (!function_exists('growth_goal_current')) {
    /** 当前生效目标（status=active 的最后一个），无则 null。 */
    function growth_goal_current(): ?array {
        $active = array_values(array_filter(growth_goal_all(), fn($g) => ($g['status'] ?? '') === 'active'));
        return $active ? end($active) : null;
    }
}

if (!function_exists('growth_goal_set')) {
    /**
     * 设定/替换当前目标（同一时刻只保留一个 active）。
     * $data: ['title','metric','target','window_days'(可选)]。返回目标对象。
     */
    function growth_goal_set(array $data): array {
        $metric = (string)($data['metric'] ?? 'revenue');
        if (!isset(growth_goal_metrics()[$metric])) $metric = 'revenue';
        $goal = [
            'id'          => 'g_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4),
            'title'       => trim((string)($data['title'] ?? '')) ?: '增长目标',
            'metric'      => $metric,
            'target'      => max(0.0, (float)($data['target'] ?? 0)),
            'window_days' => max(0, (int)($data['window_days'] ?? 0)),
            'baseline'    => (float)($data['baseline'] ?? growth_goal_current_value($metric)),
            'status'      => 'active',
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        $list = array_map(function ($g) { $g['status'] = 'archived'; return $g; }, growth_goal_all());
        $list[] = $goal;
        growth_goal_save_all($list);
        return $goal;
    }
    function growth_goal_clear(): void {
        growth_goal_save_all(array_map(function ($g) { $g['status'] = 'archived'; return $g; }, growth_goal_all()));
    }
}

if (!function_exists('growth_goal_current_value')) {
    /** 就地取指标当前值。取不到返回 0，绝不抛。 */
    function growth_goal_current_value(string $metric): float {
        try {
            switch ($metric) {
                case 'revenue':
                    $t = growth_conversion_truth();
                    return (float)($t['total']['revenue'] ?? 0);
                case 'won':
                    $t = growth_conversion_truth();
                    return (float)($t['total']['count'] ?? 0);
                case 'members':
                    return (float)count(_gg_read(DATA_DIR . '/members/index.json'));
                case 'leads':
                    return (float)count(_gg_read(DATA_DIR . '/crm.json'));
            }
        } catch (\Throwable $e) {}
        return 0.0;
    }
    function _gg_read(string $f): array {
        if (function_exists('json_read')) return json_read($f);
        if (!is_file($f)) return [];
        $d = json_decode((string)file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }
}

if (!function_exists('growth_goal_progress')) {
    /**
     * 目标进度。$current 可注入（便于测试）；否则就地取数。
     * 目标以"基线以上的增量"计：progress = 当前值 - 基线，去比 目标。
     * 返回：metric/label/target/current/gain/pct/remaining/display/pace_note。
     */
    function growth_goal_progress(?array $goal = null, ?float $current = null): array {
        $goal = $goal ?? growth_goal_current();
        if (!$goal) return ['has' => false];
        $metric = (string)($goal['metric'] ?? 'revenue');
        $meta   = growth_goal_metrics()[$metric] ?? ['label' => $metric, 'prefix' => '', 'suffix' => ''];
        $target = max(0.0, (float)($goal['target'] ?? 0));
        $base   = (float)($goal['baseline'] ?? 0);
        $cur    = $current !== null ? $current : growth_goal_current_value($metric);
        $gain   = max(0.0, $cur - $base);
        $pct    = $target > 0 ? min(100, (int)round($gain / $target * 100)) : 0;

        // 时间进度（有周期才算）：领先/落后
        $paceNote = '';
        $wd = (int)($goal['window_days'] ?? 0);
        if ($wd > 0 && $target > 0) {
            $start = strtotime((string)($goal['created_at'] ?? 'now')) ?: time();
            $elapsed = max(0, (time() - $start) / 86400);
            $expectPct = min(100, (int)round(min(1, $elapsed / $wd) * 100));
            if ($pct >= $expectPct) $paceNote = '领先进度';
            elseif ($expectPct - $pct >= 15) $paceNote = '落后进度';
            else $paceNote = '大致跟上';
        }

        $fmt = fn($v) => $meta['prefix'] . number_format($v) . $meta['suffix'];
        return [
            'has' => true, 'metric' => $metric, 'label' => $meta['label'],
            'title' => (string)($goal['title'] ?? ''),
            'target' => $target, 'current' => $cur, 'gain' => $gain,
            'pct' => $pct, 'remaining' => max(0.0, $target - $gain),
            'display' => $fmt($gain) . ' / ' . $fmt($target),
            'pace_note' => $paceNote,
        ];
    }
}

if (!function_exists('growth_goal_boost_modules')) {
    /**
     * 目标指标 → 该给哪些模块的提议加权（+多少优先级）。大脑据此让提议偏向目标。
     */
    function growth_goal_boost_modules(string $metric): array {
        switch ($metric) {
            case 'revenue':
            case 'won':    return ['Sales' => 14, 'MA' => 8];   // 要钱：临门一脚/复购/挽回
            case 'members':return ['Content' => 12, 'MA' => 10]; // 要人：内容养熟 + 触达促注册
            case 'leads':  return ['Content' => 12, 'Sales' => 6]; // 要线索：内容获客 + 挽回
        }
        return [];
    }
}
