<?php
declare(strict_types=1);
/**
 * CptSystem 关联三件套（relation / rollup / lookup）契约测试
 *   php tests/cpt_relation_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-cpt-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/CptSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "关联/汇总/查值\n";

check('字段类型含三件套', isset(cpt_field_types()['relation'], cpt_field_types()['rollup'], cpt_field_types()['lookup']));
check('汇总函数 5 种', count(cpt_relation_functions()) === 5);
check('计算类字段不入库', cpt_computed_types() === ['rollup', 'lookup']);

/* 目标类型：成员 */
$r = cpt_type_save(['name' => '成员', 'slug' => 'people', 'fields' => [
    ['key' => 'name', 'label' => '姓名', 'type' => 'text'],
    ['key' => 'budget', 'label' => '预算', 'type' => 'number'],
]]);
check('建目标类型', $r['ok'] === true, (string) ($r['error'] ?? ''));
$p = [];
foreach ([['张三', 100], ['李四', 200], ['王五', ''],] as [$n, $b]) {
    $e = cpt_entry_save('people', ['title' => $n, 'status' => 'published', 'fields' => ['name' => $n, 'budget' => $b]]);
    $p[$n] = (string) ($e['entry']['id'] ?? '');
}
check('目标记录 3 条', count(array_filter($p)) === 3);

/* 项目类型：关联 + 汇总 + 查值 */
$r = cpt_type_save(['name' => '项目', 'slug' => 'deals', 'fields' => [
    ['key' => 'label', 'label' => '项目名', 'type' => 'text'],
    ['key' => 'members', 'label' => '成员', 'type' => 'relation', 'target' => 'people', 'multiple' => true],
    ['key' => 'lead', 'label' => '负责人', 'type' => 'relation', 'target' => 'people', 'multiple' => false],
    ['key' => 'member_count', 'label' => '人数', 'type' => 'rollup', 'relation_field' => 'members', 'function' => 'count'],
    ['key' => 'budget_sum', 'label' => '预算合计', 'type' => 'rollup', 'relation_field' => 'members', 'function' => 'sum', 'target_field' => 'budget'],
    ['key' => 'budget_avg', 'label' => '人均', 'type' => 'rollup', 'relation_field' => 'members', 'function' => 'avg', 'target_field' => 'budget'],
    ['key' => 'lead_name', 'label' => '负责人姓名', 'type' => 'lookup', 'relation_field' => 'lead', 'target_field' => 'name'],
]]);
check('建源类型（含配置）', $r['ok'] === true, (string) ($r['error'] ?? ''));
$deal = cpt_type('deals');
$membersField = null; $fnField = null;
foreach ((array) ($deal['fields'] ?? []) as $f) {
    if ($f['key'] === 'members') $membersField = $f;
    if ($f['key'] === 'budget_sum') $fnField = $f;
}
check('relation 配置落盘', ($membersField['target'] ?? '') === 'people' && ($membersField['multiple'] ?? false) === true);
check('rollup 配置落盘（函数 + 目标字段）', ($fnField['function'] ?? '') === 'sum' && ($fnField['target_field'] ?? '') === 'budget');
check('非法汇总函数回落 count', (static function (): bool {
    $t = cpt_type_save(['name' => '临时', 'slug' => 'tmpf', 'fields' => [['key' => 'x', 'type' => 'rollup', 'relation_field' => 'members', 'function' => 'nope']]]);
    foreach ((array) ($t['type']['fields'] ?? []) as $f) if (($f['key'] ?? '') === 'x') return ($f['function'] ?? '') === 'count';
    return false;
})());

/* 保存：归一化 + 计算字段不收写入 */
$s = cpt_entry_save('deals', ['title' => '官网改版', 'status' => 'published', 'fields' => [
    'label' => '官网改版',
    'members' => [$p['张三'], $p['李四'], $p['张三'], ''],   // 重复 + 空值都要被归一化
    'lead' => $p['张三'],
    'member_count' => 999,          // 应被忽略
    'lead_name' => '伪造',          // 应被忽略
]]);
check('建源记录', $s['ok'] === true, implode('；', (array) ($s['errors'] ?? [])) ?: (string) ($s['error'] ?? ''));
$did = (string) ($s['entry']['id'] ?? '');
$stored = (array) ($s['entry']['fields'] ?? []);
check('关联去重 + 去空', cpt_normalize_relation($stored['members'] ?? []) === [$p['张三'], $p['李四']], json_encode($stored['members'] ?? null));
check('计算字段不落库', !isset($stored['member_count'], $stored['lead_name']));
check('多值关联存数组', is_array($stored['members'] ?? null));
check('单值关联存标量', is_string($stored['lead'] ?? null) && ($stored['lead'] ?? '') === $p['张三'], json_encode($stored['lead'] ?? null));

/* 解析：汇总 / 查值 / 关联补水 */
$res = cpt_entry_resolved('deals', $did);
$rv = (array) ($res['resolved'] ?? []);
check('人数 = 2（去重后）', ($rv['member_count'] ?? null) === 2, json_encode($rv['member_count'] ?? null));
check('预算合计 = 300', (float) ($rv['budget_sum'] ?? 0) === 300.0, json_encode($rv['budget_sum'] ?? null));
check('人均 = 150', abs(((float) ($rv['budget_avg'] ?? 0)) - 150.0) < 0.001, json_encode($rv['budget_avg'] ?? null));
check('查值取到负责人姓名', ($rv['lead_name'] ?? '') === '张三', json_encode($rv['lead_name'] ?? null));
check('关联补成可读记录', (($rv['members'][0]['title'] ?? '') === '张三') && (($rv['members'][0]['type'] ?? '') === 'people'));
check('列表级解析可用', count(cpt_entries_resolved('deals')) === 1 && isset(cpt_entries_resolved('deals')[0]['resolved']));

/* 非法关联被拒 */
$bad = cpt_entry_save('deals', ['title' => '坏数据', 'fields' => ['label' => 'x', 'members' => ['ghost_不存在'], 'lead' => '']]);
check('关联不存在的记录被拒', $bad['ok'] === false && str_contains(implode('；', (array) ($bad['errors'] ?? [])), '不存在'), json_encode($bad['errors'] ?? $bad));
$badTarget = cpt_entry_save('deals', ['title' => '坏目标', 'fields' => ['label' => 'x', 'members' => [$p['张三']], 'lead' => '']]);
check('合法关联通过（对照组）', $badTarget['ok'] === true, json_encode($badTarget['errors'] ?? $badTarget['error'] ?? null));

/* 目标是单值关联 → lookup 取单值而不是数组 */
check('单值关系查值是标量', is_string($rv['lead_name'] ?? null));

/* 目标记录被删 → 关联自动缩水（不报错、不留幽灵） */
cpt_entry_delete('people', $p['李四']);
$res2 = cpt_entry_resolved('deals', $did);
$rv2 = (array) ($res2['resolved'] ?? []);
check('删目标后人数 = 1', ($rv2['member_count'] ?? null) === 1, json_encode($rv2['member_count'] ?? null));
check('删目标后合计 = 100', (float) ($rv2['budget_sum'] ?? 0) === 100.0, json_encode($rv2['budget_sum'] ?? null));
check('关联列表不留幽灵', count((array) ($rv2['members'] ?? [])) === 1);

/* 防环：两张表互相汇总，也要能出结果 */
cpt_type_save(['name' => 'A', 'slug' => 'ta', 'fields' => [
    ['key' => 'bs', 'label' => 'B', 'type' => 'relation', 'target' => 'tb', 'multiple' => true],
    ['key' => 'bsum', 'label' => 'B汇总', 'type' => 'rollup', 'relation_field' => 'bs', 'function' => 'sum', 'target_field' => 'asum'],
]]);
cpt_type_save(['name' => 'B', 'slug' => 'tb', 'fields' => [
    ['key' => 'as', 'label' => 'A', 'type' => 'relation', 'target' => 'ta', 'multiple' => true],
    ['key' => 'asum', 'label' => 'A汇总', 'type' => 'rollup', 'relation_field' => 'as', 'function' => 'count'],
]]);
$a1 = (string) (cpt_entry_save('ta', ['title' => 'a1', 'fields' => []])['entry']['id'] ?? '');
$b1 = (string) (cpt_entry_save('tb', ['title' => 'b1', 'fields' => ['as' => [$a1]]])['entry']['id'] ?? '');
check('互相引用可写', $a1 !== '' && $b1 !== '');
$cyclic = cpt_entry_resolved('ta', $a1);
check('环形汇总能终止且不报错', isset($cyclic['resolved']['bsum']), json_encode(array_keys((array) ($cyclic['resolved'] ?? []))));

/* 纯函数：汇总计算 */
check('count/sum/avg/min/max', cpt_rollup_value('count', [1, 2, 3]) === 3
    && (float) cpt_rollup_value('sum', [1, 2, 3]) === 6.0
    && (float) cpt_rollup_value('avg', [1, 2, 3]) === 2.0
    && (float) cpt_rollup_value('min', [3, 1, 2]) === 1.0
    && (float) cpt_rollup_value('max', [3, 1, 2]) === 3.0);
check('非数字不参与求和', (float) cpt_rollup_value('sum', ['x', 5]) === 5.0);
check('无值 avg 返回空串', cpt_rollup_value('avg', []) === '');

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
