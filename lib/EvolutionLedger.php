<?php
declare(strict_types=1);
/**
 * 自我进化台账 EvolutionLedger
 *
 * 【为什么需要它】SelfEvolve 只产出「建议」，没有「动作」与「结果」——
 * 于是"自我进化"对外不可核验，也无法判断到底有没有用。
 *
 * 本模块补齐三件事，全部可回溯：
 *   1) 指标快照：关键指标现算并定期落盘（用于前后对比）
 *   2) 动作记录：某个建议采取了什么动作（系统自动 / 人做的 / 需人审未做）
 *   3) 结果结算：动作到期后用真实指标判定 improved / flat / worse
 *
 * 【诚实约束】没有对比快照 → 判级只能是 unknown/pending，绝不允许凭空标成"已改善"。
 * 对外/对内文案只允许讲「机制 + 可验证结果」，不讲「自动变强」。
 */

final class EvolutionLedger
{
    private static string $file = DATA_DIR . '/evolution-ledger.json';
    private static int $keepDays = 90;

    /** 指标定义：label / 单位 / 期望方向（up_good=越大越好，down_good=越小越好） */
    public static function metric_defs(): array
    {
        return [
            'articles_published' => ['label' => '已发布内容',      'unit' => '篇',   'dir' => 'up_good'],
            'leads_total'        => ['label' => '线索总数',        'unit' => '条',   'dir' => 'up_good'],
            'orders_paid'        => ['label' => '已支付订单',      'unit' => '单',   'dir' => 'up_good'],
            'revenue'            => ['label' => '已支付金额',      'unit' => '元',   'dir' => 'up_good'],
            'tasks_overdue'      => ['label' => '逾期任务',        'unit' => '条',   'dir' => 'down_good'],
            'suggestions_open'   => ['label' => '待处理体检建议',  'unit' => '条',   'dir' => 'down_good'],
            'php_errors'         => ['label' => '后端报错行（尾部窗口）', 'unit' => '条', 'dir' => 'down_good'],
            'js_errors'          => ['label' => '前端报错',        'unit' => '条',   'dir' => 'down_good'],
            'not_found_404'      => ['label' => '404 记录',        'unit' => '条',   'dir' => 'down_good'],
            'cron_age_hours'     => ['label' => '定时任务停滞',    'unit' => '小时', 'dir' => 'down_good'],
        ];
    }

    /* ─────────── 指标现算 ─────────── */

    public static function metrics(): array
    {
        $m = [];
        // 内容
        $m['articles_published'] = 0;
        try {
            if (function_exists('get_articles')) {
                foreach ((array) get_articles() as $a) if (($a['status'] ?? '') === 'published') $m['articles_published']++;
            }
        } catch (\Throwable $e) {}
        // 线索
        $m['leads_total'] = count((array) json_read(DATA_DIR . '/leads.json'));
        // 订单与金额
        $orders = (array) json_read(DATA_DIR . '/shop/orders.json');
        $paid = 0; $revenue = 0.0;
        foreach ($orders as $o) {
            if (!is_array($o)) continue;
            $st = (string) ($o['status'] ?? '');
            if (!in_array($st, ['paid', 'completed', 'delivered'], true)) continue;
            $paid++;
            $revenue += (float) ($o['amount'] ?? $o['total'] ?? 0);
        }
        $m['orders_paid'] = $paid;
        $m['revenue'] = (int) round($revenue);
        // 逾期任务（团队协作）
        $m['tasks_overdue'] = 0;
        try {
            if (function_exists('ps_due_buckets')) $m['tasks_overdue'] = (int) (ps_due_buckets()['counts']['overdue'] ?? 0);
        } catch (\Throwable $e) {}
        // 体检建议未解决数
        $m['suggestions_open'] = 0;
        try {
            foreach ((array) (json_read(DATA_DIR . '/evolution.json')['suggestions'] ?? []) as $s) {
                if (($s['status'] ?? 'open') !== 'resolved') $m['suggestions_open']++;
            }
        } catch (\Throwable $e) {}
        // 后端报错（尾部窗口，按行计）
        $m['php_errors'] = 0;
        $log = dirname(__DIR__) . '/php-error.log';
        if (is_file($log)) {
            $lines = @file($log) ?: [];
            foreach (array_slice($lines, -400) as $ln) {
                if (preg_match('/PHP (Fatal error|Warning|TypeError|Parse error|Notice|Deprecated)/', (string) $ln)) $m['php_errors']++;
            }
        }
        // 前端报错上报（列表或映射都吃）
        $js = json_read(DATA_DIR . '/evolution-js-errors.json');
        $m['js_errors'] = count($js);
        // 404
        $m['not_found_404'] = count((array) json_read(DATA_DIR . '/evolution-404.json'));
        // cron 停滞小时数（无记录 → 记为 999，明确"未知/异常"而不是 0）
        $lastCron = (int) (json_read(DATA_DIR . '/cron-last.json')['ts'] ?? 0);
        $m['cron_age_hours'] = $lastCron > 0 ? (int) round((time() - $lastCron) / 3600) : 999;
        return $m;
    }

    /* ─────────── 快照 ─────────── */

    private static function state(): array
    {
        $s = json_read(self::$file);
        return [
            'snapshots' => array_values((array) ($s['snapshots'] ?? [])),
            'entries'   => (array) ($s['entries'] ?? []),
            'settled'   => array_values((array) ($s['settled'] ?? [])),
        ];
    }

    private static function save(array $state): bool
    {
        return json_write(self::$file, $state);
    }

    /** 记一次快照（同一天只留最后一条；保留 keepDays 天） */
    public static function snapshot(bool $force = false): array
    {
        $state = self::state();
        $today = date('Y-m-d');
        $metrics = self::metrics();
        $idx = null;
        foreach ($state['snapshots'] as $i => $s) if (($s['date'] ?? '') === $today) { $idx = $i; break; }
        $row = ['at' => time(), 'date' => $today, 'metrics' => $metrics];
        if ($idx !== null) {
            if (!$force && ($state['snapshots'][$idx]['at'] ?? 0) > time() - 3600) return $state['snapshots'][$idx];  // 一小时内不重复
            $state['snapshots'][$idx] = $row;
        } else {
            $state['snapshots'][] = $row;
        }
        $cut = date('Y-m-d', time() - self::$keepDays * 86400);
        $state['snapshots'] = array_values(array_filter($state['snapshots'], static fn(array $s): bool => (string) ($s['date'] ?? '') >= $cut));
        usort($state['snapshots'], static fn(array $a, array $b): int => (int) ($a['at'] ?? 0) <=> (int) ($b['at'] ?? 0));
        self::save($state);
        return $row;
    }

    public static function snapshots(int $days = 90): array
    {
        $cut = date('Y-m-d', time() - $days * 86400);
        return array_values(array_filter(self::state()['snapshots'], static fn(array $s): bool => (string) ($s['date'] ?? '') >= $cut));
    }

    /** 取 ≥N 天前最近的一条快照（用于对比） */
    public static function snapshot_before(int $days): ?array
    {
        $target = date('Y-m-d', time() - $days * 86400);
        $best = null;
        foreach (self::snapshots(self::$keepDays) as $s) {
            if ((string) ($s['date'] ?? '') <= $target) $best = $s;
        }
        return $best;
    }

    /**
     * 指标趋势：现在 vs N 天前。缺对比快照时 verdict=unknown（绝不假装改善）
     * @return array{metric:string,now:int|float,past:int|float|null,delta:float|null,verdict:string,dir:string}
     */
    public static function trend(string $metric, int $days = 7): array
    {
        $defs = self::metric_defs();
        $dir = (string) ($defs[$metric]['dir'] ?? 'up_good');
        $now = (float) (self::metrics()[$metric] ?? 0);
        $past = self::snapshot_before($days);
        if ($past === null) {
            return ['metric' => $metric, 'now' => $now, 'past' => null, 'delta' => null, 'verdict' => 'unknown', 'dir' => $dir];
        }
        $was = (float) (($past['metrics'] ?? [])[$metric] ?? 0);
        return ['metric' => $metric, 'now' => $now, 'past' => $was, 'delta' => $now - $was, 'verdict' => self::judge($dir, $was, $now), 'dir' => $dir];
    }

    /** 方向敏感判级；阈值：相对 5% 或绝对 1（取更容易触发的那个，避免大基数下永不动） */
    public static function judge(string $dir, float $before, float $after): string
    {
        $delta = $after - $before;
        if (abs($delta) < 1e-9) return 'flat';
        $base = max(abs($before), 1.0);
        $rel = abs($delta) / $base;
        if ($rel < 0.05 || abs($delta) < 1) return 'flat';   // 相对 5% 以内、或绝对变化不到 1 → 视为噪声
        $good = $dir === 'down_good' ? ($delta < 0) : ($delta > 0);
        return $good ? 'improved' : 'worse';
    }

    /* ─────────── 动作与结算 ─────────── */

    /** 记录动作：kind=auto|human|needs_review，guard=allowed|blocked */
    public static function record_action(string $id, array $action): bool
    {
        if ($id === '') return false;
        $kind = (string) ($action['kind'] ?? 'human');
        if (!in_array($kind, ['auto', 'human', 'needs_review'], true)) $kind = 'human';
        $state = self::state();
        $e = (array) ($state['entries'][$id] ?? []);
        $e['id'] = $id;
        $e['action'] = [
            'kind' => $kind,
            'summary' => mb_substr((string) ($action['summary'] ?? ''), 0, 300),
            'by' => (string) ($action['by'] ?? ''),
            'guard' => (string) ($action['guard'] ?? 'allowed'),
            'at' => date('Y-m-d H:i:s'),
        ];
        $state['entries'][$id] = $e;
        return self::save($state);
    }

    /** 为某条建议挂一个度量：记下当前值作为 before，N 天后结算 */
    public static function record_measure(string $id, string $metric, int $windowDays = 7): bool
    {
        if ($id === '' || !isset(self::metric_defs()[$metric])) return false;
        $state = self::state();
        $e = (array) ($state['entries'][$id] ?? []);
        $e['id'] = $id;
        $e['measure'] = [
            'metric' => $metric,
            'before' => (float) (self::metrics()[$metric] ?? 0),
            'window_days' => max(1, min(60, $windowDays)),
            'started_at' => date('Y-m-d H:i:s'),
            'due_date' => date('Y-m-d', time() + max(1, min(60, $windowDays)) * 86400),
            'verdict' => 'pending',
            'after' => null,
            'computed_at' => null,
            'note' => '',
        ];
        $state['entries'][$id] = $e;
        return self::save($state);
    }

    /**
     * 结算到期的度量（幂等：结算过的不会再算一次）。
     * 缺对比快照/缺指标 → 保持 pending 并写明原因，绝不假装改善。
     */
    public static function settle(string $today = ''): array
    {
        $today = $today !== '' ? $today : date('Y-m-d');
        $state = self::state();
        $done = [];
        foreach ($state['entries'] as $id => $e) {
            $ms = (array) ($e['measure'] ?? []);
            if ($ms === [] || (string) ($ms['verdict'] ?? '') !== 'pending') continue;
            if ((string) ($ms['due_date'] ?? '') > $today) continue;         // 还没到期
            $metric = (string) ($ms['metric'] ?? '');
            if (!isset(self::metric_defs()[$metric])) continue;
            $past = self::snapshot_before((int) ($ms['window_days'] ?? 7));
            if ($past === null) {
                $state['entries'][$id]['measure']['note'] = '缺少对比快照（快照不足 ' . (int) ($ms['window_days'] ?? 7) . ' 天），保持待验证';
                continue;
            }
            $after = (float) (self::metrics()[$metric] ?? 0);
            $was = (float) ($ms['before'] ?? (($past['metrics'] ?? [])[$metric] ?? 0));
            $verdict = self::judge((string) (self::metric_defs()[$metric]['dir'] ?? 'up_good'), $was, $after);
            $state['entries'][$id]['measure']['after'] = $after;
            $state['entries'][$id]['measure']['verdict'] = $verdict;
            $state['entries'][$id]['measure']['computed_at'] = date('Y-m-d H:i:s');
            $state['settled'][] = [
                'id' => $id, 'metric' => $metric, 'before' => $was, 'after' => $after,
                'verdict' => $verdict, 'at' => date('Y-m-d H:i:s'),
            ];
            $done[] = ['id' => $id, 'metric' => $metric, 'before' => $was, 'after' => $after, 'verdict' => $verdict];
        }
        if ($state['settled'] !== []) $state['settled'] = array_slice($state['settled'], -200);
        self::save($state);
        return $done;
    }

    public static function entries(): array
    {
        return (array) self::state()['entries'];
    }

    public static function entry(string $id): array
    {
        return (array) (self::state()['entries'][$id] ?? []);
    }

    /** 台账统计：各判级数量（给页面头部用） */
    public static function stats(): array
    {
        $out = ['pending' => 0, 'improved' => 0, 'flat' => 0, 'worse' => 0, 'no_action' => 0, 'no_measure' => 0];
        foreach (self::entries() as $e) {
            if ((array) ($e['action'] ?? []) === []) $out['no_action']++;
            if ((array) ($e['measure'] ?? []) === []) { $out['no_measure']++; continue; }
            $v = (string) ($e['measure']['verdict'] ?? 'pending');
            if (!isset($out[$v])) $v = 'pending';
            $out[$v]++;
        }
        return $out;
    }
}
