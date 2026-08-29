<?php
/**
 * 站内信系统 — 会员收件箱 + 系统通知 + 后台广播
 * 数据：data/messages/index.json
 * 支持：个人消息 / 全体广播 / 标记已读 / 未读计数
 */
require_once __DIR__ . '/../admin/config.php';

function inbox_file(): string { return DATA_DIR . '/messages/index.json'; }
function inbox_all(): array { return json_read(inbox_file()); }
function inbox_save_all(array $msgs): void {
    if (!is_dir(dirname(inbox_file()))) mkdir(dirname(inbox_file()), 0755, true);
    json_write(inbox_file(), $msgs);
}

// 发送消息
// $to: member_id 个人；或 'all' 全体广播；或数组 多人
function inbox_send($to, string $title, string $content, array $opts = []): array {
    $msg = [
        'id' => 'msg_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 5),
        'to' => $to,                     // member_id / 'all' / 数组
        'title' => mb_substr($title, 0, 100),
        'content' => $content,
        'type' => $opts['type'] ?? 'system',  // system / order / consultation / live / membership / marketing
        'link' => $opts['link'] ?? '',
        'icon' => $opts['icon'] ?? '🔔',
        'read_by' => [],                 // 已读 member_id 列表（广播用）
        'read_at' => '',                 // 个人消息已读时间
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => $opts['by'] ?? 'system',
    ];
    $all = inbox_all();
    $all[] = $msg;
    inbox_save_all(array_slice($all, -2000)); // 最多保留 2000 条
    return $msg;
}

// 某会员的收件箱（个人消息 + 全体广播，按时间倒序）
function inbox_inbox(?array $member, int $limit = 100): array {
    if (!$member) return [];
    $id = $member['id'];
    $all = inbox_all();
    $list = array_values(array_filter($all, function ($m) use ($id) {
        if ($m['to'] === 'all') return true;
        if ($m['to'] === $id) return true;
        if (is_array($m['to']) && in_array($id, $m['to'])) return true;
        return false;
    }));
    usort($list, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return array_slice($list, 0, $limit);
}

// 未读计数
function inbox_unread(?array $member): int {
    if (!$member) return 0;
    $id = $member['id'];
    $n = 0;
    foreach (inbox_all() as $m) {
        if ($m['to'] === 'all' && !in_array($id, $m['read_by'] ?? [])) $n++;
        elseif (($m['to'] === $id || (is_array($m['to']) && in_array($id, $m['to']))) && empty($m['read_at'])) $n++;
    }
    return $n;
}

// 标记已读
function inbox_mark_read(?array $member, string $msgId = ''): void {
    if (!$member) return;
    $id = $member['id'];
    $all = inbox_all();
    foreach ($all as &$m) {
        if ($msgId !== '' && $m['id'] !== $msgId) continue;
        if ($m['to'] === 'all') {
            if (!in_array($id, $m['read_by'] ?? [])) $m['read_by'][] = $id;
        } elseif ($m['to'] === $id || (is_array($m['to']) && in_array($id, $m['to']))) {
            if (empty($m['read_at'])) $m['read_at'] = date('Y-m-d H:i:s');
        }
    }
    unset($m);
    inbox_save_all($all);
}

// 删除消息（成员删除自己的个人消息，或管理员删任意）
function inbox_delete(string $msgId, string $memberId = ''): bool {
    $all = inbox_all();
    $before = count($all);
    $all = array_values(array_filter($all, function ($m) use ($msgId, $memberId) {
        if ($m['id'] !== $msgId) return true;
        if ($memberId === '') return false; // 管理员删任意
        if ($m['to'] === $memberId) return false; // 个人消息可删
        if (is_array($m['to']) && in_array($memberId, $m['to'])) return false;
        return true; // 广播不可被单个用户删
    }));
    inbox_save_all($all);
    return count($all) < $before;
}

// 业务事件自动发信（统一入口，供各模块调用）
function inbox_notify_event(string $event, array $data = []): void {
    $map = [
        'consultation_approved' => fn() => inbox_send($data['member_id'] ?? '', '✅ 1v1 咨询审核通过', '你的 1v1 咨询报名已审核通过，请完成付款锁定时段。', ['type' => 'consultation', 'link' => '/consultation?view=my', 'icon' => '🤝']),
        'consultation_confirmed' => fn() => inbox_send($data['member_id'] ?? '', '📅 1v1 咨询时间已确认', '讲师已确认时间：' . ($data['scheduled_at'] ?? '') . '，准时进入线上会议。', ['type' => 'consultation', 'link' => '/consultation?view=my', 'icon' => '📅']),
        'consultation_completed' => fn() => inbox_send($data['member_id'] ?? '', '🎬 1v1 咨询已完成 + 回放', '咨询已交付，回放可随时查看。', ['type' => 'consultation', 'link' => '/consultation?view=my', 'icon' => '🎬']),
        'order_paid' => fn() => inbox_send($data['member_id'] ?? '', '✅ 订单支付成功', '你的订单「' . ($data['title'] ?? '') . '」已支付成功。', ['type' => 'order', 'link' => '/member.php?view=orders', 'icon' => '🛒']),
        'live_started' => fn() => inbox_send('all', '🔴 直播开始啦！', ($data['title'] ?? '直播') . ' 正在直播中，速来围观。', ['type' => 'live', 'link' => '/live.php?room=' . ($data['room_id'] ?? ''), 'icon' => '📡']),
        'membership_upgraded' => fn() => inbox_send($data['member_id'] ?? '', '👑 会员等级已升级', '恭喜升级为 ' . ($data['tier'] ?? '') . '，解锁更多权益。', ['type' => 'membership', 'link' => '/member.php?view=membership', 'icon' => '💎']),
        'points_awarded' => fn() => inbox_send($data['member_id'] ?? '', '⭐ 获得积分', '你获得了 ' . ($data['points'] ?? 0) . ' 积分（' . ($data['reason'] ?? '') . '）。', ['type' => 'membership', 'link' => '/member.php?view=level', 'icon' => '🏆']),
        'points_deducted' => fn() => inbox_send($data['member_id'] ?? '', '积分扣除', '已扣除 ' . ($data['points'] ?? 0) . ' 积分（' . ($data['reason'] ?? '') . '）。', ['type' => 'membership', 'link' => '/member.php?view=level', 'icon' => '📉']),
        'submission_reviewed' => fn() => inbox_send($data['member_id'] ?? '', '📝 投稿审核结果', '你的投稿「' . ($data['title'] ?? '') . '」已' . ($data['result'] ?? '') . '。', ['type' => 'system', 'link' => '/member.php?view=submit', 'icon' => '📝']),
    ];
    $fn = $map[$event] ?? null;
    if ($fn) { try { $fn(); } catch (Exception $e) {} }
}
