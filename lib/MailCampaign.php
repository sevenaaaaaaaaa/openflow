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
/**
 * 打开/点击统计的存储层（docs/ROADMAP.md 阶段一）
 *
 * 【为什么从 JSON 改成 SQLite】mailc_track() 挂在 api/mail-track.php 这个公开的
 * 追踪像素端点上：**每一次邮件打开、每一次链接点击都会调它一次**，而且是在群发之后
 * 集中涌进来的。老实现每次都要「读两万条 → 改一条 → 整个写回」，这有两个后果：
 *   ① 慢：一次打开就是两万条 JSON 的解析加序列化；
 *   ② **丢数据**：并发的读-改-写会互相覆盖，打开数天然偏低（这是正确性问题，不只是性能）。
 * 改成一行 UPSERT 之后，计数由 SQLite 原子累加，并发不再丢。
 *
 * 老 mail-stats.json 首次访问时一次性导入，原文件保留作回滚备份；
 * SQLite 不可用时回退到原 JSON 实现。
 */
function mailc_stats_ensure(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        require_once __DIR__ . '/Database.php';
        Database::execute("CREATE TABLE IF NOT EXISTS mail_stats (
            campaign    TEXT NOT NULL,
            email_id    TEXT NOT NULL,
            open_count  INTEGER DEFAULT 0,
            click_count INTEGER DEFAULT 0,
            first_open  TEXT DEFAULT '', last_open  TEXT DEFAULT '',
            first_click TEXT DEFAULT '', last_click TEXT DEFAULT '',
            PRIMARY KEY (campaign, email_id)
        )");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_mail_stats_campaign ON mail_stats(campaign)");

        $marker = DATA_DIR . '/.mail_stats_migrated';
        if (!is_file($marker)) {
            $legacy = json_read(mailc_stats_file());
            if (is_array($legacy) && $legacy) {
                $conn = Database::conn();
                $own = !$conn->inTransaction();
                if ($own) $conn->beginTransaction();
                try {
                    $st = $conn->prepare("INSERT OR REPLACE INTO mail_stats
                        (campaign, email_id, open_count, click_count, first_open, last_open, first_click, last_click)
                        VALUES (?,?,?,?,?,?,?,?)");
                    foreach ($legacy as $key => $v) {
                        if (!is_array($v)) continue;
                        $c = (string)($v['campaign'] ?? '');
                        $e = (string)($v['email_id'] ?? '');
                        if ($c === '' && strpos((string)$key, ':') !== false) {
                            [$c, $e] = array_pad(explode(':', (string)$key, 2), 2, '');
                        }
                        if ($c === '' || $e === '') continue;
                        $st->execute([$c, $e, (int)($v['open_count'] ?? 0), (int)($v['click_count'] ?? 0),
                            (string)($v['first_open'] ?? ''), (string)($v['last_open'] ?? ''),
                            (string)($v['first_click'] ?? ''), (string)($v['last_click'] ?? '')]);
                    }
                    if ($own) $conn->commit();
                } catch (\Throwable $ex) {
                    if ($own && $conn->inTransaction()) $conn->rollBack();
                    $ready = true;
                    return $ready;
                }
            }
            @mkdir(dirname($marker), 0755, true);
            @file_put_contents($marker, date('c'));
        }
        $ready = true;
    } catch (\Throwable $e) {
        $ready = false;
    }
    return $ready;
}

// 记录打开/点击
function mailc_track(string $campaign, string $emailId, string $type): void {
    $type = $type === 'click' ? 'click' : 'open';
    $now = date('Y-m-d H:i:s');

    if (mailc_stats_ensure()) {
        try {
            // 原子累加：并发的打开不会互相覆盖（旧的读-改-写会丢）。
            // 主键是 (campaign, email_id)，直接用复合键先查再插/更新；
            // SQLite≥3.24 也可用 UPSERT，这里用兼容写法统一。
            $hit = Database::query("SELECT first_{$type} FROM mail_stats WHERE campaign=? AND email_id=? LIMIT 1", [$campaign, $emailId]);
            if ($hit) {
                Database::execute(
                    "UPDATE mail_stats SET {$type}_count = {$type}_count + 1,
                        first_{$type} = CASE WHEN first_{$type} = '' THEN ? ELSE first_{$type} END,
                        last_{$type}  = ? WHERE campaign = ? AND email_id = ?",
                    [$now, $now, $campaign, $emailId]
                );
            } else {
                Database::execute(
                    "INSERT INTO mail_stats (campaign, email_id, {$type}_count, first_{$type}, last_{$type})
                     VALUES (?,?,1,?,?)",
                    [$campaign, $emailId, $now, $now]
                );
            }
            return;
        } catch (\Throwable $e) {
            // 高版本 SQLite 若走 UPSERT 路径失败时，回退到先查再插/更新
            try {
                $r = Database::query("SELECT first_{$type} FROM mail_stats WHERE campaign=? AND email_id=? LIMIT 1", [$campaign, $emailId]);
                if ($r) {
                    Database::execute(
                        "UPDATE mail_stats SET {$type}_count = {$type}_count + 1,
                            first_{$type} = CASE WHEN first_{$type} = '' THEN ? ELSE first_{$type} END,
                            last_{$type}  = ? WHERE campaign = ? AND email_id = ?",
                        [$now, $now, $campaign, $emailId]
                    );
                } else {
                    Database::execute(
                        "INSERT INTO mail_stats (campaign, email_id, {$type}_count, first_{$type}, last_{$type})
                         VALUES (?,?,1,?,?)",
                        [$campaign, $emailId, $now, $now]
                    );
                }
                return;
            } catch (\Throwable $e2) {}
        }
    }

    // 回退：JSON（与旧实现一致）
    $stats = json_read(mailc_stats_file());
    $key = $campaign . ':' . $emailId;
    $old = $stats[$key] ?? [];
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
    $opens = 0; $clicks = 0;
    $done = false;

    if (mailc_stats_ensure()) {
        try {
            // 「多少人打开过」——按人去重，不是按次数累加（与旧实现语义一致）
            $rows = Database::query(
                "SELECT SUM(CASE WHEN open_count  > 0 THEN 1 ELSE 0 END) AS opens,
                        SUM(CASE WHEN click_count > 0 THEN 1 ELSE 0 END) AS clicks
                 FROM mail_stats WHERE campaign = ?", [$campaign]);
            if (isset($rows[0])) {
                $opens = (int)($rows[0]['opens'] ?? 0);
                $clicks = (int)($rows[0]['clicks'] ?? 0);
                $done = true;
            }
        } catch (\Throwable $e) {}
    }

    if (!$done) {
        foreach (json_read(mailc_stats_file()) as $k => $v) {
            if (strpos($k, $campaign . ':') !== 0) continue;
            if (!empty($v['open_count'])) $opens++;
            if (!empty($v['click_count'])) $clicks++;
        }
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
