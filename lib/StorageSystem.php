<?php
/**
 * 存储健康检查 + 性能维护
 * 提供：数据文件大小统计、SQLite 表大小、高风险项识别、清理建议
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/Database.php';

// 扫描数据文件大小
function storage_scan(): array {
    $out = ['json' => [], 'sqlite' => [], 'uploads' => []];
    $scanDir = function (string $dir, array &$acc) use (&$scanDir) {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $dir . '/' . $f;
            if (is_dir($p)) { $scanDir($p, $acc); continue; }
            $acc[$p] = filesize($p);
        }
    };
    $jsonMap = [];
    $scanDir(DATA_DIR, $jsonMap);
    foreach ($jsonMap as $p => $size) {
        if (strpos($p, DATA_DIR . '/db/') === 0) continue;
        $rel = str_replace(DATA_DIR . '/', '', $p);
        $out['json'][] = ['path' => $rel, 'size' => $size];
    }
    usort($out['json'], fn($a, $b) => $b['size'] <=> $a['size']);

    // SQLite 表统计
    try {
        $db = Database::conn();
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        foreach ($tables as $t) {
            $name = $t['name'];
            $row = $db->query("SELECT COUNT(*) AS c FROM \"{$name}\"")->fetch();
            $out['sqlite'][] = ['table' => $name, 'count' => (int)($row['c'] ?? 0)];
        }
        usort($out['sqlite'], fn($a, $b) => $b['count'] <=> $a['count']);
    } catch (Exception $e) {}

    // uploads
    $upMap = [];
    $scanDir(UPLOAD_DIR, $upMap);
    $total = 0;
    foreach ($upMap as $p => $size) $total += $size;
    $out['uploads'] = ['files' => count($upMap), 'total' => $total];

    return $out;
}

// 判定风险
function storage_risks(array $scan): array {
    $risks = [];
    foreach ($scan['json'] as $j) {
        if ($j['size'] > 1024 * 1024) {
            $risks[] = ['level' => 'warn', 'msg' => "JSON 文件过大：{$j['path']} ({$j['size']}B) — 建议迁移到 SQLite"];
        }
    }
    foreach ($scan['sqlite'] as $t) {
        if ($t['count'] > 50000) {
            $risks[] = ['level' => 'info', 'msg' => "SQLite 表增长较快：{$t['table']} ({$t['count']} 行) — 建议定期归档"];
        }
    }
    if (($scan['uploads']['total'] ?? 0) > 500 * 1024 * 1024) {
        $risks[] = ['level' => 'warn', 'msg' => "素材目录较大：{$scan['uploads']['total']}B — 建议接入对象存储"];
    }
    // 缺目录
    foreach (['articles', 'courses', 'consultation', 'live', 'messages', 'knowledge', 'membership', 'db'] as $d) {
        if (!is_dir(DATA_DIR . '/' . $d)) {
            $risks[] = ['level' => 'warn', 'msg' => "数据目录缺失：data/{$d} — 相关功能可能异常"];
        }
    }
    return $risks;
}

// 自动维护：清理过期数据（cron 调用）
function storage_maintain(): array {
    $cleaned = [];
    $now = time();

    // 1. 登录日志只留 30 天
    $log = json_read(DATA_DIR . '/members/login-log.json');
    $before = count($log);
    $log = array_values(array_filter($log, fn($l) => $now - strtotime($l['time'] ?? '') < 30 * 86400));
    json_write(DATA_DIR . '/members/login-log.json', $log);
    $cleaned[] = "登录日志清理 " . ($before - count($log)) . " 条";

    // 2. 验证码日志只留 7 天
    $cap = json_read(DATA_DIR . '/members/captcha.json');
    $b2 = count($cap);
    $cap = array_values(array_filter($cap, fn($c) => $now - ($c['time'] ?? 0) < 7 * 86400));
    json_write(DATA_DIR . '/members/captcha.json', $cap);
    $cleaned[] = "验证码清理 " . ($b2 - count($cap)) . " 条";

    // 3. 直播聊天只留最近 500 条
    if (file_exists(DATA_DIR . '/live/chat.json')) {
        $chat = json_read(DATA_DIR . '/live/chat.json');
        if (count($chat) > 500) {
            json_write(DATA_DIR . '/live/chat.json', array_slice($chat, -500));
            $cleaned[] = "直播聊天清理至 500 条";
        }
    }

    // 4. 埋点 events 只留 90 天
    try {
        $db = Database::conn();
        $cutoff = date('Y-m-d H:i:s', $now - 90 * 86400);
        $n = Database::execute("DELETE FROM events WHERE created_at < ?", [$cutoff]);
        if ($n) $cleaned[] = "埋点清理 {$n} 条";
        $db->exec("VACUUM");
        $cleaned[] = "SQLite 已 VACUUM";
    } catch (Exception $e) {}

    // 5. 回收站保留 30 天
    $trash = json_read(DATA_DIR . '/trash.json');
    $b3 = count($trash);
    $trash = array_values(array_filter($trash, fn($t) => $now - strtotime($t['deleted_at'] ?? '') < 30 * 86400));
    json_write(DATA_DIR . '/trash.json', $trash);
    $cleaned[] = "回收站清理 " . ($b3 - count($trash)) . " 条";

    return $cleaned;
}

// 格式化大小
function storage_fmt(int $bytes): string {
    if ($bytes >= 1024 * 1024) return round($bytes / 1024 / 1024, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
