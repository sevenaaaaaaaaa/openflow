<?php
/**
 * T1-4 验收：可视化圈选埋点（ClickTracker）
 *   php tests/clicktrack_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-ct-' . getmypid());
@mkdir(DATA_DIR . '/cdp', 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/ClickTracker.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 事件名规范化 ──\n";
check('中文名→兜底', clicktrack_norm_event('报名 按钮!') === '');
check('英文规范化', clicktrack_norm_event('Signup Button!') === 'signup_button');
check('去首尾下划线', clicktrack_norm_event('__a b__') === 'a_b');

echo "\n── 2. 选择器安全校验 ──\n";
check('合法 class', clicktrack_valid_selector('.btn-signup') === true);
check('合法复合', clicktrack_valid_selector('div.card > a[href]') === true);
check('拒空', clicktrack_valid_selector('') === false);
check('拒含尖括号(注入)', clicktrack_valid_selector('<script>') === false);
check('拒超长', clicktrack_valid_selector(str_repeat('a', 250)) === false);

echo "\n── 3. 创建 + 自动事件名 ──\n";
$r = clicktrack_save(['name'=>'Signup Button','selector'=>'.btn-signup','enabled'=>true]);
check('创建成功', $r['ok'] === true, $r['error'] ?? '');
check('事件名自动生成', $r['track']['event'] === 'signup_button', $r['track']['event'] ?? '');
$id = $r['track']['id'];
check('非法选择器被拒', (clicktrack_save(['name'=>'x','selector'=>'<b>'])['ok'] ?? true) === false);
check('空名被拒', (clicktrack_save(['name'=>'','selector'=>'.a'])['ok'] ?? true) === false);

echo "\n── 4. 更新保留 id/hits ──\n";
clicktrack_hit('signup_button');
clicktrack_hit('signup_button');
$r2 = clicktrack_save(['id'=>$id,'name'=>'首页报名','selector'=>'.btn-signup2','event'=>'signup_v2','enabled'=>true]);
check('id 不变', $r2['track']['id'] === $id);
check('hits 保留=2', $r2['track']['hits'] === 2, (string)$r2['track']['hits']);
check('选择器已更新', clicktrack_get($id)['selector'] === '.btn-signup2');

echo "\n── 5. 按页面过滤输出 ──\n";
clicktrack_save(['name'=>'仅文章页','selector'=>'.share','page'=>'/article','enabled'=>true]);
clicktrack_save(['name'=>'停用的','selector'=>'.x','enabled'=>false]);
$home = clicktrack_for_page('/');
$art  = clicktrack_for_page('/article/hello');
check('首页不含仅文章页的', count(array_filter($home, fn($t)=>$t['name']==='仅文章页')) === 0);
check('文章页含它', count(array_filter($art, fn($t)=>$t['name']==='仅文章页')) === 1);
check('停用的都不输出', count(array_filter($art, fn($t)=>$t['name']==='停用的')) === 0);
check('输出只含最小字段', array_keys($art[0]) === ['selector','event','name']);

echo "\n── 6. 开关 / 删除 ──\n";
check('toggle 命中', clicktrack_toggle($id) === true);
check('已停用', empty(clicktrack_get($id)['enabled']));
check('删除命中', clicktrack_delete($id) === true);
check('已删', clicktrack_get($id) === null);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR.'/cdp/*')); @rmdir(DATA_DIR.'/cdp'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
