<?php
declare(strict_types=1);
/**
 * 项目级成员权限（owner / editor / viewer）契约测试
 *   php tests/project_perm_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-perm-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ProjectSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : "") . "\n"; }
}

echo "项目级权限\n";

$_SESSION = ['admin_user' => 'Seven', 'admin_role' => 'admin'];
json_write(DATA_DIR . '/users.json', [
    'Seven' => ['name' => '超级管理员', 'role' => 'admin'],
    'marketing' => ['name' => '市场总监', 'role' => 'marketing'],
    'sales' => ['name' => '销售总监', 'role' => 'sales'],
]);

check('三种角色', array_keys(ps_project_roles()) === ['owner', 'editor', 'viewer']);
check('用户选项：登录名=>显示名', (ps_user_options()['marketing'] ?? '') === '市场总监');
check('显示名解析', ps_user_display('sales') === '销售总监' && ps_user_display('ghost') === 'ghost');

/* 成员规范化：新旧格式 + 非法角色最小权限 */
check('映射格式', ps_members_normalize(['Seven' => 'owner', 'marketing' => 'editor']) === ['Seven' => 'owner', 'marketing' => 'editor']);
check('旧列表格式 → 可编辑', ps_members_normalize(['Seven', 'marketing']) === ['Seven' => 'editor', 'marketing' => 'editor']);
check('非法角色 → 只读', ps_members_normalize(['Seven' => 'boss']) === ['Seven' => 'viewer']);
check('空值安全', ps_members_normalize('x') === [] && ps_members_normalize(['' => 'owner', ' ' => 'viewer']) === []);

/* 新建项目：创建者成为 owner */
$pa = (string) (ps_project_save(['name' => '我的项目'])['id'] ?? '');
check('创建者自动是 owner', ps_project_members($pa) === ['Seven' => 'owner'], json_encode(ps_project_members($pa)));
check('角色可查', ps_project_role($pa, 'Seven') === 'owner' && ps_project_role($pa, 'marketing') === '');

/* 站点管理员无视成员 */
check('站点管理员可 view/edit/manage', ps_can($pa, 'view', '', true) && ps_can($pa, 'edit', '', true) && ps_can($pa, 'manage', '', true));
check('非管理员 owner 全权', ps_can($pa, 'view', 'Seven') && ps_can($pa, 'edit', 'Seven') && ps_can($pa, 'manage', 'Seven'));
check('非成员一律不可', !ps_can($pa, 'view', 'marketing') && !ps_can($pa, 'edit', 'marketing') && !ps_can($pa, 'manage', 'marketing'));

/* editor / viewer 的边界 */
ps_member_set($pa, 'marketing', 'editor');
ps_member_set($pa, 'sales', 'viewer');
check('editor 能看能改不能管', ps_can($pa, 'view', 'marketing') && ps_can($pa, 'edit', 'marketing') && !ps_can($pa, 'manage', 'marketing'));
check('viewer 只能看', ps_can($pa, 'view', 'sales') && !ps_can($pa, 'edit', 'sales') && !ps_can($pa, 'manage', 'sales'));
check('成员表可读回', ps_project_members($pa) === ['Seven' => 'owner', 'marketing' => 'editor', 'sales' => 'viewer'], json_encode(ps_project_members($pa)));
check('升角色生效', (ps_member_set($pa, 'sales', 'editor')['ok'] ?? false) === true && ps_can($pa, 'edit', 'sales'));
check('非法角色被拒', (ps_member_set($pa, 'sales', 'boss')['ok'] ?? true) === false);
check('空成员名被拒', (ps_member_set($pa, '', 'editor')['ok'] ?? true) === false);
check('不存在的项目被拒', (ps_member_set('p_ghost', 'sales', 'editor')['ok'] ?? true) === false);
check('移除成员', ps_member_remove($pa, 'marketing') === true && ps_project_role($pa, 'marketing') === '');
check('移除不存在返回 false', ps_member_remove($pa, 'marketing') === false);
check('移除后不再可见', !ps_can($pa, 'view', 'marketing'));

/* 公共项目（没设成员）向后兼容：能看能改、不能管 */
$pb = (string) (ps_project_save(['name' => '公共项目', 'members' => []])['id'] ?? '');
// ps_project_save 会把创建者设为 owner，这里手工清空以模拟「权限上线前的老项目」
$index = json_read(ps_index_file());
foreach ($index as $i => $p) if ((string) $p['id'] === $pb) $index[$i]['members'] = [];
json_write(ps_index_file(), $index);
check('公共项目：非成员能看能改', ps_can($pb, 'view', 'marketing') && ps_can($pb, 'edit', 'marketing'));
check('公共项目：不能管成员', !ps_can($pb, 'manage', 'marketing'));

/* 老数据的字符串列表：视作可编辑（不会被权限上线瞬间锁死） */
$pc = (string) (ps_project_save(['name' => '老项目', 'members' => ['marketing']])['id'] ?? '');
check('旧列表成员=可编辑', ps_project_role($pc, 'marketing') === 'editor' && ps_can($pc, 'edit', 'marketing'));
check('旧列表里没提的人不可见', !ps_can($pc, 'view', 'sales'));

/* 可见项目过滤 */
$vis = array_map(static fn(array $p): string => (string) $p['id'], ps_visible_projects('marketing'));
check('可见项目含公共与旧项目、不含只给 Seven 的项目', in_array($pb, $vis, true) && in_array($pc, $vis, true) && !in_array($pa, $vis, true), json_encode($vis));
check('管理员可见全部', count(ps_visible_projects('', true)) >= 3);

/* 未知项目 / 非法动作 */
check('未知项目一律 false（管理员也是）', !ps_can('p_ghost', 'view', 'Seven', true) && !ps_can('p_ghost', 'edit', '', true));
check('非法动作 false', !ps_can($pa, 'delete', 'Seven'));

/* 回归：改成员不能把归档项目顺手解档 */
$pd = (string) (ps_project_save(['name' => '归档项目'])['id'] ?? '');
ps_project_save(['id' => $pd, 'name' => '归档项目', 'archived' => true]);
ps_member_set($pd, 'sales', 'editor');
$pdRow = null;
foreach (json_read(ps_index_file()) as $p) if ((string) $p['id'] === $pd) $pdRow = $p;
check('归档状态未被成员操作冲掉', !empty($pdRow['archived']), json_encode($pdRow));
ps_project_save(['id' => $pd, 'name' => '归档项目', 'archived' => false]);
ps_member_set($pd, 'sales', 'viewer');
$pdRow2 = null;
foreach (json_read(ps_index_file()) as $p) if ((string) $p['id'] === $pd) $pdRow2 = $p;
check('显式解档后保持未归档', empty($pdRow2['archived']));

/* 改成员不能改掉项目名/描述 */
check('成员操作不动项目名与描述', (string) (ps_project_get($pa)['name'] ?? '') === '我的项目');
check('成员操作不丢任务', is_array(ps_project_get($pa)['tasks'] ?? null));

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
