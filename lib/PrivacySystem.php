<?php
/**
 * PrivacySystem — 隐私中心（个保法合规）
 * 用户自助：数据导出请求 / 账号注销
 * 后台：脱敏显示（PrivacySystem::mask）
 */

// 导出用户全部数据（JSON 打包）
function privacy_export_member(string $memberId): array {
    $member = member_get($memberId);
    if (!$member) return ['ok' => false, 'error' => '用户不存在'];
    $data = [
        'profile' => $member,
        'orders' => function_exists('shop_orders_for_member') ? shop_orders_for_member($memberId) : [],
        'messages' => [],
        'comments' => [],
        'progress' => [],
        'exported_at' => date('Y-m-d H:i:s'),
    ];
    // 站内信
    try {
        $all = json_read(DATA_DIR . '/messages/index.json');
        $data['messages'] = array_values(array_filter($all, fn($m) => ($m['to'] ?? $m['member_id'] ?? '') === $memberId));
    } catch (Throwable $e) {}
    // 评论
    try {
        $all = json_read(DATA_DIR . '/comments.json');
        $data['comments'] = array_values(array_filter($all, fn($c) => ($c['member_id'] ?? '') === $memberId));
    } catch (Throwable $e) {}
    // 学习进度
    try {
        $p = json_read(DATA_DIR . '/progress.json');
        $data['progress'] = $p[$memberId] ?? [];
    } catch (Throwable $e) {}
    return ['ok' => true, 'data' => $data];
}

// 注销账号（标记注销 + 清理 PII + 记录请求）
function privacy_delete_member(string $memberId): array {
    $member = member_get($memberId);
    if (!$member) return ['ok' => false, 'error' => '用户不存在'];
    // 保留 ID 但清空 PII
    $cleaned = $member;
    foreach (['email', 'phone', 'password_hash', 'name', 'nickname', 'avatar'] as $f) {
        $cleaned[$f] = '';
    }
    $cleaned['deleted_at'] = date('Y-m-d H:i:s');
    $cleaned['deleted'] = true;
    member_save($cleaned);
    // SQLite 镜像同步清理
    try {
        Database::execute("UPDATE members SET email=?, nickname=?, phone=?, password_hash=?, deleted_at=? WHERE id=?", ['', '已注销用户', '', '', date('Y-m-d H:i:s'), $memberId]);
    } catch (Throwable $e) {}
    // 记录删除请求
    try {
        $req = json_read(DATA_DIR . '/privacy-requests.json');
        $req[] = ['type' => 'delete', 'member_id' => $memberId, 'email' => $member['email'] ?? '', 'at' => date('Y-m-d H:i:s'), 'status' => 'done'];
        json_write(DATA_DIR . '/privacy-requests.json', array_slice($req, -200));
    } catch (Throwable $e) {}
    // 登出
    if (function_exists('member_logout')) { try { member_logout(); } catch (Throwable $e) {} }
    return ['ok' => true];
}

// 后台脱敏：手机号/邮箱打码
function privacy_mask_email(string $email): string {
    if ($email === '') return '';
    $at = strpos($email, '@');
    if ($at === false) return $email;
    return substr($email, 0, 2) . '***' . substr($email, $at - 1);
}
function privacy_mask_phone(string $phone): string {
    if (strlen($phone) < 7) return $phone;
    return substr($phone, 0, 3) . '****' . substr($phone, -4);
}
