<?php
/**
 * FrequencyCap — 跨渠道触达频控 + 疲劳度管理
 * 限制每个用户每日/每周各渠道接收上限，避免过度触达
 */

function freq_file(): string { return DATA_DIR . '/frequency-cap.json'; }
function freq_log_file(): string { return DATA_DIR . '/frequency-log.json'; }

// 频控配置（默认：邮件每天2封/每周6封；站内信每天3条；通知每天2条）
function freq_config(): array {
    $cfg = json_read(freq_file());
    return array_merge([
        'email_daily' => 2, 'email_weekly' => 6,
        'inbox_daily' => 3, 'notify_daily' => 2,
    ], $cfg);
}
function freq_save_config(array $cfg): void { json_write(freq_file(), $cfg); }

// 某用户某渠道今天/本周已发送次数
function freq_used(string $memberId, string $channel): array {
    $log = json_read(freq_log_file());
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $daily = 0; $weekly = 0;
    foreach ($log as $e) {
        if (($e['member_id'] ?? '') !== $memberId || ($e['channel'] ?? '') !== $channel) continue;
        $d = substr($e['at'] ?? '', 0, 10);
        if ($d === $today) $daily++;
        if ($d >= $weekStart) $weekly++;
    }
    return ['daily' => $daily, 'weekly' => $weekly];
}

// 判断某渠道是否允许触达
function freq_can_send(string $memberId, string $channel): bool {
    $cfg = freq_config();
    $used = freq_used($memberId, $channel);
    $limits = ['email' => ['daily' => $cfg['email_daily'], 'weekly' => $cfg['email_weekly']], 'inbox' => ['daily' => $cfg['inbox_daily'], 'weekly' => 0], 'notify' => ['daily' => $cfg['notify_daily'], 'weekly' => 0]];
    $lim = $limits[$channel] ?? ['daily' => 99, 'weekly' => 999];
    if ($lim['daily'] > 0 && $used['daily'] >= $lim['daily']) return false;
    if ($lim['weekly'] > 0 && $used['weekly'] >= $lim['weekly']) return false;
    return true;
}

// 记录一次触达
function freq_log(string $memberId, string $channel, string $label = ''): void {
    $log = json_read(freq_log_file());
    $log[] = ['member_id' => $memberId, 'channel' => $channel, 'label' => $label, 'at' => date('Y-m-d H:i:s')];
    json_write(freq_log_file(), array_slice($log, -20000));
}
