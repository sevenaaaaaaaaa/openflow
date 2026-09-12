<?php
/**
 * 插件生态 契约 —— 权限运行时强制 / 扩展面 / 安装依赖 / 脚手架 / AI 生成护栏
 *   php tests/plugin_ecosystem_test.php
 */

require_once __DIR__ . '/../lib/PluginSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }
function code_of(string $f): string { return is_file($f) ? (string)file_get_contents($f) : ''; }

echo "插件生态\n";

// 权限清单归一化
$base = PluginSystem::normalize_permissions(null);
check('未声明权限 → 基础集', $base === ['hooks', 'config', 'log']);
$p = PluginSystem::normalize_permissions(['http', 'block', 'cli', 'mcp', 'bogus', 'DATA.WRITE']);
check('新增扩展面权限被接受', in_array('block', $p, true) && in_array('cli', $p, true) && in_array('mcp', $p, true));
check('未知名被过滤', !in_array('bogus', $p, true));

// 扩展面注册 fail-closed：未授权插件注册不进去
PluginSystem::register_block('nope', ['type' => 'evil', 'name' => 'X', 'render' => fn($b) => 'x']);
check('未授权插件注册区块被拒', !isset(PluginSystem::blocks()['evil']));
PluginSystem::register_cli('nope', 'evil', 'x', fn($a) => null);
check('未授权插件注册CLI被拒', !isset(PluginSystem::cli_commands()['evil']));
PluginSystem::register_mcp_tool('nope', 'evil', 'x', [], fn($a) => null);
check('未授权插件注册MCP工具被拒', !isset(PluginSystem::mcp_tools()['evil']));

// 结构性守卫
$sdk = code_of(__DIR__ . '/../lib/PluginSDK.php');
check('SDK 读取配置查 config 权限', strpos($sdk, "can('config')") !== false);
check('SDK 写配置查 config 权限', substr_count($sdk, "can('config')") >= 2);
check('SDK 日志查 log 权限', strpos($sdk, "can('log')") !== false);

$br = code_of(__DIR__ . '/../lib/BlockRegistry.php');
check('区块表合并插件区块', strpos($br, 'PluginSystem::blocks()') !== false);
check('渲染器分派插件区块', strpos($br, "pblocks[\$t]['render']") !== false || strpos($br, "['render']") !== false);

$ps = code_of(__DIR__ . '/../lib/PluginSystem.php');
check('安装做依赖检查', strpos($ps, 'pkg_check(') !== false);

$of = code_of(__DIR__ . '/../bin/of');
check('CLI 有 plugin new 脚手架', strpos($of, "case 'plugin'") !== false && strpos($of, "plugin new") !== false);
check('CLI 分派插件命令', strpos($of, 'PluginSystem::cli_commands()') !== false);

$sg = code_of(__DIR__ . '/../lib/SkillGenerator.php');
check('AI 生成插件走安全审查', strpos($sg, 'skillguard_scan(') !== false);
check('AI 生成插件声明权限', strpos($sg, "'permissions'") !== false);
check('AI 生成插件默认不启用(草稿)', strpos($sg, "'enabled_by_default' => false") !== false);

$ap = code_of(__DIR__ . '/../admin/plugins.php');
check('后台有回滚入口', strpos($ap, 'rollback_plugin(') !== false);
check('manifest 示例不再有错误的 hooks 字段', strpos($ap, '"hooks": ["admin_sidebar"]') === false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
