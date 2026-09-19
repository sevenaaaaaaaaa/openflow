<?php
declare(strict_types=1);
/**
 * 任务 ↔ 自定义内容类型记录 互相关联 契约测试
 *   php tests/task_cpt_relation_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-tcr-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/CptSystem.php';
require_once __DIR__ . '/../lib/ProjectSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "任务 ↔ 内容记录\n";

/* 内容类型与记录 */
cpt_type_save(['name' => '直播场次', 'slug' => 'events', 'fields' => [['key' => 'when', 'label' => '时间', 'type' => 'date']]]);
$ev = (string) (cpt_entry_save('events', ['title' => '9 月产品直播', 'status' => 'published', 'fields' => ['when' => '2026-09-25']])['entry']['id'] ?? '');
check('内容记录已建', $ev !== '');

/* 关联目标：内置 + 内容类型 */
$targets = ps_ref_targets();
check('目标含内置对象', isset($targets['lead'], $targets['order'], $targets['article']));
check('目标含内容类型 cpt:events', ($targets['cpt:events'] ?? '') === '直播场次', json_encode(array_slice($targets, -2)));
check('展示名可取', ps_ref_label('cpt:events') === '直播场次' && ps_ref_label('lead') === 'CRM 线索');
check('cpt 前缀解析', ps_ref_cpt_slug('cpt:events') === 'events' && ps_ref_cpt_slug('lead') === '');

/* 建项目 + 任务 */
$pid = (string) (ps_project_save(['name' => '市场排期'])['id'] ?? '');
check('项目已建', $pid !== '');
$t = ps_task_save($pid, ['title' => '写直播预告', 'ref' => ['type' => 'cpt:events', 'id' => $ev, 'label' => '（快照）9 月产品直播']]);
check('任务可关联内容记录', ($t['ok'] ?? false) === true, implode('；', (array) ($t['errors'] ?? [])) ?: (string) ($t['error'] ?? ''));
$tid = (string) ($t['task']['id'] ?? '');

/* 校验：非法类型 / 不存在的记录 */
$bad1 = ps_task_save($pid, ['title' => 'x', 'ref' => ['type' => 'cpt:ghost', 'id' => 'cpt_1']]);
check('不存在的内容类型被拒', ($bad1['ok'] ?? false) === false, json_encode($bad1['errors'] ?? $bad1));
$bad2 = ps_task_save($pid, ['title' => 'x', 'ref' => ['type' => 'cpt:events', 'id' => 'cpt_不存在']]);
$bad2Msg = implode('；', (array) ($bad2['errors'] ?? [])) ?: (string) ($bad2['error'] ?? '');
check('不存在的内容记录被拒', ($bad2['ok'] ?? false) === false && str_contains($bad2Msg, '内容记录不存在'), json_encode($bad2));
$okBuiltin = ps_task_save($pid, ['title' => '回访线索', 'ref' => ['type' => 'lead', 'id' => 'lead_1']]);
check('内置关联不受影响（回归）', ($okBuiltin['ok'] ?? false) === true, json_encode($okBuiltin['errors'] ?? null));
check('空关联仍合法', (ps_task_save($pid, ['title' => '无关联'] )['ok'] ?? false) === true);

/* 解析：实时标题（对方改名跟着变） */
$ri = ps_ref_resolve(['type' => 'cpt:events', 'id' => $ev, 'label' => '旧快照']);
check('解析取实时标题', $ri['label'] === '9 月产品直播' && $ri['missing'] === false, json_encode($ri));
check('解析带类型展示名', $ri['type_label'] === '直播场次');
cpt_entry_save('events', ['id' => $ev, 'title' => '9 月产品直播（改期）', 'status' => 'published', 'fields' => ['when' => '2026-09-26']]);
check('对方改名后解析跟着变', (ps_ref_resolve(['type' => 'cpt:events', 'id' => $ev])['label']) === '9 月产品直播（改期）');
check('内置关联解析用存储 label', (ps_ref_resolve(['type' => 'lead', 'id' => 'lead_1', 'label' => '张三'])['label']) === '张三');

/* 反向查询 */
$refs = ps_tasks_referencing('cpt:events', $ev);
check('反查到 1 条任务', count($refs) === 1 && (string) ($refs[0]['task']['id'] ?? '') === $tid, json_encode(array_column($refs, 'task')));
check('反查带项目名', (string) ($refs[0]['project_name'] ?? '') === '市场排期');
/* 跨项目也能查到 */
$pid2 = (string) (ps_project_save(['name' => '内容运营'])['id'] ?? '');
ps_task_save($pid2, ['title' => '直播预热视频', 'ref' => ['type' => 'cpt:events', 'id' => $ev]]);
$refs2 = ps_tasks_referencing('cpt:events', $ev);
check('跨项目反查 2 条', count($refs2) === 2, (string) count($refs2));
check('反查不误伤其它记录', ps_tasks_referencing('cpt:events', 'cpt_别的') === []);
check('反查不误伤内置类型', ps_tasks_referencing('lead', 'lead_1') !== [] && ps_tasks_referencing('lead', $ev) === []);

/* 提醒文案用实时标题 + 类型名 */
$soon = ps_task_save($pid, ['title' => '开播前检查', 'due' => date('Y-m-d H:i', time() + 1800), 'ref' => ['type' => 'cpt:events', 'id' => $ev]]);
$soonId = (string) ($soon['task']['id'] ?? '');
$text = '';
foreach (ps_due_reminders() as $r) {
    if ((string) ($r['task']['id'] ?? '') === $soonId) $text = ps_reminder_text($r)['body'];
}
check('提醒文案含类型名与实时标题', str_contains($text, '直播场次') && str_contains($text, '9 月产品直播（改期）'), $text);

/* 目标被删：标记 missing、回退快照、反查不崩 */
cpt_entry_delete('events', $ev);
$gone = ps_ref_resolve(['type' => 'cpt:events', 'id' => $ev, 'label' => '历史快照']);
check('删除后标记 missing', $gone['missing'] === true);
check('删除后回退到快照并标注', $gone['label'] === '历史快照（已删除）', $gone['label']);
check('删除后反查不崩（3 条引用仍在）', count(ps_tasks_referencing('cpt:events', $ev)) === 3, (string) count(ps_tasks_referencing('cpt:events', $ev)));
/* 任务仍可保存/编辑（不因目标消失而卡住） */
$still = ps_task_save($pid, ['id' => $tid, 'title' => '写直播预告（改）', 'ref' => ['type' => 'cpt:events', 'id' => $ev, 'label' => '历史快照']]);
check('目标删除后任务仍可编辑（悬空关联不锁死）', ($still['ok'] ?? false) === true, json_encode($still['error'] ?? null));
/* 但「改成另一条同样不存在的关联」要被拒（不能借编辑之便写入新的悬空引用） */
$swap = ps_task_save($pid, ['id' => $tid, 'title' => '写直播预告（改）', 'ref' => ['type' => 'cpt:events', 'id' => 'cpt_另一条不存在的']]);
check('改成新的悬空关联仍被拒', ($swap['ok'] ?? true) === false, json_encode($swap['error'] ?? null));

/* 内容类型被删 → 目标不再是合法类型 */
cpt_type_delete('events');
check('类型删除后目标消失', !isset(ps_ref_targets()['cpt:events']));
/* 但任务上的历史关联不因此报错（只校验类型合法性，缺失类型会拒新写入） */
$after = ps_task_save($pid, ['title' => '新任务', 'ref' => ['type' => 'cpt:events', 'id' => 'x']]);
check('类型删除后新写入该类型被拒', ($after['ok'] ?? false) === false);

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
