<?php
/**
 * T2-12 验收：联邦增长智能隐私骨架（FederatedGrowth）
 *   php tests/federated_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-fed-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/FederatedGrowth.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 默认不参与（opt-in）──\n";
check('默认关闭', fed_settings()['enabled'] === false);
check('未加入→拒绝生成包', (fed_build_contribution([])['ok'] ?? true) === false);

echo "\n── 2. 强制脱敏 ──\n";
$dirty = ['email'=>'a@t.com','name'=>'张三','note'=>'联系 b@t.com 或 13800138000，见 https://x.com/p','stat'=>5,
          'nested'=>['phone'=>'139','ok'=>'safe']];
$clean = fed_sanitize($dirty);
check('剥除 email 字段', !isset($clean['email']));
check('剥除 name 字段', !isset($clean['name']));
check('嵌套里的 phone 也剥', !isset($clean['nested']['phone']));
check('保留非标识字段', $clean['stat'] === 5 && $clean['nested']['ok'] === 'safe');
check('文本里的邮箱被抹', strpos($clean['note'], '[email]') !== false && strpos($clean['note'], 'b@t.com') === false);
check('文本里的手机被抹', strpos($clean['note'], '[phone]') !== false);
check('文本里的URL被抹', strpos($clean['note'], '[url]') !== false);

echo "\n── 3. k-匿名：样本不足直接丢 ──\n";
$rows = [];
for ($i=0;$i<6;$i++) $rows[] = ['dim'=>'自然搜索','value'=>100];
for ($i=0;$i<2;$i++) $rows[] = ['dim'=>'小众来源','value'=>999];
$agg = fed_aggregate($rows);
check('够样本的保留', count(array_filter($agg, fn($r)=>$r['dim']==='自然搜索')) === 1);
check('不足 k 的丢弃', count(array_filter($agg, fn($r)=>$r['dim']==='小众来源')) === 0, json_encode($agg));
check('输出只有聚合量', array_keys($agg[0]) === ['dim','n','avg']);
check('均值正确', $agg[0]['avg'] === 100.0);
check('空维度跳过', count(fed_aggregate([['dim'=>'','value'=>1]])) === 0);

echo "\n── 4. 加入后生成贡献包 ──\n";
fed_opt_in(true);
check('已加入', fed_settings()['enabled'] === true);
$b = fed_build_contribution($rows, []);
check('生成成功', $b['ok'] === true);
check('带 schema', $b['pack']['schema'] === 'openflow.federated.v1');
check('带 k 门槛', $b['pack']['k_threshold'] === 5);
check('只含聚合', count($b['pack']['conversion_by_source']) === 1);

echo "\n── 5. 红线自检 ──\n";
$audit = fed_audit($b['pack']);
check('生成的包是干净的', $audit['clean'] === true, json_encode($audit['problems']));
$bad = fed_audit(['x'=>['email'=>'a@t.com']]);
check('识别标识字段', $bad['clean'] === false);
check('识别文本邮箱', fed_audit(['note'=>'contact a@t.com'])['clean'] === false);
check('识别文本手机', fed_audit(['note'=>'call 13800138000'])['clean'] === false);

echo "\n── 6. 消费他站数据 ──\n";
$r = fed_consume($b['pack']);
check('消费成功', $r['ok'] === true);
check('转成可读建议', count($r['tips']) === 1 && strpos($r['tips'][0], '自然搜索') !== false, json_encode($r['tips']));
check('未知格式拒收', (fed_consume(['schema'=>'x'])['ok'] ?? true) === false);
check('k 门槛不足拒收', (fed_consume(['schema'=>'openflow.federated.v1','k_threshold'=>1])['ok'] ?? true) === false);
$smuggle = ['schema'=>'openflow.federated.v1','k_threshold'=>5,'conversion_by_source'=>[['dim'=>'x','n'=>1,'avg'=>1]]];
check('包内小样本条目被过滤', count(fed_consume($smuggle)['tips']) === 0);

echo "\n── 7. 可随时退出 ──\n";
fed_opt_in(false);
check('退出后关闭', fed_settings()['enabled'] === false);
check('退出后不再生成', (fed_build_contribution($rows)['ok'] ?? true) === false);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@unlink(DATA_DIR.'/settings.json'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
