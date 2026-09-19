<?php
declare(strict_types=1);
/** TableView 多视图布局契约测试：php tests/table_view_test.php */
define('DATA_DIR', sys_get_temp_dir() . '/of-tv-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/CptSystem.php';
require_once __DIR__ . '/../lib/TableView.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : "") . "\n"; }
}
echo "多视图布局\n";
check('五种视图（含树）', array_keys(tv_views()) === ['grid', 'board', 'calendar', 'gantt', 'tree']);

cpt_type_save(['name' => '任务', 'slug' => 'tasks', 'fields' => [
    ['key' => 'state', 'label' => '状态', 'type' => 'select', 'options' => ['待办', '进行中', '完成']],
    ['key' => 'start', 'label' => '开始', 'type' => 'date'],
    ['key' => 'due', 'label' => '截止', 'type' => 'date'],
    ['key' => 'done', 'label' => '已完成', 'type' => 'bool'],
]]);
$mk = function (string $t, array $f, string $st = 'published'): string {
    return (string) (cpt_entry_save('tasks', ['title' => $t, 'status' => $st, 'fields' => $f])['entry']['id'] ?? '');
};
$mk('甲', ['state' => '待办', 'start' => '2026-09-01', 'due' => '2026-09-05']);
$mk('乙', ['state' => '进行中', 'start' => '2026-09-10', 'due' => '2026-09-20']);
$mk('丙', ['state' => '待办', 'start' => '2026-09-20', 'due' => '2026-09-02']);   // 起止写反
$mk('丁', ['state' => '', 'start' => '2026-09-15'], 'draft');                      // 只有开始、没有截止 → 日历里归「未排期」
$mk('戊', ['state' => '完成', 'due' => '2026-09-05']);                            // 与甲同日，验证一天多条并排

/* 表格 */
$rows = tv_rows('tasks');
check('表格：5 行', count($rows) === 5);
check('表格：单元格已渲染', ($rows[0]['cells']['state'] ?? '') !== '' && isset($rows[0]['cells']['done']));
check('表格：布尔展示为是/否', in_array(tv_display(['type' => 'bool'], true), ['是'], true) && tv_display(['type' => 'bool'], false) === '否');
$byTitle = [];
foreach ($rows as $r) $byTitle[$r['title']] = $r;
check('表格：日期原样', ($byTitle['甲']['cells']['start'] ?? '') === '2026-09-01');

/* 看板 */
$cols = tv_board_columns('tasks');
check('看板：按 select 选项分列（含未填）', count($cols) === 4, json_encode(array_column($cols, 'label')));
$colMap = [];
foreach ($cols as $c) $colMap[$c['label']] = count($c['rows']);
check('看板：待办 2 / 进行中 1 / 未填 1', ($colMap['待办'] ?? 0) === 2 && ($colMap['进行中'] ?? 0) === 1 && ($colMap['未填'] ?? 0) === 1, json_encode($colMap));
check('看板：完成列 1 条', ($colMap['完成'] ?? -1) === 1, json_encode($colMap));
/* 无 select 字段的类型 → 按发布状态分列 */
cpt_type_save(['name' => '便签', 'slug' => 'notes', 'fields' => [['key' => 'body', 'type' => 'text']]]);
cpt_entry_save('notes', ['title' => 'n1', 'status' => 'draft', 'fields' => ['body' => 'x']]);
$ncols = tv_board_columns('notes');
check('看板：无 select 时按状态分列', count($ncols) === 2 && $ncols[0]['label'] === '草稿' && count($ncols[0]['rows']) === 1);

/* 日历 */
$cal = tv_calendar('tasks', '2026-09');
check('日历：9 月 30 天', $cal['days'] === 30);
check('日历：9/1 是周二 → 前导 1 格', $cal['lead'] === 1, (string) $cal['lead']);
$d5 = null; foreach ($cal['cells'] as $c) if ($c['date'] === '2026-09-05') $d5 = $c;
check('日历：9/5 同日两条并排', count((array) $d5['rows']) === 2, (string) count((array) $d5['rows']));
$d20 = null; foreach ($cal['cells'] as $c) if ($c['date'] === '2026-09-20') $d20 = $c;
check('日历：9/20 落一条', count((array) $d20['rows']) === 1 && $d20['rows'][0]['title'] === '乙');
check('日历：未排期单独归拢（丁只有开始日）', count($cal['undated']) === 1 && $cal['undated'][0]['title'] === '丁', (string) count($cal['undated']));
check('日历翻页', tv_month_shift('2026-09', 1) === '2026-10' && tv_month_shift('2026-01', -1) === '2025-12');
check('日历：日期字段可指定（用 start）', count(tv_calendar('tasks', '2026-09', 'start')['cells']) === 30);

/* 甘特 */
$g = tv_gantt('tasks');
check('甘特：区间 09-01 → 09-20', $g['start'] === '2026-09-01' && $g['end'] === '2026-09-20', $g['start'] . '~' . $g['end']);
check('甘特：区间 20 天', $g['days'] === 20, (string) $g['days']);
check('甘特：5 条都要有', count($g['bars']) === 5, (string) count($g['bars']));
$bar = [];
foreach ($g['bars'] as $b) $bar[$b['title']] = $b;
check('甘特：起点 offset 0', ($bar['甲']['offset'] ?? -1) === 0);
check('甘特：甲跨 5 天（含首尾）', ($bar['甲']['span'] ?? 0) === 5, (string) ($bar['甲']['span'] ?? 0));
check('甘特：起止写反也要能画（丙 09-02→09-20）', ($bar['丙']['start'] ?? '') === '2026-09-02' && ($bar['丙']['end'] ?? '') === '2026-09-20');
check('甘特：同起止识别为里程碑', ($bar['乙']['milestone'] ?? true) === false && isset($bar['丁']['milestone']));
check('甘特：今天在区间外返回 null', tv_gantt_today(['start' => '2099-01-01', 'end' => '2099-01-31', 'days' => 30]) === null);
$gt = tv_gantt_today(['start' => date('Y-m-d', time() - 86400 * 5), 'end' => date('Y-m-d', time() + 86400 * 5), 'days' => 11]);
check('甘特：今天在区间内给出百分比', is_float($gt) && $gt > 0 && $gt < 100, json_encode($gt));
/* 无日期字段 → 空结果（页面据此提示） */
check('甘特：无日期字段返回空', tv_gantt('notes') === ['start' => '', 'end' => '', 'days' => 0, 'bars' => []]);
check('日历：无日期字段时全部进未排期', count(tv_calendar('notes', '2026-09')['undated']) === 1);

/* 关联字段在视图里的展示 */
cpt_type_save(['name' => '成员', 'slug' => 'people', 'fields' => [['key' => 'name', 'type' => 'text']]]);
$pid = (string) (cpt_entry_save('people', ['title' => '张三', 'fields' => ['name' => '张三']])['entry']['id'] ?? '');
cpt_type_save(['name' => '项目', 'slug' => 'proj', 'fields' => [
    ['key' => 'owner', 'label' => '负责人', 'type' => 'relation', 'target' => 'people', 'multiple' => false],
    ['key' => 'cnt', 'label' => '人数', 'type' => 'rollup', 'relation_field' => 'owner', 'function' => 'count'],
]]);
cpt_entry_save('proj', ['title' => 'P', 'fields' => ['owner' => $pid]]);
$pr = tv_rows('proj')[0];
check('视图：关联展示为标题', ($pr['cells']['owner'] ?? '') === '张三', (string) ($pr['cells']['owner'] ?? ''));
check('视图：汇总展示为数字', ($pr['cells']['cnt'] ?? '') === '1', (string) ($pr['cells']['cnt'] ?? ''));
check('视图：小数汇总保留 2 位', tv_display(['type' => 'rollup'], 2.5) === '2.5' && tv_display(['type' => 'rollup'], 2.0) === '2');

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
