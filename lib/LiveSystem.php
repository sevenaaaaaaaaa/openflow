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
        'page_desc' => '内容、答疑与新品发布 · 支持沉浸竖屏观看、弹幕互动与直播带货，开播可预约提醒',
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
    // 风控迁 SQLite（live_risk 表），消除 risk.json 整文件写回的并发覆盖丢状态
    try {
        require_once __DIR__ . '/Database.php';
        Database::execute("CREATE TABLE IF NOT EXISTS live_risk (
            room_id TEXT DEFAULT '',
            skey    TEXT DEFAULT '',
            ts      INTEGER DEFAULT 0,
            text    TEXT DEFAULT '',
            PRIMARY KEY (room_id, skey)
        )");
        $rows = Database::query("SELECT ts, text FROM live_risk WHERE room_id = ? AND skey = ?", [$roomId, $key]);
        $last = $rows ? ['ts' => (int)$rows[0]['ts'], 'text' => (string)$rows[0]['text']] : null;
        $now = time();
        if ($last) {
            if ($now - $last['ts'] < $minInterval) return '发言太快了，喝口水再来（' . $minInterval . 's/条）';
            if (($last['text'] ?? '') === $text) return '不要重复发送相同内容';
        }
        Database::execute(
            "INSERT OR REPLACE INTO live_risk (room_id, skey, ts, text) VALUES (?,?,?,?)",
            [$roomId, $key, $now, $text]
        );
        // 体积控制：每房间只留最近 100 个发言人（按 ts 保留最新）
        Database::execute(
            "DELETE FROM live_risk WHERE room_id = ? AND skey NOT IN (SELECT skey FROM live_risk WHERE room_id = ? ORDER BY ts DESC LIMIT 100)",
            [$roomId, $roomId]
        );
        return null;
    } catch (\Throwable $e) {}
    // SQLite 不可用回退原 JSON
    $risk = json_read(live_risk_file());
    $last = $risk[$roomId][$key] ?? null;
    $now = time();
    if ($last) {
        if ($now - (int)$last['ts'] < $minInterval) return '发言太快了，喝口水再来（' . $minInterval . 's/条）';
        if (($last['text'] ?? '') === $text) return '不要重复发送相同内容';
    }
    $risk[$roomId][$key] = ['ts' => $now, 'text' => $text];
    if (count((array)$risk[$roomId]) > 100) $risk[$roomId] = array_slice($risk[$roomId], -100, null, true);
    json_write(live_risk_file(), $risk);
    return null;
}

/* ═══ 点赞（F2b）：轻量计数 + 每会话限速 ═══ */
function live_likes_file(): string { return DATA_DIR . '/live/likes.json'; }

function live_likes(string $roomId): int {
    // 优先 SQLite 聚合（并发安全），不可用回退 JSON
    if (live_likes_ensure()) {
        try {
            require_once __DIR__ . '/Database.php';
            $r = Database::query("SELECT COALESCE(SUM(count),0) AS n FROM live_likes WHERE room_id = ?", [$roomId]);
            return (int)($r[0]['n'] ?? 0);
        } catch (\Throwable $e) {}
    }
    $all = json_read(live_likes_file());
    return (int)($all[$roomId]['count'] ?? 0);
}

/** 点赞。每会话每分钟最多 60 次（连击上限），返回最新总数。
 *  存储迁 SQLite（live_likes 表），消除 likes.json 整文件写回的并发覆盖丢计数——
 *  与 live_chat 同构的 bug：聊天修了，点赞没修，热门直播点赞会静默丢失。 */
function live_likes_ensure(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        require_once __DIR__ . '/Database.php';
        Database::execute("CREATE TABLE IF NOT EXISTS live_likes (
            room_id TEXT DEFAULT '',
            skey    TEXT DEFAULT '',
            hits    TEXT DEFAULT '[]',
            count   INTEGER DEFAULT 0,
            at      TEXT DEFAULT '',
            PRIMARY KEY (room_id, skey)
        )");
        $ready = true;
    } catch (\Throwable $e) { $ready = false; }
    return $ready;
}

function live_like(string $roomId): array {
    $key = live_user_key();
    if (live_likes_ensure()) {
        try {
            require_once __DIR__ . '/Database.php';
            $row = Database::query("SELECT hits, count FROM live_likes WHERE room_id = ? AND skey = ?", [$roomId, $key]);
            $hits = $row ? (array)(json_decode((string)$row[0]['hits'], true) ?: []) : [];
            $count = $row ? (int)$row[0]['count'] : 0;
            $now = time();
            $hits = array_values(array_filter($hits, fn($ts) => $now - (int)$ts < 60));
            if (count($hits) >= 60) return ['ok' => false, 'count' => $count];
            $hits[] = $now;
            $count++;
            Database::execute(
                "INSERT OR REPLACE INTO live_likes (room_id, skey, hits, count, at) VALUES (?,?,?,?,?)",
                [$roomId, $key, json_encode(array_slice($hits, -60)), $count, date('Y-m-d H:i:s')]
            );
            // 总数 = 各会话 count 之和（单查询聚合，避免读全量）
            $total = Database::query("SELECT COALESCE(SUM(count),0) AS n FROM live_likes WHERE room_id = ?", [$roomId]);
            return ['ok' => true, 'count' => (int)($total[0]['n'] ?? $count)];
        } catch (\Throwable $e) {}
    }
    // SQLite 不可用回退原 JSON（语义一致）
    $all = json_read(live_likes_file());
    $all[$roomId] = $all[$roomId] ?? ['count' => 0, 'hits' => []];
    $all[$roomId]['hits'] = (array)($all[$roomId]['hits'] ?? []);
    $now = time();
    $hits = array_filter((array)($all[$roomId]['hits'][$key] ?? []), fn($ts) => $now - $ts < 60);
    if (count($hits) >= 60) return ['ok' => false, 'count' => (int)$all[$roomId]['count']];
    $hits[] = $now;
    $all[$roomId]['hits'][$key] = array_values($hits);
    $all[$roomId]['count'] = (int)$all[$roomId]['count'] + 1;
    if (count($all[$roomId]['hits']) > 50) $all[$roomId]['hits'] = array_slice($all[$roomId]['hits'], -50, null, true);
    json_write(live_likes_file(), $all);
    return ['ok' => true, 'count' => (int)$all[$roomId]['count']];
}

/** 房间点赞总数（SQLite 聚合，后台/前台共用） */
function live_likes_total(string $roomId): int {
    if (live_likes_ensure()) {
        try {
            require_once __DIR__ . '/Database.php';
            $r = Database::query("SELECT COALESCE(SUM(count),0) AS n FROM live_likes WHERE room_id = ?", [$roomId]);
            return (int)($r[0]['n'] ?? 0);
        } catch (\Throwable $e) {}
    }
    $all = json_read(live_likes_file());
    return (int)($all[$roomId]['count'] ?? 0);
}

/* ═══ 观看数据层（第二批：观看人数/时长——"播了一场效果如何"的度量闭环）═══ */

/** 观看心跳表：skey(会话) 维度去重，ts 记录最后活跃，dur 累计观看秒数 */
function live_views_ensure(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        require_once __DIR__ . '/Database.php';
        Database::execute("CREATE TABLE IF NOT EXISTS live_views (
            room_id TEXT DEFAULT '',
            skey    TEXT DEFAULT '',
            first_at TEXT DEFAULT '',
            last_at  TEXT DEFAULT '',
            dur      INTEGER DEFAULT 0,
            PRIMARY KEY (room_id, skey)
        )");
        Database::execute("CREATE INDEX IF NOT EXISTS idx_live_views_room ON live_views(room_id)");
        $ready = true;
    } catch (\Throwable $e) { $ready = false; }
    return $ready;
}

/** 观看心跳：前台每 30s 调一次（LOW_POWER 60s），按会话去重累计观看时长 */
function live_view_ping(string $roomId): void {
    if (!live_views_ensure()) return;
    try {
        require_once __DIR__ . '/Database.php';
        $skey = live_user_key();
        $now = time();
        $rows = Database::query("SELECT first_at, last_at, dur FROM live_views WHERE room_id = ? AND skey = ?", [$roomId, $skey]);
        if ($rows) {
            $last = strtotime((string)$rows[0]['last_at']);
            $gap = max(0, min(120, $now - $last));   // 心跳间隔上限 120s（防切后台狂累计）
            Database::execute(
                "UPDATE live_views SET last_at = ?, dur = dur + ? WHERE room_id = ? AND skey = ?",
                [date('Y-m-d H:i:s', $now), $gap, $roomId, $skey]
            );
        } else {
            Database::execute(
                "INSERT OR REPLACE INTO live_views (room_id, skey, first_at, last_at, dur) VALUES (?,?,?,?,?)",
                [$roomId, $skey, date('Y-m-d H:i:s', $now), date('Y-m-d H:i:s', $now), 0]
            );
        }
    } catch (\Throwable $e) {}
}

/** 房间观看统计：去重人数（累计）/ 当前在线（5分钟内活跃）/ 总观看时长（分钟） */
function live_view_stats(string $roomId): array {
    if (!live_views_ensure()) return ['viewers' => 0, 'online' => 0, 'minutes' => 0];
    try {
        require_once __DIR__ . '/Database.php';
        $viewers = Database::query("SELECT COUNT(*) AS n FROM live_views WHERE room_id = ?", [$roomId]);
        $online  = Database::query("SELECT COUNT(*) AS n FROM live_views WHERE room_id = ? AND last_at >= ?", [$roomId, date('Y-m-d H:i:s', time() - 300)]);
        $dur     = Database::query("SELECT COALESCE(SUM(dur),0) AS s FROM live_views WHERE room_id = ?", [$roomId]);
        return [
            'viewers' => (int)($viewers[0]['n'] ?? 0),
            'online'  => (int)($online[0]['n'] ?? 0),
            'minutes' => round((int)($dur[0]['s'] ?? 0) / 60, 1),
        ];
    } catch (\Throwable $e) {}
    return ['viewers' => 0, 'online' => 0, 'minutes' => 0];
}

/** 直播间来源标记：直播间内下单时把房间号写进订单 source（live:{roomId}） */
function live_order_source(): string {
    $room = (string)($_COOKIE['of_live_room'] ?? '');
    return $room !== '' ? 'live:' . $room : '';
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
    // 只在 SQLite 可用时清；JSON 路径不做（回退场景体量小可接受）
    if (live_chat_ensure()) {
        try {
            require_once __DIR__ . '/Database.php';
            // 留最近 keep 条（每房间），按 id 倒序保留
            $rooms = Database::query("SELECT DISTINCT room_id FROM live_chat");
            foreach ($rooms as $r) {
                $rid = (string)$r['room_id'];
                Database::execute(
                    "DELETE FROM live_chat WHERE room_id = ? AND id NOT IN (SELECT id FROM live_chat WHERE room_id = ? ORDER BY id DESC LIMIT ?)",
                    [$rid, $rid, $keep]
                );
            }
        } catch (\Throwable $e) {}
    }
}

/* ═══ 弹幕管理（第二批：删除/禁言快捷——后台看得见也管得了）═══ */

/** 删除一条弹幕（后台管理用；SQLite + JSON 双路） */
function live_chat_delete(string $msgId): bool {
    if (live_chat_ensure()) {
        try {
            require_once __DIR__ . '/Database.php';
            Database::execute("DELETE FROM live_chat WHERE msg_id = ?", [$msgId]);
            return true;
        } catch (\Throwable $e) {}
    }
    $all = json_read(live_chat_file());
    $filtered = array_values(array_filter($all, fn($m) => ($m['id'] ?? '') !== $msgId));
    if (count($filtered) === count($all)) return false;
    json_write(live_chat_file(), $filtered);
    return true;
}

/** 禁言一个用户（把 user 标识追加进 muted 名单，立即生效于后续发言） */
function live_mute_user(string $user, string $reason = ''): bool {
    $user = trim($user);
    if ($user === '') return false;
    $settings = json_read(DATA_DIR . '/live/settings.json');
    if (!is_array($settings)) $settings = [];
    $muted = array_filter(array_map('trim', explode("\n", (string)($settings['muted'] ?? ''))));
    if (in_array($user, $muted, true)) return true;   // 已禁言
    $muted[] = $user;
    $settings['muted'] = implode("\n", array_values($muted));
    json_write(DATA_DIR . '/live/settings.json', $settings);
    if ($reason !== '') {
        @mkdir(DATA_DIR . '/live', 0775, true);
        @file_put_contents(DATA_DIR . '/live/mute-log.json', json_encode([['user'=>$user,'reason'=>$reason,'at'=>date('c')]], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }
    return true;
}

/** 取消禁言 */
function live_unmute_user(string $user): bool {
    $user = trim($user);
    if ($user === '') return false;
    $settings = json_read(DATA_DIR . '/live/settings.json');
    if (!is_array($settings)) $settings = [];
    $muted = array_filter(array_map('trim', explode("\n", (string)($settings['muted'] ?? ''))));
    $muted = array_values(array_filter($muted, fn($m) => $m !== $user));
    $settings['muted'] = implode("\n", $muted);
    json_write(DATA_DIR . '/live/settings.json', $settings);
    return true;
}

/** 当前禁言名单 */
function live_muted_list(): array {
    $settings = json_read(DATA_DIR . '/live/settings.json');
    return array_filter(array_map('trim', explode("\n", (string)($settings['muted'] ?? ''))));
}

/** 某房间的弹幕列表（后台管理用；含 msg_id 供删除按钮） */
function live_chat_admin(string $roomId, int $limit = 50): array {
    $rows = live_chat($roomId, $limit);
    return array_reverse($rows);   // 最新在前
}

/* ═══ 回放自动转章节（第二批：从弹幕/推品记录提取时间轴，回放页可点击跳转）═══ */

/**
 * 从房间弹幕+推品历史提取章节时间轴。
 * 策略：推品弹窗时的时间点 → 章节（主播推品=内容转折点）；每隔 10 分钟的弹幕密集点=讨论段。
 * 结果存 room['chapters'] = [{t:'00:00', title:'开场'}, ...]
 */
function live_auto_chapters(array $room): array {
    $roomId = (string)($room['id'] ?? '');
    if ($roomId === '') return [];
    // 已生成过且未被重置 → 直接用
    if (!empty($room['chapters']) && is_array($room['chapters'])) return $room['chapters'];

    $chapters = [];
    // 1. 推品弹窗历史 → 章节（推品=内容关键节点；相邻推品间隔按 5 分钟估）
    $products = array_values(array_filter((array)($room['products'] ?? [])));
    if ($products) {
        foreach ($products as $i => $line) {
            $t = trim(explode('|', $line)[0] ?? '');
            if ($t === '') continue;
            $offsetSec = $i * 300;   // 每 5 分钟一个推品节点
            $chapters[] = ['t' => sprintf('%02d:%02d', (int)($offsetSec / 60), $offsetSec % 60), 'title' => '推品：' . mb_substr($t, 0, 20)];
        }
    }

    // 2. 弹幕密集点 → 互动章节（用 live_chat 数据，每 10 分钟取一个点）
    if (live_chat_ensure()) {
        try {
            require_once __DIR__ . '/Database.php';
            $rows = Database::query("SELECT at, COUNT(*) c FROM live_chat WHERE room_id = ? GROUP BY substr(at,1,16) ORDER BY at", [$roomId]);
            $byMinute = [];
            foreach ($rows as $r) {
                $min = (string)$r['at'];
                $ts = strtotime($min);
                if ($ts > 0) $byMinute[$ts] = (int)$r['c'];
            }
            if ($byMinute) {
                $firstTs = min(array_keys($byMinute));
                ksort($byMinute);
                $bucket = [];   // 10 分钟桶
                foreach ($byMinute as $ts => $cnt) {
                    $bucketKey = (int)floor(($ts - min(array_keys($byMinute))) / 600);
                    $bucket[$bucketKey = $bucketKey ?? 0] = ($bucket[$bucketKey] ?? 0) + $cnt;
                }
                // 取弹幕量 Top3 的桶 → 章节
                arsort($bucket);
                $top = array_slice($bucket, 0, 3, true);
                ksort($top);
                foreach ($top as $bucketIdx => $cnt) {
                    $offsetSec = $bucketIdx * 600;
                    $chapters[] = ['t' => sprintf('%02d:%02d', (int)($offsetSec / 60), $offsetSec % 60), 'title' => '讨论热点（' . $cnt . ' 条弹幕）'];
                }
            }
        } catch (\Throwable $e) {}
    }

    // 去重（同 t 只留一条）+ 按时间排序
    $seen = []; $out = [];
    foreach ($chapters as $c) {
        $t = $c['t'] ?? '';
        if ($t === '' || isset($seen[$t])) continue;
        $seen[$t] = true;
        $out[] = $c;
    }
    usort($out, fn($a, $b) => strcmp($a['t'], $b['t']));
    if (!$out) $out = [['t' => '00:00', 'title' => '完整回放']];
    return $out;
}

/** 保存章节到房间（生成一次后缓存） */
function live_save_chapters(string $roomId, array $chapters): bool {
    $rooms = live_rooms();
    foreach ($rooms as &$r) {
        if (($r['id'] ?? '') === $roomId) { $r['chapters'] = $chapters; break; }
    }
    unset($r);
    live_rooms_save($rooms);
    return true;
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
