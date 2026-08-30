<?php
/**
 * DecisionTrace —— Agent 决策的可解释轨道（AUDIT-03 创新 / BACKLOG T2-5）
 *
 * 【为什么】Agent 决策不能是黑箱。AUDIT-03 的收束是：画布不废，降级为
 * **可解释、可审计、可干预**的一层——把"为什么对这个人做了这件事"渲染成
 * 一条可视轨迹，人能看、能纠、能加护栏。
 *
 * 【一条轨迹】= 触发(为什么现在) → 依据(看了什么) → 候选(想过哪些) → 决定(选了什么)
 *              → 护栏(是否被拦/放行) → 结果(人采纳了吗、成了吗)
 * 存 SQLite（一次决策一行，含结构化步骤），可按主体/时间查，可回写结果闭环。
 */

require_once __DIR__ . '/Database.php';

if (!function_exists('dtrace_ensure')) {

    function dtrace_ensure(): void {
        static $ready = false;
        if ($ready) return;
        Database::execute("CREATE TABLE IF NOT EXISTS decision_trace (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject TEXT DEFAULT '',
            decision TEXT DEFAULT '',
            module TEXT DEFAULT '',
            steps TEXT DEFAULT '[]',
            outcome TEXT DEFAULT '',
            created_at TEXT DEFAULT ''
        )");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_dtrace_subject ON decision_trace(subject, created_at)");
        $ready = true;
    }

    /**
     * 记录一次决策的完整轨迹。
     * $t: ['subject','decision','module','trigger','evidence'=>[],'candidates'=>[],'guard'=>string]
     * 返回 trace id。
     */
    function dtrace_record(array $t): int {
        dtrace_ensure();
        $steps = [
            ['stage' => 'trigger',   'label' => '为什么是现在', 'detail' => (string)($t['trigger'] ?? '')],
            ['stage' => 'evidence',  'label' => '看了哪些依据', 'detail' => implode('；', array_map('strval', (array)($t['evidence'] ?? [])))],
            ['stage' => 'candidates','label' => '想过哪些动作', 'detail' => implode('；', array_map('strval', (array)($t['candidates'] ?? [])))],
            ['stage' => 'decision',  'label' => '最终选择',     'detail' => (string)($t['decision'] ?? '')],
            ['stage' => 'guard',     'label' => '护栏判定',     'detail' => (string)($t['guard'] ?? '未启用自治，交人确认')],
        ];
        Database::execute(
            "INSERT INTO decision_trace (subject, decision, module, steps, outcome, created_at) VALUES (?,?,?,?,?,?)",
            [(string)($t['subject'] ?? ''), (string)($t['decision'] ?? ''), (string)($t['module'] ?? ''),
             json_encode($steps, JSON_UNESCAPED_UNICODE), '', date('Y-m-d H:i:s')]
        );
        return (int)Database::lastInsertId();
    }

    /** 回写结果（人采纳了 / 忽略了 / 成交了）——闭环这条轨迹。 */
    function dtrace_outcome(int $id, string $outcome): bool {
        dtrace_ensure();
        if ($id <= 0) return false;
        $n = Database::execute("UPDATE decision_trace SET outcome = ? WHERE id = ?", [mb_substr($outcome, 0, 200), $id]);
        return $n > 0;
    }

    /** 查询轨迹（可按主体过滤），新→旧。 */
    function dtrace_list(string $subject = '', int $limit = 50): array {
        dtrace_ensure();
        $sql = "SELECT * FROM decision_trace";
        $p = [];
        if ($subject !== '') { $sql .= " WHERE subject = ?"; $p[] = $subject; }
        $sql .= " ORDER BY id DESC LIMIT ?";
        $p[] = max(1, $limit);
        $rows = Database::query($sql, $p);
        foreach ($rows as &$r) {
            $s = json_decode($r['steps'] ?? '[]', true);
            $r['steps'] = is_array($s) ? $s : [];
        }
        unset($r);
        return $rows;
    }

    /** 单条轨迹。 */
    function dtrace_get(int $id): ?array {
        dtrace_ensure();
        $rows = Database::query("SELECT * FROM decision_trace WHERE id = ?", [$id]);
        if (!$rows) return null;
        $r = $rows[0];
        $s = json_decode($r['steps'] ?? '[]', true);
        $r['steps'] = is_array($s) ? $s : [];
        return $r;
    }

    /**
     * 渲染成一行可读的解释（给驾驶舱内联展示）。
     */
    function dtrace_explain(array $trace): string {
        $by = [];
        foreach ((array)($trace['steps'] ?? []) as $s) $by[$s['stage'] ?? ''] = (string)($s['detail'] ?? '');
        $parts = [];
        if (!empty($by['trigger']))   $parts[] = '因为' . $by['trigger'];
        if (!empty($by['evidence']))  $parts[] = '依据' . $by['evidence'];
        if (!empty($by['decision']))  $parts[] = '所以建议「' . $by['decision'] . '」';
        if (!empty($by['guard']))     $parts[] = '（' . $by['guard'] . '）';
        return implode('，', $parts);
    }

    /** 统计：决策数、被采纳率（有 outcome 且非 ignored）。 */
    function dtrace_stats(): array {
        dtrace_ensure();
        $total = (int)(Database::query("SELECT COUNT(*) c FROM decision_trace")[0]['c'] ?? 0);
        $acted = (int)(Database::query("SELECT COUNT(*) c FROM decision_trace WHERE outcome <> '' AND outcome <> 'ignored'")[0]['c'] ?? 0);
        return ['total' => $total, 'acted' => $acted, 'rate' => $total ? (int)round($acted / $total * 100) : 0];
    }
}
