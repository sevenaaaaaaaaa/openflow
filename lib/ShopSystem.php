<?php
/**
 * 商城系统 — 课程订单 + 虎皮椒支付 + 分销佣金
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SubscriptionSystem.php';
require_once __DIR__ . '/FlowSystem.php';

function shop_orders_file(): string { return DATA_DIR . '/shop/orders.json'; }
function shop_settings_file(): string { return DATA_DIR . '/shop/settings.json'; }

// 读取当前用户的 UTM 归因来源（来自 track.php 存的 cookie）
function shop_current_utm(): array {
    $utm = [];
    foreach (['utm_source','utm_medium','utm_campaign','utm_term','utm_content'] as $a) {
        if (!empty($_COOKIE['fc_utm_' . $a])) $utm[$a] = $_COOKIE['fc_utm_' . $a];
    }
    return $utm;
}

function shop_settings(): array {
    return array_merge([
        'enabled' => false,
        'currency' => 'CNY',
        'xfpay_appid' => '',       // 虎皮椒 APPID
        'xfpay_secret' => '',      // 虎皮椒 通讯密钥
        'xfpay_gateway' => 'https://api.xunhupay.com/payment/do.html',
        'commission_rate' => 20,  // 分销佣金比例 %
        'min_withdraw' => 100,    // 最低提现金额
        'course_prices' => [],    // course_id => price
    ], json_read(shop_settings_file()));
}

// 获取订单（从 SQLite）
function shop_get_orders(array $filters = []): array {
    $sql = "SELECT * FROM orders WHERE 1=1";
    $params = [];
    
    if (!empty($filters['member_id'])) {
        $sql .= " AND member_id = ?";
        $params[] = $filters['member_id'];
    }
    if (!empty($filters['status'])) {
        $sql .= " AND status = ?";
        $params[] = $filters['status'];
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    if (!empty($filters['limit'])) {
        $sql .= " LIMIT ?";
        $params[] = (int)$filters['limit'];
    }
    
    return Database::query($sql, $params);
}

// 获取单个订单
function shop_get_order(string $id): ?array {
    $results = Database::query("SELECT * FROM orders WHERE id = ?", [$id]);
    return $results[0] ?? null;
}

function shop_create_order(string $memberId, string $courseId): array {
    $settings = shop_settings();
    $courses = json_read(DATA_DIR . '/courses/index.json');
    $course = null;
    foreach ($courses as $c) if ($c['id'] === $courseId) { $course = $c; break; }
    if (!$course) return ['ok' => false, 'error' => '课程不存在'];
    $price = $settings['course_prices'][$courseId] ?? 0;
    if ($price <= 0) return ['ok' => false, 'error' => '该课程未设置价格'];

    $orderId = 'order_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $referrerId = '';
    $commission = 0;
    
    // 记录推荐人（通过会员信息）
    $members = Database::query("SELECT * FROM members WHERE id = ?", [$memberId]);
    $member = $members[0] ?? null;
    if ($member && !empty($member['referred_by'])) {
        $referrerId = $member['referred_by'];
        $commission = round($price * $settings['commission_rate'] / 100, 2);
    }
    
    $order = [
        'id' => $orderId,
        'member_id' => $memberId,
        'course_id' => $courseId,
        'course_title' => $course['title'],
        'amount' => $price,
        'status' => 'pending',
        'payment_method' => '',
        'referrer_id' => $referrerId,
        'commission' => $commission,
        'created_at' => date('Y-m-d H:i:s'),
        'paid_at' => '',
    ];
    
    Database::insert('orders', $order);
    $order['utm'] = shop_current_utm();
    return ['ok' => true, 'order' => $order];
}

// 虎皮椒下单
function shop_xfpay_create(array $order, array $member): array {
    $s = shop_settings();
    if (empty($s['xfpay_appid']) || empty($s['xfpay_secret'])) {
        return ['ok' => false, 'error' => '支付未配置，请联系管理员'];
    }
    $params = [
        'version' => '1.1',
        'appid' => $s['xfpay_appid'],
        'trade_order_id' => $order['id'],
        'total_fee' => (string)$order['amount'],
        'title' => $order['course_title'],
        'time' => time(),
        'notify_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/api/shop.php?action=notify',
        'return_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/member.php?view=orders',
        'type' => $_GET['pay_type'] ?? 'wechat', // wechat / alipay
    ];
    ksort($params);
    $signStr = urldecode(http_build_query($params)) . $s['xfpay_secret'];
    $params['sign'] = md5($signStr);
    return ['ok' => true, 'params' => $params, 'gateway' => $s['xfpay_gateway']];
}

// 虎皮椒回调验签
function shop_xfpay_verify(array $data): bool {
    $s = shop_settings();
    $sign = $data['sign'] ?? '';
    unset($data['sign'], $data['extra']);
    ksort($data);
    $signStr = urldecode(http_build_query($data)) . $s['xfpay_secret'];
    return md5($signStr) === $sign;
}

// 支付成功处理
function shop_mark_paid(string $orderId, string $method = ''): bool {
    $order = shop_get_order($orderId);
    if (!$order || $order['status'] !== 'pending') return false;
    
    // 更新订单状态
    Database::execute(
        "UPDATE orders SET status = 'paid', paid_at = ?, payment_method = ? WHERE id = ?",
        [date('Y-m-d H:i:s'), $method, $orderId]
    );
    
    // 分销佣金入账
    if (!empty($order['referrer_id']) && $order['commission'] > 0) {
        Database::execute(
            "UPDATE members SET balance = balance + ? WHERE id = ?",
            [$order['commission'], $order['referrer_id']]
        );
        // 记录积分日志
        Database::insert('point_logs', [
            'member_id' => $order['referrer_id'],
            'points' => 0,
            'type' => 'commission',
            'description' => "订单 {$orderId} 分销佣金 {$order['commission']}",
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
    
    // 订阅订单：激活订阅状态
    if (!empty($order['plan_id'])) {
        $memberId = $order['member_id'];
        $s = sub_get_member($memberId);
        $months = ($order['period'] ?? 'month') === 'year' ? 12 : 1;
        $base = ($s && ($s['status'] ?? '') === 'active' && !empty($s['expires_at'])) ? $s['expires_at'] : date('Y-m-d');
        sub_set_member($memberId, [
            'member_id' => $memberId,
            'member_name' => '',
            'plan_id' => $order['plan_id'],
            'status' => 'active',
            'expires_at' => date('Y-m-d', strtotime($base . ' +' . $months . ' month')),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
    
    // 付费技能：支付后解锁
    if (($order['goods_type'] ?? '') === 'skill' && !empty($order['goods_id'])) {
        $memberId = $order['member_id'];
        Database::execute(
            "UPDATE members SET unlocked_skills = json_insert(COALESCE(unlocked_skills, '[]'), '$[#]', ?) WHERE id = ?",
            [$order['goods_id'], $memberId]
        );
    }
    
    // CDP 打标 + LTV
    try {
        $uid = $_COOKIE['fc_uid'] ?? '';
        if ($uid) {
            cdp_touch($uid, ['member_id' => $order['member_id']]);
            cdp_score($uid, 10, 'purchase', '购买订单');
        }
    } catch (Exception $e) {}
    
    // 积分奖励
    if (!empty($order['member_id'])) {
        $points = (int)($order['amount'] * 10); // 1元=10积分
        if (function_exists('gamification_award')) {
            gamification_award($order['member_id'], $points, 'purchase');
        }
    }
    
    // 站内信通知
    if (!empty($order['member_id'])) {
        if (function_exists('inbox_send')) {
            inbox_send($order['member_id'], '订单支付成功', "您的订单 {$orderId} 已支付成功");
        }
    }

    // 营销自动化：购买触发
    try {
        if (function_exists('flow_handle')) {
            flow_handle('purchase', [
                'member_id' => $order['member_id'] ?? '',
                'email' => $order['email'] ?? '',
                'amount' => (float)($order['amount'] ?? 0),
                'course_id' => $order['course_id'] ?? '',
                'order_id' => $orderId,
                'label' => ($order['course_title'] ?? '') ?: ('订单 ' . $orderId),
            ]);
        }
    } catch (Exception $e) {}

    // 数字商品交付（Skill/插件/API套餐）——需在订单标记已支付后执行
    try {
        if (($order['goods_type'] ?? '') === 'product') {
            require_once __DIR__ . '/CommerceSystem.php';
            CommerceSystem::deliverOnPaid($orderId);
        }
    } catch (Exception $e) {}

    return true;
}

