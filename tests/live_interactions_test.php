<?php
/**
 * 直播互动 契约 —— 抽奖 / 秒杀防超卖 / 成交看板 / 回放切片 / 连麦
 *   php tests/live_interactions_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-liveint-' . getmypid());
@mkdir(DATA_DIR . '/live', 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
function inbox_send(string $uid, string $title, string $content): void {}

// 订单存根
$GLOBALS['ORDERS'] = [
    ['id' => 'o1', 'source' => 'live:r1', 'status' => 'paid', 'amount' => 199, 'paid_at' => date('Y-m-d H:i:s'), 'member_id' => 'm1', 'course_title' => '增长课'],
    ['id' => 'o2', 'source' => 'live:r1', 'status' => 'paid', 'amount' => 299, 'paid_at' => date('Y-m-d H:i:s'), 'member_id' => 'm2', 'course_title' => '增长课'],
    ['id' => 'o3', 'source' => 'live:r2', 'status' => 'paid', 'amount' => 999, 'paid_at' => date('Y-m-d H:i:s'), 'member_id' => 'm3', 'course_title' => '别的'],
];
function shop_all_orders(): array { return $GLOBALS['ORDERS']; }
function live_likes_total(string $r): int { return 42; }
function live_view_stats(string $r): array { return ['online' => 100]; }

// 秒杀数据库存根（SQLite 原子语义的最小模拟）
class Database {
    public static array $flash = [];
    public static function execute(string $sql, array $p = []): int {
        if (stripos($sql, 'CREATE TABLE') !== false) return 0;
        if (stripos($sql, 'INSERT OR REPLACE INTO live_flash') !== false) { self::$flash[$p[0]] = ['room_id' => $p[0], 'product' => $p[1], 'price' => $p[2], 'stock' => $p[3], 'sold' => 0, 'ends_at' => $p[4]]; return 1; }
        if (stripos($sql, 'UPDATE live_flash SET sold') !== false) {
            $rid = $p[1];
            if (isset(self::$flash[$rid]) && self::$flash[$rid]['sold'] < self::$flash[$rid]['stock']) { self::$flash[$rid]['sold']++; return 1; }
            return 0;
        }
        if (stripos($sql, 'DELETE FROM live_flash') !== false) { unset(self::$flash[$p[0]]); return 1; }
        return 0;
    }
    public static function query(string $sql, array $p = []): array {
        if (stripos($sql, 'SELECT * FROM live_flash') !== false) return isset(self::$flash[$p[0]]) ? [self::$flash[$p[0]]] : [];
        return [];
    }
}

require_once __DIR__ . '/../lib/LiveInteractions.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "直播互动\n";

// 抽奖
$g = li_giveaway_create('r1', '送课', '增长课 1 份', 2, 5);
check('发起抽奖', !empty($g['ok']));
$gid = $g['giveaway']['id'];
$e1 = li_giveaway_enter('r1', $gid, 'u1', '甲');
$e2 = li_giveaway_enter('r1', $gid, 'u1', '甲');
check('参与抽奖', !empty($e1['ok']) && empty($e1['dup']));
check('同一人不能重复参与', !empty($e2['dup']));
li_giveaway_enter('r1', $gid, 'u2', '乙'); li_giveaway_enter('r1', $gid, 'u3', '丙');
$draw = li_giveaway_draw('r1', $gid);
check('开奖产生 2 名中奖', !empty($draw['ok']) && count($draw['winners']) === 2);
check('开奖后可复核种子', !empty($draw['seed']));
check('开奖后不可再开', empty(li_giveaway_draw('r1', $gid)['ok']));
check('当前抽奖为已开奖', (li_giveaway_current('r1')['status'] ?? '') === 'drawn');

// 秒杀：原子扣减防超卖
li_flash_set('r1', '限量礼盒', 99, 2, 10);
$f = li_flash('r1');
check('秒杀上架', !empty($f['active']) && $f['remaining'] === 2);
$c1 = li_flash_claim('r1', 'u1'); $c2 = li_flash_claim('r1', 'u2'); $c3 = li_flash_claim('r1', 'u3');
check('秒杀前两单成功', !empty($c1['ok']) && !empty($c2['ok']));
check('库存耗尽后拒单(防超卖)', empty($c3['ok']) && !empty($c3['sold_out']));
check('剩余归零', li_flash('r1')['remaining'] === 0);
li_flash_clear('r1');
check('下架后无秒杀', li_flash('r1') === null);

// 成交看板（只统计本房间 source=live:r1 的已付订单）
$b = li_sales_board('r1');
check('看板营收/单数正确', $b['revenue'] === 498.0 && $b['orders'] === 2);
check('看板客单/爆品', $b['aov'] === 249.0 && $b['top_product'] === '增长课');
check('看板在线/转化', $b['viewers'] === 100 && $b['conv'] === 2.0);
check('不串其它房间', li_sales_board('r1')['orders'] === 2);

// 回放切片
$room = ['id' => 'r1', 'products' => ['礼盒|/a|99', '课程|/b|199'], 'chapters' => [['t' => '00:00', 'title' => '开场'], ['t' => '05:00', 'title' => '推品']]];
$clips = li_clips($room);
check('章节生成切片', count($clips) >= 2 && strpos($clips[1]['url'], '/live?room=r1&t=') !== false);

// 连麦
$r1 = li_guest_request('r1', 'u9', '嘉宾甲');
check('申请连麦', !empty($r1['ok']) && empty($r1['dup']));
check('重复申请幂等', !empty(li_guest_request('r1', 'u9', '嘉宾甲')['dup']));
$gst = li_guests('r1')[0]['id'];
check('审批通过', li_guest_set('r1', $gst, 'approved'));
check('上麦', li_guest_set('r1', $gst, 'onair'));
check('非法状态被拒', li_guest_set('r1', $gst, 'bogus') === false);

// 结构守卫
$api = file_get_contents(__DIR__ . '/../api/live.php');
check('API 有抽奖/秒杀/连麦入口', strpos($api, 'giveaway_enter') !== false && strpos($api, 'flash_claim') !== false && strpos($api, 'guest_request') !== false);
$lp = file_get_contents(__DIR__ . '/../live.php');
check('前台渲染互动条', strpos($lp, 'renderInteract') !== false);
$al = file_get_contents(__DIR__ . '/../admin/live.php');
check('后台互动控制台', strpos($al, 'li_action') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
