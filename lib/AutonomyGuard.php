<?php
/**
 * AutonomyGuard —— 渐进自治护栏 + 目标制回路（AUDIT-03 / BACKLOG T2-4）
 *
 * 【为什么】大脑已经会判断、会给动作（P0-3/P1-7），但全自动执行有品牌/花钱/翻车
 * 风险。AUDIT-03 给的务实路线是**分级放权**：低风险动作可自动、高风险留人工关口，
 * 并用预算与频控兜住；再把结果回流成"这条策略有没有效"的目标制回路。
 *
 * 【三级】
 *   L0 propose  只提议（当前默认，等同 P1-7 行为）
 *   L1 guarded  护栏内自动：仅低风险动作，且未超预算/频控/静默期
 *   L2 goal     目标制自治：给目标+预算，护栏内自主决策（仍不碰高风险动作）
 * 高风险动作（发钱/群发/改价）**在任何级别都必须人工确认**——这是硬边界。
 *
 * 设置存 data/settings.json 的 autonomy；用量存 data/growth/autonomy-usage.json。
 */

if (!function_exists('autonomy_settings')) {

    function autonomy_settings(): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $a = is_array($s['autonomy'] ?? null) ? $s['autonomy'] : [];
        return array_merge([
            'level' => 'propose',        // propose | guarded | goal
            'daily_budget' => 0,         // 每日可自动花费上限（元），0=不允许花钱
            'daily_action_cap' => 20,    // 每日自动动作上限
            'quiet_days' => 3,           // 同一个人多少天内不重复自动打扰
        ], $a);
    }

    function autonomy_save(array $d): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $lvl = in_array(($d['level'] ?? 'propose'), ['propose','guarded','goal'], true) ? ($d['level'] ?? 'propose') : 'propose';
        $s['autonomy'] = [
            'level' => $lvl,
            'daily_budget' => max(0, (float)($d['daily_budget'] ?? 0)),
            'daily_action_cap' => max(0, (int)($d['daily_action_cap'] ?? 20)),
            'quiet_days' => max(0, (int)($d['quiet_days'] ?? 3)),
        ];
        if (function_exists('json_write')) json_write(DATA_DIR . '/settings.json', $s);
        return $s['autonomy'];
    }

    /** 高风险动作：任何自治级别都必须人工确认。 */
    function autonomy_high_risk(string $action, string $module = ''): bool {
        foreach (['发钱','退款','打款','群发','改价','折扣','优惠券','删除','下架'] as $w) {
            if (mb_strpos($action, $w) !== false) return true;
        }
        return false;
    }

    /** 低风险动作：打标/推荐/站内提示这类，允许护栏内自动。 */
    function autonomy_low_risk(string $action): bool {
        foreach (['打标','标签','推荐','内容','提示','培育','记录','评分'] as $w) {
            if (mb_strpos($action, $w) !== false) return true;
        }
        return false;
    }

    /* ─── 用量 ─── */
    function autonomy_usage_file(): string { return DATA_DIR . '/growth/autonomy-usage.json'; }
    function autonomy_usage(?string $day = null): array {
        $day = $day ?: date('Y-m-d');
        $all = function_exists('json_read') ? json_read(autonomy_usage_file()) : [];
        $d = is_array($all[$day] ?? null) ? $all[$day] : [];
        $m = array_merge(['actions' => 0, 'spend' => 0.0], $d);
        // 类型稳定：JSON 往返会把 5.0 存成 5，读回来统一成 int/float
        return ['actions' => (int)$m['actions'], 'spend' => (float)$m['spend']] + $m;
    }
    function autonomy_record(float $spend = 0.0, ?string $day = null): void {
        $day = $day ?: date('Y-m-d');
        $all = function_exists('json_read') ? json_read(autonomy_usage_file()) : [];
        $cur = array_merge(['actions' => 0, 'spend' => 0.0], is_array($all[$day] ?? null) ? $all[$day] : []);
        $cur['actions'] = (int)$cur['actions'] + 1;
        $cur['spend'] = round((float)$cur['spend'] + max(0, $spend), 2);
        $all[$day] = $cur;
        if (count($all) > 60) { ksort($all); $all = array_slice($all, -60, null, true); }
        if (function_exists('json_write')) { @mkdir(dirname(autonomy_usage_file()), 0755, true); json_write(autonomy_usage_file(), $all); }
    }

    /**
     * 判定一个动作能否自动执行。
     * $proposal: ['action','module','cost'?,'subject'?]
     * 返回 ['allow'=>bool,'reason'=>string,'requires_human'=>bool]
     */
    function autonomy_can_auto(array $proposal, ?array $cfg = null, ?array $usage = null): array {
        $cfg = $cfg ?? autonomy_settings();
        $usage = $usage ?? autonomy_usage();
        $action = (string)($proposal['action'] ?? '');
        $cost = (float)($proposal['cost'] ?? 0);

        if ($cfg['level'] === 'propose') {
            return ['allow' => false, 'requires_human' => true, 'reason' => '当前为「只提议」级别，全部动作交人确认'];
        }
        if (autonomy_high_risk($action, (string)($proposal['module'] ?? ''))) {
            return ['allow' => false, 'requires_human' => true, 'reason' => '高风险动作，任何级别都需人工确认'];
        }
        if (!autonomy_low_risk($action)) {
            return ['allow' => false, 'requires_human' => true, 'reason' => '不在低风险白名单内，交人确认'];
        }
        if ((int)$cfg['daily_action_cap'] > 0 && (int)$usage['actions'] >= (int)$cfg['daily_action_cap']) {
            return ['allow' => false, 'requires_human' => true, 'reason' => '已达今日自动动作上限'];
        }
        if ($cost > 0) {
            if ((float)$cfg['daily_budget'] <= 0) {
                return ['allow' => false, 'requires_human' => true, 'reason' => '未设自动预算，花钱动作需人工确认'];
            }
            if ((float)$usage['spend'] + $cost > (float)$cfg['daily_budget']) {
                return ['allow' => false, 'requires_human' => true, 'reason' => '超出今日预算'];
            }
        }
        // 静默期：同一个人短期内不重复自动打扰
        $subject = trim((string)($proposal['subject'] ?? ''));
        if ($subject !== '' && (int)$cfg['quiet_days'] > 0 && function_exists('gmem_touched')) {
            try {
                if (gmem_touched($subject, (int)$cfg['quiet_days'])) {
                    return ['allow' => false, 'requires_human' => true, 'reason' => '该联系人在静默期内，避免重复打扰'];
                }
            } catch (\Throwable $e) {}
        }
        return ['allow' => true, 'requires_human' => false, 'reason' => '护栏内允许自动执行'];
    }

    /**
     * 目标制回路：把"自动做了多少、带来多少结果"对齐到当前目标，
     * 给出继续/收敛的建议（人看，不自动改配置）。
     */
    function autonomy_loop_report(?array $progress = null, ?array $usage = null): array {
        $usage = $usage ?? autonomy_usage();
        $progress = $progress ?? (function_exists('growth_goal_progress') ? growth_goal_progress() : ['has' => false]);
        if (empty($progress['has'])) {
            return ['has_goal' => false, 'advice' => '还没设增长目标——设一个，自治才有方向。', 'usage' => $usage];
        }
        $pct = (int)($progress['pct'] ?? 0);
        $acts = (int)$usage['actions'];
        if ($acts === 0) {
            $advice = '今天还没有自动动作。若希望大脑多做事，可提高动作上限或放宽静默期。';
        } elseif ($pct >= 100) {
            $advice = '目标已达成，建议把级别调回「只提议」，避免过度打扰。';
        } elseif (($progress['pace_note'] ?? '') === '落后进度') {
            $advice = "落后进度且今日已自动 {$acts} 次——问题可能不在数量，检查动作是否对症（看采纳箱的忽略率）。";
        } else {
            $advice = "进度 {$pct}%，今日自动 {$acts} 次，节奏正常，保持。";
        }
        return ['has_goal' => true, 'pct' => $pct, 'usage' => $usage, 'advice' => $advice];
    }

    function autonomy_levels(): array {
        return [
            'propose' => '只提议（最保守，人一键采纳）',
            'guarded' => '护栏内自动（仅低风险动作 + 预算/频控/静默期）',
            'goal'    => '目标制自治（给目标与预算，护栏内自主决策）',
        ];
    }
}
