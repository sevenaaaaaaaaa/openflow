<?php
/**
 * T0-2 验收：轻量自定义内容类型 CPT
 *
 *   php tests/cpt_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-cpt-' . getmypid());
@mkdir(DATA_DIR . '/cpt/entries', 0777, true);
function json_read(string $f): array { if (!is_file($f)) return []; $d = json_decode((string)file_get_contents($f), true); return is_array($d) ? $d : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/CptSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 定义类型（名称/slug/字段集）──\n";
$r = cpt_type_save([
    'name' => '客户案例', 'slug' => 'case', 'icon' => '🏆', 'public' => true, 'menu' => true,
    'fields' => [
        ['key' => 'Client', 'label' => '客户', 'type' => 'text', 'required' => true],
        ['key' => 'industry', 'label' => '行业', 'type' => 'select', 'options' => '电商,教育,金融', 'required' => false],
        ['key' => 'roi', 'label' => 'ROI', 'type' => 'number'],
        ['key' => 'featured', 'label' => '精选', 'type' => 'bool'],
    ],
]);
check('创建成功', $r['ok'] === true, $r['error'] ?? '');
check('slug=case', ($r['type']['slug'] ?? '') === 'case');
check('字段 key 规范化小写', ($r['type']['fields'][0]['key'] ?? '') === 'client');
check('select 选项解析成数组', ($r['type']['fields'][1]['options'] ?? []) === ['电商','教育','金融']);
check('cpt_type 能取回', cpt_type('case') !== null);

echo "\n── 2. slug 更新即 upsert（不重复；表单会重发完整字段）──\n";
cpt_type_save([
    'name' => '客户案例', 'slug' => 'case', 'icon' => '🌟',
    'fields' => [
        ['key' => 'client', 'label' => '客户', 'type' => 'text', 'required' => true],
        ['key' => 'industry', 'label' => '行业', 'type' => 'select', 'options' => '电商,教育,金融'],
        ['key' => 'roi', 'label' => 'ROI', 'type' => 'number'],
        ['key' => 'featured', 'label' => '精选', 'type' => 'bool'],
    ],
]);
check('类型仍 1 个', count(cpt_types()) === 1, '数量=' . count(cpt_types()));
check('icon 已更新', (cpt_type('case')['icon'] ?? '') === '🌟');

echo "\n── 3. 必填校验拦截 ──\n";
$r = cpt_entry_save('case', ['title' => '无客户名的案例', 'fields' => ['industry' => '电商']]);
check('缺必填 client → 失败', ($r['ok'] ?? true) === false);
check('返回 errors', !empty($r['errors']));

echo "\n── 4. select 非法值拦截 ──\n";
$r = cpt_entry_save('case', ['title' => 'x', 'fields' => ['client' => 'A公司', 'industry' => '医疗']]);
check('非法 select → 失败', ($r['ok'] ?? true) === false, json_encode($r));

echo "\n── 5. 建条目 + 字段清洗（bool/number）──\n";
$r = cpt_entry_save('case', [
    'title' => 'A公司增长案例', 'status' => 'published',
    'fields' => ['client' => 'A公司', 'industry' => '电商', 'roi' => '3.5', 'featured' => 'on', 'ghost' => '应被丢弃'],
]);
check('创建成功', $r['ok'] === true, json_encode($r['errors'] ?? $r['error'] ?? ''));
check('roi 转成数字', ($r['entry']['fields']['roi'] ?? null) === 3.5);
check('featured 转成 bool', ($r['entry']['fields']['featured'] ?? null) === true);
check('类型外字段被丢弃', !isset($r['entry']['fields']['ghost']));
check('自动生成 slug', !empty($r['entry']['slug']));
$id = $r['entry']['id'];

echo "\n── 6. 读取 / 按 slug / 公开过滤 ──\n";
cpt_entry_save('case', ['title' => '草稿案例', 'status' => 'draft', 'fields' => ['client' => 'B公司']]);
check('全部 2 条', count(cpt_entries('case')) === 2);
check('已发布仅 1 条', count(cpt_public_entries('case')) === 1);
check('按 slug 取回', cpt_entry_by_slug('case', $r['entry']['slug']) !== null);
check('按 id 取回', cpt_entry('case', $id) !== null);

echo "\n── 7. 更新条目（id 不变）──\n";
$r2 = cpt_entry_save('case', ['id' => $id, 'title' => 'A公司增长案例(改)', 'status' => 'published', 'fields' => ['client' => 'A公司']]);
check('更新成功、id 不变', ($r2['entry']['id'] ?? '') === $id);
check('标题已更新', (cpt_entry('case', $id)['title'] ?? '') === 'A公司增长案例(改)');
check('仍 2 条(未新增)', count(cpt_entries('case')) === 2);

echo "\n── 8. 删除条目 / 删除类型(留条目备份) ──\n";
check('删条目命中', cpt_entry_delete('case', $id) === true);
check('剩 1 条', count(cpt_entries('case')) === 1);
check('删类型命中', cpt_type_delete('case') === true);
check('类型已空', count(cpt_types()) === 0);
check('条目文件仍在(备份)', is_file(cpt_entries_file('case')));

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR . '/cpt/entries/*')); @array_map('unlink', glob(DATA_DIR . '/cpt/*'));
@rmdir(DATA_DIR . '/cpt/entries'); @rmdir(DATA_DIR . '/cpt'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
