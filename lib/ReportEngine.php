<?php
/**
 * ReportEngine — 自定义报表：任意「维度 × 指标 × 筛选」+ 下钻
 *
 * 把固定指标变成可组合报表：选数据源(events/orders/leads)、选指标(次数/去重人数/营收…)、
 * 选维度(日期/页面/渠道/设备/UTM/课程/阶段…)、加筛选条件，服务端聚合；每一行可下钻到原始记录。
 * 报表可保存复用。聚合在 PHP 完成（无 SQL 拼接，避免注入）。
 */

function report_dir(): string { return DATA_DIR . '/reports'; }
function report_saved_file(): string { return report_dir() . '/index.json'; }

/** 维度目录（按来源） */
function report_dimensions(string $source = ''): array {
    $all = [
        'events' => ['day' => '日期', 'event' => '事件', 'page' => '页面', 'event_category' => '事件类别', 'channel' => '渠道', 'device' => '设备', 'browser' => '浏览器', 'os' => '系统', 'utm_source' => 'UTM来源', 'utm_medium' => 'UTM媒介', 'utm_campaign' => 'UTM活动', 'country' => '国家'],
        'orders' => ['day' => '日期', 'course' => '商品/课程', 'source' => '来源', 'status' => '状态', 'payment_method' => '支付方式'],
        'leads' => ['day' => '日期', 'stage' => '阶段', 'source' => '来源', 'owner' => '负责人'],
    ];
    return $source !== '' ? ($all[$source] ?? []) : $all;
}

/** 指标目录（按来源） */
function report_metrics(string $source = ''): array {
    $all = [
        'events' => ['events' => '事件次数', 'users' => '去重访客', 'sessions' => '会话数'],
        'orders' => ['orders' => '订单数', 'revenue' => '成交额', 'buyers' => '成交人数'],
        'leads' => ['leads' => '线索数', 'pipeline_value' => '管道金额'],
    ];
    return $source !== '' ? ($all[$source] ?? []) : $all;
}

/** 取某行的维度值 */
function report_dim_value(array $row, string $source, string $dim): string {
    $props = (array)($row['properties'] ?? $row['props'] ?? []);
    switch ($dim) {
        case 'day': return substr((string)($row['paid_at'] ?? $row['created_at'] ?? $row['time'] ?? ''), 0, 10);
        case 'event': return (string)($row['event'] ?? '');
        case 'page': return (string)($row['page'] ?? $row['url'] ?? '');
        case 'event_category': return (string)($row['event_category'] ?? ($props['event_category'] ?? ''));
        case 'course': return (string)($row['course_title'] ?? $row['goods_title'] ?? '');
        case 'status': return (string)($row['status'] ?? '');
        case 'payment_method': return (string)($row['payment_method'] ?? '');
        case 'stage': return (string)($row['stage'] ?? '');
        case 'source': return (string)($row['source'] ?? '');
        case 'owner': return (string)($row['owner'] ?? '');
        default: return (string)($props[$dim] ?? ($row[$dim] ?? ''));
    }
}

/** 是否去重指标 */
function report_metric_is_distinct(string $source, string $metric): bool {
    return in_array($metric, ['users', 'sessions', 'buyers'], true);
}

/** 命中筛选条件 */
function report_row_matches(array $row, string $source, array $filters): bool {
    foreach ($filters as $f) {
        if (!is_array($f)) continue;
        $dim = (string)($f['field'] ?? ''); $op = (string)($f['op'] ?? 'eq'); $val = (string)($f['value'] ?? '');
        if ($dim === '' ) continue;
        $actual = report_dim_value($row, $source, $dim);
        if ($op === 'eq' && $actual !== $val) return false;
        if ($op === 'neq' && $actual === $val) return false;
        if ($op === 'contains' && mb_stripos($actual, $val) === false) return false;
        if ($op === 'in') { $set = array_filter(array_map('trim', explode(',', $val))); if ($set && !in_array($actual, $set, true)) return false; }
    }
    return true;
}

/** 纯聚合：rows → 按维度分组的指标值 */
function report_aggregate(array $rows, string $source, string $metric, string $dimension, array $filters = [], int $limit = 20): array {
    $groups = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        if (!report_row_matches($row, $source, $filters)) continue;
        $label = report_dim_value($row, $source, $dimension);
        if ($label === '') $label = '(未标注)';
        if (!isset($groups[$label])) $groups[$label] = ['sum' => 0.0, 'set' => []];
        if (report_metric_is_distinct($source, $metric)) {
            $key = (string)($row['member_id'] ?? $row['uid'] ?? $row['session_id'] ?? $row['id'] ?? '');
            if ($key !== '') $groups[$label]['set'][$key] = true;
        } elseif ($metric === 'revenue' || $metric === 'pipeline_value') {
            $groups[$label]['sum'] += (float)($row['amount'] ?? $row['value'] ?? 0);
        } else {
            $groups[$label]['sum'] += 1;
        }
    }
    $rowsOut = [];
    foreach ($groups as $label => $g) {
        $rowsOut[] = ['label' => $label, 'value' => report_metric_is_distinct($source, $metric) ? count($g['set']) : round($g['sum'], 2)];
    }
    usort($rowsOut, fn($a, $b) => $b['value'] <=> $a['value']);
    $total = array_sum(array_column($rowsOut, 'value'));
    return ['rows' => array_slice($rowsOut, 0, $limit), 'total' => round($total, 2), 'dimension' => $dimension, 'metric' => $metric, 'source' => $source];
}

/** 取某来源原始行 */
function report_fetch_rows(string $source, int $days = 30): array {
    $since = time() - max(1, $days) * 86400;
    if ($source === 'orders') {
        if (!function_exists('shop_all_orders')) require_once __DIR__ . '/ShopSystem.php';
        $out = [];
        try { foreach (shop_all_orders() as $o) { $t = strtotime((string)($o['paid_at'] ?? $o['created_at'] ?? '')); if ($t >= $since) $out[] = $o; } } catch (\Throwable $e) {}
        return $out;
    }
    if ($source === 'leads') {
        if (!function_exists('crm_get')) require_once __DIR__ . '/CrmSystem.php';
        $out = [];
        try { foreach ((array)(crm_get()['leads'] ?? []) as $key => $l) { $t = strtotime((string)($l['created_at'] ?? '')); if ($t === false || $t >= $since) { $l['source'] = $l['source'] ?? ''; $out[] = $l; } } } catch (\Throwable $e) {}
        return $out;
    }
    // events
    if (!class_exists('CdpSystem')) require_once __DIR__ . '/CdpSystem.php';
    $out = [];
    try { foreach (CdpSystem::allEvents(10000) as $e) { if (($e['created_at'] ?? '') >= date('Y-m-d H:i:s', $since)) $out[] = $e; } } catch (\Throwable $e) {}
    return $out;
}

/** 运行报表 */
function report_run(array $spec): array {
    $source = in_array($spec['source'] ?? '', ['events', 'orders', 'leads'], true) ? $spec['source'] : 'events';
    $metric = (string)($spec['metric'] ?? array_key_first(report_metrics($source)));
    if (!isset(report_metrics($source)[$metric])) $metric = array_key_first(report_metrics($source));
    $dimension = (string)($spec['dimension'] ?? 'day');
    if (!isset(report_dimensions($source)[$dimension])) $dimension = array_key_first(report_dimensions($source));
    $days = max(1, min(365, (int)($spec['days'] ?? 30)));
    $limit = max(5, min(100, (int)($spec['limit'] ?? 20)));
    $rows = report_fetch_rows($source, $days);
    $res = report_aggregate($rows, $source, $metric, $dimension, (array)($spec['filters'] ?? []), $limit);
    $res['spec'] = ['source' => $source, 'metric' => $metric, 'dimension' => $dimension, 'days' => $days, 'filters' => array_values((array)($spec['filters'] ?? []))];
    return $res;
}

/** 下钻：某维度值对应的原始记录（供跳转/查看） */
function report_drill(array $spec, string $value, int $limit = 50): array {
    $source = in_array($spec['source'] ?? '', ['events', 'orders', 'leads'], true) ? $spec['source'] : 'events';
    $dimension = (string)($spec['dimension'] ?? 'day');
    $days = max(1, min(365, (int)($spec['days'] ?? 30)));
    $rows = report_fetch_rows($source, $days);
    $out = [];
    foreach ($rows as $row) {
        if (report_dim_value($row, $source, $dimension) !== $value) continue;
        if ($source === 'orders') {
            $out[] = ['id' => (string)($row['id'] ?? ''), 'title' => (string)($row['course_title'] ?? $row['goods_title'] ?? ''), 'extra' => '¥' . number_format((float)($row['amount'] ?? 0), 2), 'url' => '/xmp/orders'];
        } elseif ($source === 'leads') {
            $em = (string)($row['email'] ?? ''); $out[] = ['id' => $em, 'title' => (string)($row['name'] ?? $em), 'extra' => (string)($row['stage'] ?? ''), 'url' => '/xmp/crm-lead-detail?email=' . urlencode($em)];
        } else {
            $uid = (string)($row['uid'] ?? $row['visitor_id'] ?? '');
            $out[] = ['id' => $uid, 'title' => (string)($row['event'] ?? ''), 'extra' => (string)($row['page'] ?? ''), 'url' => $uid !== '' ? '/xmp/person?id=' . urlencode($uid) : ''];
        }
        if (count($out) >= $limit) break;
    }
    return ['rows' => $out, 'count' => count($out), 'value' => $value];
}

/* ── 保存的报表 ── */
function report_saved(): array { $d = json_read(report_saved_file()); return is_array($d) ? array_values($d) : []; }
function report_find(string $id): ?array { foreach (report_saved() as $r) if (($r['id'] ?? '') === $id) return $r; return null; }
function report_save(array $r): array {
    $name = trim((string)($r['name'] ?? ''));
    if ($name === '') return ['ok' => false, 'error' => '报表名必填'];
    $id = preg_replace('/[^a-z0-9_-]/', '', (string)($r['id'] ?? ''));
    if ($id === '') $id = 'rpt_' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 4);
    $row = ['id' => $id, 'name' => mb_substr($name, 0, 60), 'spec' => (array)($r['spec'] ?? []), 'updated_at' => date('Y-m-d H:i:s')];
    $list = report_saved(); $found = false;
    foreach ($list as &$x) if (($x['id'] ?? '') === $id) { $x = $row; $found = true; break; }
    unset($x);
    if (!$found) $list[] = $row;
    json_write(report_saved_file(), $list);
    return ['ok' => true, 'report' => $row];
}
function report_delete(string $id): bool {
    $list = array_values(array_filter(report_saved(), fn($r) => ($r['id'] ?? '') !== $id));
    return json_write(report_saved_file(), $list);
}
