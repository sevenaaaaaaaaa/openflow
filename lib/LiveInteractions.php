<?php
/**
 * LiveInteractions — 直播互动与成交（对标并超越国内私域直播的运营能力）
 *
 *   抽奖 Giveaway      ：主播发起 → 观众一键参与 → 开奖（可复核随机种子）
 *   秒杀 FlashSale     ：限时限量库存，原子扣减，抢完即止（防超卖）
 *   成交看板 SalesBoard：本场实时成交（订单/金额/客单/爆品）+ 在线/点赞/弹幕
 *   回放切片 Clips     ：章节 + 弹幕高峰 → 可分享的时间点片段
 *   连麦 Guests        ：嘉宾申请/审批/上麦状态（媒体传输由流媒体服务承载，这里是运营层）
 *
 * 只读/幂等优先；秒杀用 Database 原子更新防超卖。均按 room_id 隔离。
 */

function li_file(): string { return DATA_DIR . '/live/interactions.json'; }
function li_all(): array { $d = json_read(li_file()); return is_array($d) ? $d : []; }
function li_save(array $d): void { json_write(li_file(), $d); }

/* ── 抽奖 ── */

function li_giveaways(string $roomId): array { $d = li_all(); return (array)($d[$roomId]['giveaways'] ?? []); }

function li_giveaway_create(string $roomId, string $title, string $prize, int $count = 1, int $durationMin = 5): array {
    if ($roomId === '' || trim($title) === '') return ['ok' => false, 'error' => '标题不能为空'];
    $d = li_all();
    $g = [
        'id' => 'gw_' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 4),
        'title' => mb_substr(trim($title), 0, 60), 'prize' => mb_substr(trim($prize), 0, 60),
        'count' => max(1, min(50, $count)), 'status' => 'open',
        'ends_at' => date('Y-m-d H:i:s', time() + max(1, $durationMin) * 60),
        'entries' => [], 'winners' => [], 'seed' => bin2hex(random_bytes(8)),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $d[$roomId]['giveaways'][] = $g;
    li_save($d);
    return ['ok' => true, 'giveaway' => $g];
}

function li_giveaway_current(string $roomId): ?array {
    $list = li_giveaways($roomId);
    for ($i = count($list) - 1; $i >= 0; $i--) if (($list[$i]['status'] ?? '') === 'open') return $list[$i];
    return $list ? $list[count($list) - 1] : null;
}

function li_giveaway_enter(string $roomId, string $giveawayId, string $userId, string $name = ''): array {
    if ($userId === '') return ['ok' => false, 'error' => '请先登录'];
    $d = li_all();
    foreach (($d[$roomId]['giveaways'] ?? []) as $i => $g) {
        if (($g['id'] ?? '') !== $giveawayId) continue;
        if (($g['status'] ?? '') !== 'open') return ['ok' => false, 'error' => '抽奖已结束'];
        if (strtotime((string)($g['ends_at'] ?? '')) < time()) return ['ok' => false, 'error' => '抽奖已截止'];
        foreach ((array)$g['entries'] as $e) if (($e['uid'] ?? '') === $userId) return ['ok' => true, 'dup' => true, 'count' => count($g['entries'])];
        $d[$roomId]['giveaways'][$i]['entries'][] = ['uid' => $userId, 'name' => mb_substr($name, 0, 30), 'at' => date('H:i:s')];
        li_save($d);
        return ['ok' => true, 'dup' => false, 'count' => count($d[$roomId]['giveaways'][$i]['entries'])];
    }
    return ['ok' => false, 'error' => '抽奖不存在'];
}

/** 开奖：用固定种子做可复核的随机（同种子同结果，防暗箱质疑） */
function li_giveaway_draw(string $roomId, string $giveawayId): array {
    $d = li_all();
    foreach (($d[$roomId]['giveaways'] ?? []) as $i => $g) {
        if (($g['id'] ?? '') !== $giveawayId) continue;
        if (($g['status'] ?? '') !== 'open') return ['ok' => false, 'error' => '已开奖'];
        $entries = (array)$g['entries'];
        if (!$entries) return ['ok' => false, 'error' => '无人参与'];
        $n = min((int)$g['count'], count($entries));
        // Fisher–Yates with mt_srand(seed)
        mt_srand(crc32($g['seed'] . '|' . count($entries)));
        $idx = array_keys($entries);
        for ($k = count($idx) - 1; $k > 0; $k--) { $j = mt_rand(0, $k); [$idx[$k], $idx[$j]] = [$idx[$j], $idx[$k]]; }
        $winners = [];
        foreach (array_slice($idx, 0, $n) as $p) $winners[] = ['uid' => $entries[$p]['uid'], 'name' => $entries[$p]['name']];
        $d[$roomId]['giveaways'][$i]['winners'] = $winners;
        $d[$roomId]['giveaways'][$i]['status'] = 'drawn';
        $d[$roomId]['giveaways'][$i]['drawn_at'] = date('Y-m-d H:i:s');
        li_save($d);
        // 通知中奖者
        try {
            if (!function_exists('inbox_send')) require_once __DIR__ . '/MessageSystem.php';
            if (function_exists('inbox_send')) {
                foreach ($winners as $w) if (!empty($w['uid'])) inbox_send($w['uid'], '🎉 恭喜中奖：' . $g['title'], '你在直播抽奖中赢得：' . ($g['prize'] ?: $g['title']));
            }
        } catch (\Throwable $e) {}
        return ['ok' => true, 'winners' => $winners, 'seed' => $g['seed']];
    }
    return ['ok' => false, 'error' => '抽奖不存在'];
}

/* ── 秒杀（原子扣减防超卖）── */

function li_flash_ensure(): bool {
    try {
        if (!class_exists('Database')) { $p = __DIR__ . '/Database.php'; if (is_file($p)) require_once $p; }
        if (!class_exists('Database')) return false;
        Database::execute("CREATE TABLE IF NOT EXISTS live_flash (room_id TEXT PRIMARY KEY, product TEXT, price REAL, stock INTEGER, sold INTEGER, ends_at TEXT, updated_at TEXT)");
        return true;
    } catch (\Throwable $e) { return false; }
}

function li_flash_set(string $roomId, string $product, float $price, int $stock, int $durationMin = 10): array {
    if ($roomId === '' || $product === '' || $stock <= 0) return ['ok' => false, 'error' => '参数不完整'];
    if (!li_flash_ensure()) return ['ok' => false, 'error' => '存储不可用'];
    Database::execute("INSERT OR REPLACE INTO live_flash (room_id, product, price, stock, sold, ends_at, updated_at) VALUES (?,?,?,?,0,?,?)",
        [$roomId, mb_substr($product, 0, 60), max(0, $price), $stock, date('Y-m-d H:i:s', time() + max(1, $durationMin) * 60), date('Y-m-d H:i:s')]);
    return ['ok' => true];
}

function li_flash(string $roomId): ?array {
    if (!li_flash_ensure()) return null;
    $rows = Database::query("SELECT * FROM live_flash WHERE room_id = ? LIMIT 1", [$roomId]);
    if (!$rows) return null;
    $f = $rows[0];
    $f['remaining'] = max(0, (int)$f['stock'] - (int)$f['sold']);
    $f['active'] = strtotime((string)$f['ends_at']) >= time() && $f['remaining'] > 0;
    return $f;
}

function li_flash_clear(string $roomId): void { if (li_flash_ensure()) Database::execute("DELETE FROM live_flash WHERE room_id = ?", [$roomId]); }

/** 抢购：原子扣 1，成功返回订单引用（待支付） */
function li_flash_claim(string $roomId, string $userId, string $name = ''): array {
    if ($userId === '') return ['ok' => false, 'error' => '请先登录'];
    $f = li_flash($roomId);
    if (!$f) return ['ok' => false, 'error' => '本场无秒杀'];
    if (strtotime((string)$f['ends_at']) < time()) return ['ok' => false, 'error' => '秒杀已结束'];
    // 原子：仅当 sold<stock 时 +1
    $n = Database::execute("UPDATE live_flash SET sold = sold + 1, updated_at = ? WHERE room_id = ? AND sold < stock", [date('Y-m-d H:i:s'), $roomId]);
    if ($n < 1) return ['ok' => false, 'error' => '已抢完', 'sold_out' => true];
    $ref = 'fl_' . $roomId . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $left = max(0, (int)$f['stock'] - (int)$f['sold'] - 1);
    return ['ok' => true, 'ref' => $ref, 'product' => (string)$f['product'], 'price' => (float)$f['price'], 'remaining' => $left];
}

/* ── 实时成交看板 ── */

function li_sales_board(string $roomId, int $windowMin = 0): array {
    if (!function_exists('shop_all_orders')) require_once __DIR__ . '/ShopSystem.php';
    $from = $windowMin > 0 ? time() - $windowMin * 60 : 0;
    $revenue = 0.0; $orders = 0; $buyers = []; $byProduct = [];
    try {
        foreach (shop_all_orders() as $o) {
            if (($o['source'] ?? '') !== 'live:' . $roomId) continue;
            if (($o['status'] ?? '') !== 'paid') continue;
            $ts = strtotime((string)($o['paid_at'] ?? $o['created_at'] ?? ''));
            if ($from > 0 && $ts < $from) continue;
            $amt = (float)($o['amount'] ?? 0);
            $revenue += $amt; $orders++;
            $buyers[(string)($o['member_id'] ?? '')] = true;
            $p = (string)($o['course_title'] ?? $o['goods_title'] ?? '商品');
            $byProduct[$p] = ($byProduct[$p] ?? 0) + 1;
        }
    } catch (\Throwable $e) {}
    arsort($byProduct);
    $viewers = 0; try { if (function_exists('live_view_stats')) $viewers = (int)(live_view_stats($roomId)['online'] ?? 0); } catch (\Throwable $e) {}
    $likes = function_exists('live_likes_total') ? live_likes_total($roomId) : 0;
    return [
        'revenue' => round($revenue, 2), 'orders' => $orders,
        'aov' => $orders > 0 ? round($revenue / $orders, 2) : 0,
        'buyers' => count($buyers), 'viewers' => $viewers, 'likes' => $likes,
        'top_product' => $byProduct ? array_key_first($byProduct) : '',
        'conv' => $viewers > 0 ? round($orders / $viewers * 100, 2) : 0,
    ];
}

/* ── 回放切片（章节 + 弹幕高峰）── */

function li_clips(array $room): array {
    $roomId = (string)($room['id'] ?? '');
    $chapters = !empty($room['chapters']) && is_array($room['chapters']) ? $room['chapters'] : ($roomId && function_exists('live_auto_chapters') ? live_auto_chapters($room) : []);
    $clips = [];
    $toSec = function (string $t): int { $p = array_map('intval', explode(':', $t)); return count($p) === 2 ? $p[0] * 60 + $p[1] : (count($p) === 3 ? $p[0] * 3600 + $p[1] * 60 + $p[2] : 0); };
    foreach ($chapters as $c) {
        $s = $toSec((string)($c['t'] ?? '0:0'));
        $clips[] = ['start' => $s, 'end' => $s + 300, 'title' => (string)($c['title'] ?? '片段'), 'kind' => 'chapter'];
    }
    // 弹幕高峰：按分钟聚合，取高于均值的分钟作为"高能片段"
    try {
        if ($roomId && function_exists('live_chat')) {
            $msgs = live_chat($roomId, 500);
            $bucket = [];
            foreach ($msgs as $m) {
                $t = (string)($m['time'] ?? '');
                if ($t === '') continue;
                $sec = $toSec($t);
                $bucket[intdiv($sec, 60)] = ($bucket[intdiv($sec, 60)] ?? 0) + 1;
            }
            if (count($bucket) >= 3) {
                $avg = array_sum($bucket) / count($bucket);
                foreach ($bucket as $min => $n) {
                    if ($n >= max(3, $avg * 1.6) && count($clips) < 10) $clips[] = ['start' => $min * 60 - 30, 'end' => $min * 60 + 90, 'title' => '高能片段', 'kind' => 'peak'];
                }
            }
        }
    } catch (\Throwable $e) {}
    usort($clips, fn($a, $b) => $a['start'] <=> $b['start']);
    foreach ($clips as &$c) $c['url'] = '/live?room=' . urlencode($roomId) . '&t=' . max(0, $c['start']);
    unset($c);
    return $clips;
}

/* ── 连麦管理（运营层：申请/审批/上麦）── */

function li_guests(string $roomId): array { $d = li_all(); return (array)($d[$roomId]['guests'] ?? []); }

function li_guest_request(string $roomId, string $userId, string $name = ''): array {
    if ($userId === '') return ['ok' => false, 'error' => '请先登录'];
    $d = li_all();
    foreach (($d[$roomId]['guests'] ?? []) as $g) if (($g['uid'] ?? '') === $userId) return ['ok' => true, 'dup' => true, 'status' => $g['status']];
    $d[$roomId]['guests'][] = ['id' => 'g_' . substr(bin2hex(random_bytes(5)), 0, 8), 'uid' => $userId, 'name' => mb_substr($name, 0, 30), 'status' => 'requested', 'at' => date('H:i:s')];
    li_save($d);
    return ['ok' => true, 'dup' => false];
}

function li_guest_set(string $roomId, string $guestId, string $status): bool {
    if (!in_array($status, ['requested', 'approved', 'onair', 'removed'], true)) return false;
    $d = li_all(); $hit = false;
    foreach (($d[$roomId]['guests'] ?? []) as $i => $g) if (($g['id'] ?? '') === $guestId) { $d[$roomId]['guests'][$i]['status'] = $status; $hit = true; break; }
    if ($hit) li_save($d);
    return $hit;
}
