<?php
declare(strict_types=1);
/**
 * 任务依赖（甘特前置关系）契约测试
 *   php tests/task_deps_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-deps-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ProjectSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : "") . "\n"; }
}

echo "任务依赖\n";
$_SESSION = ['admin_user' => 'Seven', 'admin_role' => 'admin'];
$pid = (string) (ps_project_save(['name' => '排期'])['id'] ?? '');
$mk = static function (string $title, array $extra = []) use ($pid): string {
    return (string) (ps_task_save($pid, ['title' => $title] + $extra)['task']['id'] ?? '');
};
$a = $mk('设计定稿');
$b = $mk('前端开发');
$c = $mk('联调上线');

/* 设置与读取 */
$r = ps_task_save($pid, ['id' => $b, 'deps' => [$a]]);
check('设置前置成功', ($r['ok'] ?? false) === true, json_encode($r['error'] ?? null));
check('前置可读回', ($r['task']['deps'] ?? []) === [$a], json_encode($r['task']['deps'] ?? null));
$deps = ps_task_deps($pid, $b);
check('前置详情含标题与状态', ($deps[0]['title'] ?? '') === '设计定稿' && ($deps[0]['done'] ?? true) === false, json_encode($deps));
check('未完成 → 被阻塞', count(ps_task_blockers($pid, $b)) === 1);
ps_task_move($pid, $a, 'done');
check('前置完成后不再阻塞', ps_task_blockers($pid, $b) === []);

/* 校验：不存在 / 自己 / 成环 */
check('前置不存在被拒', (ps_task_save($pid, ['id' => $b, 'deps' => ['t_ghost']])['ok'] ?? true) === false);
check('前置不能是自己', (ps_task_save($pid, ['id' => $b, 'deps' => [$b]])['ok'] ?? true) === false);
$chain = ps_task_save($pid, ['id' => $c, 'deps' => [$b]]);
check('链式依赖可建', ($chain['ok'] ?? false) === true, json_encode($chain['error'] ?? null));
$cycle = ps_task_save($pid, ['id' => $a, 'deps' => [$c]]);
check('成环被拒（A←C←B←A）', ($cycle['ok'] ?? false) === false && ($cycle['error'] ?? '') === '依赖成环', json_encode($cycle['error'] ?? null));
check('自我循环检测函数', ps_deps_would_cycle($pid, $a, [$c]) === true && ps_deps_would_cycle($pid, $b, [$a]) === false);

/* 去重与非法值清理 */
$dup = ps_task_save($pid, ['id' => $b, 'deps' => [$a, $a, '', '  ']]);
check('重复与空值被清掉', ($dup['task']['deps'] ?? []) === [$a], json_encode($dup['task']['deps'] ?? null));

/* 不显式传 deps 时保留（改名不清依赖） */
ps_task_save($pid, ['id' => $b, 'title' => '前端开发（改名）']);
check('改名不丢依赖', (ps_task_get_deps($pid, $b)) === [$a]);
check('显式清空依赖生效', (ps_task_save($pid, ['id' => $b, 'deps' => []])['task']['deps'] ?? ['x']) === []);

/* 删除前置节点 → 别人身上的悬空前置被摘掉 */
ps_task_save($pid, ['id' => $b, 'deps' => [$a]]);
ps_task_delete($pid, $a);
$after = ps_task_get_deps($pid, $b);
check('删除任务后他人依赖被清理', $after === [], json_encode($after));
$bTask = null;
foreach (ps_tasks($pid) as $t) if ((string) $t['id'] === $b) $bTask = $t;
check('清理后不再阻塞', ps_task_blockers($pid, $b) === []);

/* 公共数据只暴露计数，不暴露前置标题 */
$shareTok = (string) (ps_share_create($pid, 'grid', '', 'Seven', true)['token'] ?? '');
$payload = ps_share_payload($shareTok);
$row = null;
foreach (($payload['rows'] ?? []) as $rw) if (($rw['title'] ?? '') === '前端开发（改名）') $row = $rw;
check('公开模型带 blocked 标记字段', array_key_exists('blocked', (array) $row), json_encode(array_keys((array) $row)));
check('公开模型不含前置标题字段', !array_key_exists('deps', (array) $row) && !array_key_exists('blockers', (array) $row));

/* 辅助函数：直接取 id 列表 */
function ps_task_get_deps(string $pid, string $tid): array {
    foreach (ps_tasks($pid) as $t) if ((string) $t['id'] === $tid) return ps_task_deps_raw($t);
    return [];
}

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
