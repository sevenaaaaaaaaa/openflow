<?php
/**
 * 模块组合模板 契约 —— 内置业务模板 / 增删改 / 应用到页面
 *   php tests/block_template_test.php
 */

$tmp = sys_get_temp_dir() . '/of-btpl-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/BlockTemplate.php';
require_once __DIR__ . '/../lib/BuilderPages.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "模块组合模板\n";

$seeds = btpl_seeds();
check('内置四套业务模板', count($seeds) === 4);
check('模板含区块组合', count($seeds[0]['blocks']) >= 5);
check('课程模板含 hero', ($seeds[0]['blocks'][0]['_type'] ?? '') === 'hero');

// 增删改
check('空名被拒', btpl_save(['name' => '', 'blocks' => [['_type' => 'text']]])['ok'] === false);
check('空区块被拒', btpl_save(['name' => 'X', 'blocks' => []])['ok'] === false);
$r = btpl_save(['name' => '我的模板', 'category' => '测试', 'blocks' => [['_type' => 'text', 'title' => 'A'], ['_type' => 'cta', 'title' => 'B']]]);
check('创建模板', !empty($r['ok']));
$id = $r['template']['id'];
check('可读取', (btpl_get($id)['name'] ?? '') === '我的模板');
check('内置模板不可删', btpl_delete('seed_course') === false);
check('删除自定义模板', btpl_delete($id) === true && btpl_get($id) === null);

// 应用到页面
$pid = save_builder_page('', ['title' => '测试落地页', 'blocks' => []]);
check('落地页已创建', $pid !== '');
$before = count((array)(builder_page_get($pid)['blocks'] ?? []));
$a = btpl_apply($pid, 'seed_course');
check('应用模板成功', !empty($a['ok']) && $a['added'] >= 5);
$page = builder_page_get($pid);
check('页面区块数增加', count((array)$page['blocks']) === $before + $a['added']);
$keys = array_column($page['blocks'], '_key');
check('区块 key 唯一', count($keys) === count(array_unique($keys)));
check('模板区块类型保留', in_array('hero', array_column($page['blocks'], '_type'), true));

// 结构守卫
$pb = file_get_contents(__DIR__ . '/../admin/page-builder.php');
check('构建器有组合模板入口', strpos($pb, 'apply_template') !== false && strpos($pb, 'template_id') !== false);
$nav = file_get_contents(__DIR__ . '/../includes/admin-nav.php');
check('导航含组合模板', strpos($nav, 'block-templates') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
