<?php
declare(strict_types=1);
/**
 * 父子任务 + 树视图 契约测试
 *   php tests/task_tree_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-tree-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ProjectSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "父子任务与树\n";
$pid = (string) (ps_project_save(['name' => '迭代'])['id'] ?? '');
$mk = function (string $title, string $parent = '') use ($pid): string {
    return (string) (ps_task_save($pid, ['title' => $title, 'parent' => $parent])['task']['id'] ?? '');
};

/* 结构：A └─ B └─ D, C */
$a = $mk('A 顶层');
$b = $mk('B 子', $a);
$c = $mk('C 子', $a);
$d = $mk('D 孙', $b);
check('层级已建立', $a !== '' && $b !== '' && $c !== '' && $d !== '');

/* 存储：parent 落库 */
$byId = [];
foreach (ps_tasks($pid) as $t) $byId[(string) $t['id']] = $t;
check('parent 落库', (string) $byId[$b]['parent'] === $a && (string) $byId[$d]['parent'] === $b);
check('顶层 parent 为空', (string) $byId[$a]['parent'] === '');

/* 校验：父任务必须存在 / 不能自指 / 不能挂到自己的子孙下 */
$badParent = ps_task_save($pid, ['title' => 'x', 'parent' => 't_不存在']);
check('父任务不存在被拒', ($badParent['ok'] ?? false) === false && str_contains((string) ($badParent['error'] ?? ''), '父任务不存在'), json_encode($badParent['error'] ?? null));
$selfParent = ps_task_save($pid, ['id' => $a, 'title' => 'A 顶层', 'parent' => $a]);
check('父任务不能是自己', ($selfParent['ok'] ?? false) === false && str_contains((string) ($selfParent['error'] ?? ''), '不能是自己'), json_encode($selfParent['error'] ?? null));
$cycle = ps_task_save($pid, ['id' => $a, 'title' => 'A 顶层', 'parent' => $d]);
check('父任务不能是它的子孙（防环）', ($cycle['ok'] ?? false) === false && str_contains((string) ($cycle['error'] ?? ''), '不能是它自己的子任务'), json_encode($cycle['error'] ?? null));

/* 深度上限 */
$chain = [$mk('链 1')];
for ($i = 2; $i <= 9; $i++) $chain[] = $mk('链 ' . $i, $chain[$i - 2]);
check('9 层链可建', count(array_filter($chain)) === 9);
$tooDeep = ps_task_save($pid, ['title' => '链 10', 'parent' => $chain[8]]);
check('超过 8 层被拒', ($tooDeep['ok'] ?? false) === false && str_contains((string) ($tooDeep['error'] ?? ''), '层级太深'), json_encode($tooDeep['error'] ?? null));

/* 子树 / 祖先 */
check('子树含自己与后代', ps_task_subtree_ids($pid, $a) === [$a, $c, $d] || (function () use ($pid, $a, $b, $c, $d): bool {
    $ids = ps_task_subtree_ids($pid, $a);
    sort($ids); $want = [$a, $b, $c, $d]; sort($want);
    return $ids === $want;
})(), json_encode(ps_task_subtree_ids($pid, $a)));
check('叶子子树只有自己', ps_task_subtree_ids($pid, $d) === [$d]);
check('祖先链由近及远', ps_task_ancestors($pid, $d) === [$b, $a], json_encode(ps_task_ancestors($pid, $d)));

/* 树结构 + 扁平行 */
$tree = ps_task_tree($pid);
check('树根数量 = 顶层任务数', count($tree) === count(array_filter(ps_tasks($pid), static fn(array $t): bool => (string) ($t['parent'] ?? '') === '')), (string) count($tree));
$rootA = null;
foreach ($tree as $n) if ((string) ($n['task']['id'] ?? '') === $a) $rootA = $n;
check('A 是第一层根、深度 0', ($rootA['depth'] ?? -1) === 0);
check('A 有 2 个直接子', count((array) ($rootA['children'] ?? [])) === 2);
$flat = ps_task_tree_flat($pid);
$pos = [];
foreach ($flat as $i => $r) $pos[(string) $r['task']['id']] = $i;
check('扁平表：深度正确', (int) $flat[$pos[$a]]['depth'] === 0 && (int) $flat[$pos[$b]]['depth'] === 1 && (int) $flat[$pos[$d]]['depth'] === 2);
check('扁平表：DFS 顺序（父在子之前）', $pos[$a] < $pos[$b] && $pos[$b] < $pos[$d]);
check('扁平表：children 计数', (int) $flat[$pos[$b]]['children'] === 1 && (int) $flat[$pos[$d]]['children'] === 0);
check('扁平表：subtree_ids 带去重', count((array) $flat[$pos[$a]]['subtree_ids']) === 4);

/* 汇总与进度 */
$roll = ps_task_rollup($pid, $a);
check('A 汇总：后代 3 条（不含自己）', (int) $roll['total'] === 3, json_encode($roll));
check('初始 0 完成 → 0%', (int) $roll['done'] === 0 && (int) $roll['pct'] === 0);
check('叶子汇总为空', (int) ps_task_rollup($pid, $d)['total'] === 0);
$leafProg = ps_task_progress($pid, $byId[$d], ps_task_rollup($pid, $d));
check('叶子进度看自己', $leafProg['mode'] === 'self' && (int) $leafProg['total'] === 1);
$parentProg = ps_task_progress($pid, $byId[$a], $roll);
check('父任务进度看后代', $parentProg['mode'] === 'rollup' && (int) $parentProg['total'] === 3);
ps_task_move($pid, $c, 'done');
ps_task_move($pid, $d, 'done');
$roll2 = ps_task_rollup($pid, $a);
check('完成后进度 2/3 = 67%', (int) $roll2['done'] === 2 && (int) $roll2['pct'] === 67, json_encode($roll2));
$stat = ps_stats($pid);
check('统计含顶层数与节点数', (int) $stat['roots'] >= 2 && (int) $stat['nodes'] === count(ps_tasks($pid)), json_encode(['roots' => $stat['roots'], 'nodes' => $stat['nodes']]));

/* 改层级：移到顶层 */
$mv = ps_task_save($pid, ['id' => $c, 'title' => 'C 子', 'parent' => '']);
check('移到顶层成功', ($mv['ok'] ?? false) === true, json_encode($mv['error'] ?? null));
check('移出后不再是 A 的子', !in_array($c, ps_task_subtree_ids($pid, $a), true));
$mv2 = ps_task_save($pid, ['id' => $d, 'title' => 'D 孙', 'parent' => $c]);
check('跨父移动', ($mv2['ok'] ?? false) === true && (string) (($mv2['task']['parent']) ?? '') === $c);
check('移动后祖先链更新', ps_task_ancestors($pid, $d) === [$c], json_encode(ps_task_ancestors($pid, $d)));

/* 级联删除 */
$delCount = ps_task_delete($pid, $c);
check('删除返回整棵子树条数', $delCount === 2, json_encode(['del' => $delCount]));
check('子树真的没了', ps_task_subtree_ids($pid, $a) === [$a, $b] || (!in_array($c, ps_task_subtree_ids($pid, $a), true) && !in_array($d, ps_task_subtree_ids($pid, $a), true)));
check('父任务仍在（只删了子树）', in_array($a, ps_task_subtree_ids($pid, $a), true));
check('删不存在的返回 0', ps_task_delete($pid, 't_ghost') === 0);

/* 坏数据稳健性：悬空父 / 原始数据里的环，都不能把树挂死或丢节点 */
$file = ps_project_file($pid);
$raw = json_read($file);
$tasks = (array) ($raw['tasks'] ?? []);
$tasks[] = ['id' => 't_ghostparent', 'title' => '悬空父', 'parent' => 't_不存在', 'status' => 'todo', 'priority' => 'normal', 'fields' => []];
$tasks[] = ['id' => 't_cyc1', 'title' => '环 1', 'parent' => 't_cyc2', 'status' => 'todo', 'priority' => 'normal'];
foreach ($tasks as $i => $t) if ((string) ($t['id'] ?? '') === 't_cyc1') $tasks[$i]['parent'] = 't_cyc2';
$tasks[] = ['id' => 't_cyc2', 'title' => '环 2', 'parent' => 't_cyc1', 'status' => 'todo', 'priority' => 'normal'];
json_write($file, ['tasks' => $tasks]);
$flatBad = ps_task_tree_flat($pid);
$idsBad = array_map(static fn(array $r): string => (string) $r['task']['id'], $flatBad);
check('悬空父当根、不丢节点', in_array('t_ghostparent', $idsBad, true), json_encode($idsBad));
check('原始环不挂死且两个节点都在', in_array('t_cyc1', $idsBad, true) && in_array('t_cyc2', $idsBad, true));
check('坏数据下列表长度 = 任务总数', count($flatBad) === count(ps_tasks($pid)), json_encode([count($flatBad), count(ps_tasks($pid))]));

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
