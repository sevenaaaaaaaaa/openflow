<?php
/**
 * Mainline — 「今日主线」聚合引擎（加法层，不改动现有工作台）
 *
 * 把全站散落的可行动信号编排成一份**按优先级排序的行动清单**：
 *   立即（钱/风险/时效） · 今日（目标推进） · 本周（策略判断）
 * 每个 Action Item 带「为什么现在 + 一键下一步（带上下文）」，完成后回流，
 * 供排序与后续 AI 决策使用（接 B7/点7 的自生长骨架）。
 *
 * 设计约束：
 * - 只编排、不重造：所有数据来自现有模块，provider 全部 try/catch，单源失败不影响主线
 * - 只读聚合：本文件不写业务数据，仅写 mainline/events.json 记录「已处理/已忽略」
 * - 权限感知：按 has_perm 过滤来源，非超管也能拿到属于自己的主线
 */

function mainline_events_file(): string { return DATA_DIR . '/mainline/events.json'; }

/** 已处理/已忽略的 item id（处理过的从主线消失） */
function mainline_handled_ids(): array {
    $out = [];
    foreach (json_read(mainline_events_file()) as $id => $ev) {
        if (in_array(($ev['status'] ?? ''), ['done', 'snoozed'], true)) $out[$id] = $ev;
    }
    return $out;
}

/** 记录一条主线的处理结果（回流） */
function mainline_log(string $id, string $status, array $meta = []): bool {
    $all = json_read(mainline_events_file());
    $all[$id] = array_merge([
        'id' => $id,
        'status' => $status,               // done | snoozed | opened
        'by' => $_SESSION['admin_user'] ?? 'system',
        'at' => date('Y-m-d H:i:s'),
    ], $meta);
    return json_write(mainline_events_file(), $all);
}

/** 统一 item 构造函数 */
function mainline_item(string $id, string $lane, string $source, string $title, array $o = []): array {
    return array_merge([
        'id' => $id,
        'lane' => in_array($lane, ['now', 'today', 'week'], true) ? $lane : 'today',
        'source' => $source,
        'icon' => '•',
        'title' => $title,
        'why' => '',
        'action_label' => '去处理',
        'action_url' => '',
        'severity' => 'info',              // critical | warn | info | good
        'est_min' => 3,
        'due' => '',
        'goal' => '',
        'meta' => [],
    ], $o);
}

/* ═══════════════ 各来源 Provider（全部只读、防御式） ═══════════════ */

/** 直播：即将开播 / 正在直播 */
function mainline_src_live(): array {
    $items = [];
    if (!function_exists('live_rooms')) { require_once __DIR__ . '/LiveSystem.php'; }
    if (!function_exists('live_rooms')) return $items;
    foreach (live_rooms() as $r) {
        $st = function_exists('live_status') ? live_status($r) : '';
        $id = (string)($r['id'] ?? '');
        if ($id === '') continue;
        if ($st === 'live') {
            $items[] = mainline_item('live:' . $id, 'now', '直播', '正在直播：' . ($r['title'] ?? ''), [
                'icon' => '🔴', 'severity' => 'critical', 'est_min' => 5,
                'why' => '直播已开始，观众正在进入',
                'action_label' => '进入直播间', 'action_url' => '/live?room=' . urlencode($id),
                'meta' => ['room' => $id],
            ]);
        } elseif (in_array($st, ['scheduled', 'upcoming'], true) && !empty($r['start_at'])) {
            $ts = strtotime((string)$r['start_at']);
            if ($ts && $ts > time() && $ts - time() <= 7200) {
                $mins = (int)round(($ts - time()) / 60);
                $items[] = mainline_item('live:' . $id, $mins <= 60 ? 'now' : 'today', '直播', '开播倒计时：' . ($r['title'] ?? ''), [
                    'icon' => '⏰', 'severity' => $mins <= 30 ? 'warn' : 'info', 'est_min' => 5,
                    'why' => $mins . ' 分钟后开播', 'due' => (string)$r['start_at'],
                    'action_label' => '检查并预热', 'action_url' => '/xmp/live?edit=' . urlencode($id),
                    'meta' => ['room' => $id, 'minutes' => $mins],
                ]);
            }
        }
    }
    return $items;
}

/** 商城：待发货 / 待处理退款 */
function mainline_src_orders(): array {
    $items = [];
    $orders = json_read(DATA_DIR . '/commerce/orders.json');
    if (!is_array($orders)) return $items;
    $ship = 0; $refund = 0;
    foreach ($orders as $o) {
        if (($o['fulfillment'] ?? '') === 'pending') $ship++;
        if (in_array(($o['status'] ?? ''), ['refund_pending', 'refund_requested'], true)) $refund++;
    }
    if ($ship > 0) {
        $items[] = mainline_item('order:ship', 'now', '交易', $ship . ' 笔订单待发货', [
            'icon' => '📦', 'severity' => 'warn', 'est_min' => 10,
            'why' => '客户已付款，等待交付', 'action_label' => '去发货', 'action_url' => '/xmp/commerce?tab=orders&filter=ship',
            'meta' => ['count' => $ship],
        ]);
    }
    if ($refund > 0) {
        $items[] = mainline_item('order:refund', 'now', '交易', $refund . ' 笔退款待处理', [
            'icon' => '↩️', 'severity' => 'warn', 'est_min' => 10,
            'why' => '退款超时会影响口碑', 'action_label' => '去处理退款', 'action_url' => '/xmp/refunds',
            'meta' => ['count' => $refund],
        ]);
    }
    return $items;
}

/** CRM：需要跟进的线索（SLA） */
function mainline_src_leads(): array {
    $items = [];
    if (!function_exists('crm_get')) { require_once __DIR__ . '/CrmSystem.php'; }
    if (!function_exists('crm_get')) return $items;
    $leads = crm_get()['leads'] ?? [];
    $today = date('Y-m-d');
    $overdue = 0; $newStale = 0;
    foreach ($leads as $l) {
        if (in_array(($l['stage'] ?? ''), ['won', 'lost', 'closed'], true)) continue;
        $nf = (string)($l['next_followup'] ?? '');
        if ($nf !== '' && $nf <= $today) { $overdue++; continue; }
        if ($nf === '' && ($l['stage'] ?? 'new') === 'new' && strtotime((string)($l['created_at'] ?? '2100-01-01')) < time() - 2 * 86400) $newStale++;
    }
    if ($overdue > 0) {
        $items[] = mainline_item('lead:overdue', 'now', '线索', $overdue . ' 条线索跟进已超期', [
            'icon' => '🎯', 'severity' => 'critical', 'est_min' => 15,
            'why' => '跟进超期会显著降低成交率', 'action_label' => '去跟进', 'action_url' => '/xmp/crm?filter=due',
            'meta' => ['count' => $overdue],
        ]);
    }
    if ($newStale > 0) {
        $items[] = mainline_item('lead:stale', 'today', '线索', $newStale . ' 条新线索 48h 未跟进', [
            'icon' => '🫥', 'severity' => 'warn', 'est_min' => 15,
            'why' => '新线索黄金跟进期已过', 'action_label' => '批量分配', 'action_url' => '/xmp/crm?filter=new',
            'meta' => ['count' => $newStale],
        ]);
    }
    return $items;
}

/** 任务：逾期 / 今日到期（只显示与我相关或管理员全部） */
function mainline_src_tasks(): array {
    $items = [];
    $tasks = json_read(DATA_DIR . '/tasks.json');
    if (!is_array($tasks)) return $items;
    $me = $_SESSION['admin_user'] ?? '';
    $isAdmin = function_exists('has_perm') && has_perm('users');
    $today = date('Y-m-d');
    $due = 0; $overdue = 0;
    foreach ($tasks as $t) {
        if (in_array(($t['status'] ?? ''), ['done', 'cancelled'], true)) continue;
        if (!$isAdmin && ($t['assignee'] ?? '') !== $me) continue;
        $d = (string)($t['due_date'] ?? '');
        if ($d === '') continue;
        if ($d < $today) $overdue++;
        elseif ($d === $today) $due++;
    }
    if ($overdue > 0) {
        $items[] = mainline_item('task:overdue', 'now', '任务', $overdue . ' 个任务已逾期', [
            'icon' => '⚠️', 'severity' => 'warn', 'est_min' => 20,
            'why' => '承诺的截止时间已过', 'action_label' => '看待办', 'action_url' => '/xmp/tasks?filter=overdue',
            'meta' => ['count' => $overdue],
        ]);
    }
    if ($due > 0) {
        $items[] = mainline_item('task:today', 'today', '任务', $due . ' 个任务今天到期', [
            'icon' => '📋', 'severity' => 'info', 'est_min' => 20,
            'why' => '今天需要完成', 'action_label' => '看待办', 'action_url' => '/xmp/tasks?filter=today',
            'meta' => ['count' => $due],
        ]);
    }
    return $items;
}

/** 内容审核队列 */
function mainline_src_reviews(): array {
    $items = [];
    if (!function_exists('review_pending_count')) { $f = __DIR__ . '/../admin/review-lib.php'; if (is_file($f)) require_once $f; }
    if (!function_exists('review_pending_count')) return $items;
    $n = review_pending_count();
    if ($n > 0) {
        $items[] = mainline_item('review:pending', 'today', '内容', $n . ' 篇内容待审核', [
            'icon' => '📝', 'severity' => $n >= 5 ? 'warn' : 'info', 'est_min' => 10,
            'why' => '审核通过后才能发布', 'action_label' => '去审核', 'action_url' => '/xmp/reviews?status=pending',
            'meta' => ['count' => $n],
        ]);
    }
    return $items;
}

/** 社区待审评论 */
function mainline_src_moderation(): array {
    $items = [];
    $comments = json_read(DATA_DIR . '/comments.json');
    if (!is_array($comments)) return $items;
    $n = count(array_filter($comments, fn($c) => ($c['status'] ?? '') === 'pending'));
    if ($n > 0) {
        $items[] = mainline_item('moderation:pending', 'today', '社区', $n . ' 条评论待审核', [
            'icon' => '💬', 'severity' => 'info', 'est_min' => 8,
            'why' => '待审评论会影响社区氛围', 'action_label' => '去审核', 'action_url' => '/xmp/moderation',
            'meta' => ['count' => $n],
        ]);
    }
    return $items;
}

/** 订阅：即将到期 / 续费失败 */
function mainline_src_subscription(): array {
    $items = [];
    if (!function_exists('sub_get_state')) { require_once __DIR__ . '/SubscriptionSystem.php'; }
    if (!function_exists('sub_get_state')) return $items;
    $state = sub_get_state();
    $soon = 0; $failed = 0; $soonDays = 7;
    if (function_exists('sub_settings')) { $days = (string)(sub_settings()['reminder_days'] ?? '7'); $soonDays = (int)explode(',', $days)[0] ?: 7; }
    $limit = date('Y-m-d', time() + $soonDays * 86400);
    foreach ((array)$state as $sub) {
        if (($sub['status'] ?? '') !== 'active') continue;
        $exp = (string)($sub['expires_at'] ?? '');
        if ($exp !== '' && $exp <= $limit) {
            if (($sub['renew_attempts'] ?? 0) >= 1 && empty($sub['auto_renew'])) $failed++;
            elseif (!empty($sub['auto_renew'])) $soon++;
        }
    }
    if ($failed > 0) {
        $items[] = mainline_item('sub:failed', 'now', '订阅', $failed . ' 个订阅续费异常', [
            'icon' => '💳', 'severity' => 'warn', 'est_min' => 10,
            'why' => '自动续费未成功，会流失', 'action_label' => '去订阅管理', 'action_url' => '/xmp/subscription',
            'meta' => ['count' => $failed],
        ]);
    }
    if ($soon > 0) {
        $items[] = mainline_item('sub:soon', 'week', '订阅', $soon . ' 个订阅即将到期', [
            'icon' => '🔔', 'severity' => 'info', 'est_min' => 5,
            'why' => $soonDays . ' 天内到期', 'action_label' => '查看', 'action_url' => '/xmp/subscription',
            'meta' => ['count' => $soon],
        ]);
    }
    return $items;
}

/** 转化漏斗异常（FunnelGuard 报告） */
function mainline_src_funnel(): array {
    $items = [];
    $report = json_read(DATA_DIR . '/funnel-insights.json');
    $alerts = (int)($report['alerts'] ?? 0);
    if ($alerts > 0) {
        $top = $report['insights'][0] ?? [];
        $items[] = mainline_item('funnel:alerts', 'week', '洞察', $alerts . ' 处转化异常', [
            'icon' => '📉', 'severity' => 'warn', 'est_min' => 15,
            'why' => ($top['label'] ?? '') !== '' ? ('跌幅最大：' . $top['label'] . ' -' . ($top['drop_pct'] ?? 0) . '%') : '漏斗环比下滑',
            'action_label' => '看报告', 'action_url' => '/xmp/funnel-guard',
            'meta' => ['count' => $alerts],
        ]);
    }
    return $items;
}

/** 系统体检建议（SelfEvolve） */
function mainline_src_evolve(): array {
    $items = [];
    $ev = json_read(DATA_DIR . '/evolution.json');
    $sug = is_array($ev['suggestions'] ?? null) ? $ev['suggestions'] : [];
    $open = array_values(array_filter($sug, fn($s) => !in_array(($s['status'] ?? ''), ['resolved', 'dismissed'], true) && ($s['severity'] ?? '') !== 'info'));
    if (count($open) > 0) {
        $items[] = mainline_item('evolve:open', 'week', '系统', count($open) . ' 条系统优化建议', [
            'icon' => '🩺', 'severity' => 'info', 'est_min' => 20,
            'why' => (string)($open[0]['title'] ?? '平台健康待处理'),
            'action_label' => '去体检', 'action_url' => '/xmp/evolution',
            'meta' => ['count' => count($open)],
        ]);
    }
    return $items;
}

/** 产品发现草稿（ProductScout） */
function mainline_src_scout(): array {
    $items = [];
    if (!function_exists('get_articles')) { return $items; }
    $drafts = array_filter(get_articles(), fn($a) => ($a['status'] ?? '') === 'draft' && ($a['source'] ?? '') === 'product_scout');
    $n = count($drafts);
    if ($n > 0) {
        $items[] = mainline_item('scout:drafts', 'week', '发现', $n . ' 篇产品发现草稿待审', [
            'icon' => '🔭', 'severity' => 'info', 'est_min' => 10,
            'why' => 'AI 已发现新产品并写好速览', 'action_label' => '去审核', 'action_url' => '/xmp/product-scout',
            'meta' => ['count' => $n],
        ]);
    }
    return $items;
}

/* ═══════════════ 汇总 / 排序 / 权限 ═══════════════ */

/** source → 所需权限（无权限则不展示该来源） */
function mainline_source_perm(string $source): string {
    return [
        '直播' => 'live', '交易' => 'commerce', '线索' => 'crm', '任务' => 'tasks',
        '内容' => 'reviews', '社区' => 'moderation', '订阅' => 'subscription',
        '洞察' => 'analytics', '系统' => 'evolution', '发现' => 'articles',
    ][$source] ?? '';
}

function mainline_score(array $it): int {
    $base = ['critical' => 100, 'warn' => 70, 'info' => 40, 'good' => 20][$it['severity'] ?? 'info'] ?? 40;
    $lane = ['now' => 40, 'today' => 20, 'week' => 0][$it['lane'] ?? 'today'] ?? 0;
    return $base + $lane;
}

/**
 * 汇总所有 Action Item（已处理项剔除、权限过滤、排序）
 * @param array $opts ['all' => bool 是否忽略权限, 'include_handled' => bool]
 */
function mainline_items(array $opts = []): array {
    $providers = [
        'mainline_src_live', 'mainline_src_orders', 'mainline_src_leads', 'mainline_src_tasks',
        'mainline_src_reviews', 'mainline_src_moderation', 'mainline_src_subscription',
        'mainline_src_funnel', 'mainline_src_evolve', 'mainline_src_scout',
    ];
    $items = [];
    foreach ($providers as $fn) {
        try { foreach ((array)$fn() as $it) $items[] = $it; }
        catch (\Throwable $e) { /* 单源失败不影响主线 */ }
    }
    $handled = empty($opts['include_handled']) ? mainline_handled_ids() : [];
    $all = !empty($opts['all']);
    $out = [];
    foreach ($items as $it) {
        if (isset($handled[$it['id']])) continue;
        if (!$all) {
            $perm = mainline_source_perm((string)$it['source']);
            if ($perm !== '' && function_exists('has_perm') && !has_perm($perm)) continue;
        }
        $it['score'] = mainline_score($it);
        $out[] = $it;
    }
    usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
    return $out;
}

/** 按泳道分组 */
function mainline_lanes(array $items): array {
    $lanes = ['now' => [], 'today' => [], 'week' => []];
    foreach ($items as $it) $lanes[$it['lane'] ?? 'today'][] = $it;
    return $lanes;
}

/** 主线概览（供顶部卡/统计） */
function mainline_summary(array $items): array {
    $lanes = mainline_lanes($items);
    $load = 0;
    foreach ($lanes['now'] as $it) $load += (int)($it['est_min'] ?? 3);
    foreach ($lanes['today'] as $it) $load += (int)($it['est_min'] ?? 3);
    return [
        'total' => count($items),
        'now' => count($lanes['now']),
        'today' => count($lanes['today']),
        'week' => count($lanes['week']),
        'est_min' => $load,
        'top' => $items[0] ?? null,
    ];
}
