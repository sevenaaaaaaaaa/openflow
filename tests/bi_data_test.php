<?php
/**
 * 对话式 BI 契约 —— 数据集整形 / AI 选型校验 / 图表只用真实数据
 *   php tests/bi_data_test.php
 */

require_once __DIR__ . '/../lib/BiData.php';
require_once __DIR__ . '/../lib/AskData.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "对话式 BI\n";

$days = bi_fill_days([date('Y-m-d') => 5], 7);
check('时间序列补齐为 7 天', count($days) === 7);
check('缺日补 0、当日有值', $days[6]['value'] === 5 && $days[0]['value'] === 0);
check('点带 label', isset($days[0]['label'], $days[0]['value']));

$pts = bi_points(['甲' => 3, '乙' => 1]);
check('分布点保序', $pts[0]['label'] === '甲' && $pts[1]['value'] === 1);

$sets = [
    ['key' => 'revenue_by_day', 'label' => '每日营收', 'type' => 'timeseries', 'unit' => '¥', 'points' => [['label' => '2026-09-01', 'value' => 100], ['label' => '2026-09-02', 'value' => 250]]],
    ['key' => 'leads_by_stage', 'label' => '线索阶段分布', 'type' => 'breakdown', 'unit' => '条', 'points' => [['label' => 'new', 'value' => 4]]],
];
$cat = bi_catalog($sets);
check('目录含 key/type/unit', ($cat[0]['key'] ?? '') === 'revenue_by_day' && ($cat[0]['type'] ?? '') === 'timeseries');

$r = askdata_bi_shape(['answer' => '营收在涨', 'dataset' => 'revenue_by_day', 'chart_type' => 'line', 'title' => '营收趋势', 'followups' => ['a', 'b', 'c', 'd']], $sets);
check('选到数据集则出图', is_array($r['chart']) && $r['chart']['dataset'] === 'revenue_by_day');
check('图表用真实点', count($r['chart']['points']) === 2 && $r['chart']['points'][1]['value'] === 250);
check('追问最多 3 条', count($r['followups']) === 3);

$r2 = askdata_bi_shape(['answer' => 'x', 'dataset' => 'not_exist', 'chart_type' => 'bar'], $sets);
check('不存在的数据集 → 不出图', $r2['chart'] === null);

$r3 = askdata_bi_shape(['answer' => 'x', 'dataset' => 'leads_by_stage', 'chart_type' => 'line'], $sets);
check('分布数据被强制为柱状', $r3['chart']['type'] === 'bar');

$r4 = askdata_bi_shape(['answer' => 'x', 'dataset' => 'revenue_by_day', 'chart_type' => 'pie'], $sets);
check('非法图表类型 → none', $r4['chart'] === null);

// 注入式：AI 不可用时也能跑通（测试/降级）
$GLOBALS['ASKDATA_BI_FN'] = fn($q, $s) => ['ok' => true, 'answer' => 'INJ', 'chart' => null, 'followups' => [], 'data' => $s];
$ri = askdata_bi_answer('随便问', $sets);
check('注入式回答可用', ($ri['answer'] ?? '') === 'INJ');

// 结构守卫：BI 端点必须鉴权
$ep = file_get_contents(__DIR__ . '/../api/ask-data-bi.php');
check('BI 端点要登录', strpos($ep, 'require_login(') !== false);
check('BI 端点要 insights 权限', strpos($ep, "require_perm('insights')") !== false);
check('BI 端点限流', strpos($ep, 'RateLimiter::throttle') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
