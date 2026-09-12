<?php
/**
 * Flow 内容/电商原子节点 契约 —— 更新线索/建任务/分群/发布内容
 *   php tests/flow_business_nodes_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-flowbiz-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

// 外部模块 stub（避免拉起 config/CRM/CDP）
$GLOBALS['LEAD'] = []; $GLOBALS['FUPT'] = []; $GLOBALS['PUT'] = []; $GLOBALS['FLOWEV'] = ''; $GLOBALS['MEMBERS'] = [];
function crm_ensure_lead(string $e, string $n = ''): void {}
function crm_update_lead(string $e, array $u): void { $GLOBALS['LEAD'][] = $u; }
function crm_add_followup(string $e, string $c, string $o = ''): void { $GLOBALS['FUPT'][] = $c; }
function cdp_find(string $e = '', string $m = '', string $u = ''): ?array { return ['visitor_id' => 'v1', 'id' => 'v1', 'properties' => [], 'segment_memberships' => $GLOBALS['MEMBERS']]; }
function cdp_profile_put(string $id, array $p): void { $GLOBALS['PUT'][] = $p; }
function flow_handle(string $ev, array $d = []): void { $GLOBALS['FLOWEV'] = $ev; }
$GLOBALS['ART'] = ['id' => 'a1', 'title' => '旧文', 'status' => 'draft'];
function get_article(string $id): ?array { return $id === 'a1' ? $GLOBALS['ART'] : null; }
function save_article(array $a): bool { $GLOBALS['ART'] = $a; return true; }

require_once __DIR__ . '/../lib/AutomationSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "Flow 业务原子节点\n";

$ctx = ['email' => 'u@b.com', 'member_id' => 'm1', 'name' => '甲'];

// update_lead
automation_update_lead(['stage' => 'qualified', 'value' => 2000, 'owner' => 'Seven', 'followup' => '电话沟通'], $ctx, 'f1');
check('更新线索写入阶段/金额/负责人', $GLOBALS['LEAD'] && $GLOBALS['LEAD'][0]['stage'] === 'qualified' && $GLOBALS['LEAD'][0]['value'] === 2000.0);
check('线索跟进记录', $GLOBALS['FUPT'] === ['电话沟通']);

// create_task
automation_create_task(['title' => '跟进甲', 'assignee' => 'sales', 'priority' => 'high'], $ctx, 'f1');
$tasks = json_read(DATA_DIR . '/tasks.json');
check('创建待办任务', count($tasks) === 1 && $tasks[0]['title'] === '跟进甲' && $tasks[0]['assignee'] === 'sales');
check('任务优先级登记', $tasks[0]['priority'] === 'high');

// add_segment
automation_segment(['segment_id' => 'seg_high'], $ctx, 'f1', true);
check('加入分群写入 memberships', !empty($GLOBALS['PUT']) && isset($GLOBALS['PUT'][0]['segment_memberships']['seg_high']));
check('加入分群触发 segment_enter', $GLOBALS['FLOWEV'] === 'segment_enter');

// remove_segment
$GLOBALS['PUT'] = []; $GLOBALS['FLOWEV'] = ''; $GLOBALS['MEMBERS'] = ['seg_high' => []];
automation_segment(['segment_id' => 'seg_high'], $ctx, 'f1', false);
check('移出分群', !empty($GLOBALS['PUT']) && empty($GLOBALS['PUT'][0]['segment_memberships']));
check('移出分群触发 segment_exit', $GLOBALS['FLOWEV'] === 'segment_exit');

// publish_content
automation_publish_content(['article_id' => 'a1'], $ctx, 'f1');
check('发布内容置为 published', $GLOBALS['ART']['status'] === 'published' && !empty($GLOBALS['ART']['published_at']));

// 结构守卫
$as = file_get_contents(__DIR__ . '/../lib/AutomationSystem.php');
foreach (['update_lead', 'create_task', 'add_segment', 'remove_segment', 'publish_content'] as $a) {
    check("自动机含动作 {$a}", strpos($as, "case '{$a}'") !== false);
}
$cp = file_get_contents(__DIR__ . '/../lib/CopilotActions.php');
check('Copilot 流程白名单含新动作', strpos($cp, "'update_lead'") !== false && strpos($cp, "'create_task'") !== false);
$ad = file_get_contents(__DIR__ . '/../admin/automation.php');
check('后台步骤含新动作与参数', strpos($ad, 'update_lead:') !== false && strpos($ad, 'step_params[]') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
