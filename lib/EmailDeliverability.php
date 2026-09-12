<?php
/**
 * EmailDeliverability — 送达率中心 + 邮件收入归因（对标 Klaviyo/Beehiiv）
 *
 * 1) 抑制名单：硬退信 / 投诉 / 手动退订 → 永久不再发（保护发件声誉）
 * 2) 发件域认证自检：SPF / DKIM / DMARC / MX（dns_get_record，尽力而为）
 * 3) 送达率指标：打开/点击/退信/投诉率
 * 4) 收入归因：邮件点击带活动 → 下单成交 → 归因到该邮件活动
 */

function email_deliv_dir(): string { return DATA_DIR . '/email'; }
function email_suppress_file(): string { return email_deliv_dir() . '/suppression.json'; }
function email_deliv_stats_file(): string { return email_deliv_dir() . '/stats.json'; }
function email_attr_file(): string { return email_deliv_dir() . '/attribution.json'; }

function email_norm(string $email): string { return mb_strtolower(trim($email)); }

/* ── 抑制名单 ── */

function email_suppression_list(): array {
    $d = json_read(email_suppress_file());
    return is_array($d) ? $d : [];
}

function email_is_suppressed(string $email): bool {
    $e = email_norm($email);
    if ($e === '') return false;
    $list = email_suppression_list();
    return isset($list[$e]);
}

/** 加入抑制名单（reason: hard_bounce|complaint|unsubscribe|manual） */
function email_suppress(string $email, string $reason): bool {
    $e = email_norm($email);
    if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) return false;
    $list = email_suppression_list();
    if (isset($list[$e])) return true;
    $list[$e] = ['reason' => $reason, 'at' => date('Y-m-d H:i:s')];
    json_write(email_suppress_file(), $list);
    return true;
}

function email_unsuppress(string $email): bool {
    $e = email_norm($email);
    $list = email_suppression_list();
    if (!isset($list[$e])) return false;
    unset($list[$e]);
    json_write(email_suppress_file(), $list);
    return true;
}

/** 退信回执：硬退信永久抑制，软退信只计数 */
function email_record_bounce(string $email, string $type = 'hard'): void {
    $type = $type === 'soft' ? 'soft' : 'hard';
    $st = json_read(email_deliv_stats_file());
    $st['bounces'] = ($st['bounces'] ?? 0) + 1;
    $st[$type === 'hard' ? 'hard_bounces' : 'soft_bounces'] = ($st[$type === 'hard' ? 'hard_bounces' : 'soft_bounces'] ?? 0) + 1;
    $st['updated_at'] = date('Y-m-d H:i:s');
    json_write(email_deliv_stats_file(), $st);
    if ($type === 'hard') email_suppress($email, 'hard_bounce');
}

function email_record_complaint(string $email): void {
    $st = json_read(email_deliv_stats_file());
    $st['complaints'] = ($st['complaints'] ?? 0) + 1;
    $st['updated_at'] = date('Y-m-d H:i:s');
    json_write(email_deliv_stats_file(), $st);
    email_suppress($email, 'complaint');
}

/** 营销发送前的闸门：被抑制就不发 */
function email_can_marketing_send(string $email): bool {
    return !email_is_suppressed($email);
}

/** 记录已发送数（用于计算退信率/投诉率） */
function email_record_sent(int $n = 1): void {
    if ($n <= 0) return;
    $st = json_read(email_deliv_stats_file());
    $st['sent'] = (int)($st['sent'] ?? 0) + $n;
    $st['updated_at'] = date('Y-m-d H:i:s');
    json_write(email_deliv_stats_file(), $st);
}

/* ── 发件域认证自检（SPF/DKIM/DMARC/MX）── */

function email_sending_domain(): string {
    $from = '';
    try {
        if (function_exists('mail_channels')) {
            foreach (mail_channels() as $ch) {
                if (!empty($ch['enabled']) && !empty($ch['from'])) { $from = (string)$ch['from']; break; }
            }
        }
    } catch (\Throwable $e) {}
    if ($from === '' && function_exists('site_config_get')) $from = (string)site_config_get('admin_email');
    if ($from === '' && function_exists('site_config_get')) $from = (string)site_config_get('site_url');
    if (strpos($from, '@') !== false) return strtolower(substr($from, strpos($from, '@') + 1));
    $host = parse_url((strpos($from, '://') !== false ? $from : 'https://' . $from), PHP_URL_HOST) ?: '';
    return strtolower(ltrim((string)$host, 'www.'));
}

/** 尽力而为的 DNS 认证检查（离线/无 DNS 时返回 unknown，不报错） */
function email_dns_auth(string $domain = ''): array {
    $domain = $domain ?: email_sending_domain();
    $out = ['domain' => $domain, 'spf' => 'unknown', 'dmarc' => 'unknown', 'dkim' => 'unknown', 'mx' => 'unknown'];
    if ($domain === '' || !function_exists('dns_get_record')) return $out;
    $txt = function (string $name): array {
        try { $r = @dns_get_record($name, DNS_TXT); $list = []; foreach ((array)$r as $row) { if (!empty($row['txt'])) $list[] = $row['txt']; if (!empty($row['entries'])) $list = array_merge($list, $row['entries']); } return $list; }
        catch (\Throwable $e) { return []; }
    };
    try {
        foreach ($txt($domain) as $t) if (stripos($t, 'v=spf1') !== false) { $out['spf'] = 'ok'; break; }
        foreach ($txt('_dmarc.' . $domain) as $t) if (stripos($t, 'v=DMARC1') !== false) { $out['dmarc'] = 'ok'; break; }
        $sel = '';
        try { if (function_exists('mail_channels')) foreach (mail_channels() as $ch) { if (!empty($ch['dkim_selector'])) { $sel = (string)$ch['dkim_selector']; break; } } } catch (\Throwable $e) {}
        $dkimName = ($sel !== '' ? $sel : 'default') . '._domainkey.' . $domain;
        foreach ($txt($dkimName) as $t) if (stripos($t, 'v=DKIM1') !== false || stripos($t, 'k=rsa') !== false || stripos($t, 'p=') !== false) { $out['dkim'] = 'ok'; break; }
        $mx = @dns_get_record($domain, DNS_MX);
        if (!empty($mx)) $out['mx'] = 'ok';
    } catch (\Throwable $e) {}
    return $out;
}

/** 送达率指标汇总 */
function email_deliverability_stats(): array {
    $suppressed = email_suppression_list();
    $reasons = [];
    foreach ($suppressed as $row) $reasons[$row['reason'] ?? 'other'] = ($reasons[$row['reason'] ?? 'other'] ?? 0) + 1;
    $st = json_read(email_deliv_stats_file());
    $opens = 0; $clicks = 0;
    try {
        if (function_exists('mailc_stats_all')) {
            foreach (mailc_stats_all() as $row) { $opens += (int)($row['opens'] ?? 0); $clicks += (int)($row['clicks'] ?? 0); }
        }
    } catch (\Throwable $e) {}
    $bounces = (int)($st['bounces'] ?? 0);
    $complaints = (int)($st['complaints'] ?? 0);
    $sent = (int)($st['sent'] ?? 0);
    return [
        'suppressed' => count($suppressed), 'suppress_reasons' => $reasons,
        'bounces' => $bounces, 'hard_bounces' => (int)($st['hard_bounces'] ?? 0),
        'complaints' => $complaints, 'opens' => $opens, 'clicks' => $clicks,
        'sent' => $sent,
        'bounce_rate' => $sent > 0 ? round($bounces / $sent * 100, 2) : 0,
        'complaint_rate' => $sent > 0 ? round($complaints / $sent * 100, 3) : 0,
    ];
}

/* ── 邮件收入归因 ── */

/** 记录一次由邮件活动带来的成交 */
function email_attr_record(string $campaign, float $amount, string $orderId = ''): void {
    $campaign = trim($campaign);
    if ($campaign === '' || $amount <= 0) return;
    $all = json_read(email_attr_file());
    $row = $all[$campaign] ?? ['campaign' => $campaign, 'orders' => 0, 'revenue' => 0.0, 'first_at' => date('Y-m-d H:i:s')];
    $row['orders'] = (int)$row['orders'] + 1;
    $row['revenue'] = (float)$row['revenue'] + $amount;
    $row['last_at'] = date('Y-m-d H:i:s');
    if ($orderId !== '') { $row['orders_ids'] = array_slice(array_merge((array)($row['orders_ids'] ?? []), [$orderId]), -50); }
    $all[$campaign] = $row;
    json_write(email_attr_file(), $all);
}

/** 邮件归因报表：按营收排序 */
function email_attribution_report(): array {
    $all = json_read(email_attr_file());
    $rows = array_values($all);
    usort($rows, fn($a, $b) => ($b['revenue'] ?? 0) <=> ($a['revenue'] ?? 0));
    return $rows;
}
