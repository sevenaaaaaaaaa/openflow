<?php
/**
 * 前台用户 API — 注册/登录/登出/验证码
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/AutomationSystem.php';
require_once __DIR__ . '/../lib/CanvasSystem.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_once __DIR__ . '/../lib/FlowSystem.php';
require_once __DIR__ . '/../lib/QrTrack.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

switch ($action) {
    // ─── 发送验证码 ───
    case 'send_captcha':
        $target = trim($_POST['target'] ?? '');
        if (empty($target)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请输入手机号或邮箱']); exit; }
        if (member_send_captcha($target)) {
            echo json_encode(['ok' => true, 'message' => '验证码已发送（演示环境请查看后台「用户验证码」日志）'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(429);
            echo json_encode(['ok' => false, 'error' => '发送过于频繁，请稍后再试']);
        }
        break;

    // ─── 注册 ───
    case 'register':
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $captcha = trim($_POST['captcha'] ?? '');
        $referral = trim($_POST['referral'] ?? ''); // 推荐人（大使）code

        if (empty($name) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'请填写完整信息（姓名/邮箱/密码）']); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'邮箱格式不正确']); exit;
        }
        if (strlen($password) < 6) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'密码至少 6 位']); exit;
        }
        if (member_find($email) || ($phone && member_find($phone))) {
            http_response_code(409); echo json_encode(['ok'=>false,'error'=>'该邮箱或手机号已注册']); exit;
        }
        // 白名单校验
        $wl = member_check_whitelist($email, $phone);
        if (!empty($wl)) {
            http_response_code(403); echo json_encode(['ok'=>false,'error'=>implode('；', $wl)], JSON_UNESCAPED_UNICODE); exit;
        }
        // 验证码校验
        if (member_settings()['captcha_enabled']) {
            $target = strpos($email, '@') !== false ? $email : $phone;
            if (!member_verify_captcha($target, $captcha)) {
                http_response_code(400); echo json_encode(['ok'=>false,'error'=>'验证码错误或已过期']); exit;
            }
        }

        $member = [
            'id' => 'm_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'user',            // user / ambassador / teacher / admin
            'referral_code' => 'of' . substr(bin2hex(random_bytes(4)), 0, 8),
            'referred_by' => '',          // 推荐人 member id
            'ambassador' => false,
            'teacher_status' => 'none',   // none / pending / approved / rejected
            'points' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        // 处理推荐关系
        if (!empty($referral)) {
            foreach (member_get_all() as $m) {
                if (($m['referral_code'] ?? '') === $referral && !empty($m['ambassador'])) {
                    $member['referred_by'] = $m['id'];
                    break;
                }
            }
        }
        member_save($member);
        member_start_session($member);
        member_log_attempt($email, true);
        // 二维码归因：扫码后注册
        if (function_exists('qr_track_register') && !empty($_COOKIE['of_qr_id'])) {
            qr_track_register($_COOKIE['of_qr_id'], $member['id']);
        }
        // CDP：合并注册前的匿名行为
        try { cdp_merge_on_login($member['id'], $email, $_COOKIE['fc_uid'] ?? ''); } catch (Exception $e) {}
        automation_trigger('member_register', ['email' => $email, 'name' => $name, 'phone' => $phone]);
        canvas_trigger('member_register', ['email' => $email, 'name' => $name, 'phone' => $phone]);
        // 数据流/价值流联动
        try { flow_handle('register', ['member_id' => $member['id'], 'email' => $email, 'uid' => $_COOKIE['fc_uid'] ?? '', 'props' => ['name' => $name]]); } catch (Exception $e) {}
        if (class_exists('PluginSystem')) PluginSystem::do_action('user_registered', $member['id'], $email, $member);
        echo json_encode(['ok'=>true, 'message'=>'注册成功', 'member_id'=>$member['id']]);
        break;

    // ─── 登录 ───
    case 'login':
        $account = trim($_POST['account'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($account) || empty($password)) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请输入账号和密码']); exit;
        }
        if (member_is_locked($account)) {
            http_response_code(429); echo json_encode(['ok'=>false,'error'=>'尝试次数过多，请 15 分钟后再试']); exit;
        }
        $m = member_find($account);
        if ($m && password_verify($password, $m['password_hash'] ?? '')) {
            member_start_session($m);
            member_log_attempt($account, true);
            // CDP：合并匿名行为到已知客户
            try { cdp_merge_on_login($m['id'], $m['email'] ?? '', $_COOKIE['fc_uid'] ?? ''); } catch (Exception $e) {}
            // 数据流/价值流联动
            try { flow_handle('login', ['member_id' => $m['id'], 'email' => $m['email'] ?? '', 'uid' => $_COOKIE['fc_uid'] ?? '']); } catch (Exception $e) {}
            if (class_exists('PluginSystem')) PluginSystem::do_action('user_login', $m['id'], $m['email'] ?? '', $m);
            echo json_encode(['ok'=>true, 'message'=>'登录成功', 'member_id'=>$m['id']]);
        } else {
            member_log_attempt($account, false);
            http_response_code(401); echo json_encode(['ok'=>false,'error'=>'账号或密码错误']);
        }
        break;

    // ─── 申请成为讲师 ───
    case 'apply_teacher':
        $m = member_current();
        if (!$m) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }
        $m['teacher_status'] = 'pending';
        $m['teacher_intro'] = trim($_POST['intro'] ?? '');
        $m['teacher_expertise'] = trim($_POST['expertise'] ?? '');
        $m['teacher_applied_at'] = date('Y-m-d H:i:s');
        member_save($m);
        notify('讲师申请', $m['name'] . ' 申请成为讲师', ($_POST['expertise'] ?? '') ?: '擅长方向未填', 'admin/approvals.php?type=teacher');
        echo json_encode(['ok'=>true, 'message'=>'讲师申请已提交，等待审核']);
        break;

    // ─── 投稿文章 ───
    case 'submit_article':
        $m = member_current();
        if (!$m) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (empty($title) || empty($content)) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'标题和正文不能为空']); exit;
        }
        $submissions = json_read(DATA_DIR . '/member-submissions.json');
        $submissions[] = [
            'id' => 'subm_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
            'member_id' => $m['id'],
            'author' => $m['name'],
            'title' => $title,
            'category' => trim($_POST['category'] ?? 'insight'),
            'excerpt' => trim($_POST['excerpt'] ?? ''),
            'content' => $content,
            'status' => 'pending', // pending / approved / rejected
            'created_at' => date('Y-m-d H:i:s'),
        ];
        json_write(DATA_DIR . '/member-submissions.json', $submissions);
        notify('投稿', $m['name'] . ' 投稿：' . mb_substr($title, 0, 20), '新文章投稿待审核', 'admin/approvals.php?type=article');
        echo json_encode(['ok'=>true, 'message'=>'投稿已提交，等待审核']);
        break;

    // ─── 个人中心汇总（导航弹窗用） ───
    case 'profile_summary':
        $m = member_current();
        if (!$m) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'未登录']); exit; }
        // 已加入课程数（progress.json: member → course → lesson）
        $courseCount = 0;
        try {
            $progress = json_read(DATA_DIR . '/courses/progress.json');
            foreach ((array)$progress as $mid => $courses) {
                if ($mid === $m['id']) { $courseCount = count((array)$courses); break; }
            }
        } catch (Exception $e) {}
        // 订单数
        $orderCount = 0;
        try {
            $orders = json_read(DATA_DIR . '/shop/orders.json');
            $orderCount = count(array_filter((array)$orders, fn($o) => ($o['member_id'] ?? '') === $m['id']));
        } catch (Exception $e) {}
        // 未读站内信
        $unread = 0;
        try { $unread = inbox_unread($m); } catch (Exception $e) {}
        // 我的咨询
        $consultCount = 0;
        try {
            $bookings = json_read(DATA_DIR . '/consultation/bookings.json');
            $consultCount = count(array_filter((array)$bookings, fn($b) => (($b['member_id'] ?? '') === $m['id']) || (($b['user_id'] ?? '') === $m['id'])));
        } catch (Exception $e) {}
        // 所属企业（ToB）
        $orgInfo = null;
        try {
            require_once __DIR__ . '/../lib/OrgSystem.php';
            $myOrg = org_by_member($m['id']);
            if ($myOrg) {
                $orgInfo = ['id' => $myOrg['id'], 'name' => $myOrg['name'] ?? '', 'plan' => org_plan_label($myOrg['plan_type'] ?? ''), 'status' => $myOrg['status'] ?? ''];
            }
        } catch (Throwable $e) {}
        echo json_encode([
            'ok' => true,
            'name' => $m['name'] ?? '',
            'email' => $m['email'] ?? '',
            'avatar' => strtoupper(mb_substr($m['name'] ?? ($m['email'] ?? '?'), 0, 1)),
            'org' => $orgInfo,
            'stats' => ['courses' => $courseCount, 'orders' => $orderCount, 'consultations' => $consultCount, 'unread' => $unread],
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ─── 登出 ───
    case 'logout':
        member_logout();
        echo json_encode(['ok'=>true, 'message'=>'已退出']);
        break;

    // ─── 更新个人资料 ───
    case 'update_profile':
        $m = member_current();
        if (!$m) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }
        $result = member_update_profile($m['id'], $_POST);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    // ─── 修改密码 ───
    case 'change_password':
        $m = member_current();
        if (!$m) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        if (empty($oldPassword) || empty($newPassword)) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请填写完整']); exit;
        }
        $result = member_change_password($m['id'], $oldPassword, $newPassword);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    // ─── 请求重置密码 ───
    case 'request_reset':
        $account = trim($_POST['account'] ?? '');
        if (empty($account)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请输入邮箱或手机号']); exit; }
        $result = member_request_password_reset($account);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    // ─── 验证重置码 ───
    case 'verify_reset':
        $token = trim($_POST['token'] ?? '');
        $code = trim($_POST['code'] ?? '');
        if (empty($token) || empty($code)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请填写验证码']); exit; }
        $result = member_verify_reset_token($token, $code);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    // ─── 重置密码 ───
    case 'reset_password':
        $token = trim($_POST['token'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        if (empty($token) || empty($newPassword)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请填写新密码']); exit; }
        $result = member_reset_password($token, $newPassword);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
