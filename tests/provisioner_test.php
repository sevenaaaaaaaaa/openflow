<?php
/**
 * 自动装配 契约 —— 校验白名单 / 真实创建 / 跳过 / 结构
 *   php tests/provisioner_test.php
 */

$tmp = sys_get_temp_dir() . '/of-prov-' . getmypid();
@mkdir($tmp . '/articles', 0777, true);
define('ARTICLES_DIR', $tmp . '/articles');
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CopilotActions.php';
require_once __DIR__ . '/../lib/GrowthGoal.php';
require_once __DIR__ . '/../lib/ConversionGoal.php';
require_once __DIR__ . '/../lib/Provisioner.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "自动装配\n";

// 校验白名单
check('合法流程通过', prov_validate_item(['tps' => 'personalize', 'title' => '欢迎流程', 'artifact' => ['type' => 'flow', 'name' => '欢迎', 'trigger' => 'member_register', 'steps' => [['action' => 'send_email', 'subject' => '欢迎', 'content' => 'hi']]]], 0) !== null);
check('非法流程被拒', prov_validate_item(['title' => 'x', 'artifact' => ['type' => 'flow', 'name' => 'x', 'trigger' => 'evil', 'steps' => [['action' => 'exec']]]], 1) === null);
check('未知类型被拒', prov_validate_item(['title' => 'x', 'artifact' => ['type' => 'rm_rf']], 2) === null);
check('任务项通过', prov_validate_item(['tps' => 'sell', 'title' => '建报价', 'artifact' => ['type' => 'task', 'title' => '跟进甲', 'assignee' => 'sales', 'priority' => 'high']], 3) !== null);
check('转化目标通过', prov_validate_item(['tps' => 'insight', 'title' => '预约目标', 'artifact' => ['type' => 'conversion_goal', 'name' => '预约', 'event' => 'form_submit', 'value' => 50]], 4) !== null);
check('增长目标非法指标被拒', prov_validate_item(['title' => 'x', 'artifact' => ['type' => 'growth_goal', 'metric' => 'bogus', 'target' => 100]], 5) === null);
check('增长目标合法', prov_validate_item(['tps' => 'insight', 'title' => '月收入', 'artifact' => ['type' => 'growth_goal', 'metric' => 'revenue', 'target' => 50000, 'title' => '月收入']], 6) !== null);

// 组装计划并应用
$items = [];
foreach ([
    ['tps' => 'sell', 'title' => '跟进甲', 'artifact' => ['type' => 'task', 'title' => '跟进甲', 'assignee' => 'sales', 'priority' => 'high']],
    ['tps' => 'insight', 'title' => '预约目标', 'artifact' => ['type' => 'conversion_goal', 'name' => '预约诊断', 'event' => 'form_submit', 'value' => 100]],
    ['tps' => 'touch', 'title' => '招生落地页', 'artifact' => ['type' => 'landing', 'title' => '招生页', 'slug' => 'enroll', 'cta_button' => '报名', 'blocks' => [['_type' => 'hero', 'title' => '报名']]]],
] as $i => $x) { $v = prov_validate_item($x, $i); if ($v) $items[] = $v; }
prov_plan_save(['items' => $items, 'generated_at' => date('Y-m-d H:i:s'), 'summary' => 'test']);
check('计划已存', count(prov_plan()['items']) === 3);

$r1 = prov_apply($items[0]['id']);
check('接受任务项 → 真实创建', !empty($r1['ok']) && ($r1['created']['type'] ?? '') === 'task');
check('任务写入 tasks.json', count(json_read(DATA_DIR . '/tasks.json')) === 1);

$r2 = prov_apply($items[1]['id']);
check('接受转化目标 → 真实创建', !empty($r2['ok']) && cg_get($r2['created']['id']) !== null);

$r3 = prov_apply($items[2]['id']);
check('接受落地页 → 真实创建', !empty($r3['ok']) && builder_page_get($r3['created']['id']) !== null);

check('重复接受幂等', prov_apply($items[0]['id'])['already'] ?? false);
check('接受后状态为 accepted', prov_plan()['items'][0]['status'] === 'accepted');

$skipId = $items[1]['id'];
check('跳过项', prov_skip($skipId) === true);

// 结构守卫
$api = file_get_contents(__DIR__ . '/../api/provision.php');
check('装配API要登录', strpos($api, 'require_login(') !== false);
check('装配API要权限', strpos($api, "require_perm('settings')") !== false);
$nav = file_get_contents(__DIR__ . '/../includes/admin-nav.php');
check('导航含自动装配', strpos($nav, "'provision'") !== false);
check('装配页存在', is_file(__DIR__ . '/../admin/provision.php'));

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
