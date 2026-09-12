<?php
/**
 * 对象投影契约 —— 统一「人」解析 / 汇总 / 跨模块时间线
 *   php tests/object_graph_test.php
 *
 * 用最小 stub 隔离外部模块，验证：同一 email 能把 CDP 画像、CRM 线索、会员、
 * 订单、学习、互动合并成一个人 + 一条按时间倒序的时间线。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-og-' . getmypid());
@mkdir(DATA_DIR . '/courses', 0777, true);

function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

// ── stubs（避免拉起真实模块）──
$EMAIL = 'fan@example.com';
function member_get(string $id): ?array { return $id === 'm1' ? ['id' => 'm1', 'name' => '阿福', 'email' => $GLOBALS['EMAIL'], 'phone' => '13800000000'] : null; }
function member_find(string $q): ?array { return mb_strtolower($q) === $GLOBALS['EMAIL'] ? member_get('m1') : null; }
function crm_get(): array {
    return ['leads' => [$GLOBALS['EMAIL'] => [
        'email' => $GLOBALS['EMAIL'], 'name' => '阿福', 'member_id' => 'm1', 'stage' => 'qualified', 'value' => 2000,
        'created_at' => '2026-08-01 10:00:00',
        'follow_ups' => [['content' => '电话沟通了需求', 'time' => '2026-08-05 15:00:00']],
    ]]];
}
function crm_get_customers(): array { return []; }
function shop_all_orders(): array {
    return [
        ['id' => 'o1', 'member_id' => 'm1', 'email' => $GLOBALS['EMAIL'], 'status' => 'paid', 'amount' => 299, 'course_title' => '增长课', 'paid_at' => '2026-08-10 09:00:00'],
        ['id' => 'o2', 'member_id' => 'm1', 'email' => $GLOBALS['EMAIL'], 'status' => 'refunded', 'amount' => 99, 'course_title' => '进阶课', 'created_at' => '2026-08-12 09:00:00'],
    ];
}
function progress_all(): array { return ['m1' => ['c1' => ['l1' => ['done' => true, 'last_at' => '2026-08-11 20:00:00'], 'l2' => ['done' => false, 'last_at' => '2026-08-11 20:30:00']]]]; }
function sub_get_member(string $id): ?array { return $id === 'm1' ? ['status' => 'active', 'expires_at' => '2026-12-31'] : null; }
class CdpSystem {
    public static function allProfiles(): array {
        return [['visitor_id' => 'v1', 'properties' => ['email' => $GLOBALS['EMAIL'], 'member_id' => 'm1', 'name' => '阿福'], 'tags' => ['高意向' => 1]]];
    }
    public static function getProfile(string $vid): ?array { return $vid === 'v1' ? self::allProfiles()[0] : null; }
    public static function allEvents(int $limit = 10000): array {
        return [
            ['event' => 'page_view', 'uid' => 'v1', 'member_id' => '', 'props' => [], 'page' => '/pricing', 'created_at' => '2026-08-09 12:00:00'],
            ['event' => 'form_submit', 'uid' => 'v1', 'member_id' => 'm1', 'props' => [], 'page' => '/contact', 'created_at' => '2026-08-09 12:05:00'],
        ];
    }
}
json_write(DATA_DIR . '/courses/index.json', [['id' => 'c1', 'title' => '增长系统课']]);
json_write(DATA_DIR . '/comments.json', [['member_id' => 'm1', 'content' => '讲得好', 'created_at' => '2026-08-11 21:00:00']]);

require_once __DIR__ . '/../lib/ObjectGraph.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else { $fail++; echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}
$GLOBALS['EMAIL'] = $EMAIL;

echo "对象投影\n";

$p = og_person($EMAIL);
check('按 email 解析出人', is_array($p) && in_array($EMAIL, $p['emails'], true));
check('合并到 member_id', in_array('m1', $p['member_ids'], true));
check('合并到 visitor_id', in_array('v1', $p['uids'], true));
check('渠道覆盖 会员/线索/画像', count(array_intersect($p['channels'], ['member', 'lead', 'cdp'])) === 3);
check('未知 id → null', og_person('nobody@nowhere.tld') === null);

$s = og_summary($p);
check('汇总：已付 1 单 ¥299', $s['orders']['paid'] === 1 && (float)$s['orders']['total'] === 299.0);
check('汇总：线索阶段 qualified', ($s['lead']['stage'] ?? '') === 'qualified');
check('汇总：会员/订阅存在', $s['member']['id'] === 'm1' && $s['sub']['status'] === 'active');
check('汇总：课程进度 1/2', count($s['courses']) === 1 && $s['courses'][0]['done'] === 1 && $s['courses'][0]['total'] === 2);

$tl = og_timeline($p, 50);
check('时间线非空', count($tl) > 0);
$types = array_unique(array_column($tl, 'type'));
check('时间线含 行为/交易/线索/跟进/学习/社区', count(array_intersect($types, ['行为', '交易', '线索', '跟进', '学习', '社区'])) === 6, implode(',', $types));
$ts = array_column($tl, 'ts');
$sorted = $ts; rsort($sorted);
check('时间线按时间倒序', $ts === $sorted);
check('学习事件用了课程名', (bool)array_filter($tl, fn($e) => str_contains((string)$e['title'], '增长系统课')));

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
