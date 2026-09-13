<?php
/**
 * Agentic 运行时 契约 —— 工具白名单 / 参数校验 / 风险门 / 执行循环 / 重试 / 审批
 *   php tests/agent_runtime_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-agent-' . getmypid());
@mkdir(DATA_DIR . '/agents', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

// 外部依赖 stub
$GLOBALS['TAGS'] = [];
function cdp_find(string $email = ''): ?array { return ['id' => 'v_' . md5($email), 'visitor_id' => 'v_' . md5($email)]; }
function cdp_add_tag(string $id, string $tag): void { $GLOBALS['TAGS'][] = $tag; }
class CopilotActions {}
function copilot_create_flow(array $f): array { return ['ok' => true, 'flow_id' => 'flow_x', 'flow' => $f]; }
function action_enabled(): array { return []; }   // 避免拉起 ConnectionActions/admin/config

require_once __DIR__ . '/../lib/AgentRuntime.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "Agentic 运行时\n";

// 工具库
$tools = agent_tools();
check('工具库含 safe 工具', isset($tools['add_tag']) && $tools['add_tag']['risk'] === 'safe');
check('风险分级存在', $tools['create_flow']['risk'] === 'moderate' && $tools['publish_content']['risk'] === 'high');

// 参数校验
$args = agent_validate_args(['points' => 'int', 'amount' => 'number', 'name' => 'string'], ['points' => '12', 'amount' => '9.5', 'name' => 'x', 'ignore' => 'y']);
check('参数类型转换', $args['points'] === 12 && $args['amount'] === 9.5 && $args['name'] === 'x' && !isset($args['ignore']));

// 风险门
check('safe 自动放行', agent_allowed(['risk' => 'safe']) === true);
check('moderate 默认拦(待批准)', agent_allowed(['risk' => 'moderate']) === false);
check('批准后放行', agent_allowed(['risk' => 'moderate'], ['approve' => true]) === true);
check('high 也要批准', agent_allowed(['risk' => 'high']) === false);

// 执行循环：注入决策
$GLOBALS['AGENT_DECIDE_FN'] = function ($goal, $steps, $cat) {
    $n = count($steps);
    if ($n === 0) return ['thought' => '打标签', 'tool' => 'add_tag', 'args' => ['email' => 'a@b.com', 'tag' => 'VIP']];
    return ['thought' => '完成', 'done' => true, 'summary' => '已打 VIP 标签'];
};
$r = agent_run('给高价值客户打 VIP 标签');
check('运行完成', !empty($r['ok']) && $r['run']['status'] === 'completed');
check('执行了工具并记录', count($r['run']['steps']) === 1 && $r['run']['steps'][0]['ok'] === true);
check('工具产生副作用', in_array('VIP', $GLOBALS['TAGS'], true));

// 未知工具：不崩，记录错误后模型换路
$GLOBALS['AGENT_DECIDE_FN'] = function ($goal, $steps, $cat) {
    $n = count($steps);
    if ($n === 0) return ['thought' => '乱选', 'tool' => 'delete_everything', 'args' => []];
    return ['thought' => '完成', 'done' => true, 'summary' => 'ok'];
};
$r2 = agent_run('测试未知工具');
check('未知工具被拒且可恢复', $r2['run']['status'] === 'completed' && $r2['run']['steps'][0]['ok'] === false);

// 失败工具：重试后记录错误
$GLOBALS['AGENT_DECIDE_FN'] = function ($goal, $steps, $cat) {
    $n = count($steps);
    if ($n === 0) return ['thought' => '参数不全', 'tool' => 'add_tag', 'args' => ['email' => '', 'tag' => '']];
    return ['done' => true, 'summary' => '放弃'];
};
$r3 = agent_run('触发失败');
check('失败工具被记录(不中断)', $r3['run']['steps'][0]['ok'] === false && $r3['run']['steps'][0]['error'] !== '');

// 风险门：moderate → needs_approval
$GLOBALS['AGENT_DECIDE_FN'] = function () {
    return ['thought' => '建流程', 'tool' => 'create_flow', 'args' => ['flow' => ['name' => 'X', 'trigger' => 'purchase', 'steps' => [['action' => 'notify', 'title' => 't']]]]];
};
$r4 = agent_run('建一条流程');
check('中等风险停在待批准', $r4['run']['status'] === 'needs_approval' && !empty($r4['run']['pending']));
$ap = agent_approve($r4['run']['id']);
check('批准后执行成功', !empty($ap['ok']) && $ap['run']['status'] === 'completed' && !empty($ap['run']['steps'][0]['approved']));

// 结构守卫
$api = file_get_contents(__DIR__ . '/../api/agent.php');
check('Agent API 要登录', strpos($api, 'require_login(') !== false);
check('Agent API 要权限', strpos($api, 'require_perm(') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
