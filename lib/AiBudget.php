<?php
/**
 * AiBudget —— AI 的电表与保险丝（docs/ROADMAP.md 阶段一第 2 件）
 *
 * 【为什么】这是一个按 AI 调用花钱的产品，但在此之前：
 * 每次调用只记了「哪个供应商、成没成功」，**没有 token、没有成本、没有是哪个功能花的**。
 * 也就是说，"这个月 AI 花了多少钱、花在哪"这个对一人公司最要紧的数字，答不出来。
 *
 * 这一层做两件事：
 *   ① 电表：每次调用记一行——哪个功能、哪个模型、进出多少 token、多久、成没成。
 *      **token 是实测的事实**（从供应商响应里读），成本是由可配单价推算出来的。
 *      单价没配就只记 token 不记钱——宁可没有数字，也不给一个假的数字。
 *   ② 保险丝：日额度。超了就拦下后续调用，让调用方降级（而不是继续烧）。
 *      额度默认关闭（不给别人的生产环境塞一个我拍脑袋的数），但装好了随时能开。
 *      公开接口另有一档更低的额度，这样外部滥用烧不穿站长自己的用量。
 *
 * 依赖仅 Database + DATA_DIR，便于隔离测试。
 */

require_once __DIR__ . '/Database.php';

if (!function_exists('ai_usage_ensure')) {

    /** 建表。返回 false 表示 SQLite 不可用（调用方静默跳过记账，不影响 AI 本身）。 */
    function ai_usage_ensure(): bool {
        static $ready = null;
        if ($ready !== null) return $ready;
        try {
            Database::execute("CREATE TABLE IF NOT EXISTS ai_usage (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                at         TEXT DEFAULT '',
                day        TEXT DEFAULT '',
                feature    TEXT DEFAULT '',
                tier       TEXT DEFAULT 'admin',
                provider   TEXT DEFAULT '',
                model      TEXT DEFAULT '',
                in_tokens  INTEGER DEFAULT 0,
                out_tokens INTEGER DEFAULT 0,
                cost       REAL DEFAULT 0,
                ms         INTEGER DEFAULT 0,
                ok         INTEGER DEFAULT 1,
                error      TEXT DEFAULT ''
            )");
            Database::execute("CREATE INDEX IF NOT EXISTS idx_ai_usage_day ON ai_usage(day)");
            Database::execute("CREATE INDEX IF NOT EXISTS idx_ai_usage_day_tier ON ai_usage(day, tier)");
            Database::execute("CREATE INDEX IF NOT EXISTS idx_ai_usage_feature ON ai_usage(day, feature)");
            $ready = true;
        } catch (\Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    // ─── 设置 ────────────────────────────────────────────

    /**
     * 预算设置。
     *   daily_cost_cap   日成本上限（站点币种，0 = 不限）——需要先配单价才有意义
     *   daily_token_cap  日 token 上限（0 = 不限）——不依赖单价，配不配价都能用
     *   public_cost_cap  公开接口（访客侧）单独的日成本上限，0 = 跟随总额度
     *   public_token_cap 公开接口单独的日 token 上限
     *   public_call_cap  公开接口每日调用次数上限（默认 500，这是唯一默认开着的闸）
     *   currency         只用于显示
     */
    function ai_budget_settings(): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $b = is_array($s['ai_budget'] ?? null) ? $s['ai_budget'] : [];
        return array_merge([
            'daily_cost_cap'   => 0.0,
            'daily_token_cap'  => 0,
            'public_cost_cap'  => 0.0,
            'public_token_cap' => 0,
            'public_call_cap'  => 500,
            'currency'         => '¥',
        ], $b);
    }

    function ai_budget_save(array $patch): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $cur = ai_budget_settings();
        foreach (['daily_cost_cap', 'public_cost_cap'] as $k) if (isset($patch[$k])) $cur[$k] = max(0.0, (float)$patch[$k]);
        foreach (['daily_token_cap', 'public_token_cap', 'public_call_cap'] as $k) if (isset($patch[$k])) $cur[$k] = max(0, (int)$patch[$k]);
        if (isset($patch['currency'])) $cur['currency'] = mb_substr((string)$patch['currency'], 0, 4);
        $s['ai_budget'] = $cur;
        if (function_exists('json_write')) json_write(DATA_DIR . '/settings.json', $s);
        return $cur;
    }

    /**
     * 单价表：模型 → ['in' => 每百万输入 token 单价, 'out' => 每百万输出 token 单价]。
     *
     * **默认是空的**，这是刻意的：各家单价差几十倍且经常变，内置一份猜的价钱
     * 只会让看板给出一个看起来精确、其实是错的数字。没配单价时只记 token 不记钱，
     * 后台会把「用过但没配价的模型」列出来提示补录。
     */
    function ai_price_table(): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $p = is_array($s['ai_prices'] ?? null) ? $s['ai_prices'] : [];
        $out = [];
        foreach ($p as $model => $v) {
            if (!is_array($v)) continue;
            $out[(string)$model] = ['in' => (float)($v['in'] ?? 0), 'out' => (float)($v['out'] ?? 0)];
        }
        return $out;
    }

    function ai_price_save(string $model, float $in, float $out): void {
        $model = trim($model);
        if ($model === '') return;
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        if (!is_array($s['ai_prices'] ?? null)) $s['ai_prices'] = [];
        $s['ai_prices'][$model] = ['in' => max(0.0, $in), 'out' => max(0.0, $out)];
        if (function_exists('json_write')) json_write(DATA_DIR . '/settings.json', $s);
    }

    /** 按单价估算一次调用的成本；模型没配单价则返回 0.0（不猜）。 */
    function ai_estimate_cost(string $model, int $inTokens, int $outTokens): float {
        $t = ai_price_table();
        $p = $t[$model] ?? null;
        if ($p === null) {
            // 允许用前缀配价（如给 "gpt-4o" 配价，"gpt-4o-2026-01" 也能命中）
            foreach ($t as $k => $v) {
                if ($k !== '' && strncasecmp($model, $k, strlen($k)) === 0) { $p = $v; break; }
            }
        }
        if ($p === null) return 0.0;
        return round($inTokens / 1000000 * $p['in'] + $outTokens / 1000000 * $p['out'], 6);
    }

    // ─── 电表：记账与汇总 ─────────────────────────────────

    /** 记一次调用。绝不抛异常——记账失败不能拖垮 AI 调用本身。 */
    function ai_usage_record(array $row): void {
        if (!ai_usage_ensure()) return;
        try {
            $model = (string)($row['model'] ?? '');
            $in = max(0, (int)($row['in_tokens'] ?? 0));
            $out = max(0, (int)($row['out_tokens'] ?? 0));
            $cost = isset($row['cost']) ? (float)$row['cost'] : ai_estimate_cost($model, $in, $out);
            Database::execute(
                "INSERT INTO ai_usage (at, day, feature, tier, provider, model, in_tokens, out_tokens, cost, ms, ok, error)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    date('Y-m-d H:i:s'), date('Y-m-d'),
                    mb_substr((string)($row['feature'] ?? 'unknown'), 0, 60),
                    in_array(($row['tier'] ?? 'admin'), ['public', 'admin', 'batch'], true) ? (string)$row['tier'] : 'admin',
                    mb_substr((string)($row['provider'] ?? ''), 0, 40),
                    mb_substr($model, 0, 80),
                    $in, $out, $cost,
                    max(0, (int)($row['ms'] ?? 0)),
                    !empty($row['ok']) ? 1 : 0,
                    mb_substr((string)($row['error'] ?? ''), 0, 200),
                ]
            );
        } catch (\Throwable $e) {}
    }

    /** 某天的用量汇总（默认今天）；可只看某一档。 */
    function ai_spend(string $day = '', string $tier = ''): array {
        $day = $day ?: date('Y-m-d');
        $zero = ['calls' => 0, 'in_tokens' => 0, 'out_tokens' => 0, 'tokens' => 0, 'cost' => 0.0, 'failed' => 0];
        if (!ai_usage_ensure()) return $zero;
        try {
            $sql = "SELECT COUNT(*) c, COALESCE(SUM(in_tokens),0) i, COALESCE(SUM(out_tokens),0) o,
                           COALESCE(SUM(cost),0) k, COALESCE(SUM(CASE WHEN ok=0 THEN 1 ELSE 0 END),0) f
                    FROM ai_usage WHERE day = ?";
            $args = [$day];
            if ($tier !== '') { $sql .= " AND tier = ?"; $args[] = $tier; }
            $r = Database::query($sql, $args);
            if (!isset($r[0])) return $zero;
            $i = (int)$r[0]['i']; $o = (int)$r[0]['o'];
            return ['calls' => (int)$r[0]['c'], 'in_tokens' => $i, 'out_tokens' => $o,
                    'tokens' => $i + $o, 'cost' => round((float)$r[0]['k'], 4), 'failed' => (int)$r[0]['f']];
        } catch (\Throwable $e) { return $zero; }
    }

    /** 按功能拆分（看板用：钱花在哪个功能上）。 */
    function ai_spend_by(string $groupBy = 'feature', int $days = 7): array {
        $groupBy = in_array($groupBy, ['feature', 'model', 'provider', 'tier', 'day'], true) ? $groupBy : 'feature';
        if (!ai_usage_ensure()) return [];
        try {
            $since = date('Y-m-d', time() - max(0, $days - 1) * 86400);
            return Database::query(
                "SELECT {$groupBy} AS k, COUNT(*) AS calls,
                        COALESCE(SUM(in_tokens),0) AS in_tokens, COALESCE(SUM(out_tokens),0) AS out_tokens,
                        COALESCE(SUM(cost),0) AS cost, COALESCE(AVG(ms),0) AS avg_ms,
                        COALESCE(SUM(CASE WHEN ok=0 THEN 1 ELSE 0 END),0) AS failed
                 FROM ai_usage WHERE day >= ?
                 GROUP BY {$groupBy} ORDER BY cost DESC, calls DESC", [$since]);
        } catch (\Throwable $e) { return []; }
    }

    /** 用过但还没配单价的模型（后台提示补录用）。 */
    function ai_models_without_price(int $days = 30): array {
        $seen = ai_spend_by('model', $days);
        $out = [];
        foreach ($seen as $r) {
            $m = (string)($r['k'] ?? '');
            if ($m === '') continue;
            if (ai_estimate_cost($m, 1000000, 0) <= 0 && ai_estimate_cost($m, 0, 1000000) <= 0) {
                $out[] = ['model' => $m, 'calls' => (int)$r['calls'],
                          'tokens' => (int)$r['in_tokens'] + (int)$r['out_tokens']];
            }
        }
        return $out;
    }

    // ─── 保险丝：额度闸门 ─────────────────────────────────

    /**
     * 这次调用还能不能发。
     * @param string $tier public（访客侧）/ admin（后台交互）/ batch（后台批处理）
     * @return array ['allowed'=>bool, 'reason'=>string, 'hint'=>string]
     *
     * 公开档先撞自己那道更低的闸，所以外部滥用烧不穿站长自己的用量。
     */
    function ai_budget_check(string $tier = 'admin'): array {
        $ok = ['allowed' => true, 'reason' => '', 'hint' => ''];
        $b = ai_budget_settings();
        $cur = ai_spend();                       // 今日全站

        if ($b['daily_cost_cap'] > 0 && $cur['cost'] >= $b['daily_cost_cap']) {
            return ['allowed' => false, 'reason' => 'daily_cost_cap',
                    'hint' => "今日 AI 花费已达上限（{$b['currency']}{$b['daily_cost_cap']}），已暂停调用。"];
        }
        if ($b['daily_token_cap'] > 0 && $cur['tokens'] >= $b['daily_token_cap']) {
            return ['allowed' => false, 'reason' => 'daily_token_cap',
                    'hint' => '今日 AI 用量已达上限，已暂停调用。'];
        }
        if ($tier !== 'public') return $ok;

        $pub = ai_spend('', 'public');
        if ($b['public_call_cap'] > 0 && $pub['calls'] >= $b['public_call_cap']) {
            return ['allowed' => false, 'reason' => 'public_call_cap',
                    'hint' => '今日访客侧 AI 问答已达上限，已切换为仅检索站内知识。'];
        }
        if ($b['public_cost_cap'] > 0 && $pub['cost'] >= $b['public_cost_cap']) {
            return ['allowed' => false, 'reason' => 'public_cost_cap',
                    'hint' => '今日访客侧 AI 花费已达上限，已切换为仅检索站内知识。'];
        }
        if ($b['public_token_cap'] > 0 && $pub['tokens'] >= $b['public_token_cap']) {
            return ['allowed' => false, 'reason' => 'public_token_cap',
                    'hint' => '今日访客侧 AI 用量已达上限，已切换为仅检索站内知识。'];
        }
        return $ok;
    }

    /** 从各家响应里取 token 用量（形状不同，统一成 [in, out]）。 */
    function ai_extract_usage(array $data): array {
        $u = $data['usage'] ?? [];
        if (!is_array($u)) $u = [];
        // OpenAI 兼容：prompt_tokens / completion_tokens
        // Anthropic：input_tokens / output_tokens
        // 部分国产：total_tokens 只给总数
        $in  = (int)($u['prompt_tokens'] ?? $u['input_tokens'] ?? 0);
        $out = (int)($u['completion_tokens'] ?? $u['output_tokens'] ?? 0);
        if ($in === 0 && $out === 0 && isset($u['total_tokens'])) {
            $out = (int)$u['total_tokens'];      // 只有总数时全算作输出（偏保守）
        }
        return [$in, $out];
    }
}
