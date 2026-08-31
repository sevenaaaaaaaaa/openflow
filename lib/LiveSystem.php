<?php
/**
 * 直播系统 — OBS/RTMP 推流 + 线上直播 + 售卖课程
 *
 * 部署说明（服务器需支持 RTMP/HLS）：
 *  - 推荐用 nginx-rtmp 或 SRS，把推流收流到 rtmp://域名/live/{stream_key}
 *  - 播放地址 hls://域名/live/{stream_key}.m3u8 填入房间 hls_url
 *  - 直播间通过 video 播放 hls；无 RTMP 服务器时可填入 B 站/视频号等第三方直播链接
 */
require_once __DIR__ . '/../admin/config.php';

function live_file(): string { return DATA_DIR . '/live/index.json'; }
function live_settings_file(): string { return DATA_DIR . '/live/settings.json'; }

function live_settings(): array {
    return array_merge([
        'enabled' => true,
        'rtmp_url' => 'rtmp://your-server.com/live',   // OBS 推流地址
        'rtmp_key_prefix' => '',                        // stream key 前缀（如子域）
        'page_title' => 'OpenFlow 直播',
        'page_desc' => '网站增长 / AI 运营 线上直播',
    ], json_read(live_settings_file()));
}

function live_rooms(): array { return json_read(live_file()); }
function live_room(string $id): ?array {
    foreach (live_rooms() as $r) if ($r['id'] === $id) return $r;
    return null;
}
function live_rooms_save(array $rooms): void {
    if (!is_dir(dirname(live_file()))) mkdir(dirname(live_file()), 0755, true);
    json_write(live_file(), $rooms);
}
function live_room_save(array $room): void {
    $rooms = live_rooms();
    $found = false;
    foreach ($rooms as &$r) { if ($r['id'] === $room['id']) { $r = $room; $found = true; break; } }
    unset($r);
    if (!$found) $rooms[] = $room;
    live_rooms_save($rooms);
}

// 生成推流密钥
function live_gen_key(): string {
    return substr(bin2hex(random_bytes(8)), 0, 16);
}

// 消息（聊天）
//
// 【为什么迁 SQLite】直播聊天是**多人同时在发**的场景。老实现是
// 「读全部 300 条 → 追加一条 → 整个写回」，两个观众同时发言时后写的会覆盖先写的，
// **消息就这么静悄悄地丢了**。这不是性能问题（300 条很小），是正确性问题——
// 而直播恰恰是最不能丢消息的场景。改成一行 INSERT 之后，并发由 SQLite 保证。
// 老 chat.json 首次访问自动导入，原文件保留作回滚备份；SQLite 不可用时回退原实现。
function live_chat_file(): string { return DATA_DIR . '/live/chat.json'; }

function live_chat_ensure(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        require_once __DIR__ . '/Database.php';
        Database::execute("CREATE TABLE IF NOT EXISTS live_chat (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            msg_id   TEXT DEFAULT '',
            room_id  TEXT DEFAULT '',
            user     TEXT DEFAULT '',
            text     TEXT DEFAULT '',
            time     TEXT DEFAULT '',
            at       TEXT DEFAULT ''
        )");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_live_chat_room ON live_chat(room_id, id)");

        $marker = DATA_DIR . '/live/.chat_migrated';
        if (!is_file($marker)) {
            $legacy = json_read(live_chat_file());
            if (is_array($legacy) && $legacy) {
                $conn = Database::conn();
                $own = !$conn->inTransaction();
                if ($own) $conn->beginTransaction();
                try {
                    $st = $conn->prepare("INSERT INTO live_chat (msg_id, room_id, user, text, time, at) VALUES (?,?,?,?,?,?)");
                    foreach ($legacy as $m) {
                        if (!is_array($m)) continue;
                        $st->execute([(string)($m['id'] ?? ''), (string)($m['room_id'] ?? ''),
                                      (string)($m['user'] ?? ''), (string)($m['text'] ?? ''),
                                      (string)($m['time'] ?? ''), date('Y-m-d H:i:s')]);
                    }
                    if ($own) $conn->commit();
                } catch (\Throwable $e) {
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

function live_chat(string $roomId, int $limit = 60): array {
    $limit = max(1, min(500, $limit));
    if (live_chat_ensure()) {
        try {
            $rows = Database::query(
                "SELECT msg_id, room_id, user, text, time FROM live_chat
                 WHERE room_id = ? ORDER BY id DESC LIMIT {$limit}", [$roomId]);
            $out = [];
            foreach (array_reverse($rows) as $r) {
                $out[] = ['id' => $r['msg_id'], 'room_id' => $r['room_id'],
                          'user' => $r['user'], 'text' => $r['text'], 'time' => $r['time']];
            }
            return $out;
        } catch (\Throwable $e) {}
    }
    $all = json_read(live_chat_file());
    $msgs = array_values(array_filter($all, fn($m) => ($m['room_id'] ?? '') === $roomId));
    return array_slice($msgs, -$limit);
}

function live_chat_send(string $roomId, string $user, string $text): array {
    $msg = [
        'id' => 'cm_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 5),
        'room_id' => $roomId,
        'user' => $user,
        'text' => mb_substr($text, 0, 300),
        'time' => date('H:i:s'),
    ];
    if (live_chat_ensure()) {
        try {
            Database::execute(
                "INSERT INTO live_chat (msg_id, room_id, user, text, time, at) VALUES (?,?,?,?,?,?)",
                [$msg['id'], $msg['room_id'], $msg['user'], $msg['text'], $msg['time'], date('Y-m-d H:i:s')]
            );
            live_chat_prune();
            return $msg;
        } catch (\Throwable $e) {}
    }
    // 回退：JSON（与旧实现一致）
    $all = json_read(live_chat_file());
    $all[] = $msg;
    json_write(live_chat_file(), array_slice($all, -300));
    return $msg;
}

/** 每个房间只留最近 500 条，抽样触发（不必每条消息都清一次）。 */
function live_chat_prune(int $keep = 500): void {
    if (random_int(1, 50) !== 1) return;
    try {
        Database::execute(
            "DELETE FROM live_chat WHERE id NOT IN (
                SELECT id FROM live_chat AS c2 WHERE c2.room_id = live_chat.room_id
                ORDER BY id DESC LIMIT {$keep})"
        );
    } catch (\Throwable $e) {}
}

// 直播状态
function live_status(array $room): string {
    $now = time();
    if (!empty($room['is_live'])) return 'live';
    $start = strtotime(($room['start_at'] ?? '') ?: '');
    $end = strtotime(($room['end_at'] ?? '') ?: '');
    if ($start && $end && $now >= $start && $now <= $end) return 'upcoming';
    if ($start && $now < $start) return 'scheduled';
    if (!empty($room['replay_url'])) return 'replay';
    return 'off';
}

function live_status_label(string $s): string {
    $map = ['live' => '直播中', 'upcoming' => '即将开播', 'scheduled' => '已预告', 'replay' => '可回放', 'off' => '未开播'];
    return $map[$s] ?? $s;
}
function live_status_color(string $s): string {
    $map = ['live' => '#dc2626', 'upcoming' => '#d97706', 'scheduled' => '#2563eb', 'replay' => '#16a34a', 'off' => '#9ca3af'];
    return $map[$s] ?? '#6b7280';
}
