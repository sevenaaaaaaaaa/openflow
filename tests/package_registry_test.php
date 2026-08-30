<?php
/**
 * T2-11 验收：版本/依赖/兼容（PackageRegistry）
 *   php tests/package_registry_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-pkg-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/PackageRegistry.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 版本解析与比较 ──\n";
check('解析 1.2.3', semver_parse('1.2.3') === [1,2,3]);
check('去 v 前缀', semver_parse('v2.0') === [2,0,0]);
check('忽略预发布后缀', semver_parse('1.2.3-beta.1') === [1,2,3]);
check('缺位补 0', semver_parse('3') === [3,0,0]);
check('小于', semver_cmp('1.2.3','1.3.0') === -1);
check('大于', semver_cmp('2.0.0','1.9.9') === 1);
check('相等', semver_cmp('1.0','1.0.0') === 0);

echo "\n── 2. 版本区间约束 ──\n";
check('* 任意', semver_satisfies('9.9.9','*') === true);
check('空约束任意', semver_satisfies('1.0.0','') === true);
check('^1.2.3 接受 1.9', semver_satisfies('1.9.0','^1.2.3') === true);
check('^1.2.3 拒 2.0', semver_satisfies('2.0.0','^1.2.3') === false);
check('^1.2.3 拒 1.2.2', semver_satisfies('1.2.2','^1.2.3') === false);
check('~1.2.3 接受 1.2.9', semver_satisfies('1.2.9','~1.2.3') === true);
check('~1.2.3 拒 1.3.0', semver_satisfies('1.3.0','~1.2.3') === false);
check('>=2.0 接受 2.1', semver_satisfies('2.1.0','>=2.0') === true);
check('>=2.0 拒 1.9', semver_satisfies('1.9.0','>=2.0') === false);
check('<3 接受 2.9', semver_satisfies('2.9.0','<3') === true);
check('=1.0.0 精确', semver_satisfies('1.0.0','=1.0.0') === true && semver_satisfies('1.0.1','=1.0.0') === false);
check('多约束与逻辑', semver_satisfies('1.5.0','>=1.2 <2.0') === true && semver_satisfies('2.1.0','>=1.2 <2.0') === false);

echo "\n── 3. 依赖检查 ──\n";
$pkg = ['id'=>'my','version'=>'1.0.0','requires'=>['seo'=>'^1.2'],'platform'=>'>=1.0','conflicts'=>['old-seo']];
$ok = pkg_check($pkg, ['seo'=>'1.5.0'], '2.0.0');
check('依赖满足→ok', $ok['ok'] === true, json_encode($ok['reasons']));
$miss = pkg_check($pkg, [], '2.0.0');
check('缺依赖', $miss['ok'] === false && count($miss['missing']) === 1);
check('给出缺什么', $miss['missing'][0]['id'] === 'seo');
$old = pkg_check($pkg, ['seo'=>'1.0.0'], '2.0.0');
check('版本过旧', count($old['outdated']) === 1);
check('说明当前与需要', $old['outdated'][0]['have'] === '1.0.0' && $old['outdated'][0]['need'] === '^1.2');
$conf = pkg_check($pkg, ['seo'=>'1.5.0','old-seo'=>'1.0.0'], '2.0.0');
check('冲突检测', $conf['ok'] === false && $conf['conflicts'] === ['old-seo']);
$plat = pkg_check($pkg, ['seo'=>'1.5.0'], '0.9.0');
check('平台版本不满足', $plat['platform_ok'] === false && $plat['ok'] === false);
check('给出可读原因', count($plat['reasons']) >= 1 && strpos($plat['reasons'][0], '平台版本') !== false);
check('无依赖声明→直接 ok', pkg_check(['id'=>'x'], [], '1.0.0')['ok'] === true);

echo "\n── 4. 升级影响评估 ──\n";
$installed = [
    ['id'=>'a','requires'=>['seo'=>'^1.0']],
    ['id'=>'b','requires'=>['seo'=>'>=1.5']],
    ['id'=>'c','requires'=>[]],
];
$minor = pkg_upgrade_impact('seo', '1.5.0', '1.8.0', $installed);
check('小版本非破坏性', $minor['breaking'] === false);
check('无人被升挂', $minor['safe'] === true, json_encode($minor['will_break']));
$major = pkg_upgrade_impact('seo', '1.5.0', '2.0.0', $installed);
check('主版本=破坏性', $major['breaking'] === true);
check('识别会挂的包', count($major['will_break']) === 1 && $major['will_break'][0]['id'] === 'a', json_encode($major['will_break']));
check('b 因 >=1.5 仍兼容', count(array_filter($major['affected'], fn($x)=>$x['id']==='b' && $x['ok_after'])) === 1);
check('无关包不列入', count(array_filter($major['affected'], fn($x)=>$x['id']==='c')) === 0);
check('降级方向识别', pkg_upgrade_impact('seo','2.0.0','1.0.0',[])['direction'] === -1);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
