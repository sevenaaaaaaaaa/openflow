<?php
/**
 * T2-4 验收：渐进自治护栏 + 目标制回路（AutonomyGuard）
 *   php tests/autonomy_guard_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-auto-' . getmypid());
@mkdir(DATA_DIR . '/growth', 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/AutonomyGuard.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 默认只提议（最保守）──\n";
check('默认级别 propose', autonomy_settings()['level'] === 'propose');
$r = autonomy_can_auto(['action'=>'打标签：高意向']);
check('propose 下一律不自动', $r['allow'] === false && $r['requires_human'] === true);

echo "\n── 2. 风险分级（硬边界）──\n";
check('发钱=高风险', autonomy_high_risk('给他发钱补偿') === true);
check('群发=高风险', autonomy_high_risk('群发本周通讯') === true);
check('改价=高风险', autonomy_high_risk('改价促销') === true);
check('打标=低风险', autonomy_low_risk('打标签：高意向') === true);
check('推荐=低风险', autonomy_low_risk('推荐相关内容') === true);
check('发报价单不算低风险', autonomy_low_risk('主动推成交 · 发报价单') === false);

echo "\n── 3. guarded：低风险放行、高风险仍拦 ──\n";
autonomy_save(['level'=>'guarded','daily_action_cap'=>3,'daily_budget'=>0,'quiet_days'=>0]);
$ok = autonomy_can_auto(['action'=>'打标签：高意向']);
check('低风险允许自动', $ok['allow'] === true, json_encode($ok));
$hr = autonomy_can_auto(['action'=>'群发优惠券']);
check('高风险任何级别都拦', $hr['allow'] === false);
check('给出拦截理由', strpos($hr['reason'], '高风险') !== false);
check('非白名单动作也拦', autonomy_can_auto(['action'=>'打电话给客户'])['allow'] === false);

echo "\n── 4. 预算护栏 ──\n";
$costly = autonomy_can_auto(['action'=>'推荐内容','cost'=>5]);
check('未设预算→花钱动作拦', $costly['allow'] === false && strpos($costly['reason'],'预算') !== false);
autonomy_save(['level'=>'guarded','daily_action_cap'=>10,'daily_budget'=>10,'quiet_days'=>0]);
check('预算内放行', autonomy_can_auto(['action'=>'推荐内容','cost'=>5])['allow'] === true);
check('超预算拦', autonomy_can_auto(['action'=>'推荐内容','cost'=>50])['allow'] === false);
check('累计超预算拦', autonomy_can_auto(['action'=>'推荐内容','cost'=>8], null, ['actions'=>1,'spend'=>5.0])['allow'] === false);

echo "\n── 5. 频控护栏 ──\n";
check('达上限拦', autonomy_can_auto(['action'=>'打标签'], null, ['actions'=>10,'spend'=>0])['allow'] === false);
check('未达上限放行', autonomy_can_auto(['action'=>'打标签'], null, ['actions'=>3,'spend'=>0])['allow'] === true);

echo "\n── 6. 用量累计 ──\n";
autonomy_record(3.5, '2026-08-30');
autonomy_record(1.5, '2026-08-30');
$u = autonomy_usage('2026-08-30');
check('动作累计 2', $u['actions'] === 2);
check('花费累计 5.0', $u['spend'] === 5.0);
check('未记录日期为 0', autonomy_usage('2020-01-01')['actions'] === 0);

echo "\n── 7. 目标制回路建议 ──\n";
$noGoal = autonomy_loop_report(['has'=>false], ['actions'=>0,'spend'=>0]);
check('无目标提示设目标', $noGoal['has_goal'] === false && strpos($noGoal['advice'],'设一个') !== false);
$done = autonomy_loop_report(['has'=>true,'pct'=>100], ['actions'=>5,'spend'=>0]);
check('达标建议收敛', strpos($done['advice'], '只提议') !== false);
$behind = autonomy_loop_report(['has'=>true,'pct'=>20,'pace_note'=>'落后进度'], ['actions'=>15,'spend'=>0]);
check('落后但动作多→查是否对症', strpos($behind['advice'], '对症') !== false);
$idle = autonomy_loop_report(['has'=>true,'pct'=>30], ['actions'=>0,'spend'=>0]);
check('零动作提示放宽', strpos($idle['advice'], '还没有自动动作') !== false);
$normal = autonomy_loop_report(['has'=>true,'pct'=>50], ['actions'=>4,'spend'=>0]);
check('正常节奏保持', strpos($normal['advice'], '保持') !== false);

echo "\n── 8. 级别清单 ──\n";
check('三级齐全', count(autonomy_levels()) === 3);
check('非法级别回落 propose', autonomy_save(['level'=>'bogus'])['level'] === 'propose');

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR.'/growth/*')); @unlink(DATA_DIR.'/settings.json');
@rmdir(DATA_DIR.'/growth'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
