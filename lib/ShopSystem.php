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

// 统一订单读取：SQLite 主 + JSON 兜底（合并历史遗留 data/shop/orders.json，按 id 去重）
function shop_all_orders(): array {
    $map = [];
    foreach (Database::query("SELECT * FROM orders ORDER BY created_at DESC") as $r) {
        $rid = $r['id'] ?? '';
        if ($rid !== '') $map[$rid] = $r;
    }
    // JSON 兜底：仅补入 SQLite 中没有的订单（历史数据）
    $jsonFile = shop_orders_file();
    if (is_file($jsonFile)) {
        foreach (json_read($jsonFile) as $j) {
            $jid = $j['id'] ?? '';
            if ($jid !== '' && !isset($map[$jid])) $map[$jid] = $j;
        }
    }
    return array_values($map);
}

// 某成员的订单（统一读取）
function shop_orders_for_member(string $memberId, string $status = ''): array {
    return array_values(array_filter(shop_all_orders(), function ($o) use ($memberId, $status) {
        if (($o['member_id'] ?? '') !== $memberId) return false;
        if ($status !== '' && ($o['status'] ?? '') !== $status) return false;
        return true;
    }));
}

// 某成员已购课程 ID（统一读取，支持 SQLite 与 JSON 两种结构）
function shop_course_ids_for_member(string $memberId): array {
    $ids = [];
    foreach (shop_orders_for_member($memberId, 'paid') as $o) {
        $cid = $o['course_id'] ?? ($o['goods_id'] ?? '');
        if ($cid && ($o['goods_type'] ?? '') !== 'skill') $ids[] = $cid;
    }
    return array_unique($ids);
}

function shop_create_order(string $memberId, string $courseId, string $ref = ''): array {
    $settings = shop_settings();
    $courses = json_read(DATA_DIR . '/courses/index.json');
    $course = null;
    foreach ($courses as $c) if ($c['id'] === $courseId) { $course = $c; break; }
    if (!$course) return ['ok' => false, 'error' => '课程不存在'];
    $price = $settings['course_prices'][$courseId] ?? 0;
    if ($price <= 0) return ['ok' => false, 'error' => '该课程未设置价格'];

    // 课程限时折扣（促销价 + 起止时间）
    $promo = $settings['course_promos'][$courseId] ?? [];
    $now = time();
    $promoOn = !empty($promo['price']) && $promo['price'] > 0
        && (!$promo['start'] || strtotime($promo['start']) <= $now)
        && (!$promo['end'] || strtotime($promo['end']) >= $now);
    $original = $price;
    if ($promoOn) $price = (float)$promo['price'];

    $orderId = 'order_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $referrerId = '';
    $commission = 0;
    
    // 记录推荐人/分销者：URL ref 分销码优先，其次注册推荐人
    $members = Database::query("SELECT * FROM members WHERE id = ?", [$memberId]);
    $member = $members[0] ?? null;
    $referrerId = '';
    if ($ref !== '') {
        // 解析分销码（referral_code 或 member_id 或派生码 of+md5(id)，不能是自己）
        foreach (member_get_all() as $m) {
            if (($m['id'] ?? '') === $memberId) continue;
            $derived = 'of' . substr(md5($m['id'] ?? ''), 0, 8);
            if (($m['referral_code'] ?? '') === $ref || ($m['id'] ?? '') === $ref || $derived === $ref) { $referrerId = $m['id']; break; }
        }
    }
    if ($referrerId === '' && $member && !empty($member['referred_by'])) {
        $referrerId = $member['referred_by'];
    }
    if ($referrerId !== '') $commission = round($price * $settings['commission_rate'] / 100, 2);

    // 课程作者（讲师体系）：platform 课程无 author_id，讲师课程作者分成为主
    $authorId = $course['author_id'] ?? '';
    $platformFee = round($price * 0.1, 2); // 平台抽 10% 覆盖支付手续费
    
    $order = [
        'id' => $orderId,
        'member_id' => $memberId,
        'course_id' => $courseId,
        'course_title' => $course['title'],
        'amount' => $price,
        'original_amount' => $original,
        'goods_type' => 'course',
        'author' => $authorId,
        'platform_fee' => $platformFee,
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
    // 统一取订单：SQLite 优先，JSON（订阅/实物订单）兜底
    $order = shop_get_order($orderId);
    $inJson = false;
    if (!$order) {
        $jsonFile = shop_orders_file();
        if (is_file($jsonFile)) {
            foreach (json_read($jsonFile) as $j) {
                if (($j['id'] ?? '') === $orderId) { $order = $j; $inJson = true; break; }
            }
        }
    }
    if (!$order || ($order['status'] ?? '') !== 'pending') return false;

    // 更新订单状态（双源）
    if ($inJson) {
        $orders = json_read(shop_orders_file());
        foreach ($orders as &$o) {
            if (($o['id'] ?? '') === $orderId) {
                $o['status'] = 'paid'; $o['paid_at'] = date('Y-m-d H:i:s'); $o['payment_method'] = $method;
                break;
            }
        }
        unset($o);
        json_write(shop_orders_file(), $orders);
    } else {
        Database::execute(
            "UPDATE orders SET status = 'paid', paid_at = ?, payment_method = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $method, $orderId]
        );
    }
    
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

    // 课程作者分成入账（平台抽 10% + 分销佣金后剩余归作者；platform 课程无作者跳过）
    if (!empty($order['author']) && ($order['goods_type'] ?? '') === 'course') {
        $authorAmount = round((float)$order['amount'] - (float)$order['platform_fee'] - (float)$order['commission'], 2);
        if ($authorAmount > 0) {
            $authRows = Database::query("SELECT id FROM members WHERE id = ?", [$order['author']]);
            if (!empty($authRows)) {
                Database::execute("UPDATE members SET balance = balance + ? WHERE id = ?", [$authorAmount, $order['author']]);
                Database::insert('point_logs', [
                    'member_id' => $order['author'], 'points' => 0, 'type' => 'course_author',
                    'description' => "课程「{$order['course_title']}」作者分成 ¥{$authorAmount}", 'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
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

    // 支付成功 → 插件钩子（旁路）
    if (class_exists('PluginSystem')) {
        PluginSystem::do_action('payment_success', $orderId, $order, $method);
        if (!empty($order['course_id'])) {
            PluginSystem::do_action('course_enrolled', (string)($order['member_id'] ?? ''), (string)$order['course_id'], $order);
        }
    }

    // 数字商品交付（Skill/插件/API套餐）——需在订单标记已支付后执行
    try {
        if (($order['goods_type'] ?? '') === 'product') {
            require_once __DIR__ . '/CommerceSystem.php';
            CommerceSystem::deliverOnPaid($orderId);
        }
    } catch (Exception $e) {}

    // 收款链接（quote）付款成功 → 把对应 CRM 线索推进为「已成交」，金额进管道。
    // crm_update_lead 内部会发 crm_deal_won + flow 事件，整条销售链随之联动。
    try {
        if (($order['goods_type'] ?? '') === 'quote' && !empty($order['crm_email'])) {
            require_once __DIR__ . '/CrmSystem.php';
            if (function_exists('crm_update_lead')) {
                crm_ensure_lead((string)$order['crm_email'], (string)($order['customer'] ?? ''));
                crm_update_lead((string)$order['crm_email'], [
                    'stage' => 'won',
                    'value' => (float)($order['amount'] ?? 0),
                ]);
                crm_add_followup((string)$order['crm_email'], '通过收款链接成交：¥' . number_format((float)($order['amount'] ?? 0), 2) . '（订单 ' . $orderId . '）', 'system');
            }
        }
    } catch (\Throwable $e) {}

    return true;
}

/**
 * 订单退款 —— shop_mark_paid() 的对称回滚
 *
 * 支付时发生的每一笔权益/入账，退款时都要逆向撤销，否则会出现
 * 「钱退了但课程还在、佣金还在」的漏洞。
 *
 * @param string $orderId 订单号
 * @param string $reason  退款原因（记入订单与日志）
 * @param float  $amount  退款金额，0 或超额时按订单全额
 * @return array ['ok'=>bool, 'error'=>string, 'amount'=>float]
 */
function shop_refund_order(string $orderId, string $reason = '', float $amount = 0): array {
    $order = shop_get_order($orderId);
    $inJson = false;
    if (!$order) {
        $jsonFile = shop_orders_file();
        if (is_file($jsonFile)) {
            foreach (json_read($jsonFile) as $j) {
                if (($j['id'] ?? '') === $orderId) { $order = $j; $inJson = true; break; }
            }
        }
    }
    if (!$order)                                  return ['ok'=>false,'error'=>'订单不存在','amount'=>0];
    if (($order['status'] ?? '') === 'refunded')  return ['ok'=>false,'error'=>'该订单已退款','amount'=>0];
    if (($order['status'] ?? '') !== 'paid')      return ['ok'=>false,'error'=>'只有已支付订单可退款','amount'=>0];

    $paid = (float)($order['amount'] ?? 0);
    $refund = ($amount > 0 && $amount < $paid) ? round($amount, 2) : $paid;
    $isPartial = $refund < $paid;
    $now = date('Y-m-d H:i:s');

    // ── 1. 订单状态（双源，与 shop_mark_paid 一致）──
    if ($inJson) {
        $orders = json_read(shop_orders_file());
        foreach ($orders as &$o) {
            if (($o['id'] ?? '') === $orderId) {
                $o['status'] = 'refunded';
                $o['refunded_at'] = $now;
                $o['refund_amount'] = $refund;
                $o['refund_reason'] = $reason;
                break;
            }
        }
        unset($o);
        json_write(shop_orders_file(), $orders);
    } else {
        Database::execute(
            "UPDATE orders SET status = 'refunded', refunded_at = ?, refund_amount = ?, refund_reason = ? WHERE id = ?",
            [$now, $refund, $reason, $orderId]
        );
    }

    // ── 2. 分销佣金回收 ──
    if (!empty($order['referrer_id']) && (float)($order['commission'] ?? 0) > 0) {
        $back = $isPartial ? round((float)$order['commission'] * $refund / $paid, 2) : (float)$order['commission'];
        try {
            Database::execute("UPDATE members SET balance = balance - ? WHERE id = ?", [$back, $order['referrer_id']]);
            Database::insert('point_logs', [
                'member_id' => $order['referrer_id'], 'points' => 0, 'type' => 'commission_refund',
                'description' => "订单 {$orderId} 退款，回收分销佣金 {$back}", 'created_at' => $now,
            ]);
        } catch (Exception $e) {}
    }

    // ── 3. 课程作者分成回收 ──
    if (!empty($order['author']) && ($order['goods_type'] ?? '') === 'course') {
        $authorAmount = round($paid - (float)($order['platform_fee'] ?? 0) - (float)($order['commission'] ?? 0), 2);
        if ($authorAmount > 0) {
            $back = $isPartial ? round($authorAmount * $refund / $paid, 2) : $authorAmount;
            try {
                Database::execute("UPDATE members SET balance = balance - ? WHERE id = ?", [$back, $order['author']]);
                Database::insert('point_logs', [
                    'member_id' => $order['author'], 'points' => 0, 'type' => 'course_author_refund',
                    'description' => "订单 {$orderId} 退款，回收作者分成 ¥{$back}", 'created_at' => $now,
                ]);
            } catch (Exception $e) {}
        }
    }

    // ── 4. 权益撤销（部分退款保留权益，只退钱）──
    if (!$isPartial) {
        // 订阅：置为已取消
        if (!empty($order['plan_id']) && !empty($order['member_id']) && function_exists('sub_get_member')) {
            try {
                $s = sub_get_member($order['member_id']);
                if ($s) {
                    $s['status'] = 'cancelled';
                    $s['updated_at'] = $now;
                    sub_set_member($order['member_id'], $s);
                }
            } catch (Exception $e) {}
        }
        // 付费技能：撤销解锁
        if (($order['goods_type'] ?? '') === 'skill' && !empty($order['goods_id']) && !empty($order['member_id'])) {
            try {
                $rows = Database::query("SELECT unlocked_skills FROM members WHERE id = ?", [$order['member_id']]);
                $list = json_decode($rows[0]['unlocked_skills'] ?? '[]', true);
                if (is_array($list)) {
                    $list = array_values(array_filter($list, fn($x) => (string)$x !== (string)$order['goods_id']));
                    Database::execute("UPDATE members SET unlocked_skills = ? WHERE id = ?",
                        [json_encode($list, JSON_UNESCAPED_UNICODE), $order['member_id']]);
                }
            } catch (Exception $e) {}
        }
    }

    // ── 4b. 购物积分回收（shop_mark_paid 按 1元=10积分 发放，这里等比收回）──
    // 不收回的话，「下单拿积分 → 申请退款 → 积分留下」就是一个白嫖闭环。
    if (!empty($order['member_id']) && function_exists('gamification_award')) {
        $backPoints = (int)round($refund * 10);
        if ($backPoints > 0) {
            try { gamification_award($order['member_id'], -$backPoints, 'purchase_refund'); }
            catch (Exception $e) {}
        }
    }

    // ── 5. 站内信通知 ──
    if (!empty($order['member_id']) && function_exists('inbox_send')) {
        try {
            $tip = $isPartial ? "部分退款 ¥{$refund}" : "已全额退款 ¥{$refund}";
            inbox_send($order['member_id'], '订单退款', "您的订单 {$orderId} {$tip}"
                . ($reason !== '' ? "（原因：{$reason}）" : ''));
        } catch (Exception $e) {}
    }

    // ── 6. 事件总线：CDP 打标 + MA/画布触发器 ──
    try {
        if (function_exists('flow_handle')) {
            flow_handle('refund', [
                'member_id' => $order['member_id'] ?? '',
                'email'     => $order['email'] ?? '',
                'amount'    => $refund,
                'order_id'  => $orderId,
                'label'     => ($order['course_title'] ?? '') ?: ('订单 ' . $orderId),
                'props'     => ['reason' => $reason, 'partial' => $isPartial ? 1 : 0, 'paid_amount' => $paid],
            ]);
        }
    } catch (Exception $e) {}

    // ── 7. 插件钩子（旁路）──
    if (class_exists('PluginSystem')) {
        PluginSystem::do_action('payment_refund', $orderId, $order, $refund, $reason);
    }

    return ['ok'=>true, 'error'=>'', 'amount'=>$refund];
}

