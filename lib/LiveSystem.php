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

/* ═══ 弹幕风控（F2c）：频率 / 重复 / 敏感词 / 慢速模式 / 禁言 ═══ */
function live_risk_file(): string { return DATA_DIR . '/live/risk.json'; }

/** 当前发言者 key：会员 ID 优先，游客用 IP 哈希 */
function live_user_key(): string {
    if (function_exists('member_current')) {
        $m = member_current();
        if ($m) return 'm:' . ($m['id'] ?? '');
    }
    return 'ip:' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/**
 * 发言前风控检查。返回 null 放行，否则返回错误文案。
 * 四道闸：禁言名单 → 敏感词 → 频率（房间慢速模式优先，默认 3s）→ 重复内容
 */
function live_risk_check(string $roomId, string $text, ?array $room = null): ?string {
    $settings = live_settings();
    $key = live_user_key();

    // 1. 禁言名单（会员 ID 或 IP 哈希，每行一个）
    $muted = array_filter(array_map('trim', explode("\n", (string)($settings['muted'] ?? ''))));
    $rawKey = $key;
    if (str_starts_with($key, 'm:')) $rawKey = substr($key, 2);
    foreach ($muted as $m) {
        if ($m !== '' && ($m === $rawKey || $m === $key)) return '你已被禁言，无法发言';
    }

    // 2. 敏感词（每行一个，命中即拒）
    $banned = array_filter(array_map('trim', explode("\n", (string)($settings['banned_words'] ?? ''))));
    foreach ($banned as $w) {
        if ($w !== '' && mb_stripos($text, $w) !== false) return '消息包含不允许的内容';
    }

    // 3+4. 频率与重复（按房间+发言人）
    $room = $room ?? live_room($roomId);
    $minInterval = max(1, (int)($room['slow_mode'] ?? 3));   // 慢速模式秒数，默认 3s
    $risk = json_read(live_risk_file());
    $last = $risk[$roomId][$key] ?? null;
    $now = time();
    if ($last) {
        if ($now - (int)$last['ts'] < $minInterval) return '发言太快了，喝口水再来（' . $minInterval . 's/条）';
        if (($last['text'] ?? '') === $text) return '不要重复发送相同内容';
    }
    $risk[$roomId][$key] = ['ts' => $now, 'text' => $text];
    // 控制文件体积：每房间只留最近 100 个发言人
    if (count((array)$risk[$roomId]) > 100) $risk[$roomId] = array_slice($risk[$roomId], -100, null, true);
    json_write(live_risk_file(), $risk);
    return null;
}

/* ═══ 点赞（F2b）：轻量计数 + 每会话限速 ═══ */
function live_likes_file(): string { return DATA_DIR . '/live/likes.json'; }

function live_likes(string $roomId): int {
    $all = json_read(live_likes_file());
    return (int)($all[$roomId]['count'] ?? 0);
}

/** 点赞。每会话每分钟最多 60 次（连击上限），返回最新总数 */
function live_like(string $roomId): array {
    $key = live_user_key();
    $all = json_read(live_likes_file());
    $all[$roomId] = $all[$roomId] ?? ['count' => 0, 'hits' => []];
    $all[$roomId]['hits'] = (array)($all[$roomId]['hits'] ?? []);
    $now = time();
    $hits = array_filter((array)($all[$roomId]['hits'][$key] ?? []), fn($ts) => $now - $ts < 60);
    if (count($hits) >= 60) return ['ok' => false, 'count' => (int)$all[$roomId]['count']];
    $hits[] = $now;
    $all[$roomId]['hits'][$key] = array_values($hits);
    $all[$roomId]['count'] = (int)$all[$roomId]['count'] + 1;
    // 控制体积：只留最近 50 个点赞者
    if (count($all[$roomId]['hits']) > 50) $all[$roomId]['hits'] = array_slice($all[$roomId]['hits'], -50, null, true);
    json_write(live_likes_file(), $all);
    return ['ok' => true, 'count' => (int)$all[$roomId]['count']];
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

/* ═══ 直播预约（E3）：未开播时观众留资，开播/开播前提醒 ═══ */
function live_subs_file(): string { return DATA_DIR . '/live/subs.json'; }

/** 预约某场直播。$contact = 会员ID 或邮箱。同一场同一 contact 只记一次。 */
function live_subscribe(string $roomId, string $contact): array {
    $roomId = trim($roomId); $contact = trim($contact);
    if ($roomId === '' || $contact === '') return ['ok' => false, 'error' => '参数缺失'];
    $subs = json_read(live_subs_file());
    $subs[$roomId] = $subs[$roomId] ?? [];
    if (in_array($contact, array_column($subs[$roomId], 'contact'), true)) {
        return ['ok' => true, 'dup' => true];
    }
    $subs[$roomId][] = ['contact' => $contact, 'ts' => time(), 'reminded' => false];
    json_write(live_subs_file(), $subs);
    return ['ok' => true];
}

function live_subs(string $roomId): array {
    $subs = json_read(live_subs_file());
    return array_values((array)($subs[$roomId] ?? []));
}

function live_sub_count(string $roomId): int { return count(live_subs($roomId)); }

/** 开播时通知所有预约者（站内信优先，邮箱走通知渠道） */
function live_notify_subs(string $roomId, string $title): int {
    $subs = live_subs($roomId);
    $n = 0;
    foreach ($subs as $s) {
        $c = (string)$s['contact'];
        if (str_contains($c, '@')) {
            if (function_exists('notify_channels_send')) notify_channels_send('直播开播提醒', "你预约的《{$title}》开播了", '/live?room=' . $roomId);
        } else {
            if (function_exists('inbox_send')) inbox_send($c, '直播开播提醒', "你预约的《{$title}》开播了，点击进入直播间", ['link' => '/live?room=' . $roomId]);
        }
        $n++;
    }
    return $n;
}

function live_status_label(string $s): string {
    $map = ['live' => '直播中', 'upcoming' => '即将开播', 'scheduled' => '已预告', 'replay' => '可回放', 'off' => '未开播'];
    return $map[$s] ?? $s;
}
function live_status_color(string $s): string {
    $map = ['live' => '#dc2626', 'upcoming' => '#d97706', 'scheduled' => '#2563eb', 'replay' => '#16a34a', 'off' => '#9ca3af'];
    return $map[$s] ?? '#6b7280';
}
