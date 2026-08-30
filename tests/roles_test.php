<?php
/**
 * 自定义角色与权限矩阵验收
 *
 *   php tests/roles_test.php
 *
 * 重点：admin 永远不能被削弱（防自锁）、自定义角色只认注册表内的权限、
 * 脏数据被挡、内置角色可覆盖但不可删。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-roles-test-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}

// 抽取 config.php 里的角色相关函数（避开整份 config 的副作用）
$src = file_get_contents(__DIR__ . '/../admin/config.php');
foreach (['of_perm_registry','of_builtin_roles','of_custom_roles','role_perms','role_label'] as $fn) {
    if (!preg_match('/\nfunction ' . $fn . '\(.*?\n\}/s', $src, $m)) { fwrite(STDERR, "缺 {$fn}\n"); exit(2); }
    eval($m[0]);
}

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}
$rolesFile = DATA_DIR . '/roles.json';
function reset_roles(array $r = []) { global $rolesFile; json_write($rolesFile, $r); }

echo "\n── 1. 内置角色 ──\n";
reset_roles();
$rp = role_perms();
check('内置三角色都在', isset($rp['admin'], $rp['marketing'], $rp['sales']));
check('admin = 全量注册表', count($rp['admin']) === count(of_perm_registry()));
check('sales 是收窄的子集', count($rp['sales']) < count($rp['admin']) && in_array('crm', $rp['sales'], true));
check('内置中文名', role_label('admin') === '超级管理员' && role_label('sales') === '销售');

echo "\n── 2. 新增自定义角色 ──\n";
reset_roles(['editor' => ['label' => '内容编辑', 'perms' => ['articles','pages','media','seo']]]);
$rp = role_perms();
check('自定义角色进入映射', isset($rp['editor']));
check('权限正确', $rp['editor'] === ['pages','articles','seo','media'] || array_diff(['articles','pages','media','seo'], $rp['editor']) === []);
check('自定义中文名', role_label('editor') === '内容编辑');
check('未授的权限确实没有', !in_array('settings', $rp['editor'], true) && !in_array('users', $rp['editor'], true));

echo "\n── 3. 安全底线：admin 永不被削弱 ──\n";
reset_roles(['admin' => ['label' => '假 admin', 'perms' => ['articles']]]);
$rp = role_perms();
check('覆盖 admin 被忽略', count($rp['admin']) === count(of_perm_registry()));
check('admin 中文名不被改', role_label('admin') === '超级管理员');

echo "\n── 4. 脏数据防御 ──\n";
reset_roles(['weird' => ['label' => 'X', 'perms' => ['articles','__不存在__','settings','; DROP']]]);
$rp = role_perms();
check('非注册表权限被过滤', $rp['weird'] === array_values(array_intersect(of_perm_registry(), ['articles','__不存在__','settings','; DROP'])));
check('只留下合法项', $rp['weird'] === ['articles','settings'] || (in_array('articles',$rp['weird'],true) && in_array('settings',$rp['weird'],true) && count($rp['weird'])===2));

reset_roles(['plain' => ['articles','crm']]);  // 兼容纯数组写法
$rp = role_perms();
check('兼容纯数组权限写法', isset($rp['plain']) && in_array('crm', $rp['plain'], true));

echo "\n── 5. 注册表覆盖了新加的权限位 ──\n";
check('security 在注册表', in_array('security', of_perm_registry(), true));
check('注册表无重复', count(of_perm_registry()) === count(array_unique(of_perm_registry())));

@unlink($rolesFile);
@rmdir(DATA_DIR);

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
