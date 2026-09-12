<?php
/**
 * CDP 标签表达式 + 实时人群看板 契约
 *   php tests/cdp_rules_test.php
 */

$tmp = sys_get_temp_dir() . '/of-cdprules-' . getmypid();
@mkdir($tmp . '/cdp', 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CdpSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

$profile = [
    'visitor_id' => 'v1',
    'properties' => ['city' => '上海', 'vip' => '1'],
    'summaries' => ['article_view' => 6, 'purchase_amount_total' => 500],
    'tags' => ['vip' => ['type' => 'auto'], '高意向' => ['type' => 'auto']],
    'lifecycle' => ['stage' => 'customer'],
];

echo "CDP 标签表达式 + 实时人群\n";

// AND
check('AND：汇总≥5 且 城市=上海', CdpSystem::evaluateExpr(['op' => 'and', 'rules' => [
    ['field' => 'summaries.article_view', 'operator' => 'gte', 'value' => 5],
    ['field' => 'properties.city', 'operator' => 'eq', 'value' => '上海'],
]], $profile) === true);
check('AND：一条不满足即 false', CdpSystem::evaluateExpr(['op' => 'and', 'rules' => [
    ['field' => 'properties.city', 'operator' => 'eq', 'value' => '北京'],
    ['field' => 'summaries.article_view', 'operator' => 'gte', 'value' => 5],
]], $profile) === false);

// OR + 嵌套
check('OR：城市=北京 或 有 vip 标签', CdpSystem::evaluateExpr(['op' => 'or', 'rules' => [
    ['field' => 'properties.city', 'operator' => 'eq', 'value' => '北京'],
    ['field' => 'tags', 'operator' => 'exists', 'value' => 'vip'],
]], $profile) === true);
check('嵌套：AND( 消费>100, OR(城市=上海, 标签=高意向) )', CdpSystem::evaluateExpr(['op' => 'and', 'rules' => [
    ['field' => 'summaries.purchase_amount_total', 'operator' => 'gt', 'value' => 100],
    ['op' => 'or', 'rules' => [
        ['field' => 'properties.city', 'operator' => 'eq', 'value' => '上海'],
        ['field' => 'tags', 'value' => '高意向'],
    ]],
]], $profile) === true);

// 运算符
check('between', CdpSystem::evaluateExpr(['field' => 'summaries.purchase_amount_total', 'operator' => 'between', 'value' => [100, 1000]], $profile) === true);
check('neq', CdpSystem::evaluateExpr(['field' => 'properties.city', 'operator' => 'neq', 'value' => '北京'], $profile) === true);
check('in', CdpSystem::evaluateExpr(['field' => 'properties.city', 'operator' => 'in', 'value' => ['上海', '北京']], $profile) === true);
check('contains', CdpSystem::evaluateExpr(['field' => 'properties.city', 'operator' => 'contains', 'value' => '上'], $profile) === true);
check('not_exists', CdpSystem::evaluateExpr(['field' => 'properties.phone', 'operator' => 'not_exists'], $profile) === true);
check('lifecycle.stage', CdpSystem::evaluateExpr(['field' => 'lifecycle.stage', 'operator' => 'eq', 'value' => 'customer'], $profile) === true);
check('regex', CdpSystem::evaluateExpr(['field' => 'properties.city', 'operator' => 'regex', 'value' => '^上'], $profile) === true);

// 结构守卫
$ce = file_get_contents(__DIR__ . '/../lib/CdpSystem.php');
check('autoTag 支持表达式', strpos($ce, "evaluateExpr(\$rule['expr']") !== false);
$tr = file_get_contents(__DIR__ . '/../admin/tag-rules.php');
check('标签页支持表达式', strpos($tr, 'name="expr"') !== false);
$api = file_get_contents(__DIR__ . '/../api/cdp.php');
check('实时人群 API', strpos($api, 'live_audience') !== false);
check('实时人群页存在', is_file(__DIR__ . '/../admin/audience-live.php'));

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
