<?php
/**
 * T1-9 验收：销售话术/物料草稿（SalesPlaybook）
 *   php tests/sales_playbook_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-pb-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/SalesPlaybook.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$newLead = ['name'=>'张先生','email'=>'z@t.com','score'=>80,'won_count'=>0,'ltv'=>0,'days_idle'=>1,'source'=>'自然搜索'];
$oldCust = ['name'=>'李总','won_count'=>3,'ltv'=>12000,'days_idle'=>45,'source'=>''];
$anon    = ['name'=>'ghost@t.com','won_count'=>0,'ltv'=>0,'days_idle'=>90];

echo "\n── 1. 动作→物料类型映射 ──\n";
check('推成交→报价说明', playbook_kind_for_action('主动推成交 · 发报价单') === 'quote');
check('复购→跟进邮件', playbook_kind_for_action('老客复购召回') === 'followup');
check('挽回→跟进邮件', playbook_kind_for_action('沉默高意向挽回') === 'followup');
check('培育→开场白', playbook_kind_for_action('内容培育 · 补全画像') === 'opener');

echo "\n── 2. 称呼 ──\n";
check('有名字用名字', playbook_salutation($newLead) === '张先生 您好');
check('邮箱当名字→通称', playbook_salutation($anon) === '您好');
check('空名→通称', playbook_salutation([]) === '您好');

echo "\n── 3. 报价草稿 ──\n";
$q = playbook_draft('quote', $newLead, ['amount'=>5000]);
check('标题=报价说明', $q['title'] === '报价说明');
check('含称呼', strpos($q['body'], '张先生 您好') !== false);
check('含金额', strpos($q['body'], '5,000') !== false, $q['body']);
check('含收款链接占位', strpos($q['body'], '[收款链接]') !== false);
check('带提示', !empty($q['tips']));
$q2 = playbook_draft('quote', $newLead);
check('无金额时不显示价格', strpos($q2['body'], '总价') === false);

echo "\n── 4. 跟进邮件按老客/新客分叉 ──\n";
$f1 = playbook_draft('followup', $oldCust);
check('老客提到沉默天数', strpos($f1['body'], '45 天') !== false, $f1['body']);
check('高 LTV 给优先支持', strpos($f1['body'], '优先支持') !== false);
$f2 = playbook_draft('followup', $newLead);
check('未成交走"问阻力"版本', strpos($f2['body'], '没讲清楚') !== false);
check('给明确说不的出口', strpos($f2['body'], '不再跟进') !== false);

echo "\n── 5. 异议应对 / 开场白 ──\n";
$o = playbook_draft('objection', $newLead);
check('含"太贵了"', strpos($o['body'], '太贵了') !== false);
check('含"再考虑"', strpos($o['body'], '再考虑') !== false);
$op = playbook_draft('opener', $newLead);
check('开场白带来源', strpos($op['body'], '自然搜索') !== false);
$op2 = playbook_draft('opener', $oldCust);
check('无来源时不硬塞', strpos($op2['body'], '过来的') === false);

echo "\n── 6. 非法类型回落 opener ──\n";
check('未知 kind→opener', playbook_draft('bogus', $newLead)['kind'] === 'opener');

echo "\n── 7. 按提议直接配物料 ──\n";
$pb = playbook_for_proposal(['action'=>'主动推成交 · 发报价单','module'=>'Sales'], $newLead);
check('提议→报价物料', $pb['kind'] === 'quote');

echo "\n── 8. AI 增强：可注入、失败回落 ──\n";
$GLOBALS['PLAYBOOK_AI_FN'] = function($ctx) { return 'AI 改写后的话术'; };
$polished = playbook_ai_polish($q, $newLead);
check('AI 改写生效', $polished['body'] === 'AI 改写后的话术');
check('标记 ai', ($polished['ai'] ?? false) === true);
$GLOBALS['PLAYBOOK_AI_FN'] = function($ctx) { return '   '; };
$keep = playbook_ai_polish($q, $newLead);
check('AI 返回空白→保留原文', $keep['body'] === $q['body']);
unset($GLOBALS['PLAYBOOK_AI_FN']);
check('无 AI 配置→原样', playbook_ai_polish($q, $newLead)['body'] === $q['body']);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
