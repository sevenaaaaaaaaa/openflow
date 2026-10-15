<?php
/**
 * 三方契约 v1.1 · O1：InsightStore（insights 表 + 入站幂等表）
 *
 *   php tests/insight_store_test.php
 *
 * 验：扁平/嵌套写法都能落库、id 别名、数组字段编解码、重复推送=更新且保留 received_at、
 *     列表过滤与排序、缺 insight_id 拒绝、(source,event_id) 幂等登记。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-insight-store-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/InsightStore.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

echo "\n── 1. 扁平写法 upsert ──\n";
$r = insight_store_upsert([
    'insight_id' => 'ins_flat', 'workspace_id' => 'ws1', 'type' => 'competitor_pricing',
    'title' => '竞品降价', 'claim' => '竞品 A 定价页 -12%', 'summary' => '摘要', 'severity' => 'high',
    'confidence' => 0.82, 'evidence' => [['url' => 'https://a.example/pricing']], 'actions' => [['type' => 'mflow.create_content']],
    'stage_tags' => ['S1', 'S3'], 'status' => 'open', 'url' => 'https://if.example/i/ins_flat', 'created_at' => '2026-10-12T08:00:00Z',
]);
check('ok + created', ($r['ok'] ?? false) && ($r['created'] ?? false), json_encode($r, JSON_UNESCAPED_UNICODE));
$g = insight_store_get('ins_flat');
check('get 命中', $g !== null);
check('id 别名 = insight_id', ($g['id'] ?? '') === 'ins_flat');
check('source 默认 insight-flow', ($g['source'] ?? '') === 'insight-flow');
check('confidence 为浮点', ($g['confidence'] ?? 0) === 0.82);
check('evidence 解码为数组', ($g['evidence'][0]['url'] ?? '') === 'https://a.example/pricing');
check('actions 解码为数组', ($g['actions'][0]['type'] ?? '') === 'mflow.create_content');
check('stage_tags 解码', ($g['stage_tags'] ?? []) === ['S1', 'S3']);
check('created_at 从 payload 回显', ($g['created_at'] ?? '') === '2026-10-12T08:00:00Z');
check('received_at/updated_at 已填', ($g['received_at'] ?? '') !== '' && ($g['updated_at'] ?? '') !== '');

echo "\n── 2. 嵌套写法（字段在 data）+ id 别名 ──\n";
$r2 = insight_store_upsert([
    'event' => 'insight.upsert', 'event_id' => 'e-1', 'source' => 'insight-flow', 'workspace_id' => 'ws2',
    'data' => ['id' => 'ins_nested', 'title' => '嵌套标题', 'severity' => 'low', 'confidence' => '0.4', 'stage_tags' => 'S2, S4'],
], 'insight-flow');
check('嵌套 upsert ok', ($r2['ok'] ?? false) === true, json_encode($r2, JSON_UNESCAPED_UNICODE));
$g2 = insight_store_get('ins_nested');
check('data.id → insight_id', ($g2['insight_id'] ?? '') === 'ins_nested');
check('data 内字段被读出', ($g2['title'] ?? '') === '嵌套标题');
check('顶层 workspace_id 被合并', ($g2['workspace_id'] ?? '') === 'ws2');
check('数字字符串 confidence', ($g2['confidence'] ?? 0) === 0.4);
check('逗号串 stage_tags 拆分', ($g2['stage_tags'] ?? []) === ['S2', 'S4'], json_encode($g2['stage_tags'] ?? null));

echo "\n── 3. normalize：顶层优先 ──\n";
$f = insight_store_normalize(['title' => '顶层', 'data' => ['title' => '内层', 'claim' => 'c']]);
check('顶层覆盖 data', $f['title'] === '顶层');
check('data 独有字段保留', $f['claim'] === 'c');
check('payload 作为 data 别名', (insight_store_normalize(['payload' => ['insight_id' => 'x']])['insight_id'] ?? '') === 'x');

echo "\n── 4. 重复推送 = 更新，保留 received_at ──\n";
$before = insight_store_get('ins_flat');
sleep(1);
$r3 = insight_store_upsert(['insight_id' => 'ins_flat', 'workspace_id' => 'ws1', 'title' => '竞品降价（更新）', 'severity' => 'critical']);
$after = insight_store_get('ins_flat');
check('更新非新建', ($r3['created'] ?? true) === false);
check('标题已更新', ($after['title'] ?? '') === '竞品降价（更新）');
check('received_at 不变', $after['received_at'] === $before['received_at']);
check('updated_at 前进', $after['updated_at'] > $before['updated_at'], $before['updated_at'] . ' → ' . $after['updated_at']);

echo "\n── 5. 列表与过滤 ──\n";
insight_store_upsert(['insight_id' => 'ins_3', 'workspace_id' => 'ws1', 'severity' => 'high', 'title' => 't3']);
$all = insight_store_list([], 10);
check('共 3 条', count($all) === 3, (string)count($all));
check('按 workspace 过滤', count(insight_store_list(['workspace_id' => 'ws1'])) === 2);
check('按 severity 过滤', count(insight_store_list(['severity' => 'high'])) === 1);
check('空过滤值被忽略', count(insight_store_list(['severity' => '', 'status' => null])) === 3);
check('limit 生效', count(insight_store_list([], 1)) === 1);
check('不存在返回 null', insight_store_get('nope') === null);

echo "\n── 6. 缺 insight_id 拒绝 ──\n";
$bad = insight_store_upsert(['title' => '无 id']);
check('ok=false', ($bad['ok'] ?? true) === false);
check('错误说明', ($bad['error'] ?? '') === 'missing insight_id');

echo "\n── 7. 入站幂等 (source, event_id) ──\n";
check('初始未见', insight_ingest_seen('insight-flow', 'evt-1') === false);
check('首次登记 true', insight_ingest_mark('insight-flow', 'evt-1', 'insight.upsert', 'ins_flat') === true);
check('之后已见', insight_ingest_seen('insight-flow', 'evt-1') === true);
check('重复登记 false', insight_ingest_mark('insight-flow', 'evt-1') === false);
check('不同 source 互不影响', insight_ingest_seen('mflow', 'evt-1') === false);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR . '/db/*')); @rmdir(DATA_DIR . '/db'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
