<?php
/**
 * 付费订阅系统（v2.1 商业化核心）
 *
 * 能力：计划管理 · 订阅生命周期 · 到期提醒 · 自动续费 · 取消流程
 * 存储：JSON 文件（subscription/plans.json + state.json + settings.json）
 * 依赖：PaymentChannel（支付）、会员系统（权益）、邮件发送
 */

function sub_plans_file(): string { return DATA_DIR . '/subscription/plans.json'; }
function sub_settings_file(): string { return DATA_DIR . '/subscription/settings.json'; }
function sub_state_file(): string { return DATA_DIR . '/subscription/state.json'; }

// ─── 订阅计划 ───
function sub_get_plans(): array { return json_read(sub_plans_file()); }
function sub_save_plans(array $plans): bool {
    if (!is_dir(dirname(sub_plans_file()))) mkdir(dirname(sub_plans_file()), 0755, true);
    return json_write(sub_plans_file(), $plans);
}
function sub_plan(string $planId): ?array {
    foreach (sub_get_plans() as $p) if (($p['id'] ?? '') === $planId) return $p;
    return null;
}
function sub_plans_enabled(): array {
    return array_values(array_filter(sub_get_plans(), fn($p) => !empty($p['enabled'])));
}

// ─── 订阅设置 ───
function sub_settings(): array {
    return array_merge([
        'enabled' => false,
        'currency' => 'CNY',
        // 到期提醒天数（在 expires_at 前几天提醒，逗号分隔）
        'reminder_days' => '7,3,1',
        // 自动续费尝试次数上限
        'max_renew_attempts' => 3,
        // Ghost 海外版
        'ghost_enabled' => false,
        'ghost_api_url' => '',
        'ghost_content_key' => '',
        'ghost_admin_key' => '',
    ], json_read(sub_settings_file()));
}
function sub_save_settings(array $s): bool {
    if (!is_dir(dirname(sub_settings_file()))) mkdir(dirname(sub_settings_file()), 0755, true);
    return json_write(sub_settings_file(), $s);
}

// ─── 订阅状态（member_id => 订阅信息）───
function sub_get_state(): array { return json_read(sub_state_file()); }
function sub_save_state(array $state): bool {
    if (!is_dir(dirname(sub_state_file()))) mkdir(dirname(sub_state_file()), 0755, true);
    return json_write(sub_state_file(), $state);
}
function sub_get_member(string $memberId): ?array {
    $state = sub_get_state();
    return $state[$memberId] ?? null;
}
function sub_set_member(string $memberId, array $info): void {
    $state = sub_get_state();
    $state[$memberId] = $info;
    sub_save_state($state);
}

// ─── 订阅生命周期 ───

/**
 * 创建订阅
 * @param string $memberId 会员ID
 * @param string $planId   计划ID
 * @param array  $opts     auto_renew, payment_method, order_id
 */
function sub_create(string $memberId, string $planId, array $opts = []): array {
    $plan = sub_plan($planId);
    if (!$plan) return ['ok' => false, 'error' => '计划不存在'];

    $now = date('Y-m-d H:i:s');
    $period = $plan['period'] ?? 'month';
    $expires = ($period === 'year')
        ? date('Y-m-d', strtotime('+1 year'))
        : date('Y-m-d', strtotime('+1 month'));

    $sub = [
        'plan_id'        => $planId,
        'status'         => 'active',
        'auto_renew'     => !empty($opts['auto_renew']),
        'created_at'     => $now,
        'expires_at'     => $expires,
        'renewed_at'     => $now,
        'cancel_at'      => '',          // 到期后才真正取消（提前取消不立即断）
        'cancel_reason'  => '',
        'payment_method' => $opts['payment_method'] ?? '',
        'order_id'       => $opts['order_id'] ?? '',
        'renew_attempts' => 0,
        'last_reminder'  => '',
        'history'        => [['ts' => $now, 'action' => 'created', 'plan' => $planId]],
    ];
    sub_set_member($memberId, $sub);
    // 同步会员等级
    if (function_exists('mem_sync_from_subscription')) mem_sync_from_subscription($memberId);
    // 触发事件（旁路）
    if (function_exists('plugin_hook')) plugin_hook('subscription_created', $sub);
    return ['ok' => true, 'subscription' => $sub];
}

// 当前会员是否有活跃订阅
function sub_is_active(string $memberId): bool {
    $s = sub_get_member($memberId);
    if (!$s) return false;
    $status = $s['status'] ?? '';
    if ($status !== 'active' && $status !== 'pending_cancel') return false;
    if (!empty($s['expires_at']) && $s['expires_at'] < date('Y-m-d')) return false;
    return true;
}

// ─── 取消订阅 ───

/**
 * 取消订阅（可选立即或到期后）
 * @param string $memberId
 * @param string $reason
 * @param bool   $immediate true=立即取消，false=到期后取消（默认）
 */
function sub_cancel(string $memberId, string $reason = '', bool $immediate = false): array {
    $s = sub_get_member($memberId);
    if (!$s) return ['ok' => false, 'error' => '无订阅'];
    if (($s['status'] ?? '') !== 'active') return ['ok' => false, 'error' => '订阅非活跃'];

    $now = date('Y-m-d H:i:s');
    if ($immediate) {
        $s['status'] = 'cancelled';
        $s['expires_at'] = date('Y-m-d');
    } else {
        $s['status'] = 'pending_cancel';
        $s['cancel_at'] = $s['expires_at'] ?? '';
    }
    $s['cancel_reason'] = $reason;
    $s['history'][] = ['ts' => $now, 'action' => 'cancelled', 'reason' => $reason, 'immediate' => $immediate];
    sub_set_member($memberId, $s);
    // 同步会员等级
    if (function_exists('mem_sync_from_subscription')) mem_sync_from_subscription($memberId);
    // 发送取消确认（旁路）
    if (function_exists('sub_send_cancellation_email')) sub_send_cancellation_email($memberId, $s, $immediate);
    if (function_exists('plugin_hook')) plugin_hook('subscription_cancelled', $s);
    return ['ok' => true, 'status' => $s['status']];
}

/**
 * 恢复已取消的订阅（仅 pending_cancel 状态可恢复）
 */
function sub_resume(string $memberId): array {
    $s = sub_get_member($memberId);
    if (!$s) return ['ok' => false, 'error' => '无订阅'];
    if (($s['status'] ?? '') !== 'pending_cancel') return ['ok' => false, 'error' => '订阅非待取消状态'];

    $s['status'] = 'active';
    $s['cancel_at'] = '';
    $s['cancel_reason'] = '';
    $s['history'][] = ['ts' => date('Y-m-d H:i:s'), 'action' => 'resumed'];
    sub_set_member($memberId, $s);
    // 同步会员等级
    if (function_exists('mem_sync_from_subscription')) mem_sync_from_subscription($memberId);
    return ['ok' => true];
}

// ─── 自动续费 ───

/**
 * 尝试续费（由 cron 调用）
 * @return array ['ok'=>bool, 'attempted'=>int, 'succeeded'=>int, 'failed'=>int]
 */
function sub_attempt_renewals(): array {
    $settings = sub_settings();
    $maxAttempts = (int)($settings['max_renew_attempts'] ?? 3);
    $state = sub_get_state();
    $today = date('Y-m-d');
    $attempted = 0; $succeeded = 0; $failed = 0;

    foreach ($state as $mid => &$s) {
        // 只处理 active + auto_renew + 即将到期（3天内）的订阅
        if (($s['status'] ?? '') !== 'active') continue;
        if (empty($s['auto_renew'])) continue;
        $expires = $s['expires_at'] ?? '';
        if ($expires === '' || $expires > date('Y-m-d', strtotime('+3 days'))) continue;
        if ($expires < $today) continue; // 已过期，交给 expire_check 处理

        $attempts = (int)($s['renew_attempts'] ?? 0);
        if ($attempts >= $maxAttempts) continue; // 已达最大尝试次数

        $attempted++;
        $s['renew_attempts'] = $attempts + 1;

        // 尝试支付
        $renewResult = sub_try_renew_payment($mid, $s);
        if ($renewResult['ok']) {
            $succeeded++;
            // 续费成功：延长到期日，重置尝试次数
            $plan = sub_plan($s['plan_id'] ?? '');
            $period = $plan['period'] ?? 'month';
            $newExpiry = ($period === 'year')
                ? date('Y-m-d', strtotime($expires . ' +1 year'))
                : date('Y-m-d', strtotime($expires . ' +1 month'));
            $s['expires_at'] = $newExpiry;
            $s['renewed_at'] = date('Y-m-d H:i:s');
            $s['renew_attempts'] = 0;
            $s['status'] = 'active';
            $s['cancel_at'] = '';
            $s['history'][] = ['ts' => date('Y-m-d H:i:s'), 'action' => 'renewed', 'method' => $renewResult['method'] ?? '', 'expires' => $newExpiry];
            // 发送续费确认（旁路）
            if (function_exists('sub_send_renewal_email')) sub_send_renewal_email($mid, $s, $newExpiry);
        } else {
            $failed++;
            $s['history'][] = ['ts' => date('Y-m-d H:i:s'), 'action' => 'renew_failed', 'reason' => $renewResult['error'] ?? 'payment_failed'];
            // 达到最大尝试次数时标记待取消
            if ($s['renew_attempts'] >= $maxAttempts) {
                $s['status'] = 'pending_cancel';
                $s['cancel_reason'] = '自动续费失败（已尝试 ' . $s['renew_attempts'] . ' 次）';
                $s['cancel_at'] = $expires;
                $s['history'][] = ['ts' => date('Y-m-d H:i:s'), 'action' => 'pending_cancel_renew_failed'];
            }
        }
    }
    unset($s);
    sub_save_state($state);
    return ['ok' => true, 'attempted' => $attempted, 'succeeded' => $succeeded, 'failed' => $failed];
}

/**
 * 执行续费支付（调用支付通道）
 */
function sub_try_renew_payment(string $memberId, array $sub): array {
    $plan = sub_plan($sub['plan_id'] ?? '');
    if (!$plan) return ['ok' => false, 'error' => '计划不存在'];
    $amount = (float)($plan['price'] ?? 0);
    if ($amount <= 0) return ['ok' => false, 'error' => '价格异常'];

    // 使用上次支付方式（或默认通道）
    $method = $sub['payment_method'] ?? '';
    if (empty($method) || !function_exists('payment_channel_ready') || !payment_channel_ready($method)) {
        return ['ok' => false, 'error' => '支付通道不可用'];
    }

    // 创建订单
    $orderId = 'sub_' . $memberId . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    $order = [
        'id'          => $orderId,
        'member_id'   => $memberId,
        'type'        => 'subscription',
        'plan_id'     => $sub['plan_id'],
        'amount'      => $amount,
        'currency'    => sub_settings()['currency'] ?? 'CNY',
        'status'      => 'pending',
        'payment_method' => $method,
        'created_at'  => date('Y-m-d H:i:s'),
    ];
    if (function_exists('shop_all_orders')) {
        $orders = shop_all_orders();
        $orders[] = $order;
        if (function_exists('json_write')) json_write(DATA_DIR . '/shop/orders.json', $orders);
    }

    // 调用支付通道创建支付
    if (function_exists('payment_channel_create')) {
        $result = payment_channel_create($method, $order);
        if (!empty($result['pay_url']) || !empty($result['qrcode'])) {
            // 支付创建成功（需要用户完成支付，或自动扣款通道直接完成）
            $sub['order_id'] = $orderId;
            return ['ok' => true, 'method' => $method, 'order_id' => $orderId, 'pay_url' => $result['pay_url'] ?? ''];
        }
    }
    return ['ok' => false, 'error' => '支付创建失败'];
}

// ─── 到期提醒 ───

/**
 * 发送到期提醒（由 cron 调用）
 * @return array ['ok'=>bool, 'sent'=>int]
 */
function sub_send_reminders(): array {
    $settings = sub_settings();
    $reminderDays = array_map('intval', explode(',', $settings['reminder_days'] ?? '7,3,1'));
    $state = sub_get_state();
    $today = date('Y-m-d');
    $sent = 0;

    foreach ($state as $mid => $s) {
        if (($s['status'] ?? '') !== 'active' && ($s['status'] ?? '') !== 'pending_cancel') continue;
        $expires = $s['expires_at'] ?? '';
        if ($expires === '' || $expires < $today) continue;

        $daysLeft = (int)floor((strtotime($expires) - strtotime($today)) / 86400);
        $lastReminder = $s['last_reminder'] ?? '';

        foreach ($reminderDays as $day) {
            if ($daysLeft === $day && $lastReminder !== $today) {
                // 发送提醒（旁路，失败不影响主逻辑）
                if (function_exists('sub_send_expiry_reminder')) {
                    sub_send_expiry_reminder($mid, $s, $daysLeft);
                }
                $state[$mid]['last_reminder'] = $today;
                $state[$mid]['history'][] = ['ts' => date('Y-m-d H:i:s'), 'action' => 'reminder_sent', 'days_left' => $daysLeft];
                $sent++;
                break; // 每天最多提醒一次
            }
        }
    }
    sub_save_state($state);
    return ['ok' => true, 'sent' => $sent];
}

// ─── 检查所有订阅是否过期（cron 调用，扩展现有逻辑）───
function sub_expire_check(): void {
    $state = sub_get_state();
    $changed = false;
    $changedMembers = [];
    foreach ($state as $mid => &$s) {
        if (($s['status'] ?? '') === 'active' && !empty($s['expires_at']) && $s['expires_at'] < date('Y-m-d')) {
            $s['status'] = 'expired';
            $s['history'][] = ['ts' => date('Y-m-d H:i:s'), 'action' => 'expired'];
            $changed = true;
            $changedMembers[] = $mid;
            // 到期事件（旁路）
            if (function_exists('plugin_hook')) plugin_hook('subscription_expired', $s);
        }
        // pending_cancel 且已过到期日 → 正式取消
        if (($s['status'] ?? '') === 'pending_cancel' && !empty($s['cancel_at']) && $s['cancel_at'] < date('Y-m-d')) {
            $s['status'] = 'cancelled';
            $s['history'][] = ['ts' => date('Y-m-d H:i:s'), 'action' => 'cancelled_by_expiry'];
            $changed = true;
            $changedMembers[] = $mid;
        }
    }
    unset($s);
    if ($changed) {
        sub_save_state($state);
        // 同步会员等级
        foreach ($changedMembers as $mid) {
            if (function_exists('mem_sync_from_subscription')) mem_sync_from_subscription($mid);
        }
    }
}

// ─── 查询 / 统计 ───

function sub_stats(): array {
    $state = sub_get_state();
    $stats = ['active' => 0, 'pending_cancel' => 0, 'expired' => 0, 'cancelled' => 0, 'total' => 0];
    foreach ($state as $s) {
        $st = $s['status'] ?? 'unknown';
        $stats[$st] = ($stats[$st] ?? 0) + 1;
        $stats['total']++;
    }
    return $stats;
}

function sub_list_by_status(string $status): array {
    $out = [];
    foreach (sub_get_state() as $mid => $s) {
        if (($s['status'] ?? '') === $status) $out[$mid] = $s;
    }
    return $out;
}

// ─── Ghost 海外版支持（保留原有逻辑）───
function sub_ghost_enabled(): bool {
    $s = sub_settings();
    return !empty($s['ghost_enabled']) && !empty($s['ghost_api_url']);
}
function sub_ghost_content_key(): string {
    return sub_settings()['ghost_content_key'] ?? '';
}
