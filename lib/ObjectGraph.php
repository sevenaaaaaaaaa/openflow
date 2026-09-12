<?php
/**
 * ObjectGraph — 统一「人」对象投影 + 跨模块时间线
 *
 * 解决的问题：画像在 CDP、线索在 CRM、订单在商城、会员在会员系统、学习在课程系统，
 * 各看各的。这里把同一个人在这些模块里的记录解析、合并成一个对象和一条时间线，
 * 让「这个人」成为一等公民——而不是五个模块里五条互不相识的记录。
 *
 * 解析以 email 为主键（跨模块最通用），member_id / visitor_id 为辅；全部只读、防御式。
 */

function og_truthy(array $set): array { return array_values(array_filter(array_keys($set), fn($k) => $k !== '' && $k !== null)); }

/**
 * 解析「人」：输入 uid / email / member_id / 手机号，返回统一身份键集合。
 * @return array|null ['query','name','phone','emails','member_ids','uids','channels']
 */
function og_person(string $id): ?array {
    $id = trim($id);
    if ($id === '') return null;
    $lower = mb_strtolower($id);

    $emails = []; $memberIds = []; $uids = []; $name = ''; $phone = ''; $channels = []; $found = false;

    if (!function_exists('member_get')) require_once __DIR__ . '/MemberSystem.php';
    if (!function_exists('crm_get')) require_once __DIR__ . '/CrmSystem.php';
    if (!class_exists('CdpSystem')) require_once __DIR__ . '/CdpSystem.php';

    // 1) 会员
    $member = member_get($id);
    if (!$member && function_exists('member_find')) { try { $member = member_find($id); } catch (\Throwable $e) {} }
    if ($member) {
        if (!empty($member['id'])) $memberIds[$member['id']] = true;
        if (!empty($member['email'])) $emails[mb_strtolower($member['email'])] = true;
        if (!empty($member['phone'])) $phone = (string)$member['phone'];
        $name = (string)($member['name'] ?? '');
        $channels['member'] = true; $found = true;
    }

    // 2) 输入本身是 email
    if (mb_strpos($id, '@') !== false) $emails[$lower] = true;

    // 3) CRM 线索 / 客户
    try {
        $crm = crm_get();
        foreach ((array)($crm['leads'] ?? []) as $key => $l) {
            $le = mb_strtolower((string)($l['email'] ?? ''));
            $lm = (string)($l['member_id'] ?? '');
            $hit = ($le !== '' && $le === $lower) || ($lm !== '' && isset($memberIds[$lm])) || (($l['id'] ?? '') === $id);
            if (!$hit) continue;
            if ($le !== '') $emails[$le] = true;
            if ($lm !== '') $memberIds[$lm] = true;
            $name = $name ?: (string)($l['name'] ?? '');
            $phone = $phone ?: (string)($l['phone'] ?? '');
            $channels['lead'] = true; $found = true;
        }
        foreach (crm_get_customers() as $c) {
            $ce = mb_strtolower((string)($c['email'] ?? ''));
            $lk = mb_strtolower((string)($c['lead_key'] ?? ''));
            if (($ce !== '' && $ce === $lower) || ($lk !== '' && $lk === $lower) || (isset($emails[$ce]) && $ce !== '')) {
                if ($ce !== '') $emails[$ce] = true;
                $name = $name ?: (string)($c['name'] ?? '');
                $channels['customer'] = true; $found = true;
            }
        }
    } catch (\Throwable $e) {}

    // 4) CDP 画像（按 visitor_id / email 属性 / member_id 属性）
    try {
        foreach (CdpSystem::allProfiles() as $p) {
            $vid = (string)($p['visitor_id'] ?? $p['id'] ?? '');
            $props = (array)($p['properties'] ?? []);
            $pe = mb_strtolower((string)($props['email'] ?? ($p['email'] ?? '')));
            $pm = (string)($props['member_id'] ?? '');
            $hit = ($vid !== '' && $vid === $id) || ($pe !== '' && isset($emails[$pe])) || ($pm !== '' && isset($memberIds[$pm]));
            if (!$hit) continue;
            if ($vid !== '') $uids[$vid] = true;
            if ($pe !== '') $emails[$pe] = true;
            if ($pm !== '') $memberIds[$pm] = true;
            $channels['cdp'] = true; $found = true;
        }
    } catch (\Throwable $e) {}

    $emails = og_truthy($emails); $memberIds = og_truthy($memberIds); $uids = og_truthy($uids);
    if (!$found) return null;

    return [
        'query' => $id, 'name' => $name, 'phone' => $phone,
        'emails' => $emails, 'member_ids' => $memberIds, 'uids' => $uids,
        'channels' => array_keys($channels),
        'primary_uid' => $uids[0] ?? '',
    ];
}

/** 这个人当前在系统里的全貌（各模块记录 + 汇总数字） */
function og_summary(array $person): array {
    if (!class_exists('CdpSystem')) require_once __DIR__ . '/CdpSystem.php';
    if (!function_exists('shop_all_orders')) require_once __DIR__ . '/ShopSystem.php';
    if (!function_exists('progress_all')) require_once __DIR__ . '/ProgressSystem.php';

    $out = ['profile' => null, 'lead' => null, 'customer' => null, 'member' => null,
            'orders' => ['count' => 0, 'paid' => 0, 'total' => 0.0, 'last' => ''], 'courses' => [], 'sub' => null];

    // CDP 画像
    foreach ($person['uids'] as $uid) { $p = CdpSystem::getProfile($uid); if ($p) { $out['profile'] = $p; break; } }
    if (!$out['profile'] && $person['emails']) {
        foreach (CdpSystem::allProfiles() as $p) {
            $pe = mb_strtolower((string)(($p['properties'] ?? [])['email'] ?? ''));
            if ($pe !== '' && in_array($pe, $person['emails'], true)) { $out['profile'] = $p; break; }
        }
    }

    // CRM 线索
    try {
        $crm = crm_get();
        foreach ((array)($crm['leads'] ?? []) as $key => $l) {
            $le = mb_strtolower((string)($l['email'] ?? ''));
            $lm = (string)($l['member_id'] ?? '');
            if (($le !== '' && in_array($le, $person['emails'], true)) || ($lm !== '' && in_array($lm, $person['member_ids'], true))) { $out['lead'] = $l + ['_key' => $key]; break; }
        }
        foreach (crm_get_customers() as $c) {
            $ce = mb_strtolower((string)($c['email'] ?? '')); $lk = mb_strtolower((string)($c['lead_key'] ?? ''));
            if (($ce !== '' && in_array($ce, $person['emails'], true)) || ($lk !== '' && in_array($lk, $person['emails'], true))) { $out['customer'] = $c; break; }
        }
    } catch (\Throwable $e) {}

    // 会员
    foreach ($person['member_ids'] as $mid) { $m = member_get($mid); if ($m) { $out['member'] = $m; break; } }
    if (!$out['member'] && $person['emails'] && function_exists('member_find')) {
        foreach ($person['emails'] as $em) { try { $m = member_find($em); if ($m) { $out['member'] = $m; break; } } catch (\Throwable $e) {} }
    }

    // 订单
    try {
        foreach (shop_all_orders() as $o) {
            $om = (string)($o['member_id'] ?? '');
            $oe = mb_strtolower((string)($o['email'] ?? ''));
            if (!(in_array($om, $person['member_ids'], true) || ($oe !== '' && in_array($oe, $person['emails'], true)))) continue;
            $out['orders']['count']++;
            $at = (string)($o['paid_at'] ?? $o['created_at'] ?? '');
            if (($o['status'] ?? '') === 'paid') { $out['orders']['paid']++; $out['orders']['total'] += (float)($o['amount'] ?? 0); }
            if ($at > $out['orders']['last']) $out['orders']['last'] = $at;
        }
    } catch (\Throwable $e) {}

    // 课程学习
    try {
        $all = progress_all();
        foreach ($person['member_ids'] as $mid) {
            foreach ((array)($all[$mid] ?? []) as $courseId => $lessons) {
                $done = 0; $total = 0; $lastAt = '';
                foreach ((array)$lessons as $st) { $total++; if (!empty($st['done'])) $done++; if (($st['last_at'] ?? '') > $lastAt) $lastAt = (string)($st['last_at'] ?? ''); }
                $out['courses'][] = ['course_id' => $courseId, 'done' => $done, 'total' => $total, 'last_at' => $lastAt];
            }
        }
    } catch (\Throwable $e) {}

    // 订阅
    try {
        if (!function_exists('sub_get_member')) require_once __DIR__ . '/SubscriptionSystem.php';
        foreach ($person['member_ids'] as $mid) { $s = sub_get_member($mid); if ($s) { $out['sub'] = $s; break; } }
    } catch (\Throwable $e) {}

    return $out;
}

/** 统一时间线：把各模块记录合并成一条按时间倒序的事件流 */
function og_timeline(array $person, int $limit = 80): array {
    if (!class_exists('CdpSystem')) require_once __DIR__ . '/CdpSystem.php';
    if (!function_exists('shop_all_orders')) require_once __DIR__ . '/ShopSystem.php';
    if (!function_exists('progress_all')) require_once __DIR__ . '/ProgressSystem.php';

    $ev = [];
    $push = function (string $at, string $type, string $icon, string $title, string $detail = '', string $link = '') use (&$ev) {
        $ts = strtotime($at) ?: 0;
        if ($ts <= 0) return;
        $ev[] = ['ts' => $ts, 'at' => $at, 'type' => $type, 'icon' => $icon, 'title' => $title, 'detail' => $detail, 'link' => $link];
    };

    // 1) CDP 行为事件
    try {
        foreach (CdpSystem::allEvents(3000) as $e) {
            $uid = (string)($e['uid'] ?? '');
            $mid = (string)($e['member_id'] ?? '');
            $props = is_string($e['props'] ?? null) ? (json_decode($e['props'], true) ?: []) : (array)($e['props'] ?? []);
            $pe = mb_strtolower((string)($props['email'] ?? ''));
            $hit = ($uid !== '' && in_array($uid, $person['uids'], true))
                || ($mid !== '' && in_array($mid, $person['member_ids'], true))
                || ($pe !== '' && in_array($pe, $person['emails'], true));
            if (!$hit) continue;
            $detail = trim((string)($e['page'] ?? ''));
            if ($detail === '' && $props) $detail = mb_substr(json_encode($props, JSON_UNESCAPED_UNICODE), 0, 80);
            $push((string)($e['created_at'] ?? ''), '行为', '👣', (string)($e['event'] ?? 'event'), $detail);
        }
    } catch (\Throwable $e) {}

    // 2) 订单
    try {
        foreach (shop_all_orders() as $o) {
            $om = (string)($o['member_id'] ?? '');
            $oe = mb_strtolower((string)($o['email'] ?? ''));
            if (!(in_array($om, $person['member_ids'], true) || ($oe !== '' && in_array($oe, $person['emails'], true)))) continue;
            $paid = ($o['status'] ?? '') === 'paid';
            $push((string)($o['paid_at'] ?? $o['created_at'] ?? ''), '交易', '💰',
                ($paid ? '支付成功' : '下单') . '：' . (string)($o['course_title'] ?? $o['goods_title'] ?? '订单'),
                '¥' . number_format((float)($o['amount'] ?? 0), 2) . ' · ' . (string)($o['id'] ?? ''),
                '/xmp/orders');
        }
    } catch (\Throwable $e) {}

    // 3) CRM 线索创建 + 跟进
    try {
        $crm = crm_get();
        foreach ((array)($crm['leads'] ?? []) as $l) {
            $le = mb_strtolower((string)($l['email'] ?? ''));
            $lm = (string)($l['member_id'] ?? '');
            if (!(($le !== '' && in_array($le, $person['emails'], true)) || ($lm !== '' && in_array($lm, $person['member_ids'], true)))) continue;
            $push((string)($l['created_at'] ?? ''), '线索', '🎯', '进入线索池', '阶段：' . (string)($l['stage'] ?? 'new'), '/xmp/crm');
            foreach ((array)($l['follow_ups'] ?? []) as $f) {
                $push((string)($f['time'] ?? ''), '跟进', '📞', '跟进记录', (string)($f['content'] ?? ''), '/xmp/crm?tab=pipeline');
            }
        }
    } catch (\Throwable $e) {}

    // 4) 课程学习（每门课取最近一次）
    try {
        $all = progress_all();
        $courses = json_read(DATA_DIR . '/courses/index.json');
        $cname = [];
        foreach ($courses as $c) $cname[(string)($c['id'] ?? '')] = (string)($c['title'] ?? '');
        foreach ($person['member_ids'] as $mid) {
            foreach ((array)($all[$mid] ?? []) as $courseId => $lessons) {
                $done = 0; $total = 0; $lastAt = '';
                foreach ((array)$lessons as $st) { $total++; if (!empty($st['done'])) $done++; if (($st['last_at'] ?? '') > $lastAt) $lastAt = (string)($st['last_at'] ?? ''); }
                $push($lastAt, '学习', '📚', '学习《' . ($cname[$courseId] ?? $courseId) . '》', "{$done}/{$total} 节完成", '/xmp/courses');
            }
        }
    } catch (\Throwable $e) {}

    // 5) 评论
    try {
        foreach ((array)json_read(DATA_DIR . '/comments.json') as $c) {
            $cm = (string)($c['member_id'] ?? '');
            if ($cm === '' || !in_array($cm, $person['member_ids'], true)) continue;
            $push((string)($c['created_at'] ?? ''), '社区', '💬', '发表评论', mb_substr((string)($c['content'] ?? ''), 0, 80));
        }
    } catch (\Throwable $e) {}

    usort($ev, fn($a, $b) => $b['ts'] <=> $a['ts']);
    return array_slice($ev, 0, $limit);
}
