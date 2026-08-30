<?php
/**
 * T2-3 验收：建群人数预估 + 群趋势（SegmentEstimate）
 *   php tests/segment_estimate_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-segest-' . getmypid());
@mkdir(DATA_DIR . '/cdp', 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/SegmentEstimate.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$profiles = [];
for ($i=0; $i<100; $i++) {
    $profiles["v$i"] = ['visitor_id'=>"v$i",'events_count'=>$i,'properties'=>['city'=>$i<30?'北京':'上海']];
}

echo "\n── 1. 规则预估 ──\n";
$seg = ['rules'=>[['field'=>'city','op'=>'eq','value'=>'北京']]];
$e = segest_estimate($seg, $profiles);
check('命中 30 人', $e['count'] === 30, json_encode($e));
check('总数 100', $e['total'] === 100);
check('占比 30%', $e['rate'] === 30.0);
check('未采样', $e['sampled'] === false);

echo "\n── 2. 数值比较算子 ──\n";
check('gte 50 → 50 人', segest_estimate(['rules'=>[['field'=>'events_count','op'=>'gte','value'=>50]]], $profiles)['count'] === 50);
check('lt 10 → 10 人', segest_estimate(['rules'=>[['field'=>'events_count','op'=>'lt','value'=>10]]], $profiles)['count'] === 10);
check('neq 北京 → 70 人', segest_estimate(['rules'=>[['field'=>'city','op'=>'neq','value'=>'北京']]], $profiles)['count'] === 70);

echo "\n── 3. 多条规则=与逻辑 ──\n";
$both = ['rules'=>[['field'=>'city','op'=>'eq','value'=>'北京'],['field'=>'events_count','op'=>'gte','value'=>20]]];
check('北京且活跃≥20 → 10 人', segest_estimate($both, $profiles)['count'] === 10, json_encode(segest_estimate($both,$profiles)));

echo "\n── 4. 边界 ──\n";
check('无规则→全部命中', segest_estimate(['rules'=>[]], $profiles)['count'] === 100);
$zero = segest_estimate($seg, []);
check('空画像→0 且不炸', $zero['count'] === 0 && $zero['total'] === 0);
check('空画像 rate=0', $zero['rate'] === 0);
check('not_empty 算子', segest_estimate(['rules'=>[['field'=>'city','op'=>'not_empty']]], $profiles)['count'] === 100);

echo "\n── 5. 大数据集采样估算 ──\n";
$big = [];
for ($i=0; $i<12000; $i++) $big["b$i"] = ['properties'=>['city'=>$i%2===0?'北京':'上海']];
$eb = segest_estimate($seg, $big);
check('标记为采样', $eb['sampled'] === true);
check('估算接近真实一半', abs($eb['count'] - 6000) < 600, (string)$eb['count']);
check('总数仍是真实值', $eb['total'] === 12000);

echo "\n── 6. 趋势快照 ──\n";
segest_snapshot('seg1', 100, '2026-08-28');
segest_snapshot('seg1', 120, '2026-08-29');
segest_snapshot('seg1', 115, '2026-08-30');
$t = segest_trend('seg1', 30);
check('最新 115', $t['latest'] === 115);
check('环比 -5', $t['delta'] === -5);
check('方向 down', $t['direction'] === 'down');
check('序列 3 天', count($t['series']) === 3);
segest_snapshot('seg1', 130, '2026-08-30');
check('同日覆盖不新增', count(segest_trend('seg1')['series']) === 3);
check('覆盖后最新 130', segest_trend('seg1')['latest'] === 130);
check('无数据群→空序列', segest_trend('nope')['series'] === []);
check('单点时 delta=0', (function(){ segest_snapshot('solo', 10, '2026-08-30'); return segest_trend('solo')['direction']; })() === 'flat');

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR.'/cdp/*')); @rmdir(DATA_DIR.'/cdp'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
