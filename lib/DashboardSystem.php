<?php
/**
 * 经营驾驶舱数据聚合
 */

// 核心 KPI
function dash_kpis(): array {
    $kpis = [];
    try {
        // 近30天访问（唯一访客 + PV）
        $v = Database::query("SELECT COUNT(DISTINCT uid) as uv, COUNT(*) as pv FROM events WHERE event='page_view' AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
        $kpis['uv'] = (int)($v[0]['uv'] ?? 0);
        $kpis['pv'] = (int)($v[0]['pv'] ?? 0);
        // 今日访问
        $today = Database::query("SELECT COUNT(DISTINCT uid) as uv FROM events WHERE event='page_view' AND created_at >= ?", [date('Y-m-d')]);
        $kpis['today_uv'] = (int)($today[0]['uv'] ?? 0);
    } catch (Exception $e) { $kpis += ['uv'=>0,'pv'=>0,'today_uv'=>0]; }

    // 线索
    $kpis['leads'] = count(json_read(DATA_DIR . '/submissions/index.json'));

    // 订单与收入
    $orders = json_read(DATA_DIR . '/shop/orders.json');
    $paid = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid'));
    $kpis['orders'] = count($orders);
    $kpis['paid_orders'] = count($paid);
    $kpis['revenue'] = round(array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $paid)), 2);
    // 近30天收入
    $recentPaid = array_values(array_filter($paid, fn($o) => ($o['paid_at'] ?? '') >= date('Y-m-d', strtotime('-30 days'))));
    $kpis['revenue_30d'] = round(array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $recentPaid)), 2);

    // 订阅活跃数
    $activeSub = count(array_filter(json_read(DATA_DIR . '/subscription/state.json'), fn($s) => ($s['status'] ?? '') === 'active'));
    $kpis['active_subscribers'] = $activeSub;

    // 会员总数
    $kpis['members'] = count(json_read(DATA_DIR . '/members/index.json'));

    // 分销佣金
    $kpis['commission_paid'] = round(array_sum(array_map(fn($o) => (float)($o['commission'] ?? 0), $paid)), 2);

    return $kpis;
}

// 近14天访问趋势
function dash_trend(): array {
    $days = [];
    try {
        $rows = Database::query("SELECT substr(created_at,1,10) as d, COUNT(DISTINCT uid) as uv FROM events WHERE event='page_view' GROUP BY d ORDER BY d DESC LIMIT 14");
        foreach (array_reverse($rows) as $r) $days[$r['d']] = (int)$r['uv'];
    } catch (Exception $e) {}
    // 补全缺失日期
    $result = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $result[$d] = $days[$d] ?? 0;
    }
    return $result;
}

// 渠道归因（按 utm_source 聚合已支付订单）
function dash_channel_attribution(): array {
    $orders = json_read(DATA_DIR . '/shop/orders.json');
    $paid = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid'));
    $byChannel = [];
    foreach ($paid as $o) {
        $source = ($o['utm']['utm_source'] ?? '') ?: ($o['utm']['utm_medium'] ?? '') ?: '直接访问';
        $byChannel[$source] = $byChannel[$source] ?? ['orders'=>0,'revenue'=>0];
        $byChannel[$source]['orders']++;
        $byChannel[$source]['revenue'] += (float)($o['amount'] ?? 0);
    }
    arsort($byChannel);
    foreach ($byChannel as &$c) $c['revenue'] = round($c['revenue'], 2);
    unset($c);
    return $byChannel;
}

// 收入报表（按月 + 分销佣金明细）
function dash_revenue_report(): array {
    $orders = json_read(DATA_DIR . '/shop/orders.json');
    $paid = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid'));

    // 按月
    $monthly = [];
    foreach ($paid as $o) {
        $month = substr($o['paid_at'] ?? '', 0, 7);
        if (empty($month)) continue;
        $monthly[$month] = $monthly[$month] ?? ['orders'=>0,'revenue'=>0,'commission'=>0];
        $monthly[$month]['orders']++;
        $monthly[$month]['revenue'] += (float)($o['amount'] ?? 0);
        $monthly[$month]['commission'] += (float)($o['commission'] ?? 0);
    }
    krsort($monthly);

    // 分销佣金明细
    $members = json_read(DATA_DIR . '/members/index.json');
    $memberName = [];
    foreach ($members as $m) $memberName[$m['id']] = $m['name'] ?? '';
    $commByRef = [];
    foreach ($paid as $o) {
        if (!empty($o['referrer_id']) && $o['commission'] > 0) {
            $commByRef[$o['referrer_id']] = ($commByRef[$o['referrer_id']] ?? 0) + (float)$o['commission'];
        }
    }
    $commDetail = [];
    foreach ($commByRef as $mid => $amt) $commDetail[] = ['name'=>$memberName[$mid] ?? $mid, 'commission'=>round($amt,2)];
    usort($commDetail, fn($a,$b) => $b['commission'] <=> $a['commission']);

    return ['monthly'=>$monthly, 'commission'=>$commDetail];
}

// NPS 汇总
function dash_nps(): array {
    require_once __DIR__ . '/../admin/nps-lib.php';
    $projects = json_read(DATA_DIR . '/nps/projects.json');
    $total = 0; $npsSum = 0; $count = 0;
    foreach ($projects as $p) {
        $st = nps_compute(nps_get_responses($p['id']));
        $total += $st['total'];
        if ($st['nps'] !== null) { $npsSum += $st['nps']; $count++; }
    }
    return ['total_responses'=>$total, 'avg_nps'=>$count > 0 ? round($npsSum/$count) : null];
}
