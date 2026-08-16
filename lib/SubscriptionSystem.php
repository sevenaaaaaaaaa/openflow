<?php
/**
 * Newsletter 付费订阅系统
 * 国内版：自研（虎皮椒支付 + 订阅状态 + 邮件发送）
 * 海外版：Ghost CMS 集成（配置 Ghost 站点后走 Ghost 订阅）
 */

function sub_plans_file(): string { return DATA_DIR . '/subscription/plans.json'; }
function sub_settings_file(): string { return DATA_DIR . '/subscription/settings.json'; }
function sub_state_file(): string { return DATA_DIR . '/subscription/state.json'; }

// ─── 订阅计划 ───
function sub_get_plans(): array {
    return json_read(sub_plans_file());
}
function sub_save_plans(array $plans): bool {
    if (!is_dir(dirname(sub_plans_file()))) mkdir(dirname(sub_plans_file()), 0755, true);
    return json_write(sub_plans_file(), $plans);
}

// ─── 订阅设置（含 Ghost 海外版）───
function sub_settings(): array {
    return array_merge([
        'enabled' => false,
        'currency' => 'CNY',
        'ghost_enabled' => false,       // 海外版开关
        'ghost_api_url' => '',          // Ghost 站点 API URL
        'ghost_content_key' => '',      // Ghost Content API Key
        'ghost_admin_key' => '',        // Ghost Admin API Key（可选）
    ], json_read(sub_settings_file()));
}
function sub_save_settings(array $s): bool {
    if (!is_dir(dirname(sub_settings_file()))) mkdir(dirname(sub_settings_file()), 0755, true);
    return json_write(sub_settings_file(), $s);
}

// ─── 订阅状态（member_id => 订阅信息）───
function sub_get_state(): array {
    return json_read(sub_state_file());
}
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

// 当前会员是否有活跃订阅
function sub_is_active(string $memberId): bool {
    $s = sub_get_member($memberId);
    if (!$s || ($s['status'] ?? '') !== 'active') return false;
    if (!empty($s['expires_at']) && $s['expires_at'] < date('Y-m-d')) return false;
    return true;
}

// 检查所有订阅是否过期（cron 调用）
function sub_expire_check(): void {
    $state = sub_get_state();
    foreach ($state as $mid => &$s) {
        if (($s['status'] ?? '') === 'active' && !empty($s['expires_at']) && $s['expires_at'] < date('Y-m-d')) {
            $s['status'] = 'expired';
        }
    }
    unset($s);
    sub_save_state($state);
}
