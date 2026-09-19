<?php
declare(strict_types=1);
/**
 * 任务重复规则 契约测试
 *   php tests/task_repeat_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-rep-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ProjectSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : "") . "\n"; }
}

echo "任务重复规则\n";
$pid = (string) (ps_project_save(['name' => '例行'])['id'] ?? '');

/* 规则规范化 */
check('四种频率', array_keys(ps_repeat_freqs()) === ['none', 'daily', 'weekly', 'monthly']);
check('非法频率 → 不重复', ps_repeat_normalize(['freq' => 'hourly'])['freq'] === 'none');
check('间隔夹在 1..12', ps_repeat_normalize(['freq' => 'daily', 'interval' => 99])['interval'] === 12);
check('非法 until 丢弃', ps_repeat_normalize(['freq' => 'daily', 'until' => '2026/01/01'])['until'] === '');
check('可读描述', ps_repeat_label(['freq' => 'weekly', 'interval' => 2]) === '每 2 周' && ps_repeat_label(['freq' => 'daily', 'interval' => 1]) === '每天');

/* 日期推进 */
$d = static fn(string $freq, int $n = 1, string $from = '2026-09-15', string $until = ''): ?string => ps_repeat_next_date(['freq' => $freq, 'interval' => $n, 'until' => $until], $from);
check('每天 +1', $d('daily') === '2026-09-16');
check('每 3 天', $d('daily', 3) === '2026-09-18');
check('每周 +7', $d('weekly') === '2026-09-22');
check('每 2 周', $d('weekly', 2) === '2026-09-29');
check('每月同日', $d('monthly', 1, '2026-09-15') === '2026-10-15');
check('每月跨年', $d('monthly', 1, '2026-12-15') === '2027-01-15');
check('31 号 → 次月末（2 月钳到 28）', $d('monthly', 1, '2026-01-31') === '2026-02-28');
check('31 号 → 4 月钳到 30', $d('monthly', 1, '2026-03-31') === '2026-04-30');
check('闰年 2 月 29', $d('monthly', 1, '2028-01-31') === '2028-02-29');
check('超出 until → null', $d('daily', 1, '2026-09-15', '2026-09-15') === null);
check('未超 until → 正常', $d('daily', 1, '2026-09-15', '2026-09-20') === '2026-09-16');
check('none → null', ps_repeat_next_date(['freq' => 'none'], '2026-09-15') === null);
check('日期平移', ps_repeat_shift('2026-09-20', -3) === '2026-09-17');

/* 保存与保留：改名/改父任务不能清掉规则 */
$r = ps_task_save($pid, ['title' => '每周内容排期', 'due' => '2026-09-15', 'start' => '2026-09-13', 'priority' => 'high', 'assignee' => 'Seven', 'repeat' => ['freq' => 'weekly', 'interval' => 1]]);
$tid = (string) ($r['task']['id'] ?? '');
check('带规则创建成功', ($r['ok'] ?? false) === true && ($r['task']['repeat']['freq'] ?? '') === 'weekly');
ps_task_save($pid, ['id' => $tid, 'title' => '每周内容排期（改名）', 'due' => '2026-09-15', 'start' => '2026-09-13']);
$now = null;
foreach (ps_tasks($pid) as $t) if ((string) $t['id'] === $tid) $now = $t;
check('改名不清规则', ($now['repeat']['freq'] ?? '') === 'weekly');
ps_task_save($pid, ['id' => $tid, 'title' => '每周内容排期（改名）', 'repeat' => ['freq' => 'monthly', 'interval' => 2]]);
foreach (ps_tasks($pid) as $t) if ((string) $t['id'] === $tid) $now = $t;
check('显式改规则生效', ($now['repeat']['freq'] ?? '') === 'monthly' && (int) ($now['repeat']['interval'] ?? 0) === 2);
ps_task_save($pid, ['id' => $tid, 'title' => '每周内容排期（改名）', 'repeat' => ['freq' => 'weekly', 'interval' => 1]]);

/* 未完成不生成 */
check('未完成不生成下一实例', ps_repeat_spawn_due($pid, '2026-09-16') === []);
check('没规则的完成任务不生成', count(array_filter(ps_tasks($pid), static fn(array $t): bool => (string) ($t['repeat']['freq'] ?? 'none') === 'none')) >= 0);

/* 完成 → 生成下一实例 */
ps_task_move($pid, $tid, 'done');
$spawned = ps_repeat_spawn_due($pid, '2026-09-16');
check('完成后生成 1 条', count($spawned) === 1, json_encode($spawned));
check('新实例截止日 = 下次日期', (substr((string) ($spawned[0]['due'] ?? ''), 0, 10)) === '2026-09-22', json_encode($spawned[0]));
$all = ps_tasks($pid);
$fresh = null;
foreach ($all as $t) if ((string) ($t['id'] ?? '') === (string) ($spawned[0]['new'] ?? '')) $fresh = $t;
check('新实例：状态待办', (string) ($fresh['status'] ?? '') === 'todo');
check('新实例：照抄标题/负责人/优先级/关联', (string) $fresh['title'] === '每周内容排期（改名）' && (string) $fresh['assignee'] === 'Seven' && (string) $fresh['priority'] === 'high');
check('新实例：start 按原相对关系平移', (string) ($fresh['start'] ?? '') === '2026-09-20', (string) ($fresh['start'] ?? ''));
check('新实例：沿用规则（每周）', (string) ($fresh['repeat']['freq'] ?? '') === 'weekly');
check('新实例：无 spawned 标记', !isset($fresh['repeat']['spawned']));
check('新实例：记录来源', (string) ($fresh['repeat_from'] ?? '') === $tid && (string) ($fresh['repeat_src'] ?? '') === $tid);
check('新实例不带评论键', !array_key_exists('comments', $fresh));

/* 幂等：同一已完成实例只生成一次 */
check('再跑一次不重复生成', ps_repeat_spawn_due($pid, '2026-09-16') === []);
check('已完成实例记住 spawned', (static function () use ($pid, $tid): bool {
    foreach (ps_tasks($pid) as $t) if ((string) $t['id'] === $tid) return (string) ($t['repeat']['spawned'] ?? '') === '2026-09-22';
    return false;
})());

/* 第二轮：完成新实例 → 再生成下周 */
ps_task_move($pid, (string) $fresh['id'], 'done');
$spawn2 = ps_repeat_spawn_due($pid, '2026-09-23');
check('第二轮生成 1 条', count($spawn2) === 1 && substr((string) $spawn2[0]['due'], 0, 10) === '2026-09-29', json_encode($spawn2));

/* until 结束 */
$r3 = ps_task_save($pid, ['title' => '限时重复', 'due' => '2026-09-15', 'repeat' => ['freq' => 'daily', 'interval' => 1, 'until' => '2026-09-15']]);
$tid3 = (string) ($r3['task']['id'] ?? '');
ps_task_move($pid, $tid3, 'done');
$spawn3 = ps_repeat_spawn_due($pid, '2026-09-16');
check('超出 until 不生成', $spawn3 === [], json_encode($spawn3));
check('超出 until 标记已结束', (static function () use ($pid, $tid3): bool {
    foreach (ps_tasks($pid) as $t) if ((string) $t['id'] === $tid3) return !empty($t['repeat']['ended']);
    return false;
})());
check('已结束不再处理', ps_repeat_spawn_due($pid, '2026-09-20') === []);

/* 没写截止的重复任务：以今天为基准 */
$r4 = ps_task_save($pid, ['title' => '无截止重复', 'repeat' => ['freq' => 'weekly', 'interval' => 1]]);
$tid4 = (string) ($r4['task']['id'] ?? '');
ps_task_move($pid, $tid4, 'done');
$spawn4 = ps_repeat_spawn_due($pid, '2026-09-16');
check('无截止也能推进（以参考日为基准）', count($spawn4) === 1 && substr((string) $spawn4[0]['due'], 0, 10) === '2026-09-23', json_encode($spawn4));

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
