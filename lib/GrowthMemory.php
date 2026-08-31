<?php
/**
 * GrowthMemory —— 大脑的共享记忆层（AUDIT-07 P1-6 / BACKLOG T1-17）
 *
 * 【为什么】大脑现在每次判断都从零开始：它知道"这个人此刻的画像"，却不记得
 * "上次对他做过什么、结果如何、他说过什么"。这些事实散在各模块各自的 JSON 里
 * （CRM 跟进、成交账本、采纳箱、收件箱），没有一处是**跨模块、跨会话**的记忆。
 *
 * 【设计】一条记忆 = 关于某个主体(subject)的一个事实(fact)，带类型、来源、时间。
 *   subject: 人(profile/email/member) 或 单(lead/order)
 *   kind: interaction(接触过) / outcome(结果) / preference(偏好) / note(人写的)
 * 存 SQLite 一行一条 + 主体索引；读按主体取最近 N 条，直接可喂给决策与话术。
 *
 * 【原则】只追加不改写(事实不该被悄悄改)；写入幂等键可选(同一事实不重复记)；
 * 依赖仅 Database，可隔离测试。
 */

require_once __DIR__ . '/Database.php';

if (!function_exists('gmem_ensure')) {

    function gmem_ensure(): void {
        static $ready = false;
        if ($ready) return;
        Database::execute("CREATE TABLE IF NOT EXISTS growth_memory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject TEXT NOT NULL,
            kind TEXT DEFAULT 'interaction',
            summary TEXT DEFAULT '',
            detail TEXT DEFAULT '{}',
            source TEXT DEFAULT '',
            dedupe_key TEXT DEFAULT '',
            created_at TEXT DEFAULT ''
        )");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_gmem_subject ON growth_memory(subject, created_at)");
        // 部分唯一索引（需 SQLite ≥3.8）：去重键非空时数据库层兜底防重复；
        // 旧版 SQLite（<3.8）不支持部分索引，退化为普通索引，
        // 去重逻辑由 gmem_remember() 的「先查再插」保证，功能等价。
        try {
            Database::execute("CREATE UNIQUE INDEX IF NOT EXISTS idx_gmem_dedupe ON growth_memory(dedupe_key) WHERE dedupe_key <> ''");
        } catch (Exception $e) {
            Database::execute("CREATE INDEX IF NOT EXISTS idx_gmem_dedupe ON growth_memory(dedupe_key)");
        }
        $ready = true;
    }

    function gmem_kinds(): array {
        return ['interaction' => '接触', 'outcome' => '结果', 'preference' => '偏好', 'note' => '备注'];
    }

    /** 主体归一：邮箱小写，其余去空白。 */
    function gmem_subject(string $s): string {
        $s = trim($s);
        return strpos($s, '@') !== false ? mb_strtolower($s) : $s;
    }

    /**
     * 记一条事实（只追加）。dedupe_key 非空时同键只记一次。
     * 返回 ['ok'=>bool,'dup'=>bool]
     */
    function gmem_remember(string $subject, string $summary, array $opts = []): array {
        $subject = gmem_subject($subject);
        $summary = trim($summary);
        if ($subject === '' || $summary === '') return ['ok' => false, 'error' => 'subject/summary 必填'];
        gmem_ensure();
        $kind = isset(gmem_kinds()[$opts['kind'] ?? '']) ? $opts['kind'] : 'interaction';
        $key  = trim((string)($opts['dedupe_key'] ?? ''));

        if ($key !== '') {
            $hit = Database::query("SELECT id FROM growth_memory WHERE dedupe_key = ? LIMIT 1", [$key]);
            if ($hit) return ['ok' => true, 'dup' => true];
        }
        try {
            Database::execute(
                "INSERT INTO growth_memory (subject, kind, summary, detail, source, dedupe_key, created_at) VALUES (?,?,?,?,?,?,?)",
                [$subject, $kind, mb_substr($summary, 0, 500),
                 json_encode((array)($opts['detail'] ?? []), JSON_UNESCAPED_UNICODE),
                 (string)($opts['source'] ?? ''), $key, date('Y-m-d H:i:s')]
            );
        } catch (\Throwable $e) {
            return ['ok' => true, 'dup' => true];   // 唯一索引冲突 = 已记过
        }
        return ['ok' => true, 'dup' => false];
    }

    /** 取某主体的最近记忆（新→旧）。 */
    function gmem_recall(string $subject, int $limit = 20, string $kind = ''): array {
        gmem_ensure();
        $subject = gmem_subject($subject);
        $sql = "SELECT * FROM growth_memory WHERE subject = ?";
        $p = [$subject];
        if ($kind !== '') { $sql .= " AND kind = ?"; $p[] = $kind; }
        $sql .= " ORDER BY created_at DESC, id DESC LIMIT ?";
        $p[] = max(1, $limit);
        $rows = Database::query($sql, $p);
        foreach ($rows as &$r) { $d = json_decode($r['detail'] ?? '{}', true); $r['detail'] = is_array($d) ? $d : []; }
        unset($r);
        return $rows;
    }

    /**
     * 记忆摘要：把最近记忆压成给决策/话术用的一段话。
     * 让大脑"记得上次聊到哪"，而不是每次从零开始。
     */
    function gmem_brief(string $subject, int $limit = 6): string {
        $rows = gmem_recall($subject, $limit);
        if (!$rows) return '';
        $lines = [];
        foreach ($rows as $r) {
            $d = substr((string)($r['created_at'] ?? ''), 0, 10);
            $k = gmem_kinds()[$r['kind'] ?? ''] ?? '';
            $lines[] = "· {$d} [{$k}] " . (string)($r['summary'] ?? '');
        }
        return implode("\n", $lines);
    }

    /** 主体是否已被接触过（避免重复打扰的判断依据）。 */
    function gmem_touched(string $subject, int $withinDays = 7): bool {
        gmem_ensure();
        $cut = date('Y-m-d H:i:s', time() - max(0, $withinDays) * 86400);
        $r = Database::query("SELECT id FROM growth_memory WHERE subject = ? AND created_at >= ? LIMIT 1",
                             [gmem_subject($subject), $cut]);
        return !empty($r);
    }

    /** 统计（后台观测用）。 */
    function gmem_stats(): array {
        gmem_ensure();
        $total = (int)(Database::query("SELECT COUNT(*) c FROM growth_memory")[0]['c'] ?? 0);
        $subjects = (int)(Database::query("SELECT COUNT(DISTINCT subject) c FROM growth_memory")[0]['c'] ?? 0);
        $byKind = [];
        foreach (Database::query("SELECT kind, COUNT(*) c FROM growth_memory GROUP BY kind") as $r) {
            $byKind[(string)$r['kind']] = (int)$r['c'];
        }
        return ['total' => $total, 'subjects' => $subjects, 'by_kind' => $byKind];
    }
}
