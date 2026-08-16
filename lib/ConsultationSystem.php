<?php
/**
 * 1v1 预约付费咨询系统
 * 咨询师库 → 用户选导师/看课程 → 填报名表(3 个期望时段) → 资格审核 → 付款 → 讲师确认时间 → 线上交付 + 回放
 */
require_once __DIR__ . '/../admin/config.php';

function con_file(): string { return DATA_DIR . '/consultation/index.json'; }
function con_mentors_file(): string { return DATA_DIR . '/consultation/mentors.json'; }
function con_bookings_file(): string { return DATA_DIR . '/consultation/bookings.json'; }

function con_settings(): array {
    return array_merge([
        'enabled' => true,
        'page_title' => '1v1 专家咨询',
        'page_desc' => '与网站增长 / AI 运营专家一对一线上咨询',
        'need_review' => true,        // 报名后是否需要资格审核
        'xfpay_appid' => '',
        'xfpay_secret' => '',
        'xfpay_gateway' => 'https://api.xunhupay.com/payment/do.html',
        'default_price' => 199,       // 默认单次价格
    ], json_read(DATA_DIR . '/consultation/settings.json'));
}

// ─── 咨询师 ───
function con_mentors(): array { return json_read(con_mentors_file()); }
function con_mentor(string $id): ?array {
    foreach (con_mentors() as $m) if ($m['id'] === $id) return $m;
    return null;
}
function con_mentor_save(array $mentor): void {
    $list = con_mentors();
    $found = false;
    foreach ($list as &$m) { if ($m['id'] === $mentor['id']) { $m = $mentor; $found = true; break; } }
    unset($m);
    if (!$found) $list[] = $mentor;
    if (!is_dir(dirname(con_mentors_file()))) mkdir(dirname(con_mentors_file()), 0755, true);
    json_write(con_mentors_file(), $list);
}
function con_mentor_delete(string $id): void {
    json_write(con_mentors_file(), array_values(array_filter(con_mentors(), fn($m) => $m['id'] !== $id)));
}

// ─── 预约单 ───
function con_bookings(): array { return json_read(con_bookings_file()); }
function con_booking(string $id): ?array {
    foreach (con_bookings() as $b) if ($b['id'] === $id) return $b;
    return null;
}
function con_bookings_save(array $bookings): void {
    if (!is_dir(dirname(con_bookings_file()))) mkdir(dirname(con_bookings_file()), 0755, true);
    json_write(con_bookings_file(), $bookings);
}

// 创建预约单（会员报名）
function con_create_booking(array $member, string $mentorId, array $data): array {
    $mentor = con_mentor($mentorId);
    if (!$mentor) return ['ok' => false, 'error' => '咨询师不存在'];
    $settings = con_settings();
    $price = (float)($mentor['price'] ?? $settings['default_price']);

    $bookings = con_bookings();
    // 防止同一会员对同一导师重复报名未完成单
    foreach ($bookings as $b) {
        if ($b['member_id'] === $member['id'] && $b['mentor_id'] === $mentorId &&
            in_array($b['status'], ['pending_review', 'approved', 'paid', 'confirmed'])) {
            return ['ok' => false, 'error' => '你已有进行中的预约，请勿重复提交'];
        }
    }
    $booking = [
        'id' => 'cons_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 5),
        'member_id' => $member['id'],
        'member_name' => $member['name'] ?? '',
        'mentor_id' => $mentorId,
        'mentor_name' => $mentor['name'],
        // 报名资料（资格审核用）
        'company' => trim($data['company'] ?? ''),
        'position' => trim($data['position'] ?? ''),
        'phone' => trim($data['phone'] ?? ''),
        'goal' => trim($data['goal'] ?? ''),
        'experience' => trim($data['experience'] ?? ''),
        // 三个期望时段
        'slots' => array_filter(array_slice(array_values($data['slots'] ?? []), 0, 3)),
        'amount' => $price,
        'status' => $settings['need_review'] ? 'pending_review' : 'approved',
        // pending_review → approved → paid → confirmed → completed / rejected / cancelled
        'review_note' => '',
        'meeting_link' => '',     // 讲师确认后生成的线上会议链接
        'scheduled_at' => '',     // 讲师确认的时间
        'replay_url' => '',       // 交付后回放
        'delivery_note' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $bookings[] = $booking;
    con_bookings_save($bookings);
    return ['ok' => true, 'booking' => $booking];
}

function con_booking_update(string $id, array $fields): bool {
    $bookings = con_bookings();
    $found = false;
    foreach ($bookings as &$b) {
        if ($b['id'] === $id) {
            foreach ($fields as $k => $v) $b[$k] = $v;
            $b['updated_at'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    unset($b);
    if ($found) { con_bookings_save($bookings); return true; }
    return false;
}

// 讲师可选的三个交付时段模板（每日）
function con_slot_options(): array {
    return ['09:00-10:30', '14:00-15:30', '19:30-21:00'];
}

// 状态文案
function con_status_label(string $s): string {
    $map = [
        'pending_review' => '待审核',
        'approved' => '待付款',
        'paid' => '待确认时间',
        'confirmed' => '已约时间',
        'completed' => '已完成',
        'rejected' => '已拒绝',
        'cancelled' => '已取消',
    ];
    return $map[$s] ?? $s;
}
function con_status_color(string $s): string {
    $map = [
        'pending_review' => '#d97706',
        'approved' => '#7c3aed',
        'paid' => '#2563eb',
        'confirmed' => '#0891b2',
        'completed' => '#16a34a',
        'rejected' => '#dc2626',
        'cancelled' => '#9ca3af',
    ];
    return $map[$s] ?? '#6b7280';
}

// ─── 支付（虎皮椒，复用商城模式）───
function con_xfpay_create(array $booking, array $member): array {
    $s = con_settings();
    if (empty($s['xfpay_appid']) || empty($s['xfpay_secret'])) {
        return ['ok' => false, 'error' => '支付未配置，请联系管理员'];
    }
    $params = [
        'version' => '1.1',
        'appid' => $s['xfpay_appid'],
        'trade_order_id' => $booking['id'],
        'total_fee' => (string)$booking['amount'],
        'title' => '1v1咨询：' . ($booking['mentor_name'] ?? ''),
        'time' => time(),
        'notify_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/api/consultation.php?action=notify',
        'return_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/consultation?view=booking&id=' . $booking['id'],
        'type' => $_GET['pay_type'] ?? 'wechat',
    ];
    ksort($params);
    $params['sign'] = md5(urldecode(http_build_query($params)) . $s['xfpay_secret']);
    return ['ok' => true, 'params' => $params, 'gateway' => $s['xfpay_gateway']];
}
function con_xfpay_verify(array $data): bool {
    $s = con_settings();
    $sign = $data['sign'] ?? '';
    unset($data['sign'], $data['extra']);
    ksort($data);
    return md5(urldecode(http_build_query($data)) . $s['xfpay_secret']) === $sign;
}
function con_mark_paid(string $bookingId, string $method = ''): bool {
    $b = con_booking($bookingId);
    if (!$b) return false;
    if ($b['status'] !== 'approved') return false;
    con_booking_update($bookingId, ['status' => 'paid', 'payment_method' => $method, 'paid_at' => date('Y-m-d H:i:s')]);
    return true;
}
