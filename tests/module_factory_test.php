<?php
/**
 * 模块工厂 契约 —— 分类/子分类/样式变体/目录分组
 *   php tests/module_factory_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-mf-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/BlockRegistry.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "模块工厂\n";

check('分类体系存在', (blockschema_categories()['layout'] ?? '') === '布局');

// 保存一个带分类/变体的自定义模块
$mod = [
    'key' => 'student_quote', 'name' => '学员评价', 'status' => 'active',
    'category' => 'social', 'subcategory' => '课程页',
    'fields' => [['key' => 'quote', 'type' => 'text', 'label' => '引文']],
    'style' => ['align' => 'left'],
    'variants' => [
        ['id' => 'dark', 'name' => '深色', 'style' => ['bg' => '#111827', 'align' => 'center']],
        ['id' => 'soft', 'name' => '柔和', 'style' => ['bg' => '#f5f5f5']],
    ],
];
$r = blockschema_save($mod);
check('保存模块成功', !empty($r['ok']));
$saved = blockschema_get('student_quote');
check('分类/子分类入库', ($saved['category'] ?? '') === 'social' && ($saved['subcategory'] ?? '') === '课程页');
check('变体入库', count($saved['variants'] ?? []) === 2);
check('取变体', (blockschema_variant($saved, 'dark')['style']['bg'] ?? '') === '#111827');
check('取不存在的变体 → null', blockschema_variant($saved, 'nope') === null);

// 非法分类回落 other
$bad = blockschema_save(['key' => 'x1', 'name' => 'X', 'category' => 'bogus!!', 'fields' => [['key' => 'a', 'type' => 'text']]]);
$bx = blockschema_get('x1');
check('非法分类回落 other', ($bx['category'] ?? '') === 'other');

// 分类归属：内置 + 自定义
check('内置模块分类(hero→layout)', block_type_category('hero') === 'layout');
check('自定义模块分类被识别', block_type_category('student_quote') === 'social');

// 目录分组
$pal = block_palette();
check('目录按分类分组', isset($pal['social']) && isset($pal['layout']));
$socialTypes = array_column($pal['social'], 'type');
check('自定义模块进入其分类', in_array('student_quote', $socialTypes, true));
$sq = null; foreach ($pal['social'] as $it) if ($it['type'] === 'student_quote') $sq = $it;
check('目录带变体清单', $sq && count($sq['variants']) === 2 && $sq['custom'] === true);

// 结构性：页构建器持久化变体；模块工厂有克隆/导出/导入
$pb = file_get_contents(__DIR__ . '/../admin/page-builder.php');
check('页构建器持久化 block_variant', strpos($pb, 'block_variant') !== false);
check('页构建器面板分类/搜索', strpos($pb, 'paletteCat(') !== false && strpos($pb, 'paletteFilter(') !== false);
$am = file_get_contents(__DIR__ . '/../admin/modules.php');
check('模块工厂支持克隆', strpos($am, "'clone'") !== false || strpos($am, '"clone"') !== false);
check('模块工厂支持导出', strpos($am, 'export') !== false);
check('模块工厂支持导入', strpos($am, "'import'") !== false || strpos($am, '"import"') !== false);

// 结构性：渲染器应用变体
$br = file_get_contents(__DIR__ . '/../lib/BlockRegistry.php');
check('渲染器应用变体样式', strpos($br, 'blockschema_variant(') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
