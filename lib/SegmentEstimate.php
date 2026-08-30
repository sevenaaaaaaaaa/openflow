<?php
/**
 * SegmentEstimate —— 建群人数预估 + 群规模趋势（AUDIT-02 / BACKLOG T2-3）
 *
 * 【为什么】建分群时看不到"这条规则能圈到多少人"，只能存了再看——运营决策要看
 * 的恰恰是这个数。存储修好(T0-1 画像迁 SQLite)之后，实时预估才跑得动。
 * 另外群规模的变化趋势（涨还是掉）比某一天的绝对值更有意义。
 *
 * 【设计】预估走已有 SegmentEngine::matchSegment 逐画像判定（画像已在 SQLite，
 * 量大时按采样估算，避免建群面板卡住）；趋势按天快照存 data/cdp/segment_trend.json。
 */

if (!function_exists('segest_estimate')) {

    /** 超过这个画像数就采样估算，不做全量。 */
    function segest_sample_threshold(): int { return 5000; }

    /**
     * 预估某规则能圈到多少人。
     * $profiles 可注入（测试）；否则读全部画像。
     * 返回 ['count'=>估算人数,'total'=>画像总数,'sampled'=>bool,'rate'=>占比%]
     */
    function segest_estimate(array $segment, ?array $profiles = null): array {
        if ($profiles === null) {
            $profiles = [];
            try {
                if (function_exists('cdp_profile_all')) $profiles = cdp_profile_all();
                elseif (class_exists('CdpSystem')) $profiles = \CdpSystem::allProfiles();
            } catch (\Throwable $e) { $profiles = []; }
        }
        $total = count($profiles);
        if ($total === 0) return ['count' => 0, 'total' => 0, 'sampled' => false, 'rate' => 0];

        $threshold = segest_sample_threshold();
        $sampled = $total > $threshold;
        $subset = $profiles;
        if ($sampled) {
            // 均匀取样，保证代表性又不卡面板
            $step = (int)ceil($total / $threshold);
            $subset = [];
            $i = 0;
            foreach ($profiles as $k => $p) { if ($i++ % $step === 0) $subset[$k] = $p; }
        }

        $hit = 0;
        foreach ($subset as $p) {
            if (!is_array($p)) continue;
            if (segest_match($segment, $p)) $hit++;
        }
        $scanned = max(1, count($subset));
        $count = $sampled ? (int)round($hit / $scanned * $total) : $hit;

        return [
            'count' => $count, 'total' => $total, 'sampled' => $sampled,
            'rate' => $total ? round($count / $total * 100, 1) : 0,
        ];
    }

    /** 判定单个画像是否命中规则（优先用 SegmentEngine，缺失时用内置简版）。 */
    function segest_match(array $segment, array $profile): bool {
        try {
            if (class_exists('SegmentEngine') && method_exists('SegmentEngine', 'matchSegment')) {
                return (bool)\SegmentEngine::matchSegment($segment, $profile);
            }
        } catch (\Throwable $e) {}
        // 内置简版：rules[{field,op,value}] 全部满足
        foreach ((array)($segment['rules'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $field = (string)($r['field'] ?? '');
            $op = (string)($r['op'] ?? 'eq');
            $val = $r['value'] ?? '';
            $actual = $profile[$field] ?? ($profile['properties'][$field] ?? null);
            if (is_array($actual)) $actual = implode(',', $actual);
            switch ($op) {
                case 'eq':  if ((string)$actual !== (string)$val) return false; break;
                case 'neq': if ((string)$actual === (string)$val) return false; break;
                case 'gt':  if (!((float)$actual > (float)$val)) return false; break;
                case 'gte': if (!((float)$actual >= (float)$val)) return false; break;
                case 'lt':  if (!((float)$actual < (float)$val)) return false; break;
                case 'contains': if ($actual === null || mb_strpos((string)$actual, (string)$val) === false) return false; break;
                case 'not_empty': if ($actual === null || $actual === '' ) return false; break;
                case 'empty': if (!($actual === null || $actual === '')) return false; break;
            }
        }
        return true;
    }

    /* ─────────── 规模趋势 ─────────── */

    function segest_trend_file(): string { return DATA_DIR . '/cdp/segment_trend.json'; }
    function segest_trend_all(): array {
        $d = function_exists('json_read') ? json_read(segest_trend_file()) : [];
        return is_array($d) ? $d : [];
    }

    /** 记录某群今日规模（同日覆盖，天然幂等）。 */
    function segest_snapshot(string $segmentId, int $count, ?string $day = null): void {
        $day = $day ?: date('Y-m-d');
        $all = segest_trend_all();
        $all[$segmentId][$day] = $count;
        // 只留最近 90 天
        if (count($all[$segmentId]) > 90) {
            ksort($all[$segmentId]);
            $all[$segmentId] = array_slice($all[$segmentId], -90, null, true);
        }
        if (function_exists('json_write')) { @mkdir(dirname(segest_trend_file()), 0755, true); json_write(segest_trend_file(), $all); }
    }

    /**
     * 取趋势：最近 N 天序列 + 环比变化。
     * 返回 ['series'=>[day=>count], 'latest'=>int, 'delta'=>int, 'direction'=>'up|down|flat']
     */
    function segest_trend(string $segmentId, int $days = 30): array {
        $s = segest_trend_all()[$segmentId] ?? [];
        ksort($s);
        $s = array_slice($s, -max(1, $days), null, true);
        $vals = array_values($s);
        $latest = (int)(end($vals) ?: 0);
        $prev = count($vals) >= 2 ? (int)$vals[count($vals) - 2] : $latest;
        $delta = $latest - $prev;
        return [
            'series' => $s, 'latest' => $latest, 'delta' => $delta,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
        ];
    }
}
