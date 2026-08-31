<?php
/**
 * FrequencyCap — 跨渠道触达频控 + 疲劳度管理
 * 限制每个用户每日/每周各渠道接收上限，避免过度触达
 *
 * 【为什么改成 SQLite】（docs/ROADMAP.md 阶段一）
 * 频控在群发链路里**逐个收件人**调用：判断能不能发一次、发完记一次。
 * 老实现把两万条触达记录整个读进内存扫一遍，再整个写回去——
 * 发 1000 人就是三百万次数组遍历 + 1000 次两万条 JSON 的解析与序列化。
 * 这跟埋点那条是同一个毛病：热路径上做全量读写。
 *
 * 现在：判断 = 两个带索引的 COUNT，记录 = 一行 INSERT。
 * 老 frequency-log.json 首次访问时一次性导入，原文件保留作回滚备份。
 * SQLite 不可用（或隔离测试里没有 Database）时，自动回退到原来的 JSON 实现，
 * 语义完全一致。
 */

require_once __DIR__ . '/Database.php';

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

/**
 * 建表 + 一次性导入老 JSON。返回 false 表示 SQLite 不可用（调用方回退 JSON）。
 */
function freq_ensure(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        Database::execute("CREATE TABLE IF NOT EXISTS frequency_log (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            member_id TEXT DEFAULT '',
            channel   TEXT DEFAULT '',
            label     TEXT DEFAULT '',
            at        TEXT DEFAULT ''
        )");
        // 频控只问「今天几条、本周几条」，查询形状固定是 member+channel+时间
        Database::execute("CREATE INDEX IF NOT EXISTS idx_freq_member_channel ON frequency_log(member_id, channel, at)");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_freq_at ON frequency_log(at)");

        $marker = DATA_DIR . '/.frequency_log_migrated';
        if (!is_file($marker)) {
            $legacy = function_exists('json_read') ? json_read(freq_log_file()) : [];
            if (is_array($legacy) && $legacy) {
                $conn = Database::conn();
                $own = !$conn->inTransaction();
                if ($own) $conn->beginTransaction();
                try {
                    $st = $conn->prepare("INSERT INTO frequency_log (member_id, channel, label, at) VALUES (?,?,?,?)");
                    foreach ($legacy as $e) {
                        if (!is_array($e)) continue;
                        $st->execute([(string)($e['member_id'] ?? ''), (string)($e['channel'] ?? ''),
                                      (string)($e['label'] ?? ''), (string)($e['at'] ?? '')]);
                    }
                    if ($own) $conn->commit();
                } catch (\Throwable $e) {
                    if ($own && $conn->inTransaction()) $conn->rollBack();
                    $ready = true;      // 别卡死群发；下次再试导入
                    return $ready;
                }
            }
            @mkdir(dirname($marker), 0755, true);
            @file_put_contents($marker, date('c'));
        }
        $ready = true;
    } catch (\Throwable $e) {
        $ready = false;                 // 回退 JSON
    }
    return $ready;
}

// 某用户某渠道今天/本周已发送次数
function freq_used(string $memberId, string $channel): array {
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));

    if (freq_ensure()) {
        try {
            // 一次查询同时算出日/周计数（at 存的是 'Y-m-d H:i:s'，按前缀比较即可）
            $rows = Database::query(
                "SELECT
                    SUM(CASE WHEN substr(at,1,10) =  ? THEN 1 ELSE 0 END) AS daily,
                    SUM(CASE WHEN substr(at,1,10) >= ? THEN 1 ELSE 0 END) AS weekly
                 FROM frequency_log WHERE member_id = ? AND channel = ?",
                [$today, $weekStart, $memberId, $channel]
            );
            if (isset($rows[0])) {
                return ['daily' => (int)($rows[0]['daily'] ?? 0), 'weekly' => (int)($rows[0]['weekly'] ?? 0)];
            }
        } catch (\Throwable $e) {}
    }

    // 回退：JSON 全量扫描（与旧实现逐字一致）
    $daily = 0; $weekly = 0;
    foreach (json_read(freq_log_file()) as $e) {
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
    if (freq_ensure()) {
        try {
            Database::execute(
                "INSERT INTO frequency_log (member_id, channel, label, at) VALUES (?,?,?,?)",
                [$memberId, $channel, $label, date('Y-m-d H:i:s')]
            );
            freq_prune();
            return;
        } catch (\Throwable $e) {}
    }
    // 回退：JSON
    $log = json_read(freq_log_file());
    $log[] = ['member_id' => $memberId, 'channel' => $channel, 'label' => $label, 'at' => date('Y-m-d H:i:s')];
    json_write(freq_log_file(), array_slice($log, -20000));
}

/**
 * 清理过期记录。频控只关心「今天」和「本周」，超过 60 天的记录没有任何用途，
 * 留着只会让表和索引白白变大。按时间清（不是按条数），所以不会误删还在窗口里的。
 * 抽样触发：约百分之一的写入才真的跑一次 DELETE，避免每次触达都扫一遍。
 */
function freq_prune(int $keepDays = 60): void {
    if (random_int(1, 100) !== 1) return;
    try {
        Database::execute("DELETE FROM frequency_log WHERE at < ?",
            [date('Y-m-d H:i:s', time() - $keepDays * 86400)]);
    } catch (\Throwable $e) {}
}
