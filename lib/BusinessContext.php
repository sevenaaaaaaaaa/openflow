<?php
/**
 * BusinessContext — 给「小福控制台」用的真实业务快照
 *
 * 把散落各模块的关键经营数字，收成一份紧凑、可读、可喂给模型的结构化上下文。
 * 全部只读、全部 try/catch：任一块失败不影响其余，绝不编造数字（没有就是 0/空）。
 */

function business_context(): array {
    $ctx = [
        'audience' => ['profiles' => 0, 'active_7d' => 0, 'new_7d' => 0],
        'leads'    => ['total' => 0, 'new_7d' => 0, 'due' => 0, 'won_30d' => 0, 'pipeline' => 0.0],
        'commerce' => ['orders_30d' => 0, 'revenue_30d' => 0.0, 'refunds_30d' => 0],
        'content'  => ['published' => 0, 'drafts' => 0, 'published_30d' => 0],
        'courses'  => ['published' => 0, 'enrollments' => 0],
        'live'     => ['upcoming' => 0, 'next_start' => ''],
        'ops'      => ['reviews_pending' => 0, 'comments_pending' => 0, 'fulfillment_pending' => 0, 'sub_failed' => 0],
        'goal'     => null,
    ];

    // 受众（CDP）
    try {
        require_once __DIR__ . '/CdpSystem.php';
        $profiles = CdpSystem::allProfiles();
        $ctx['audience']['profiles'] = count($profiles);
        foreach ($profiles as $p) {
            $seen = strtotime((string)($p['last_seen'] ?? '2000-01-01'));
            if ($seen > time() - 7 * 86400) $ctx['audience']['active_7d']++;
            if ($seen > time() - 7 * 86400 && strtotime((string)($p['created_at'] ?? '2000-01-01')) > time() - 7 * 86400) $ctx['audience']['new_7d']++;
        }
    } catch (\Throwable $e) {}

    // 线索（CRM）
    try {
        require_once __DIR__ . '/CrmSystem.php';
        $leads = crm_get()['leads'] ?? [];
        $today = date('Y-m-d');
        foreach ($leads as $l) {
            $ctx['leads']['total']++;
            $created = strtotime((string)($l['created_at'] ?? '2000-01-01'));
            if ($created > time() - 7 * 86400) $ctx['leads']['new_7d']++;
            $stage = (string)($l['stage'] ?? 'new');
            if (in_array($stage, ['qualified', 'opportunity'], true)) $ctx['leads']['pipeline'] += (float)($l['value'] ?? 0);
            if ($stage === 'won' && strtotime((string)($l['updated_at'] ?? $l['created_at'] ?? '2000-01-01')) > time() - 30 * 86400) $ctx['leads']['won_30d']++;
            if (in_array($stage, ['won', 'lost', 'closed'], true)) continue;
            $nf = (string)($l['next_followup'] ?? '');
            if ($nf !== '' && $nf <= $today) $ctx['leads']['due']++;
        }
    } catch (\Throwable $e) {}

    // 交易（订单）
    try {
        require_once __DIR__ . '/ShopSystem.php';
        $orders = function_exists('shop_all_orders') ? shop_all_orders() : [];
        foreach ($orders as $o) {
            if (strtotime((string)($o['paid_at'] ?? $o['created_at'] ?? '2000-01-01')) < time() - 30 * 86400) continue;
            $st = (string)($o['status'] ?? '');
            if ($st === 'paid') { $ctx['commerce']['orders_30d']++; $ctx['commerce']['revenue_30d'] += (float)($o['amount'] ?? 0); }
            elseif (in_array($st, ['refunded', 'refund'], true)) $ctx['commerce']['refunds_30d']++;
        }
    } catch (\Throwable $e) {}

    // 内容
    try {
        if (function_exists('get_articles_list')) {
            foreach (get_articles_list() as $a) {
                $st = (string)($a['status'] ?? '');
                if ($st === 'published') {
                    $ctx['content']['published']++;
                    if (strtotime((string)($a['created_at'] ?? '2000-01-01')) > time() - 30 * 86400) $ctx['content']['published_30d']++;
                } elseif ($st === 'draft') $ctx['content']['drafts']++;
            }
        }
    } catch (\Throwable $e) {}

    // 课程
    try {
        $courses = json_read(DATA_DIR . '/courses/index.json');
        foreach ($courses as $c) {
            $st = (string)($c['status'] ?? '');
            if (in_array($st, ['published', 'active'], true)) $ctx['courses']['published']++;
            $ctx['courses']['enrollments'] += (int)($c['students'] ?? 0);
        }
    } catch (\Throwable $e) {}

    // 直播
    try {
        if (!function_exists('live_rooms')) { require_once __DIR__ . '/LiveSystem.php'; }
        foreach (live_rooms() as $r) {
            $ts = strtotime((string)($r['start_at'] ?? ''));
            if ($ts && $ts > time()) {
                $ctx['live']['upcoming']++;
                if ($ctx['live']['next_start'] === '' || $ts < strtotime($ctx['live']['next_start'])) $ctx['live']['next_start'] = (string)$r['start_at'];
            }
        }
    } catch (\Throwable $e) {}

    // 待办运营项
    try {
        if (function_exists('review_pending_count')) $ctx['ops']['reviews_pending'] = review_pending_count();
        $comments = json_read(DATA_DIR . '/comments.json');
        $ctx['ops']['comments_pending'] = is_array($comments) ? count(array_filter($comments, fn($c) => ($c['status'] ?? '') === 'pending')) : 0;
        $co = json_read(DATA_DIR . '/commerce/orders.json');
        $ctx['ops']['fulfillment_pending'] = is_array($co) ? count(array_filter($co, fn($o) => ($o['fulfillment'] ?? '') === 'pending')) : 0;
        if (!function_exists('sub_get_state')) { require_once __DIR__ . '/SubscriptionSystem.php'; }
        foreach ((array)sub_get_state() as $s) {
            if (($s['status'] ?? '') === 'active' && ($s['renew_attempts'] ?? 0) >= 1 && empty($s['auto_renew'])) $ctx['ops']['sub_failed']++;
        }
    } catch (\Throwable $e) {}

    // 目标
    try {
        require_once __DIR__ . '/GrowthGoal.php';
        $g = growth_goal_current();
        if ($g) {
            $prog = growth_goal_progress($g);
            $ctx['goal'] = ['title' => (string)($g['title'] ?? ''), 'metric' => (string)($g['metric'] ?? ''), 'target' => (float)($g['target'] ?? 0), 'current' => (float)($prog['current'] ?? 0), 'pct' => (int)($prog['pct'] ?? 0)];
        }
    } catch (\Throwable $e) {}

    $ctx['digest'] = business_context_digest($ctx);
    $ctx['has_data'] = ($ctx['audience']['profiles'] > 0 || $ctx['leads']['total'] > 0 || $ctx['content']['published'] > 0 || $ctx['commerce']['orders_30d'] > 0);
    return $ctx;
}

/** 把快照编成一段紧凑中文，供模型阅读 */
function business_context_digest(array $c): string {
    $lines = [];
    $lines[] = "【受众】画像 {$c['audience']['profiles']} · 7日活跃 {$c['audience']['active_7d']} · 7日新增 {$c['audience']['new_7d']}";
    $lines[] = "【线索】共 {$c['leads']['total']} · 7日新增 {$c['leads']['new_7d']} · 跟进超期 {$c['leads']['due']} · 30日成交 {$c['leads']['won_30d']} · 管道金额 ¥" . number_format($c['leads']['pipeline'], 0);
    $lines[] = "【交易】30日已付 {$c['commerce']['orders_30d']} 单 · 收入 ¥" . number_format($c['commerce']['revenue_30d'], 0) . " · 退款 {$c['commerce']['refunds_30d']}";
    $lines[] = "【内容】已发布 {$c['content']['published']} · 草稿 {$c['content']['drafts']} · 30日新发 {$c['content']['published_30d']}";
    $lines[] = "【课程】已上架 {$c['courses']['published']} · 累计学员 {$c['courses']['enrollments']}";
    $lines[] = "【直播】待开播 {$c['live']['upcoming']}" . ($c['live']['next_start'] !== '' ? " · 最近 " . substr($c['live']['next_start'], 0, 16) : '');
    $lines[] = "【待处理】内容待审 {$c['ops']['reviews_pending']} · 社区待审 {$c['ops']['comments_pending']} · 待发货 {$c['ops']['fulfillment_pending']} · 订阅续费异常 {$c['ops']['sub_failed']}";
    if ($c['goal']) $lines[] = "【本周目标】{$c['goal']['title']}（{$c['goal']['metric']}）：{$c['goal']['current']} / {$c['goal']['target']} · 完成 {$c['goal']['pct']}%";
    else $lines[] = '【本周目标】未设置';
    return implode("\n", $lines);
}
