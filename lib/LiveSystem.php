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
function live_chat_file(): string { return DATA_DIR . '/live/chat.json'; }
function live_chat(string $roomId, int $limit = 60): array {
    $all = json_read(live_chat_file());
    $msgs = array_values(array_filter($all, fn($m) => $m['room_id'] === $roomId));
    return array_slice($msgs, -$limit);
}
function live_chat_send(string $roomId, string $user, string $text): array {
    $all = json_read(live_chat_file());
    $msg = [
        'id' => 'cm_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 5),
        'room_id' => $roomId,
        'user' => $user,
        'text' => mb_substr($text, 0, 300),
        'time' => date('H:i:s'),
    ];
    $all[] = $msg;
    // 只保留最近 300 条
    json_write(live_chat_file(), array_slice($all, -300));
    return $msg;
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
