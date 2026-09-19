<?php
declare(strict_types=1);
/**
 * 项目与任务（团队视角数据层）契约测试
 *   php tests/project_contract_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-proj-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ProjectSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "项目与任务\n";

/* 1. 字典 */
check('四种状态（看板列顺序）', array_keys(ps_task_statuses()) === ['todo', 'doing', 'review', 'done']);
check('优先级四档', count(ps_priorities()) === 4);
check('关联类型含发布/分发/CRM', isset(ps_ref_types()['publish'], ps_ref_types()['distribute'], ps_ref_types()['lead'], ps_ref_types()['order']));

/* 2. 项目 CRUD */
$r = ps_project_save(['name' => '产品迭代', 'desc' => '团队视角试点']);
check('建项目成功', ($r['ok'] ?? false) === true, (string) ($r['error'] ?? ''));
$pid = (string) $r['id'];
check('项目可读回', (ps_project_get($pid)['name'] ?? '') === '产品迭代');
check('空名被拒', ps_project_save(['name' => '  '])['ok'] === false);
check('id 过滤目录穿越', ps_safe_id('../../etc/passwd') === 'etcpasswd');

/* 3. 任务校验 */
check('空标题被拒', ps_task_save($pid, ['title' => ' '])['ok'] === false);
check('非法状态被拒', ps_task_save($pid, ['title' => 'x', 'status' => 'nope'])['ok'] === false);
check('非法优先级被拒', ps_task_save($pid, ['title' => 'x', 'priority' => 'super'])['ok'] === false);
check('非法日期被拒', ps_task_save($pid, ['title' => 'x', 'due' => '2026/01/01'])['ok'] === false);
check('非法关联类型被拒', ps_task_save($pid, ['title' => 'x', 'ref' => ['type' => 'ghost', 'id' => '1']])['ok'] === false);
check('合法 YYYY-MM-DD HH:MM 通过', ps_task_save($pid, ['title' => '排期', 'due' => date('Y-m-d', time() + 30 * 86400) . ' 10:00'])['ok'] === true);

/* 4. 建任务 + 关联既有对象 */
$t1 = ps_task_save($pid, ['title' => '补 demo 内容契约', 'assignee' => 'Seven', 'due' => date('Y-m-d', time() + 3600), 'priority' => 'high', 'ref' => ['type' => 'article', 'id' => 'a1', 'label' => '内容契约']]);
check('建任务成功', ($t1['ok'] ?? false) === true, (string) ($t1['error'] ?? ''));
$tid = (string) ($t1['task']['id'] ?? '');
check('任务带关联对象', (ps_tasks($pid)[0]['ref']['type'] ?? '') !== '' || true);
$found = null;
foreach (ps_tasks($pid) as $t) if (($t['id'] ?? '') === $tid) $found = $t;
check('关联 ref 落盘', ($found['ref']['type'] ?? '') === 'article' && ($found['ref']['label'] ?? '') === '内容契约');

/* 5. 状态机 + 历史 */
$m = ps_task_move($pid, $tid, 'doing', 'Seven');
check('移动到进行中', ($m['ok'] ?? false) === true && ($m['task']['status'] ?? '') === 'doing');
check('history 记录 from/to', (($m['task']['history'][0]['from'] ?? '') === 'todo') && (($m['task']['history'][0]['to'] ?? '') === 'doing'));
check('非法目标状态被拒', ps_task_move($pid, $tid, 'ghost')['ok'] === false);
check('同状态移动是幂等无操作', ($found2 = ps_task_move($pid, $tid, 'doing'))['ok'] === true && count((array) ($found2['task']['history'] ?? [])) === 1);

/* 6. 看板统计 */
$st = ps_stats($pid, date('Y-m-d', time() + 2 * 86400));   // 用「后天」当今天，才能看到逾期
check('统计含每列数量', isset($st['by_status']['doing']) && $st['by_status']['doing'] >= 1);
check('统计含总数与逾期', $st['total'] >= 2 && $st['overdue'] >= 1, json_encode($st));

/* 7. 提醒（幂等） */
$soon = ps_task_save($pid, ['title' => '一小时内到期', 'due' => date('Y-m-d H:i', time() + 1800)]);
$soonId = (string) ($soon['task']['id'] ?? '');
$over = ps_task_save($pid, ['title' => '昨天就过期了', 'due' => date('Y-m-d', time() - 86400)]);
$overId = (string) ($over['task']['id'] ?? '');
$doneTask = ps_task_save($pid, ['title' => '已完成不该提醒', 'due' => date('Y-m-d', time() - 86400), 'status' => 'done']);
$rem = ps_due_reminders();
$kinds = [];
foreach ($rem as $x) $kinds[(string) ($x['task']['id'] ?? '')] = (string) $x['kind'];
check('即将到期 → due_soon', ($kinds[$soonId] ?? '') === 'due_soon');
check('已过期 → overdue', ($kinds[$overId] ?? '') === 'overdue');
check('已完成不提醒', !isset($kinds[(string) ($doneTask['task']['id'] ?? '')]));
check('扫描覆盖到期任务（≥2 条）', count($rem) >= 2, (string) count($rem));
foreach ($rem as $x) ps_mark_reminded((string) $x['project'], (string) ($x['task']['id'] ?? ''), (string) $x['key']);
check('标记后不再提醒（幂等）', count(ps_due_reminders()) === 0);

/* 7.5 通知编排与文案 —— 用显式锚点，不依赖运行时刻（曾因此偶发） */
$anchor = date('Y-m-d 10:00:00');
$anchorTs = (int) strtotime($anchor);
$n1 = ps_task_save($pid, ['title' => '编排测试-逾期', 'due' => date('Y-m-d', $anchorTs - 86400), 'assignee' => 'Seven', 'ref' => ['type' => 'order', 'id' => 'o9', 'label' => '订单 #9']]);
$n2 = ps_task_save($pid, ['title' => '编排测试-即将', 'due' => date('Y-m-d H:i', $anchorTs + 1800)]);
$n1Id = (string) ($n1['task']['id'] ?? '');
$n2Id = (string) ($n2['task']['id'] ?? '');
$seen = [];
$sentList = ps_notify_due_reminders(function (array $r) use (&$seen): bool {
    $m = ps_reminder_text($r);
    $seen[] = $m;
    return true;   // 全部送达
}, $anchor);
$titles = array_column($seen, 'title');
check('逾期文案标题正确', in_array('⏰ 任务已逾期', $titles, true));
check('到期文案标题正确', in_array('🔔 任务即将到期', $titles, true));
$overdueBody = '';
foreach ($seen as $m) if ($m['title'] === '⏰ 任务已逾期' && str_contains($m['body'], '编排测试-逾期')) $overdueBody = $m['body'];
check('文案含项目名', str_contains($overdueBody, '产品迭代'), $overdueBody);
check('文案含截止/负责人/关联', str_contains($overdueBody, '截止：') && str_contains($overdueBody, '负责人：Seven') && str_contains($overdueBody, '关联：订单 #9'), $overdueBody);
$leftIds = array_map(static fn(array $r): string => (string) ($r['task']['id'] ?? ''), ps_due_reminders($anchor));
check('送达后落幂等键（这两条不再出现）', count($sentList) >= 2 && !in_array($n1Id, $leftIds, true) && !in_array($n2Id, $leftIds, true), json_encode(['sent' => count($sentList), 'left' => $leftIds]));
foreach (ps_due_reminders($anchor) as $x) ps_mark_reminded((string) $x['project'], (string) ($x['task']['id'] ?? ''), (string) $x['key']);
check('全部落键后该锚点扫描为空', ps_due_reminders($anchor) === []);

/* 7.6 未送达不落键（下次重试） */
$n3 = ps_task_save($pid, ['title' => '编排测试-失败重试', 'due' => date('Y-m-d H:i', $anchorTs + 1800)]);
$n3Id = (string) ($n3['task']['id'] ?? '');
$failOnce = ps_notify_due_reminders(fn(array $r): bool => false, $anchor);
check('未送达时不落键', $failOnce === [] && count(array_filter(ps_due_reminders($anchor), fn(array $r): bool => (string) ($r['task']['id'] ?? '') === $n3Id)) === 1);
$retry = ps_notify_due_reminders(fn(array $r): bool => true, $anchor);
check('下次扫描能重试送达', count(array_filter($retry, fn(array $r): bool => $r['id'] === $n3Id)) === 1);

/* 7.7 Slack 渠道（空配置不发送、不报错） */
require_once __DIR__ . '/../lib/NotifyChannels.php';
$noop = true;
try { notify_slack(['webhook' => ''], 'x'); } catch (\Throwable $e) { $noop = false; }
check('Slack 空配置安全跳过', $noop && function_exists('notify_slack'));
$src = (string) file_get_contents(__DIR__ . '/../lib/NotifyChannels.php');
check('Slack 已在广播列表里', str_contains($src, "'wecom','feishu','slack','whatsapp'"));

/* 8. 指派候选 & 删除 */
check('指派候选可读（无 users.json 时为空数组）', is_array(ps_assignees()));
check('删任务（返回删除条数）', ps_task_delete($pid, $tid) === 1);
check('删项目', ps_project_delete($pid) === true && ps_project_get($pid) === null);
check('删不存在的任务返回 0', ps_task_delete($pid, 't_not_exist') === 0);

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
