<?php
/**
 * T2-5 验收：Agent 决策可解释轨道（DecisionTrace）
 *   php tests/decision_trace_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-dt-' . getmypid());
@mkdir(DATA_DIR . '/db', 0777, true);
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/DecisionTrace.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 记录轨迹 ──\n";
$id = dtrace_record([
    'subject'=>'z@t.com','decision'=>'主动推成交 · 发报价单','module'=>'Sales',
    'trigger'=>'互动分达 80 且未成交','evidence'=>['互动分 80','来源=自然搜索'],
    'candidates'=>['发报价单','内容培育'],'guard'=>'人工采纳',
]);
check('返回自增 id', $id > 0);
$t = dtrace_get($id);
check('能取回', $t !== null);
check('五个阶段齐全', count($t['steps']) === 5, (string)count($t['steps']));
check('trigger 记录', $t['steps'][0]['detail'] === '互动分达 80 且未成交');
check('evidence 用分号拼', strpos($t['steps'][1]['detail'], '互动分 80；来源=自然搜索') !== false);
check('candidates 记录', strpos($t['steps'][2]['detail'], '内容培育') !== false);
check('decision 记录', $t['steps'][3]['detail'] === '主动推成交 · 发报价单');
check('guard 记录', $t['steps'][4]['detail'] === '人工采纳');

echo "\n── 2. 默认护栏文案 ──\n";
$id2 = dtrace_record(['subject'=>'a@t.com','decision'=>'打标签']);
check('未给 guard 时有默认', strpos(dtrace_get($id2)['steps'][4]['detail'], '交人确认') !== false);

echo "\n── 3. 可读解释 ──\n";
$ex = dtrace_explain($t);
check('含"因为"', strpos($ex, '因为互动分达 80') !== false, $ex);
check('含"所以建议"', strpos($ex, '所以建议「主动推成交') !== false);
check('空轨迹返回空串', dtrace_explain([]) === '');

echo "\n── 4. 结果回写闭环 ──\n";
check('回写成功', dtrace_outcome($id, '客户已付款') === true);
check('结果已存', dtrace_get($id)['outcome'] === '客户已付款');
check('不存在的 id 返回 false', dtrace_outcome(999999, 'x') === false);
check('非法 id 返回 false', dtrace_outcome(0, 'x') === false);

echo "\n── 5. 列表与筛选 ──\n";
dtrace_record(['subject'=>'z@t.com','decision'=>'复购召回','module'=>'MA']);
check('全部 3 条', count(dtrace_list('', 50)) === 3);
check('按主体筛 2 条', count(dtrace_list('z@t.com', 50)) === 2);
check('新→旧', dtrace_list('z@t.com')[0]['decision'] === '复购召回');
check('limit 生效', count(dtrace_list('', 1)) === 1);
check('陌生主体返回空', dtrace_list('nobody@t.com') === []);

echo "\n── 6. 统计采纳率 ──\n";
$s = dtrace_stats();
check('总数 3', $s['total'] === 3);
check('被执行 1', $s['acted'] === 1);
check('采纳率 33%', $s['rate'] === 33, (string)$s['rate']);
dtrace_outcome($id2, 'ignored');
check('ignored 不计入执行', dtrace_stats()['acted'] === 1);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@unlink(DATA_DIR.'/db/openflow.db'); @array_map('unlink', glob(DATA_DIR.'/db/*'));
@rmdir(DATA_DIR.'/db'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
