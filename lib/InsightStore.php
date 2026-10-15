<?php
declare(strict_types=1);
/**
 * InsightStore —— Insight Flow 洞察本地落库（三方契约 v1.1 §4）
 *
 * 【为什么】此前洞察只能实时回源 IF 拉取，IF 一挂后台卡片/GrowthBrain 就断粮，
 * 也没有地方记录"OpenFlow 收到过哪些洞察"。现在 IF 通过插件 inbox 推送
 * `insight.upsert`，OpenFlow 落进 openflow.db 的 `insights` 表，读路径本地优先。
 *
 * 表：
 *   insights            —— 一条洞察一行，insight_id 主键，重复推送即更新
 *   insight_ingest_log  —— 入站幂等表，(source, event_id) 主键；重放直接短路
 *
 * 用法：
 *   insight_store_upsert($flat, 'insight-flow')  → 规整后的行
 *   insight_store_get('ins_abc')                 → 行 | null
 *   insight_store_list(['severity'=>'high'], 20) → 行数组（updated_at 倒序）
 *   insight_store_normalize($envelope)           → 扁平 + 嵌套写法统一成扁平
 */

require_once __DIR__ . '/Database.php';

if (!function_exists('insight_store_ensure')) {

    function insight_store_ensure(): void {
        static $ready = false;
        if ($ready) return;
        Database::execute("CREATE TABLE IF NOT EXISTS insights (
            insight_id TEXT PRIMARY KEY,
            source TEXT DEFAULT '',
            workspace_id TEXT DEFAULT '',
            type TEXT DEFAULT '',
            title TEXT DEFAULT '',
            claim TEXT DEFAULT '',
            summary TEXT DEFAULT '',
            severity TEXT DEFAULT '',
            confidence REAL DEFAULT 0,
            evidence_json TEXT DEFAULT '[]',
            actions_json TEXT DEFAULT '[]',
            stage_tags_json TEXT DEFAULT '[]',
            status TEXT DEFAULT '',
            url TEXT DEFAULT '',
            payload_json TEXT DEFAULT '{}',
            received_at TEXT DEFAULT '',
            updated_at TEXT DEFAULT ''
        )");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_insights_ws ON insights(workspace_id, updated_at)");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_insights_sev ON insights(severity, updated_at)");
        Database::execute("CREATE TABLE IF NOT EXISTS insight_ingest_log (
            source TEXT NOT NULL,
            event_id TEXT NOT NULL,
            event TEXT DEFAULT '',
            ref TEXT DEFAULT '',
            received_at TEXT DEFAULT '',
            PRIMARY KEY (source, event_id)
        )");
        $ready = true;
    }

    /**
     * 契约 §1：接收方对扁平/嵌套两种写法都要能读。
     * flat = {**data, **顶层除 data 外字段}（顶层优先）；`payload` 作为 `data` 的别名。
     */
    function insight_store_normalize(array $envelope): array {
        $data = [];
        if (isset($envelope['data']) && is_array($envelope['data'])) $data = $envelope['data'];
        elseif (isset($envelope['payload']) && is_array($envelope['payload'])) $data = $envelope['payload'];
        $top = $envelope;
        unset($top['data'], $top['payload']);
        $flat = array_merge($data, $top);
        if (empty($flat['insight_id']) && !empty($data['id'])) $flat['insight_id'] = $data['id'];
        if (empty($flat['insight_id']) && !empty($flat['id']))  $flat['insight_id'] = $flat['id'];
        return $flat;
    }

    /** 数组字段：数组原样，JSON 字符串解码，逗号串拆分，其余空数组 */
    function insight_store_list_field($v): array {
        if (is_array($v)) return array_values($v);
        if (is_string($v) && $v !== '') {
            $d = json_decode($v, true);
            if (is_array($d)) return array_values($d);
            return array_values(array_filter(array_map('trim', explode(',', $v)), fn($x) => $x !== ''));
        }
        return [];
    }

    /** 行 → 对外结构（JSON 列解码，补 id 别名，兼容 IF /api/v1/insights 的读法） */
    function insight_store_row_out(array $r): array {
        $out = [
            'id'           => (string)$r['insight_id'],
            'insight_id'   => (string)$r['insight_id'],
            'source'       => (string)$r['source'],
            'workspace_id' => (string)$r['workspace_id'],
            'type'         => (string)$r['type'],
            'title'        => (string)$r['title'],
            'claim'        => (string)$r['claim'],
            'summary'      => (string)$r['summary'],
            'severity'     => (string)$r['severity'],
            'confidence'   => (float)$r['confidence'],
            'evidence'     => json_decode((string)$r['evidence_json'], true) ?: [],
            'actions'      => json_decode((string)$r['actions_json'], true) ?: [],
            'stage_tags'   => json_decode((string)$r['stage_tags_json'], true) ?: [],
            'status'       => (string)$r['status'],
            'url'          => (string)$r['url'],
            'received_at'  => (string)$r['received_at'],
            'updated_at'   => (string)$r['updated_at'],
        ];
        $payload = json_decode((string)$r['payload_json'], true);
        $out['created_at'] = is_array($payload) ? (string)($payload['created_at'] ?? '') : '';
        return $out;
    }

    /**
     * 写入/更新一条洞察（insight_id 必填）。接受扁平或嵌套写法。
     * 首次写入记 received_at，之后只刷新 updated_at。
     * @return array ['ok'=>bool, 'insight'=>row, 'created'=>bool] | ['ok'=>false,'error'=>..]
     */
    function insight_store_upsert(array $data, string $source = 'insight-flow'): array {
        insight_store_ensure();
        $f = insight_store_normalize($data);
        $id = trim((string)($f['insight_id'] ?? ''));
        if ($id === '') return ['ok' => false, 'error' => 'missing insight_id'];
        if (mb_strlen($id) > 200) return ['ok' => false, 'error' => 'insight_id too long'];

        $now = date('Y-m-d H:i:s');
        $existing = Database::query("SELECT received_at FROM insights WHERE insight_id = ?", [$id]);
        $receivedAt = $existing ? (string)$existing[0]['received_at'] : $now;
        $src = (string)($source !== '' ? $source : ($f['source'] ?? 'insight-flow'));
        $enc = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE);

        $row = [
            'insight_id'      => $id,
            'source'          => $src,
            'workspace_id'    => (string)($f['workspace_id'] ?? ''),
            'type'            => (string)($f['type'] ?? ''),
            'title'           => mb_substr((string)($f['title'] ?? ''), 0, 500),
            'claim'           => (string)($f['claim'] ?? ''),
            'summary'         => (string)($f['summary'] ?? ''),
            'severity'        => (string)($f['severity'] ?? ''),
            'confidence'      => is_numeric($f['confidence'] ?? null) ? (float)$f['confidence'] : 0.0,
            'evidence_json'   => $enc(insight_store_list_field($f['evidence'] ?? [])),
            'actions_json'    => $enc(insight_store_list_field($f['actions'] ?? [])),
            'stage_tags_json' => $enc(insight_store_list_field($f['stage_tags'] ?? [])),
            'status'          => (string)($f['status'] ?? ''),
            'url'             => (string)($f['url'] ?? ''),
            'payload_json'    => $enc($f),
            'received_at'     => $receivedAt,
            'updated_at'      => $now,
        ];
        $cols = array_keys($row);
        $sets = implode(',', array_map(fn($c) => "{$c}=excluded.{$c}", array_diff($cols, ['insight_id', 'received_at'])));
        Database::execute(
            "INSERT INTO insights (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")
             ON CONFLICT(insight_id) DO UPDATE SET {$sets}",
            array_values($row)
        );
        return ['ok' => true, 'created' => !$existing, 'insight' => insight_store_get($id)];
    }

    function insight_store_get(string $insightId): ?array {
        insight_store_ensure();
        $r = Database::query("SELECT * FROM insights WHERE insight_id = ?", [$insightId]);
        return $r ? insight_store_row_out($r[0]) : null;
    }

    /**
     * 列表：filters 支持 source / workspace_id / severity / status / type（精确匹配）。
     * 按 updated_at 倒序，limit 1..200。
     */
    function insight_store_list(array $filters = [], int $limit = 20): array {
        insight_store_ensure();
        $where = []; $params = [];
        foreach (['source', 'workspace_id', 'severity', 'status', 'type'] as $k) {
            $v = $filters[$k] ?? '';
            if ($v === '') continue;   // ?? 已把 null 归一成 ''
            $where[] = "{$k} = ?"; $params[] = (string)$v;
        }
        $limit = max(1, min(200, $limit));
        $sql = "SELECT * FROM insights" . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY updated_at DESC, insight_id ASC LIMIT {$limit}";
        return array_map('insight_store_row_out', Database::query($sql, $params));
    }

    // ── 入站幂等：(source, event_id) ──

    function insight_ingest_seen(string $source, string $eventId): bool {
        insight_store_ensure();
        return (bool)Database::query("SELECT 1 FROM insight_ingest_log WHERE source = ? AND event_id = ?", [$source, $eventId]);
    }

    /** 处理成功后登记；返回 true=新登记，false=已存在（并发重放） */
    function insight_ingest_mark(string $source, string $eventId, string $event = '', string $ref = ''): bool {
        insight_store_ensure();
        return Database::execute(
            "INSERT OR IGNORE INTO insight_ingest_log (source, event_id, event, ref, received_at) VALUES (?,?,?,?,?)",
            [$source, $eventId, $event, $ref, date('Y-m-d H:i:s')]
        ) > 0;
    }
}
