<?php
/**
 * P1-7 验收：一键采纳（提议 → 行动闭环）
 *
 *   php tests/growth_action_test.php
 *
 * 验：采纳建 pending 待办、按人+动作去重、报价动作带上下文预填链接、
 *     完成/忽略改状态、open_keys 让已采纳提议不再重复、stats 计数。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-act-test-' . getmypid());
@mkdir(DATA_DIR . '/growth', 0777, true);
require_once __DIR__ . '/../lib/GrowthAction.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

echo "\n── 1. 采纳 → 建 pending 待办 + 带上下文链接 ──\n";
$r = growth_action_adopt([
    'profile_id' => 'c1', 'profile_name' => '张先生', 'profile_email' => 'z@t.com',
    'module' => 'Sales', 'action' => '主动推成交 · 发报价单', 'reason' => '互动分80，未成交', 'cta' => '建报价单',
]);
check('采纳成功、非重复', ($r['ok'] ?? false) && !($r['dup'] ?? true));
check('状态 pending', ($r['action']['status'] ?? '') === 'pending');
check('报价动作 → quotes 预填链接', strpos($r['action']['link'] ?? '', '/xmp/quotes?brain=1') === 0, $r['action']['link'] ?? '');
check('链接带客户名(编码)', strpos($r['action']['link'] ?? '', 'prefill_customer=') !== false);
check('链接带邮箱', strpos($r['action']['link'] ?? '', rawurlencode('z@t.com')) !== false);

echo "\n── 2. 去重：同人同动作不重复建 ──\n";
$r2 = growth_action_adopt([
    'profile_id' => 'c1', 'profile_name' => '张先生', 'module' => 'Sales', 'action' => '主动推成交 · 发报价单',
]);
check('返回 dup', ($r2['dup'] ?? false) === true);
check('待办仍只有 1 条', count(growth_action_pending()) === 1, '数量=' . count(growth_action_pending()));

echo "\n── 3. open_keys：已采纳的提议应被滤掉 ──\n";
$keys = growth_action_open_keys();
check('c1+推成交 在 open_keys', isset($keys[growth_action_key('c1', '主动推成交 · 发报价单')]));
check('别的动作不在', !isset($keys[growth_action_key('c1', '老客复购召回')]));

echo "\n── 4. 非报价动作 → 对应模块链接 ──\n";
$rc = growth_action_adopt(['profile_id' => 'c2', 'module' => 'MA', 'action' => '老客复购召回']);
check('MA → /xmp/canvas', ($rc['action']['link'] ?? '') === '/xmp/canvas', $rc['action']['link'] ?? '');
$rk = growth_action_adopt(['profile_id' => 'c3', 'module' => 'Content', 'action' => '内容培育 · 补全画像']);
check('Content → /xmp/promos', ($rk['action']['link'] ?? '') === '/xmp/promos');

echo "\n── 5. 完成 / 忽略 改状态 ──\n";
$id = $r['action']['id'];
check('完成命中', growth_action_complete($id) === true);
$done = array_values(array_filter(growth_action_all(), fn($a) => $a['id'] === $id));
check('状态=done + done_at', ($done[0]['status'] ?? '') === 'done' && !empty($done[0]['done_at']));
check('完成后不在 pending', count(array_filter(growth_action_pending(), fn($a) => $a['id'] === $id)) === 0);
check('完成后不在 open_keys（可被重新提议）', !isset(growth_action_open_keys()[growth_action_key('c1', '主动推成交 · 发报价单')]));
check('忽略未知 id 返回 false', growth_action_dismiss('nope') === false);

echo "\n── 6. stats 计数 ──\n";
$s = growth_action_stats();
check('total=3', $s['total'] === 3, json_encode($s));
check('done=1', $s['done'] === 1);
check('pending=2', $s['pending'] === 2);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR . '/growth/*')); @rmdir(DATA_DIR . '/growth'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
