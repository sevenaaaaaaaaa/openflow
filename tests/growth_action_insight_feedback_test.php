<?php
/**
 * 三方契约 v1.1 · O3：动作带 insight_id → 状态变化回传 openflow.action_feedback
 *
 *   php tests/growth_action_insight_feedback_test.php
 *
 * 验：adopt 持久化 insight_id、重复采纳补 insight_id、set_status 触发 growth_action_status_changed 钩子、
 *     插件发出的反馈信封字段/签名/event_id、无 insight_id 或未配置不发、HTTP 失败或钩子抛错不影响状态变更、
 *     /feedback 路由与钩子共用同一信封构造、AgentRuntime 记录 insight_id。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-act-fb-' . getmypid());
@mkdir(DATA_DIR . '/growth', 0777, true);
@mkdir(DATA_DIR . '/agents', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/PluginSystem.php';
require_once __DIR__ . '/../lib/PluginSDK.php';
require_once __DIR__ . '/../plugins/insight-flow/plugin.php';
require_once __DIR__ . '/../lib/GrowthAction.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}
function hdr(array $headers, string $name): string {
    foreach ($headers as $h) { [$k, $v] = array_map('trim', explode(':', $h, 2)); if (strcasecmp($k, $name) === 0) return $v; }
    return '';
}

$SECRET = 'fb-secret';
$SENT = [];
$GLOBALS['INSIGHT_FLOW_HTTP_POST'] = function (string $url, array $payload, array $headers) use (&$SENT) {
    $SENT[] = ['url' => $url, 'payload' => $payload, 'headers' => $headers];
    return ['ok' => true, 'status' => 200, 'body' => '{"ok":true}', 'error' => ''];
};
$HOOKED = [];
PluginSystem::add_action('growth_action_status_changed', function ($a, $st, $prev = '') use (&$HOOKED) { $HOOKED[] = [$a['id'] ?? '', $st, $prev]; });

echo "\n── 1. adopt 持久化 insight_id ──\n";
$r = growth_action_adopt(['profile_id' => 'c1', 'module' => 'Content', 'action' => '竞品降价应对文章', 'insight_id' => 'ins_77']);
check('采纳成功', ($r['ok'] ?? false) && !($r['dup'] ?? true));
check('row 带 insight_id', ($r['action']['insight_id'] ?? '') === 'ins_77');
$stored = array_values(array_filter(growth_action_all(), fn($a) => $a['id'] === $r['action']['id']))[0] ?? [];
check('落盘带 insight_id', ($stored['insight_id'] ?? '') === 'ins_77');
$r0 = growth_action_adopt(['profile_id' => 'c2', 'module' => 'MA', 'action' => '召回']);
check('不带 insight_id 时不写该字段', !array_key_exists('insight_id', $r0['action']));
$rd = growth_action_adopt(['profile_id' => 'c2', 'module' => 'MA', 'action' => '召回', 'insight_id' => 'ins_late']);
check('重复采纳 → dup，并补上 insight_id', ($rd['dup'] ?? false) && ($rd['action']['insight_id'] ?? '') === 'ins_late');

echo "\n── 2. 未配置插件：钩子照常触发，但不出站 ──\n";
check('dismiss 成功', growth_action_dismiss($r0['action']['id']) === true);
check('钩子收到 (id, dismissed, pending)', end($HOOKED) === [$r0['action']['id'], 'dismissed', 'pending'], json_encode(end($HOOKED)));
check('未配置不发', count($SENT) === 0);

echo "\n── 3. 已配置：done → openflow.action_feedback ──\n";
plugin('insight-flow')->setConfig(['if_url' => 'https://if.example', 'if_secret' => $SECRET, 'workspace_id' => 'ws_fb']);
$id = $r['action']['id'];
check('complete 成功', growth_action_complete($id) === true);
check('发出 1 条', count($SENT) === 1, (string)count($SENT));
$p = $SENT[0]['payload'] ?? [];
check('URL', ($SENT[0]['url'] ?? '') === 'https://if.example/api/v1/ingest');
check('event = openflow.action_feedback', ($p['event'] ?? '') === 'openflow.action_feedback');
check('schema_version 1.1 / source openflow', ($p['schema_version'] ?? '') === '1.1' && ($p['source'] ?? '') === 'openflow');
check('event_id = of-fb-<action_id>-done', ($p['event_id'] ?? '') === "of-fb-{$id}-done", (string)($p['event_id'] ?? ''));
check('workspace_id', ($p['workspace_id'] ?? '') === 'ws_fb');
$d = $p['data'] ?? [];
check('data.insight_id', ($d['insight_id'] ?? '') === 'ins_77');
check('data.action_id', ($d['action_id'] ?? '') === $id);
check('data.module / action', ($d['module'] ?? '') === 'Content' && ($d['action'] ?? '') === '竞品降价应对文章');
check('data.status = done', ($d['status'] ?? '') === 'done');
check('data.at ISO8601', (bool)preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', (string)($d['at'] ?? '')), (string)($d['at'] ?? ''));
check('签名可验', hash_equals(hash_hmac('sha256', json_encode($p, JSON_UNESCAPED_UNICODE), $SECRET), hdr($SENT[0]['headers'] ?? [], 'X-IF-Signature')));
check('X-Inbound-Signature 同值 + X-IF-Timestamp', hdr($SENT[0]['headers'] ?? [], 'X-Inbound-Signature') === hdr($SENT[0]['headers'] ?? [], 'X-IF-Signature') && hdr($SENT[0]['headers'] ?? [], 'X-IF-Timestamp') !== '');

echo "\n── 4. 不该发的情况 ──\n";
$SENT = [];
growth_action_set_status($id, 'pending');
check('回到 pending（不在契约 status 枚举）不发', count($SENT) === 0);
growth_action_set_status($id, 'pending');
$before = count($HOOKED);
growth_action_set_status($id, 'pending');
check('状态未变化不触发钩子', count($HOOKED) === $before);
$rn = growth_action_adopt(['profile_id' => 'c3', 'module' => 'Sales', 'action' => '无洞察动作']);
growth_action_complete($rn['action']['id']);
check('无 insight_id 不发', count($SENT) === 0);

echo "\n── 5. 失败不影响状态变更 ──\n";
$GLOBALS['INSIGHT_FLOW_HTTP_POST'] = function () { return ['ok' => false, 'status' => 503, 'body' => '', 'error' => 'down']; };
$r5 = growth_action_adopt(['profile_id' => 'c5', 'module' => 'Content', 'action' => 'A5', 'insight_id' => 'ins_5']);
check('IF 503 时 complete 仍成功', growth_action_complete($r5['action']['id']) === true);
check('状态已是 done', (array_values(array_filter(growth_action_all(), fn($a) => $a['id'] === $r5['action']['id']))[0]['status'] ?? '') === 'done');
check('失败写进插件日志', (bool)array_filter(plugin('insight-flow')->tailLog(20), fn($l) => strpos($l, '出站失败:openflow.action_feedback') !== false));
$GLOBALS['INSIGHT_FLOW_HTTP_POST'] = function () { throw new RuntimeException('boom'); };
PluginSystem::add_action('growth_action_status_changed', function () { throw new RuntimeException('其他插件炸了'); });
$r6 = growth_action_adopt(['profile_id' => 'c6', 'module' => 'Content', 'action' => 'A6', 'insight_id' => 'ins_6']);
$ok = false; $threw = false;
try { $ok = growth_action_dismiss($r6['action']['id']); } catch (\Throwable $e) { $threw = true; }
check('发送抛异常 + 其他钩子抛异常 → 不冒泡', !$threw);
check('dismiss 仍返回 true', $ok === true);

echo "\n── 6. /feedback 路由与钩子同一信封 ──\n";
$env = insight_flow_feedback_envelope(['insight_id' => 'ins_9', 'action_id' => 'act_9', 'module' => 'MA', 'action' => 'x', 'status' => 'effective'], 'ws');
check('effective 合法', ($env['data']['status'] ?? '') === 'effective' && ($env['event_id'] ?? '') === 'of-fb-act_9-effective');
check('兼容旧字段 result', (insight_flow_feedback_envelope(['insight_id' => 'i', 'result' => 'ineffective'])['data']['status'] ?? '') === 'ineffective');
check('缺 insight_id → null', insight_flow_feedback_envelope(['status' => 'done']) === null);
check('非法 status → null', insight_flow_feedback_envelope(['insight_id' => 'i', 'status' => 'pending']) === null);
$e1 = insight_flow_feedback_envelope(['insight_id' => 'i', 'action_id' => 'a', 'status' => 'done', 'at' => '2026-10-12T00:00:00Z']);
$e2 = insight_flow_feedback_envelope(['insight_id' => 'i', 'action_id' => 'a', 'status' => 'done', 'at' => '2026-10-12T00:00:00Z']);
check('重试 event_id 稳定', $e1['event_id'] === $e2['event_id']);

echo "\n── 7. AgentRuntime 记录 insight_id ──\n";
function action_enabled(): array { return []; }
require_once __DIR__ . '/../lib/AgentRuntime.php';
$GLOBALS['AGENT_STEP'] = 0;
$GLOBALS['AGENT_DECIDE_FN'] = function ($goal, $steps) {
    if ($GLOBALS['AGENT_STEP']++ === 0) return ['thought' => '建任务', 'tool' => 'create_task', 'args' => ['title' => '跟进', 'insight_id' => 'ins_step']];
    return ['done' => true, 'summary' => 'ok'];
};
$run = agent_run('测试目标', ['insight_id' => 'ins_run', 'approve' => true, 'max_steps' => 2])['run'] ?? [];
check('run 带 insight_id', ($run['insight_id'] ?? '') === 'ins_run', json_encode($run, JSON_UNESCAPED_UNICODE));
check('step 带 args 里的 insight_id', ($run['steps'][0]['insight_id'] ?? '') === 'ins_step', json_encode($run['steps'][0] ?? null, JSON_UNESCAPED_UNICODE));
check('insight_id 不进工具参数（白名单不变）', !isset($run['steps'][0]['args']['insight_id']));

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exit($fail === 0 ? 0 : 1);
