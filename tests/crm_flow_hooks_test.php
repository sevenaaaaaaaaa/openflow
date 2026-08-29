<?php
/**
 * A1 + C1 验收测试：CRM 阶段变化 → flow 发射 + 插件钩子
 *
 * 独立运行，不依赖 admin/config.php：
 *   php tests/crm_flow_hooks_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-crm-test-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

// ── 最小依赖桩 ──
function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    @mkdir(dirname($f), 0777, true);
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}

$GLOBALS['FLOW_CALLS'] = [];
function flow_crm_stage_change(string $email, string $old, string $new, array $lead = []): array {
    $GLOBALS['FLOW_CALLS'][] = compact('email', 'old', 'new');
    return [];
}

require_once __DIR__ . '/../lib/PluginSystem.php';
require_once __DIR__ . '/../lib/CrmSystem.php';

// ── 断言 ──
$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else     { $fail++; echo "  ✗ {$name}" . ($detail ? "  → {$detail}" : '') . "\n"; }
}

$HOOKS = [];
foreach (['crm_lead_created','crm_stage_changed','crm_deal_won','crm_deal_lost','crm_followup_added'] as $h) {
    PluginSystem::add_action($h, function (...$a) use ($h, &$HOOKS) { $HOOKS[] = $h; });
}

echo "\n── 1. 新建线索 ──\n";
crm_ensure_lead('a@test.com', '张三');
check('触发 crm_lead_created', in_array('crm_lead_created', $HOOKS, true));

echo "\n── 2. 阶段 new → contacted ──\n";
$HOOKS = []; $GLOBALS['FLOW_CALLS'] = [];
crm_update_lead('a@test.com', ['stage' => 'contacted']);
check('触发 crm_stage_changed', in_array('crm_stage_changed', $HOOKS, true));
check('调用 flow_crm_stage_change', count($GLOBALS['FLOW_CALLS']) === 1);
check('旧阶段传对（new）', ($GLOBALS['FLOW_CALLS'][0]['old'] ?? '') === 'new',
      '实际 ' . json_encode($GLOBALS['FLOW_CALLS'][0] ?? null, JSON_UNESCAPED_UNICODE));
check('新阶段传对（contacted）', ($GLOBALS['FLOW_CALLS'][0]['new'] ?? '') === 'contacted');

echo "\n── 3. 非阶段字段更新，不应误触发 ──\n";
$HOOKS = []; $GLOBALS['FLOW_CALLS'] = [];
crm_update_lead('a@test.com', ['name' => '张三丰', 'value' => 999]);
check('不触发 crm_stage_changed', !in_array('crm_stage_changed', $HOOKS, true));
check('不调用 flow', count($GLOBALS['FLOW_CALLS']) === 0);
check('普通字段确实写入了', (crm_get()['leads']['a@test.com']['name'] ?? '') === '张三丰');

echo "\n── 4. 同值重复写入，不应误触发 ──\n";
$HOOKS = []; $GLOBALS['FLOW_CALLS'] = [];
crm_update_lead('a@test.com', ['stage' => 'contacted']);
check('阶段未变则不触发', count($GLOBALS['FLOW_CALLS']) === 0);

echo "\n── 5. 终态 won / lost ──\n";
$HOOKS = [];
crm_update_lead('a@test.com', ['stage' => 'won']);
check('won 额外触发 crm_deal_won', in_array('crm_deal_won', $HOOKS, true));
check('won 不触发 crm_deal_lost', !in_array('crm_deal_lost', $HOOKS, true));

echo "\n── 6. 跟进记录 ──\n";
$HOOKS = [];
crm_add_followup('a@test.com', '打了电话');
check('触发 crm_followup_added', in_array('crm_followup_added', $HOOKS, true));
check('跟进内容确实写入', (crm_get()['leads']['a@test.com']['follow_ups'][0]['content'] ?? '') === '打了电话');

echo "\n── 7. 关键：插件抛异常不能打断 CRM 主流程 ──\n";
PluginSystem::add_action('crm_stage_changed', function () { throw new RuntimeException('插件炸了'); });
$broke = false;
try { crm_update_lead('a@test.com', ['stage' => 'lost']); }
catch (\Throwable $e) { $broke = true; }
check('crm_update_lead 未被异常打断', !$broke);
check('数据仍然正确落盘', (crm_get()['leads']['a@test.com']['stage'] ?? '') === 'lost');
check('异常已记录到 plugin-errors.log', is_file(DATA_DIR . '/plugin-errors.log')
      && strpos((string)file_get_contents(DATA_DIR . '/plugin-errors.log'), '插件炸了') !== false);

echo "\n── 8. 过滤器同样不能被插件打断 ──\n";
PluginSystem::add_filter('t_filter', function ($v) { throw new RuntimeException('filter 炸了'); });
PluginSystem::add_filter('t_filter', function ($v) { return $v . '-ok'; }, 20);
$out = PluginSystem::apply_filters('t_filter', 'base');
check('坏回调被跳过，好回调仍生效', $out === 'base-ok', "实际 '{$out}'");

// 清理
array_map('unlink', glob(DATA_DIR . '/*') ?: []);
@rmdir(DATA_DIR);

echo "\n" . str_repeat('─', 44) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
