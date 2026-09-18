<?php
declare(strict_types=1);
/**
 * ProfitLab — 利润公式推演器 v0(what-if)
 *
 * 把增长方法论软件化:基于真实基线(访客→线索→成交→复购),回答
 * 「如果某一环 +X%,月收入会变多少」——而不是只回顾过去。
 *
 * 模型(与 R.B.E 方法论一致,刻意保持可解释):
 *   月收入 = 月访客 × 线索率 × 成交率 × 客单价 × (1 + 复购加成)
 *
 * v0 是确定性计算(不是 AI);基线从 DashboardSystem/EventStore 现算;
 * 供对话式 BI / 前台模拟器 / 训练营教学复用。
 */
require_once __DIR__ . '/DashboardSystem.php';

/** 基线:真实数据现算,任何一环取不到就置 0 并在 missing 里说明 */
function profitlab_baseline(): array {
    $kpis = [];
    try { $kpis = dash_kpis(); } catch (Throwable $e) {}
    $orders = json_read(DATA_DIR . '/commerce/orders.json');
    $paid = array_values(array_filter(is_array($orders) ? $orders : [], fn($o) => ($o['status'] ?? '') === 'paid'));
    $aov = count($paid) ? array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $paid)) / count($paid) : 0;

    $uv = max(0, (int)($kpis['uv'] ?? 0));
    $leads = max(0, (int)($kpis['leads'] ?? 0));
    $revenue = (float)($kpis['revenue'] ?? 0);
    $buyers = count(array_unique(array_map(fn($o) => (string)($o['member_id'] ?? $o['email'] ?? ''), $paid)));

    // 复购加成:同一买家重复购买的比例(粗粒度,v1 再细化周期)
    $buyCounts = [];
    foreach ($paid as $o) { $k = (string)($o['member_id'] ?? $o['email'] ?? ''); $buyCounts[$k] = ($buyCounts[$k] ?? 0) + 1; }
    $repeatBuyers = count(array_filter($buyCounts, fn($n) => $n > 1));
    $repeatRate = $buyers ? $repeatBuyers / $buyers : 0;

    $leadRate  = $uv ? $leads / $uv : 0;
    $closeRate = $leads ? $buyers / $leads : 0;
    // 数据不足时的经验兜底(明确标注,不冒充实测)
    $missing = [];
    if (!$uv) $missing[] = '访客(30天)';
    if (!$leads) $missing[] = '线索';
    if (!$buyers) $missing[] = '成交';
    if (!$aov) $missing[] = '客单价';
    $fallbackUsed = false;
    if (!$leadRate) { $leadRate = 0.02; $fallbackUsed = true; }
    if (!$closeRate) { $closeRate = 0.20; $fallbackUsed = true; }
    if (!$aov) { $aov = 299.0; $fallbackUsed = true; }

    return [
        'window' => '30d',
        'uv' => $uv, 'leads' => $leads, 'buyers' => $buyers,
        'lead_rate' => round($leadRate, 4), 'close_rate' => round($closeRate, 4),
        'aov' => round($aov, 2), 'repeat_rate' => round($repeatRate, 4),
        'monthly_revenue' => round($revenue, 2),
        'model_revenue' => round($uv * $leadRate * $closeRate * $aov * (1 + $repeatRate), 2),
        'fallback_used' => $fallbackUsed,
        'missing' => $missing,
    ];
}

/**
 * what-if 推演:对任一环节施加增量,返回新收入与差值
 * $delta 形如 ['uv' => 0.2, 'lead_rate' => 0.1, 'close_rate' => -0.05, 'aov' => 0.3, 'repeat_rate' => 0]
 * 值为相对变化比例(0.2 = +20%)
 */
function profitlab_whatif(array $delta = [], ?array $baseline = null): array {
    $b = $baseline ?: profitlab_baseline();
    $factors = ['uv', 'lead_rate', 'close_rate', 'aov', 'repeat_rate'];
    $adj = [];
    foreach ($factors as $f) {
        $d = (float)($delta[$f] ?? 0);
        $adj[$f] = max(0, (float)$b[$f] * (1 + $d));
    }
    $newRevenue = $adj['uv'] * $adj['lead_rate'] * $adj['close_rate'] * $adj['aov'] * (1 + $adj['repeat_rate']);
    $old = (float)$b['model_revenue'];
    return [
        'baseline' => $b,
        'adjusted' => array_map(fn($v) => round($v, 4), $adj),
        'new_monthly_revenue' => round($newRevenue, 2),
        'delta_revenue' => round($newRevenue - $old, 2),
        'delta_pct' => $old > 0 ? round(($newRevenue - $old) / $old * 100, 1) : null,
        'inputs' => array_filter($delta, fn($v) => abs((float)$v) > 1e-9),
    ];
}

/** 灵敏度:每一环单独 +10% 对月收入的影响,排序得出「最值得投入的杠杆」 */
function profitlab_sensitivity(?array $baseline = null): array {
    $b = $baseline ?: profitlab_baseline();
    $out = [];
    foreach (['uv' => '访客量', 'lead_rate' => '线索率', 'close_rate' => '成交率', 'aov' => '客单价', 'repeat_rate' => '复购'] as $f => $label) {
        $r = profitlab_whatif([$f => 0.10], $b);
        $out[] = ['factor' => $f, 'label' => $label, 'delta_revenue' => $r['delta_revenue'], 'delta_pct' => $r['delta_pct']];
    }
    usort($out, fn($a, $c) => $c['delta_revenue'] <=> $a['delta_revenue']);
    return $out;
}
