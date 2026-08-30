<?php
/**
 * T2-10 验收：贡献复利飞轮（ContributionFlywheel）
 *   php tests/contribution_flywheel_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-fw-' . getmypid());
@mkdir(DATA_DIR . '/ecosystem', 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/ContributionFlywheel.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 复用记账 ──\n";
check('记一笔', flywheel_record('skill:a', 'agent', 'call')['ok'] === true);
check('缺被使用方被拒', (flywheel_record('')['ok'] ?? true) === false);
flywheel_record('skill:a', 'agent', 'call');
flywheel_record('skill:a', 'skill:b', 'call');   // b 用了 a
flywheel_record('skill:a', 'user1', 'install');
$u = flywheel_usage('skill:a');
check('总使用 4 次', $u['total'] === 4, (string)$u['total']);
check('按类型拆分', ($u['by_kind']['call'] ?? 0) === 3 && ($u['by_kind']['install'] ?? 0) === 1);
check('按来源拆分', ($u['by_from']['agent'] ?? 0) === 2);
check('非法类型归 call', flywheel_record('skill:z','agent','bogus')['ok'] === true && (flywheel_usage('skill:z')['by_kind']['call'] ?? 0) === 1);

echo "\n── 2. 复利：二阶传播算功劳 ──\n";
// b 被用了 5 次，而 b 用了 a → a 有二阶功劳
for ($i=0;$i<5;$i++) flywheel_record('skill:b', 'agent', 'call');
$sa = flywheel_score('skill:a');
check('直接使用 4', $sa['direct'] === 4);
check('二阶 5（b 的使用量）', $sa['second'] === 5, (string)$sa['second']);
check('复利分 = 4 + 5*0.3 = 5.5', $sa['score'] === 5.5, (string)$sa['score']);
check('识别出下游消费者', in_array('skill:b', $sa['consumers'], true));
$sb = flywheel_score('skill:b');
check('b 无下游贡献物→二阶 0', $sb['second'] === 0);
check('权重可调', flywheel_score('skill:a', null, 1.0)['score'] === 9.0);

echo "\n── 3. 贡献者排行（谁让池子变厚）──\n";
$owners = ['skill:a'=>'张三', 'skill:b'=>'李四', 'skill:z'=>'张三'];
$lb = flywheel_leaderboard($owners);
check('张三居首(a 的复利高)', $lb[0]['owner'] === '张三', json_encode($lb));
check('聚合了他的多个贡献物', $lb[0]['items'] === 2);
check('李四也在榜', count(array_filter($lb, fn($r)=>$r['owner']==='李四')) === 1);
check('limit 生效', count(flywheel_leaderboard($owners, null, 1)) === 1);

echo "\n── 4. 公共能力池提名（提名不自动生效）──\n";
$cand = flywheel_promote_candidates(['skill:a','skill:b','skill:z'], 5);
$ids = array_column($cand, 'uid');
check('高复用被提名', in_array('skill:a', $ids, true) && in_array('skill:b', $ids, true), json_encode($ids));
check('低复用不提名', !in_array('skill:z', $ids, true));
check('按分排序', $cand[0]['score'] >= $cand[1]['score']);
check('阈值调高则无人达标', flywheel_promote_candidates(['skill:a'], 999) === []);

echo "\n── 5. 飞轮健康度（正外部性有没有发生）──\n";
$h = flywheel_health(2, 3);
check('人均贡献物 1.5', $h['per_contributor'] === 1.5);
check('件均复用>=1 视为健康', $h['healthy'] === true, json_encode($h));
$cold = flywheel_health(0, 0, []);
check('空生态不炸', $cold['per_contributor'] === 0.0 && $cold['healthy'] === false);
check('有贡献无复用→不健康', flywheel_health(1, 10, [])['healthy'] === false);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR.'/ecosystem/*')); @rmdir(DATA_DIR.'/ecosystem'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
