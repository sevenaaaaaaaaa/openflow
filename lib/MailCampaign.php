<?php
/**
 * MailCampaign — 邮件营销闭环
 * 退订链接/端点 · 打开(px)统计 · 点击统计 · 模板管理 · 邮件序列
 */

function mailc_file(): string { return DATA_DIR . '/mail-campaign.json'; }
function mailc_templates_file(): string { return DATA_DIR . '/mail-templates.json'; }
function mailc_stats_file(): string { return DATA_DIR . '/mail-stats.json'; }

// ─── 模板 ───
function mailc_templates(): array {
    return json_read(mailc_templates_file());
}
function mailc_save_templates(array $list): void { json_write(mailc_templates_file(), $list); }
function mailc_template(string $id): ?array {
    foreach (mailc_templates() as $t) if (($t['id'] ?? '') === $id) return $t;
    return null;
}

// ─── 退订 ───
function mailc_unsub_token(string $email): string {
    return bin2hex(hash_hmac('sha256', strtolower($email), (DATA_DIR . mailc_file()), true));
}
function mailc_unsub_link(string $email): string {
    return site_config_get('site_url') . '/api/unsubscribe.php?token=' . urlencode(mailc_unsub_token($email)) . '&email=' . urlencode($email);
}
function mailc_verify_unsub(string $email, string $token): bool {
    return hash_equals(mailc_unsub_token($email), $token);
}
function mailc_unsubscribe(string $email): bool {
    $subs = json_read(DATA_DIR . '/newsletter/subscribers.json');
    $changed = false;
    foreach ($subs as &$s) {
        if (strtolower(trim($s['email'] ?? '')) === strtolower(trim($email))) {
            $s['status'] = 'unsubscribed';
            $s['unsubscribed_at'] = date('Y-m-d H:i:s');
            $changed = true;
            break;
        }
    }
    unset($s);
    if ($changed) json_write(DATA_DIR . '/newsletter/subscribers.json', $subs);
    return $changed;
}

// ─── 打开/点击统计 ───
// 生成打开统计 pixel URL
function mailc_pixel(string $campaign, string $email): string {
    return site_config_get('site_url') . '/api/mail-track.php?c=' . urlencode($campaign) . '&e=' . urlencode(mailc_pixel_id($email)) . '&t=open';
}
// 包装链接加点击统计
function mailc_wrap_link(string $url, string $campaign, string $email): string {
    return site_config_get('site_url') . '/api/mail-track.php?c=' . urlencode($campaign) . '&e=' . urlencode(mailc_pixel_id($email)) . '&t=click&u=' . urlencode($url);
}
// 匿名化邮箱（不落 PII 到统计）
function mailc_pixel_id(string $email): string {
    return substr(hash('sha256', strtolower(trim($email))), 0, 16);
}
// 记录打开/点击
function mailc_track(string $campaign, string $emailId, string $type): void {
    $stats = json_read(mailc_stats_file());
    $key = $campaign . ':' . $emailId;
    $old = $stats[$key] ?? [];
    $now = date('Y-m-d H:i:s');
    $stats[$key] = array_merge($old, [
        'campaign' => $campaign, 'email_id' => $emailId,
        $type . '_count' => (int)($old[$type . '_count'] ?? 0) + 1,
        'first_' . $type => $old['first_' . $type] ?? $now,
        'last_' . $type => $now,
    ]);
    json_write(mailc_stats_file(), array_slice($stats, -20000));
}
// 统计汇总（打开率/点击率）
function mailc_campaign_stats(string $campaign, int $sentCount = 0): array {
    $stats = json_read(mailc_stats_file());
    $opens = 0; $clicks = 0;
    foreach ($stats as $k => $v) {
        if (strpos($k, $campaign . ':') !== 0) continue;
        if (!empty($v['open_count'])) $opens++;
        if (!empty($v['click_count'])) $clicks++;
    }
    return [
        'sent' => $sentCount, 'opens' => $opens, 'clicks' => $clicks,
        'open_rate' => $sentCount > 0 ? round($opens / $sentCount * 100, 1) : 0,
        'click_rate' => $sentCount > 0 ? round($clicks / $sentCount * 100, 1) : 0,
    ];
}

// ─── 渲染邮件内容（模板变量 + 退订/pixel/链接包装） ───
function mailc_render(string $html, array $vars, string $campaign, string $email): string {
    foreach ($vars as $k => $v) {
        $html = str_replace('{{' . $k . '}}', (string)$v, $html);
    }
    // 自动注入退订链接（若模板含 {{unsubscribe}}）
    $unsub = mailc_unsub_link($email);
    $html = str_replace('{{unsubscribe}}', $unsub, $html);
    $siteHost = parse_url(site_config_get('site_url'), PHP_URL_HOST) ?: '';
    // 链接包装（仅外部 http 链接，跳过本站/退订/pixel/邮件/锚点）
    $html = preg_replace_callback('#(href="(https?://[^"]+)")#', function ($mm) use ($campaign, $email, $siteHost) {
        $url = $mm[2];
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if ($host === $siteHost || strpos($url, '/api/') !== false) return $mm[1]; // 本站内链不包装
        return 'href="' . mailc_wrap_link($url, $campaign, $email) . '"';
    }, $html);
    return $html;
}
