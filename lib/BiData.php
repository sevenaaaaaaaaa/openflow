<?php
/**
 * BiData — 全站经营数据集（对话式 BI 的数据底座）
 *
 * 把订单/线索/内容/流量/课程/漏斗/来源 等全站数据，整理成一组**有标签、有时序/分布**的
 * 数据集。AI 只做三件事：选用哪个数据集、说人话、建议追问——**数字全部来自这里，绝不编造**。
 *
 * 只读、防御式；每个数据集 {key,label,type,unit,points:[{label,value}]}。
 */

function bi_datasets(int $days = 30): array {
    $days = max(7, min(180, $days));
    $from = date('Y-m-d', time() - $days * 86400);
    $ds = [];

    // ── 汇总 KPI ──
    $kpis = ['members' => 0, 'leads' => 0, 'content_published' => 0, 'courses_published' => 0, 'revenue' => 0.0, 'orders_paid' => 0];
    try {
        if (!function_exists('member_get_all')) require_once __DIR__ . '/MemberSystem.php';
        $kpis['members'] = count(member_get_all());
    } catch (\Throwable $e) {}
    try {
        if (!function_exists('crm_get')) require_once __DIR__ . '/CrmSystem.php';
        $kpis['leads'] = count((array)(crm_get()['leads'] ?? []));
    } catch (\Throwable $e) {}
    try {
        if (function_exists('get_articles_list')) {
            foreach (get_articles_list() as $a) if (($a['status'] ?? '') === 'published') $kpis['content_published']++;
        }
    } catch (\Throwable $e) {}
    try {
        foreach (json_read(DATA_DIR . '/courses/index.json') as $c) if (in_array(($c['status'] ?? ''), ['published', 'active'], true)) $kpis['courses_published']++;
    } catch (\Throwable $e) {}

    // ── 订单：营收/单量按天 + 来源分布 ──
    $revByDay = []; $ordByDay = []; $revBySource = [];
    try {
        if (!function_exists('shop_all_orders')) require_once __DIR__ . '/ShopSystem.php';
        foreach (shop_all_orders() as $o) {
            if (($o['status'] ?? '') !== 'paid') continue;
            $day = substr((string)($o['paid_at'] ?? $o['created_at'] ?? ''), 0, 10);
            $amt = (float)($o['amount'] ?? 0);
            if ($day !== '') { $revByDay[$day] = ($revByDay[$day] ?? 0) + $amt; $ordByDay[$day] = ($ordByDay[$day] ?? 0) + 1; }
            $src = (string)($o['utm']['utm_source'] ?? '');
            if ($src === '') $src = (string)($o['source'] ?? '') !== '' ? (string)$o['source'] : '直接访问';
            $revBySource[$src] = ($revBySource[$src] ?? 0) + $amt;
            $kpis['revenue'] += $amt; $kpis['orders_paid']++;
        }
    } catch (\Throwable $e) {}

    $ds[] = ['key' => 'kpis', 'label' => '经营总览', 'type' => 'scalar', 'unit' => '', 'points' => array_map(fn($k, $v) => ['label' => $k, 'value' => $v], array_keys($kpis), array_values($kpis))];
    $ds[] = ['key' => 'revenue_by_day', 'label' => '每日营收', 'type' => 'timeseries', 'unit' => '¥', 'points' => bi_fill_days($revByDay, $days)];
    $ds[] = ['key' => 'orders_by_day', 'label' => '每日成交单数', 'type' => 'timeseries', 'unit' => '单', 'points' => bi_fill_days($ordByDay, $days)];
    if ($revBySource) { arsort($revBySource); $ds[] = ['key' => 'revenue_by_source', 'label' => '各来源营收', 'type' => 'breakdown', 'unit' => '¥', 'points' => bi_points($revBySource)]; }

    // ── 线索：阶段 / 来源 ──
    try {
        $byStage = []; $bySource = [];
        foreach ((array)(crm_get()['leads'] ?? []) as $l) {
            $st = (string)($l['stage'] ?? 'new'); $byStage[$st] = ($byStage[$st] ?? 0) + 1;
            $src = (string)($l['source'] ?? $l['utm_source'] ?? ''); if ($src === '') $src = '未标注';
            $bySource[$src] = ($bySource[$src] ?? 0) + 1;
        }
        if ($byStage) { arsort($byStage); $ds[] = ['key' => 'leads_by_stage', 'label' => '线索阶段分布', 'type' => 'breakdown', 'unit' => '条', 'points' => bi_points($byStage)]; }
        if ($bySource) { arsort($bySource); $ds[] = ['key' => 'leads_by_source', 'label' => '线索来源分布', 'type' => 'breakdown', 'unit' => '条', 'points' => bi_points($bySource)]; }
    } catch (\Throwable $e) {}

    // ── 内容：发布节奏 ──
    try {
        $pub = [];
        if (function_exists('get_articles_list')) {
            foreach (get_articles_list() as $a) {
                if (($a['status'] ?? '') !== 'published') continue;
                $day = substr((string)($a['created_at'] ?? ''), 0, 10);
                if ($day !== '') $pub[$day] = ($pub[$day] ?? 0) + 1;
            }
        }
        $ds[] = ['key' => 'content_by_day', 'label' => '每日发布内容', 'type' => 'timeseries', 'unit' => '篇', 'points' => bi_fill_days($pub, $days)];
    } catch (\Throwable $e) {}

    // ── 流量：每日页面浏览 ──
    try {
        if (!class_exists('CdpSystem')) require_once __DIR__ . '/CdpSystem.php';
        $pv = [];
        foreach (CdpSystem::allEvents(5000) as $e) {
            if (($e['event'] ?? '') !== 'page_view') continue;
            $day = substr((string)($e['created_at'] ?? ''), 0, 10);
            if ($day !== '') $pv[$day] = ($pv[$day] ?? 0) + 1;
        }
        $ds[] = ['key' => 'traffic_by_day', 'label' => '每日页面浏览', 'type' => 'timeseries', 'unit' => '次', 'points' => bi_fill_days($pv, $days)];
    } catch (\Throwable $e) {}

    // ── 转化漏斗 ──
    try {
        require_once __DIR__ . '/FunnelDefinition.php';
        if (!class_exists('CdpSystem')) require_once __DIR__ . '/CdpSystem.php';
        $f = CdpSystem::getFunnel(funnel_steps(), $days);
        if ($f) $ds[] = ['key' => 'funnel', 'label' => '转化漏斗', 'type' => 'breakdown', 'unit' => '人', 'points' => array_map(fn($s) => ['label' => (string)($s['step'] ?? ''), 'value' => (int)($s['count'] ?? 0)], $f)];
    } catch (\Throwable $e) {}

    // 只保留近 $days 的时间序列窗口
    return $ds;
}

/** 把 {date=>value} 补齐成连续 N 天的点 */
function bi_fill_days(array $map, int $days): array {
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', time() - $i * 86400);
        $out[] = ['label' => $d, 'value' => $map[$d] ?? 0];
    }
    return $out;
}

/** {label=>value} → 点数组，保持传入顺序 */
function bi_points(array $map): array {
    $out = [];
    foreach ($map as $k => $v) $out[] = ['label' => (string)$k, 'value' => $v];
    return $out;
}

/** 数据集目录（喂给模型做意图→数据集映射，不含明细，控制 token） */
function bi_catalog(array $datasets): array {
    $out = [];
    foreach ($datasets as $d) {
        $pts = array_slice((array)($d['points'] ?? []), -6);
        $out[] = ['key' => $d['key'], 'label' => $d['label'], 'type' => $d['type'], 'unit' => $d['unit'],
                  'sample' => array_map(fn($p) => [$p['label'] ?? '', $p['value'] ?? 0], $pts)];
    }
    return $out;
}
