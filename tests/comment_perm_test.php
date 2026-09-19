<?php
declare(strict_types=1);
/**
 * 记录级评论权限 契约测试
 *   php tests/comment_perm_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-cperm-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ProjectSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : "") . "\n"; }
}

echo "评论权限\n";
$_SESSION = ['admin_user' => 'Seven', 'admin_role' => 'admin'];
json_write(DATA_DIR . '/users.json', [
    'Seven' => ['name' => '超级管理员'],
    'editor' => ['name' => '编辑'],
    'viewer' => ['name' => '只读'],
]);
$pid = (string) (ps_project_save(['name' => '含内部讨论的项目'])['id'] ?? '');
ps_member_set($pid, 'editor', 'editor');
ps_member_set($pid, 'viewer', 'viewer');
$tid = (string) (ps_task_save($pid, ['title' => '定价讨论'])['task']['id'] ?? '');
$task = null;
foreach (ps_tasks($pid) as $t) $task = $t;

check('三种策略', array_keys(ps_comment_policies()) === ['members', 'editors', 'off']);
check('默认策略=成员都能看', ps_project_comment_policy($pid) === 'members');
check('默认：viewer 可读', ps_can_read_comments($pid, $task, 'viewer') === true);
check('默认：viewer 不可写', ps_can_write_comments($pid, $task, 'viewer') === false);
check('默认：editor 可读可写', ps_can_read_comments($pid, $task, 'editor') === true && ps_can_write_comments($pid, $task, 'editor') === true);
check('默认：非成员不可读', ps_can_read_comments($pid, $task, 'outsider') === false);

/* 收紧到仅可编辑者 */
ps_comment_policy_set($pid, 'editors', true);
check('仅可编辑者：viewer 读不到', ps_can_read_comments($pid, $task, 'viewer') === false);
check('仅可编辑者：editor 仍可读可写', ps_can_read_comments($pid, $task, 'editor') === true && ps_can_write_comments($pid, $task, 'editor') === true);
check('仅可编辑者：管理员始终可读', ps_can_read_comments($pid, $task, 'Seven', true) === true);
check('viewer 不能改策略', ps_comment_policy_set($pid, 'off', false) === false);

/* 记录级覆盖：该任务放开给成员 */
$up = ps_task_save($pid, ['id' => $tid, 'comment_policy' => 'members']);
check('任务级覆盖已保存', (string) ($up['task']['comment_policy'] ?? '') === 'members');
$task2 = null;
foreach (ps_tasks($pid) as $t) if ((string) $t['id'] === $tid) $task2 = $t;
check('覆盖生效：viewer 又能读', ps_can_read_comments($pid, $task2, 'viewer') === true);
check('项目策略未变', ps_project_comment_policy($pid) === 'editors');
check('非法策略值被拒（保持原值）', (string) (ps_task_save($pid, ['id' => $tid, 'comment_policy' => 'public'])['task']['comment_policy'] ?? '') === 'members');

/* 记录级覆盖：这条任务关掉 */
ps_task_save($pid, ['id' => $tid, 'comment_policy' => 'off']);
$task3 = null;
foreach (ps_tasks($pid) as $t) if ((string) $t['id'] === $tid) $task3 = $t;
check('关闭后谁都不能读', ps_can_read_comments($pid, $task3, 'editor', false) === false && ps_can_read_comments($pid, $task3, 'Seven', true) === false);
check('关闭后谁都不能写', ps_can_write_comments($pid, $task3, 'editor', false) === false && ps_can_write_comments($pid, $task3, 'Seven', true) === false);
check('关闭只影响这条任务（另一条不受影响）', (static function () use ($pid, $tid): bool {
    $t2 = (string) (ps_task_save($pid, ['title' => '另一条'])['task']['id'] ?? '');
    foreach (ps_tasks($pid) as $t) if ((string) $t['id'] === $t2) return ps_can_read_comments($pid, $t, 'editor') === true;
    return false;
})());

/* 恢复跟随项目 + 全局关闭 */
ps_task_save($pid, ['id' => $tid, 'comment_policy' => 'inherit']);
$task4 = null;
foreach (ps_tasks($pid) as $t) if ((string) $t['id'] === $tid) $task4 = $t;
check('inherit 回到项目策略', ps_comment_policy($pid, $task4) === 'editors');
ps_comment_policy_set($pid, 'off', true);
check('项目关闭：editor 也读不到', ps_can_read_comments($pid, $task4, 'editor') === false && ps_can_write_comments($pid, $task4, 'editor') === false);
check('项目关闭：管理员读不到（但管理操作不受限）', ps_can_read_comments($pid, $task4, 'Seven', true) === false);

/* 回归：改名/改成员不丢策略 */
ps_project_save(['id' => $pid, 'name' => '含内部讨论的项目（改名）']);
check('改名不丢项目策略', ps_project_comment_policy($pid) === 'off');
ps_member_set($pid, 'viewer', 'editor');
check('改成员不丢策略', ps_project_comment_policy($pid) === 'off');
check('评论区数据未被策略改动清空', (static function () use ($pid, $tid): bool {
    ps_task_comment_add($pid, $tid, '内部讨论：涨价 20%', 'Seven');
    return count(ps_task_comments($pid, $tid)) === 1;
})());

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
