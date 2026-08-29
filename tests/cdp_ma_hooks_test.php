<?php
/**
 * C1 第三刀验收：CDP 事件过滤器 + 钩子旁路契约
 *
 *   php tests/cdp_ma_hooks_test.php
 *
 * cdp_event_received 是本批唯一的 filter（可改写/丢弃事件），
 * 行为与 do_action 不同，必须单独验证。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-cdp-test-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

require_once __DIR__ . '/../lib/PluginSystem.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else     { $fail++; echo "  ✗ {$name}" . ($detail ? "  → {$detail}" : '') . "\n"; }
}

/**
 * 复刻 CdpSystem::track() 中的过滤器契约段落，
 * 隔离验证「改写 / 丢弃 / 异常」三种插件行为。
 */
function apply_event_filter(array $entry) {
    $filtered = PluginSystem::apply_filters('cdp_event_received', $entry);
    if ($filtered === null || $filtered === false) return null;   // 丢弃
    if (is_array($filtered)) $entry = $filtered;
    return $entry;
}

$base = ['id' => 'evt_1', 'event' => 'page_view', 'properties' => ['a' => 1]];

echo "\n── 1. 无插件时原样通过 ──\n";
check('返回原事件', apply_event_filter($base) === $base);

echo "\n── 2. 插件改写事件 ──\n";
PluginSystem::add_filter('cdp_event_received', function ($e) {
    $e['properties']['injected'] = 'yes';
    return $e;
});
$r = apply_event_filter($base);
check('改写生效', ($r['properties']['injected'] ?? '') === 'yes');
check('原字段保留', ($r['event'] ?? '') === 'page_view');

echo "\n── 3. 插件抛异常：跳过该回调，保留上一轮的值 ──\n";
PluginSystem::add_filter('cdp_event_received', function ($e) { throw new RuntimeException('炸'); }, 15);
PluginSystem::add_filter('cdp_event_received', function ($e) { $e['properties']['after_error'] = 'ok'; return $e; }, 20);
$r = apply_event_filter($base);
check('未抛到调用方', is_array($r));
check('坏回调之后的回调仍执行', ($r['properties']['after_error'] ?? '') === 'ok');
check('坏回调之前的改写仍在', ($r['properties']['injected'] ?? '') === 'yes');
check('异常已落 plugin-errors.log',
      is_file(DATA_DIR . '/plugin-errors.log')
      && strpos((string)file_get_contents(DATA_DIR . '/plugin-errors.log'), 'cdp_event_received') !== false);

echo "\n── 4. 插件丢弃事件（返回 null）──\n";
PluginSystem::add_filter('cdp_event_received', fn($e) => null, 30);
check('事件被丢弃', apply_event_filter($base) === null);

echo "\n── 5. do_action 的多回调顺序与隔离 ──\n";
$order = [];
PluginSystem::add_action('t_order', function () use (&$order) { $order[] = 'p20'; }, 20);
PluginSystem::add_action('t_order', function () use (&$order) { throw new RuntimeException('中间炸'); }, 15);
PluginSystem::add_action('t_order', function () use (&$order) { $order[] = 'p10'; }, 10);
PluginSystem::do_action('t_order');
check('按 priority 升序执行', $order === ['p10', 'p20'], '实际 ' . json_encode($order));
check('中间抛错不影响后续回调', in_array('p20', $order, true));

echo "\n── 6. 未注册的钩子不报错 ──\n";
$ok = true;
try { PluginSystem::do_action('never_registered', 1, 2, 3); } catch (\Throwable $e) { $ok = false; }
check('do_action 空钩子安全', $ok);
check('apply_filters 空钩子返回原值', PluginSystem::apply_filters('never_registered', 'orig') === 'orig');

foreach (glob(DATA_DIR . '/*') ?: [] as $f) if (is_file($f)) @unlink($f);
@rmdir(DATA_DIR);

echo "\n" . str_repeat('─', 44) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
