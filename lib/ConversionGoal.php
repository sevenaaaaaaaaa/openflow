<?php
/**
 * ConversionGoal — 转化目标对象（把"目标事件 + 作用域 + 价值"变成一等对象）
 *
 * 之前"转化"散落成漏斗硬编码、A/B 手写 JS、广告回传；没有一个可命名的目标。
 * 这里定义目标：命中哪个事件、限定哪个 URL 作用域、每次转化值多少、成交类怎么算金额。
 * 落地页 / A/B / 报表都挂在目标上，口径统一。
 */

function cg_file(): string { return DATA_DIR . '/conversion/goals.json'; }
function cg_all(): array { $d = json_read(cg_file()); return is_array($d) ? array_values($d) : []; }

function cg_get(string $id): ?array {
    foreach (cg_all() as $g) if (($g['id'] ?? '') === $id) return $g;
    return null;
}

function cg_save(array $g): array {
    $name = trim((string)($g['name'] ?? ''));
    $event = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($g['event'] ?? '')));
    if ($name === '' || $event === '') return ['ok' => false, 'error' => '名称与目标事件必填'];
    $id = preg_replace('/[^a-z0-9_-]/', '', (string)($g['id'] ?? ''));
    if ($id === '') $id = 'cg_' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 4);
    $row = [
        'id' => $id, 'name' => mb_substr($name, 0, 60), 'event' => $event,
        'scope' => mb_substr(trim((string)($g['scope'] ?? '')), 0, 120),   // URL 前缀，空=全站
        'value' => (float)($g['value'] ?? 0),                              // 非成交类每次转化价值
        'revenue_mode' => !empty($g['revenue_mode']),                      // 成交类：金额取订单实付
        'status' => (($g['status'] ?? 'active') === 'active') ? 'active' : 'paused',
        'created_at' => (string)($g['created_at'] ?? date('Y-m-d H:i:s')),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $list = cg_all();
    $found = false;
    foreach ($list as &$x) if (($x['id'] ?? '') === $id) { $row['created_at'] = $x['created_at'] ?? $row['created_at']; $x = $row; $found = true; break; }
    unset($x);
    if (!$found) $list[] = $row;
    json_write(cg_file(), $list);
    return ['ok' => true, 'goal' => $row];
}

function cg_delete(string $id): bool {
    $list = array_values(array_filter(cg_all(), fn($g) => ($g['id'] ?? '') !== $id));
    return json_write(cg_file(), $list);
}

/** 统计某目标近 N 天：转化数、价值、基准（page_view）与转化率 */
function cg_stats(string $id, int $days = 30): array {
    $g = cg_get($id);
    if (!$g) return [];
    $days = max(1, min(365, $days));
    $since = date('Y-m-d H:i:s', time() - $days * 86400);
    $event = (string)$g['event'];
    $scope = (string)$g['scope'];

    $count = 0; $value = 0.0; $base = 0;
    // 优先 SQL 聚合（events 表）
    try {
        if (!class_exists('Database')) { $p = __DIR__ . '/Database.php'; if (is_file($p)) require_once $p; }
        if (class_exists('Database')) {
            $like = $scope !== '' ? $scope . '%' : null;
            $sql = "SELECT COUNT(*) c FROM events WHERE event = ? AND created_at >= ?" . ($like ? " AND page LIKE ?" : "");
            $p1 = [$event, $since]; if ($like) $p1[] = $like;
            $rows = Database::query($sql, $p1);
            $count = (int)($rows[0]['c'] ?? 0);
            $sql2 = "SELECT COUNT(*) c FROM events WHERE event = 'page_view' AND created_at >= ?" . ($like ? " AND page LIKE ?" : "");
            $p2 = [$since]; if ($like) $p2[] = $like;
            $rows2 = Database::query($sql2, $p2);
            $base = (int)($rows2[0]['c'] ?? 0);
        }
    } catch (\Throwable $e) {}
    // 回退：从 CdpSystem 事件里过滤
    if ($count === 0 && $base === 0) {
        try {
            if (!class_exists('CdpSystem')) require_once __DIR__ . '/CdpSystem.php';
            foreach (CdpSystem::allEvents(5000) as $e) {
                if (($e['created_at'] ?? '') < $since) continue;
                $page = (string)($e['page'] ?? '');
                if ($scope !== '' && strpos($page, $scope) !== 0) continue;
                if (($e['event'] ?? '') === $event) $count++;
                if (($e['event'] ?? '') === 'page_view') $base++;
            }
        } catch (\Throwable $e) {}
    }
    // 成交类：金额取订单实付（限定作用域不适用，按订单归属）
    if (!empty($g['revenue_mode'])) {
        try {
            if (!function_exists('shop_all_orders')) require_once __DIR__ . '/ShopSystem.php';
            foreach (shop_all_orders() as $o) {
                if (($o['status'] ?? '') !== 'paid') continue;
                if (strtotime((string)($o['paid_at'] ?? $o['created_at'] ?? '')) < time() - $days * 86400) continue;
                $value += (float)($o['amount'] ?? 0);
            }
        } catch (\Throwable $e) {}
    } else {
        $value = $count * (float)$g['value'];
    }
    return ['goal' => $g, 'days' => $days, 'conversions' => $count, 'value' => round($value, 2),
        'baseline' => $base, 'rate' => $base > 0 ? round($count / $base * 100, 2) : 0];
}

function cg_stats_all(int $days = 30): array {
    $out = [];
    foreach (cg_all() as $g) $out[] = cg_stats((string)$g['id'], $days);
    return $out;
}
