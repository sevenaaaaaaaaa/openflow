<?php
/**
 * P0-2 验收：传出神经第一环 —— 成交反哺 CDP
 *
 *   php tests/growth_signal_test.php
 *
 * 重点验三件事：
 *   1. 账本聚合是纯函数、算得对（来源/分群各自累计、未归因不丢数、负数夹紧）；
 *   2. 成交真相排名按收入降序、均单价对；
 *   3. growth_signal_conversion 端到端：写账本文件 + 正确调用 cdp_* 反哺画像，
 *      且任何一步都不冒泡（成交本身不受影响）。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-gsig-test-' . getmypid());
@mkdir(DATA_DIR . '/growth', 0777, true);

// ── 桩：cdp_* 记录调用，验证反哺确实发生（GrowthSignal 用 function_exists 探测）──
$GLOBALS['CDP'] = ['ltv' => [], 'props' => [], 'tags' => [], 'store' => []];
function cdp_find(string $email, string $memberId = '', string $uid = ''): ?array {
    return $GLOBALS['CDP']['store'][$email] ?? null;
}
function cdp_get_by_id(string $id): ?array {
    foreach ($GLOBALS['CDP']['store'] as $c) if ($c['id'] === $id) return $c;
    return null;
}
function cdp_add_ltv(string $id, float $amount): void {
    $GLOBALS['CDP']['ltv'][$id] = ($GLOBALS['CDP']['ltv'][$id] ?? 0) + $amount;
}
function cdp_set_prop(string $id, string $key, $value): void {
    $GLOBALS['CDP']['props'][$id][$key] = $value;
    // 让后续 cdp_get_by_id 能读到累计后的 props（模拟落库）
    foreach ($GLOBALS['CDP']['store'] as &$c) if ($c['id'] === $id) {
        $p = json_decode($c['props'] ?? '{}', true) ?: []; $p[$key] = $value;
        $c['props'] = json_encode($p, JSON_UNESCAPED_UNICODE);
    }
}
function cdp_add_tag(string $id, string $tag): void {
    $GLOBALS['CDP']['tags'][$id][] = $tag;
}

require_once __DIR__ . '/../lib/GrowthSignal.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

echo "\n── 1. 账本聚合（纯函数 growth_conv_apply）──\n";
$L = growth_conv_blank();
$L = growth_conv_apply($L, '自然搜索', 'VIP', 1000, '2026-08-30 10:00:00');
$L = growth_conv_apply($L, '自然搜索', 'VIP', 500,  '2026-08-30 11:00:00');
$L = growth_conv_apply($L, '广告',     '新客', 2000, '2026-08-30 12:00:00');
check('来源=自然搜索 计 2 单', $L['sources']['自然搜索']['count'] === 2);
check('来源=自然搜索 收入 1500', $L['sources']['自然搜索']['revenue'] === 1500.0);
check('分群=VIP 收入 1500', $L['segments']['VIP']['revenue'] === 1500.0);
check('总计 3 单', $L['total']['count'] === 3);
check('总收入 3500', $L['total']['revenue'] === 3500.0);
check('updated_at 已写', $L['updated_at'] === '2026-08-30 12:00:00');

echo "\n── 2. 未归因不丢数 + 负数夹紧 ──\n";
$L2 = growth_conv_apply(growth_conv_blank(), '', '', -50);
check('空来源归到占位键', isset($L2['sources']['(未归因来源)']));
check('空分群归到占位键', isset($L2['segments']['(未分群)']));
check('负数金额夹到 0', $L2['total']['revenue'] === 0.0);
check('仍计 1 单（不丢）', $L2['total']['count'] === 1);

echo "\n── 3. 成交真相排名（growth_conversion_truth）──\n";
$truth = growth_conversion_truth($L);
check('来源按收入降序：广告居首', $truth['sources'][0]['key'] === '广告', $truth['sources'][0]['key']);
check('广告均单价 2000', $truth['sources'][0]['avg'] === 2000.0);
check('自然搜索均单价 750', $truth['sources'][1]['avg'] === 750.0, (string)$truth['sources'][1]['avg']);

echo "\n── 4. 端到端 growth_signal_conversion（反哺 + 落账本）──\n";
$GLOBALS['CDP']['store']['buyer@t.com'] = ['id' => 'c1', 'email' => 'buyer@t.com', 'channel' => '公众号', 'props' => '{}'];
$r = growth_signal_conversion([
    'email' => 'buyer@t.com', 'amount' => 800, 'segment' => '老客', 'customer' => $GLOBALS['CDP']['store']['buyer@t.com'],
]);
check('返回 ok', ($r['ok'] ?? false) === true, json_encode($r));
check('LTV 累加 800 到 c1', ($GLOBALS['CDP']['ltv']['c1'] ?? 0) === 800.0);
check('source 缺省回落到渠道=公众号', $r['source'] === '公众号', $r['source'] ?? '');
check('写了 won_count=1', ($GLOBALS['CDP']['props']['c1']['won_count'] ?? 0) === 1);
check('写了 won_value_total=800', ($GLOBALS['CDP']['props']['c1']['won_value_total'] ?? 0) === 800.0);
check('打了成交来源标签', in_array('成交来源:公众号', $GLOBALS['CDP']['tags']['c1'] ?? []));
check('打了成交分群标签', in_array('成交分群:老客', $GLOBALS['CDP']['tags']['c1'] ?? []));
check('账本文件已生成', is_file(growth_conv_ledger_file()));

echo "\n── 5. 第二单累计（won_count→2, 账本读回）──\n";
$r2 = growth_signal_conversion([
    'email' => 'buyer@t.com', 'amount' => 200, 'segment' => '老客', 'customer' => cdp_get_by_id('c1'),
]);
check('won_count 累加到 2', ($GLOBALS['CDP']['props']['c1']['won_count'] ?? 0) === 2);
check('won_value_total 累加到 1000', ($GLOBALS['CDP']['props']['c1']['won_value_total'] ?? 0) === 1000.0);
$back = growth_conv_read();
check('账本读回：公众号 2 单', ($back['sources']['公众号']['count'] ?? 0) === 2, json_encode($back['sources'] ?? []));
check('账本读回：公众号收入 1000', (float)($back['sources']['公众号']['revenue'] ?? 0) === 1000.0);

echo "\n── 6. 异常不冒泡（客户为 null 也安全落账本）──\n";
$r3 = growth_signal_conversion(['email' => 'ghost@t.com', 'amount' => 300, 'source' => '直连', 'segment' => '']);
check('无客户也 ok', ($r3['ok'] ?? false) === true);
$back2 = growth_conv_read();
check('无客户仍记账本：直连 1 单', ($back2['sources']['直连']['count'] ?? 0) === 1);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
// 清理
@array_map('unlink', glob(DATA_DIR . '/growth/*'));
@rmdir(DATA_DIR . '/growth'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
