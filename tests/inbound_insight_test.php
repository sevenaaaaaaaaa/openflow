<?php
/**
 * 三方契约 v1.1 · InboundReceiver：insight 类型 + 未知类型 400 + 无连接器洞察不再静默吞
 *
 *   php tests/inbound_insight_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-inbound-insight-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/InboundReceiver.php';
require_once __DIR__ . '/../lib/InsightStore.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

inbound_save_connectors([
    ['id' => 'c_ins', 'name' => 'IF 洞察', 'type' => 'insight', 'enabled' => true, 'source' => 'insight-flow'],
    ['id' => 'c_bad', 'name' => '错配', 'type' => 'weird', 'enabled' => true],
]);

echo "\n── 1. type=insight → InsightStore ──\n";
// InFlow openflow.webhook_insight 老载荷（扁平，id 作为洞察 id）
$r = inbound_handle('c_ins', ['id' => 'ins_legacy', 'workspace_id' => 'ws_1', 'type' => 'competitor_pricing',
                              'title' => '竞品降价', 'summary' => '定价页 -12%', 'severity' => 'high', 'confidence' => 0.8]);
check('ok', ($r['ok'] ?? false) === true, json_encode($r, JSON_UNESCAPED_UNICODE));
check('返回 insight_id', ($r['insight']['insight_id'] ?? '') === 'ins_legacy');
$g = insight_store_get('ins_legacy');
check('已落 insights 表', ($g['title'] ?? '') === '竞品降价');
check('source 取连接器设置', ($g['source'] ?? '') === 'insight-flow');
$r = inbound_handle('c_ins', ['event' => 'insight.upsert', 'event_id' => 'e1', 'data' => ['insight_id' => 'ins_n', 'title' => '嵌套']]);
check('嵌套写法也可', ($r['ok'] ?? false) && (insight_store_get('ins_n')['title'] ?? '') === '嵌套');
$r = inbound_handle('c_ins', ['title' => '没有 id']);
check('缺 insight_id → ok=false code=400', ($r['ok'] ?? true) === false && ($r['code'] ?? 0) === 400, json_encode($r, JSON_UNESCAPED_UNICODE));
$log = json_read(inbound_log_file());
check('处理日志记了 insight 成功', ($log[1]['type'] ?? '') === 'insight' && ($log[1]['status'] ?? '') === 'ok', json_encode($log[1] ?? null, JSON_UNESCAPED_UNICODE));

echo "\n── 2. 未知类型 → 明确 400 ──\n";
$r = inbound_handle('c_bad', ['x' => 1]);
check('ok=false', ($r['ok'] ?? true) === false);
check('code=400', ($r['code'] ?? 0) === 400);
check('错误信息列出支持类型', strpos((string)($r['error'] ?? ''), 'insight') !== false);
check('未知类型写进日志', (json_read(inbound_log_file())[0]['status'] ?? '') === 'error');

echo "\n── 3. 无连接器的 IF 洞察识别（防传统分支假成功） ──\n";
check('IF 老载荷命中', inbound_looks_like_insight(['id' => 'ins_1', 'workspace_id' => 'ws', 'severity' => 'high', 'confidence' => 0.8]) === true);
check('insight.upsert 信封命中', inbound_looks_like_insight(['event' => 'insight.upsert', 'event_id' => 'x']) === true);
check('嵌套 data 命中', inbound_looks_like_insight(['data' => ['insight_id' => 'a', 'workspace_id' => 'w', 'severity' => 'low', 'confidence' => 0]]) === true);
check('source=insight-flow 命中', inbound_looks_like_insight(['workspace_id' => 'w', 'severity' => 'low', 'confidence' => 1, 'source' => 'insight-flow']) === true);
check('普通线索不命中', inbound_looks_like_insight(['email' => 'a@b.com', 'name' => '张三']) === false);
check('缺 confidence 不命中', inbound_looks_like_insight(['id' => 'x', 'workspace_id' => 'w', 'severity' => 'high']) === false);
check('无洞察标识不命中', inbound_looks_like_insight(['workspace_id' => 'w', 'severity' => 'high', 'confidence' => 1]) === false);

echo "\n── 4. api/webhook.php 接线 ──\n";
$src = (string)file_get_contents(__DIR__ . '/../api/webhook.php');
$guard = strpos($src, 'inbound_looks_like_insight(');
$legacy = strpos($src, '传统 Webhook 转发');
$inboundBranch = strpos($src, 'inbound_handle($inboundId');
check('webhook.php 调用了识别函数', $guard !== false);
check('识别在 X-Inbound-Id 分支之后、传统转发之前', $guard !== false && $guard > $inboundBranch && $guard < $legacy);
check('命中时返回 400 并指向插件 inbox', (bool)preg_match('#/api/plugin/insight-flow/inbox.*400#s', substr($src, $guard, 600)));
check('传统分支 ok:true 行为保留', strpos($src, "json_out(['ok' => true, 'message' => 'Webhook triggered']);") !== false);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exit($fail === 0 ? 0 : 1);
