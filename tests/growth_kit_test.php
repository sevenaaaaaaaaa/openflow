<?php
/**
 * 增长工具箱 v0 契约 — 晨会简报 / 利润推演 / MCP prompts
 *   php tests/growth_kit_test.php
 */

$tmp = sys_get_temp_dir() . '/of-growthkit-' . getmypid();
@mkdir($tmp . '/trends', 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MorningBriefing.php';
require_once __DIR__ . '/../lib/ProfitLab.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "晨会简报\n";
$d = morning_briefing_data();
check('简报数据含日期与泳道', isset($d['date'], $d['now'], $d['today']));
$tpl = morning_briefing_text($d);
check('模板简报可生成', mb_strlen($tpl) > 50);
check('简报开场口吻', mb_strpos($tpl, '早上好') === 0);
$r = morning_briefing_ai();
check('AI 简报降级安全', !empty($r['ok']) && mb_strlen($r['text']) > 50);
check('简报标注模式', in_array($r['mode'] ?? '', ['template', 'ai'], true));

echo "利润推演\n";
$b = profitlab_baseline();
check('基线含五环', isset($b['uv'], $b['lead_rate'], $b['close_rate'], $b['aov'], $b['repeat_rate']));
check('空数据用经验兜底并标注', !empty($b['fallback_used']));
// 自洽基线:1000 访客 × 3% 线索率 × 20% 成交率 × ¥299 × (1+10% 复购) = 1973.4/30天
$BASE = ['uv' => 1000, 'leads' => 30, 'buyers' => 6, 'lead_rate' => 0.03, 'close_rate' => 0.2, 'aov' => 299, 'repeat_rate' => 0.1, 'monthly_revenue' => 1973.4, 'model_revenue' => 1973.4, 'fallback_used' => false, 'missing' => []];
$w = profitlab_whatif(['close_rate' => 0.1], $BASE);
check('whatif 返回收入变化', isset($w['new_monthly_revenue'], $w['delta_revenue']));
check('成交率+10% 收入上升', $w['delta_revenue'] > 0, 'delta=' . ($w['delta_revenue'] ?? '?'));
$w2 = profitlab_whatif(['uv' => -0.5], $BASE);
check('访客减半收入下降', $w2['delta_revenue'] < 0, 'delta=' . ($w2['delta_revenue'] ?? '?'));
$s = profitlab_sensitivity($BASE);
check('灵敏度输出五杠杆', count($s) === 5);
check('杠杆按影响排序', $s[0]['delta_revenue'] >= $s[4]['delta_revenue']);

echo "MCP 增长技能包\n";
$code = file_get_contents(__DIR__ . '/../mcp-server.php');
check('initialize 声明 prompts 能力', strpos($code, "'prompts'=>['listChanged'=>false]") !== false);
check('prompts/list 已分发', strpos($code, "case 'prompts/list'") !== false);
check('prompts/get 已分发', strpos($code, "case 'prompts/get'") !== false);
foreach (['weekly_growth_review', 'content_idea_from_trends', 'lead_nurture_plan', 'site_growth_audit'] as $p) {
    check("prompt {$p} 已定义", strpos($code, "'id' => '{$p}'") !== false, '未找到 id 键');
}

echo "\n{$pass} passed · {$fail} failed\n";
exit($fail ? 1 : 0);
