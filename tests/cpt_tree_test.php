<?php
declare(strict_types=1);
/**
 * 内容类型记录层级 + 树视图 契约测试
 *   php tests/cpt_tree_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-cpttree-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/CptSystem.php';
require_once __DIR__ . '/../lib/TableView.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "内容类型层级与树\n";

/* 类型：自关联 parent（单选）+ 一个指向别处的关联（不该被当层级） */
cpt_type_save(['name' => '部门', 'slug' => 'dept', 'fields' => [
    ['key' => 'name', 'label' => '名称', 'type' => 'text'],
    ['key' => 'parent', 'label' => '上级部门', 'type' => 'relation', 'target' => 'dept', 'multiple' => false],
    ['key' => 'peers', 'label' => '协作部门', 'type' => 'relation', 'target' => 'dept', 'multiple' => true],
]]);
cpt_type_save(['name' => '成员', 'slug' => 'person', 'fields' => [['key' => 'name', 'type' => 'text']]]);
cpt_type_save(['name' => '项目', 'slug' => 'proj', 'fields' => [
    ['key' => 'owner', 'label' => '负责人', 'type' => 'relation', 'target' => 'person', 'multiple' => false],
]]);
$dept = cpt_type('dept');
$hf = cpt_hierarchy_fields($dept);
check('识别自关联单值字段为层级', $hf === ['parent'], json_encode($hf));
check('多选自关联不算层级', !in_array('peers', $hf, true));
check('指向别的类型不算层级', cpt_hierarchy_fields(cpt_type('proj')) === []);

/* 建层级：公司 └─ 技术 └─ 前端, 市场 */
$mk = static function (string $title, string $parent = ''): string {
    return (string) (cpt_entry_save('dept', ['title' => $title, 'fields' => ['name' => $title, 'parent' => $parent]])['entry']['id'] ?? '');
};
$root = $mk('公司');
$tech = $mk('技术部', $root);
$fe = $mk('前端组', $tech);
$mkt = $mk('市场部', $root);
check('层级记录已建', $root !== '' && $tech !== '' && $fe !== '' && $mkt !== '');

/* 存储：单值关联存标量 */
$byId = [];
foreach (cpt_entries('dept') as $e) $byId[(string) $e['id']] = $e;
check('父记录存标量', (string) $byId[$tech]['fields']['parent'] === $root, json_encode($byId[$tech]['fields']['parent'] ?? null));

/* 校验：不能指向自己 / 自己的下级 */
$self = cpt_entry_save('dept', ['id' => $tech, 'title' => '技术部', 'fields' => ['name' => '技术部', 'parent' => $tech]]);
check('不能指向自己', ($self['ok'] ?? false) === false && str_contains(implode('；', (array) ($self['errors'] ?? [])), '不能指向记录自己'), json_encode($self['errors'] ?? $self));
$cycle = cpt_entry_save('dept', ['id' => $root, 'title' => '公司', 'fields' => ['name' => '公司', 'parent' => $fe]]);
check('不能指向自己的下级（防环）', ($cycle['ok'] ?? false) === false && str_contains(implode('；', (array) ($cycle['errors'] ?? [])), '不能指向自己的下级'), json_encode($cycle['errors'] ?? $cycle));

/* 后代与树 */
$desc = cpt_hierarchy_descendants('dept', $root, 'parent');
sort($desc); $want = [$tech, $fe, $mkt]; sort($want);
check('后代含全部层级（不含自己）', $desc === $want, json_encode($desc));
check('叶子无后代', cpt_hierarchy_descendants('dept', $fe, 'parent') === []);
$tree = cpt_tree('dept', 'parent');
check('树根数量正确', count($tree) === 1 && (string) ($tree[0]['entry']['id'] ?? '') === $root, json_encode(array_column($tree, 'depth')));
check('根的直属子 2 条', count((array) ($tree[0]['children'] ?? [])) === 2);
check('根的后代总数 3', (int) ($tree[0]['descendants'] ?? 0) === 3);
$flat = cpt_tree_flat('dept', 'parent');
$pos = [];
foreach ($flat as $i => $r) $pos[(string) $r['entry']['id']] = $i;
check('扁平表深度正确', (int) $flat[$pos[$root]]['depth'] === 0 && (int) $flat[$pos[$tech]]['depth'] === 1 && (int) $flat[$pos[$fe]]['depth'] === 2);
check('扁平表 DFS 顺序', $pos[$root] < $pos[$tech] && $pos[$tech] < $pos[$fe]);
check('扁平表子记录数', (int) $flat[$pos[$tech]]['children'] === 1 && (int) $flat[$pos[$fe]]['children'] === 0);

/* 改层级：把前端组移到市场部 */
$mv = cpt_entry_save('dept', ['id' => $fe, 'title' => '前端组', 'fields' => ['name' => '前端组', 'parent' => $mkt]]);
check('跨父移动成功', ($mv['ok'] ?? false) === true, json_encode($mv['errors'] ?? null));
$rootDesc = cpt_hierarchy_descendants('dept', $root, 'parent');
sort($rootDesc); $wantRoot = [$tech, $mkt, $fe]; sort($wantRoot);   // 技术部已无下级，前端组挂到市场部
check('移动后后代集合正确', $rootDesc === $wantRoot, json_encode($rootDesc));
$d2 = cpt_hierarchy_descendants('dept', $mkt, 'parent');
check('新父下含被移动的记录', in_array($fe, $d2, true), json_encode($d2));

/* 删除父记录：子记录升为顶层，不被删 */
$before = count(cpt_entries('dept'));
$del = cpt_entry_delete('dept', $mkt);
check('删除父记录成功', $del === true);
check('只删了自己（子记录保留）', count(cpt_entries('dept')) === $before - 1);
$feAfter = cpt_entry('dept', $fe);
check('子记录的父指针被清空', $feAfter !== null && (string) ($feAfter['fields']['parent'] ?? 'x') === '', json_encode($feAfter['fields']['parent'] ?? null));
$flat2 = cpt_tree_flat('dept', 'parent');
$ids2 = array_map(static fn(array $r): string => (string) $r['entry']['id'], $flat2);
check('升为顶层后仍在树里', in_array($fe, $ids2, true), json_encode($ids2));

/* 坏数据：悬空父 + 原始环，不丢记录、不挂死 */
$file = cpt_entries_file('dept');
$raw = json_read($file);
$raw[] = ['id' => 'ghost1', 'title' => '悬空父', 'status' => 'draft', 'fields' => ['name' => '悬空父', 'parent' => 'nope']];
$raw[] = ['id' => 'cyc1', 'title' => '环 A', 'status' => 'draft', 'fields' => ['name' => '环 A', 'parent' => 'cyc2']];
$raw[] = ['id' => 'cyc2', 'title' => '环 B', 'status' => 'draft', 'fields' => ['name' => '环 B', 'parent' => 'cyc1']];
json_write($file, $raw);
$flatBad = cpt_tree_flat('dept', 'parent');
$idsBad = array_map(static fn(array $r): string => (string) $r['entry']['id'], $flatBad);
check('悬空父当根、不丢记录', in_array('ghost1', $idsBad, true), json_encode($idsBad));
check('原始环两个节点都在', in_array('cyc1', $idsBad, true) && in_array('cyc2', $idsBad, true));
check('坏数据下行数 = 记录总数', count($flatBad) === count(cpt_entries('dept')), json_encode([count($flatBad), count(cpt_entries('dept'))]));

/* 视图注册 */
check('视图清单含树视图', array_keys(tv_views()) === ['grid', 'board', 'calendar', 'gantt', 'tree'], json_encode(array_keys(tv_views())));

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
