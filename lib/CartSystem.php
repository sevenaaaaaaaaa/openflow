<?php
/**
 * 购物车系统 — A2 商业化核心
 *
 * 能力：多商品统一结算 · 优惠码 · 单笔支付 · 会员折扣
 * 存储：SESSION（短期）+ JSON（持久化订单）
 * 依赖：ShopSystem（订单）、CouponSystem（优惠码）、PaymentChannel（支付）
 */

// ─── 购物车 SESSION 管理 ───

function cart_session_key(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    return $_SESSION['cart_key'] ?? 'default';
}

function cart_get(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $key = cart_session_key();
    return $_SESSION['cart'][$key] ?? ['items' => [], 'coupon' => '', 'updated_at' => ''];
}

function cart_save(array $cart): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $key = cart_session_key();
    $cart['updated_at'] = date('Y-m-d H:i:s');
    $_SESSION['cart'][$key] = $cart;
}

function cart_clear(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $key = cart_session_key();
    unset($_SESSION['cart'][$key]);
}

// ─── 购物车操作 ───

/**
 * 添加商品到购物车
 * @param string $itemId   商品ID（课程ID/模板ID等）
 * @param string $type     商品类型（course/template/skill/plugin）
 * @param string $name     商品名称
 * @param float  $price    单价
 * @param int    $qty      数量
 */
function cart_add(string $itemId, string $type, string $name, float $price, int $qty = 1): array {
    $cart = cart_get();
    $found = false;
    foreach ($cart['items'] as &$item) {
        if ($item['id'] === $itemId && $item['type'] === $type) {
            $item['qty'] += $qty;
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) {
        $cart['items'][] = [
            'id'    => $itemId,
            'type'  => $type,
            'name'  => $name,
            'price' => $price,
            'qty'   => $qty,
        ];
    }
    cart_save($cart);
    return ['ok' => true, 'cart' => $cart];
}

/**
 * 更新购物车商品数量
 */
function cart_update_qty(string $itemId, string $type, int $qty): array {
    $cart = cart_get();
    if ($qty <= 0) {
        $cart['items'] = array_values(array_filter($cart['items'], fn($i) => !($i['id'] === $itemId && $i['type'] === $type)));
    } else {
        foreach ($cart['items'] as &$item) {
            if ($item['id'] === $itemId && $item['type'] === $type) {
                $item['qty'] = $qty;
                break;
            }
        }
        unset($item);
    }
    cart_save($cart);
    return ['ok' => true, 'cart' => $cart];
}

/**
 * 移除购物车商品
 */
function cart_remove(string $itemId, string $type): array {
    $cart = cart_get();
    $cart['items'] = array_values(array_filter($cart['items'], fn($i) => !($i['id'] === $itemId && $i['type'] === $type)));
    cart_save($cart);
    return ['ok' => true, 'cart' => $cart];
}

/**
 * 应用优惠码
 */
function cart_apply_coupon(string $code, ?array $member = null): array {
    if (!function_exists('coupon_validate')) return ['ok' => false, 'error' => '优惠码系统不可用'];
    $cart = cart_get();
    if (empty($cart['items'])) return ['ok' => false, 'error' => '购物车为空'];
    $subtotal = cart_subtotal($cart);
    $result = coupon_validate($member, $code, $subtotal);
    if (!$result['ok']) return $result;
    $cart['coupon'] = $code;
    cart_save($cart);
    return ['ok' => true, 'cart' => $cart, 'discount' => $result['discount'] ?? 0];
}

/**
 * 移除优惠码
 */
function cart_remove_coupon(): array {
    $cart = cart_get();
    $cart['coupon'] = '';
    cart_save($cart);
    return ['ok' => true, 'cart' => $cart];
}

// ─── 价格计算 ───

function cart_subtotal(array $cart): float {
    $total = 0;
    foreach ($cart['items'] as $item) {
        $total += ($item['price'] ?? 0) * ($item['qty'] ?? 1);
    }
    return round($total, 2);
}

function cart_discount(array $cart, ?array $member = null): float {
    $code = $cart['coupon'] ?? '';
    if (empty($code)) return 0;
    if (!function_exists('coupon_validate')) return 0;
    $subtotal = cart_subtotal($cart);
    $result = coupon_validate($member, $code, $subtotal);
    return ($result['ok'] ?? false) ? (float)($result['discount'] ?? 0) : 0;
}

function cart_total(array $cart, ?array $member = null): float {
    $subtotal = cart_subtotal($cart);
    $discount = cart_discount($cart, $member);
    // 会员折扣（如果会员有专属折扣率）
    $memberDiscount = 0;
    if ($member && function_exists('member_shop_plan')) {
        $plan = member_shop_plan($member);
        if ($plan && ($plan['discount_rate'] ?? 0) > 0) {
            $memberDiscount = round($subtotal * $plan['discount_rate'], 2);
        }
    }
    return round(max(0, $subtotal - $discount - $memberDiscount), 2);
}

function cart_summary(?array $member = null): array {
    $cart = cart_get();
    $subtotal = cart_subtotal($cart);
    $discount = cart_discount($cart, $member);
    $total = cart_total($cart, $member);
    return [
        'items'       => $cart['items'],
        'coupon'      => $cart['coupon'] ?? '',
        'subtotal'    => $subtotal,
        'discount'    => $discount,
        'total'       => $total,
        'item_count'  => array_sum(array_column($cart['items'], 'qty')),
    ];
}

// ─── 结算（创建订单） ───

/**
 * 购物车结算 → 创建订单 + 发起支付
 * @param string $memberId 会员ID
 * @param string $method   支付方式（xfpay/wechat/alipay）
 */
function cart_checkout(string $memberId, string $method = ''): array {
    $cart = cart_get();
    if (empty($cart['items'])) return ['ok' => false, 'error' => '购物车为空'];

    $member = function_exists('member_get') ? member_get($memberId) : null;
    $total = cart_total($cart, $member);
    if ($total < 0) return ['ok' => false, 'error' => '订单金额异常'];

    // 创建订单（复用 ShopSystem 的订单格式）
    $orderId = 'cart_' . $memberId . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    $order = [
        'id'              => $orderId,
        'member_id'       => $memberId,
        'type'            => 'cart',
        'items'           => $cart['items'],
        'subtotal'        => cart_subtotal($cart),
        'discount'        => cart_discount($cart, $member),
        'coupon'          => $cart['coupon'] ?? '',
        'total'           => $total,
        'currency'        => 'CNY',
        'status'          => 'pending',
        'payment_method'  => $method,
        'created_at'      => date('Y-m-d H:i:s'),
    ];

    // 持久化订单
    $ordersFile = DATA_DIR . '/shop/orders.json';
    $orders = json_read($ordersFile);
    $orders[] = $order;
    if (!is_dir(dirname($ordersFile))) @mkdir(dirname($ordersFile), 0755, true);
    json_write($ordersFile, $orders);

    // 发起支付（复用 ShopSystem 的支付通道）
    if ($method && function_exists('payment_channel_create')) {
        $payResult = payment_channel_create($method, $order);
        if (!empty($payResult['pay_url'])) {
            return ['ok' => true, 'order_id' => $orderId, 'pay_url' => $payResult['pay_url'], 'total' => $total];
        }
    }

    // 金额为 0 的订单直接完成（免费）
    if ($total == 0) {
        cart_mark_completed($orderId, $memberId, $cart);
        cart_clear();
        return ['ok' => true, 'order_id' => $orderId, 'total' => 0, 'free' => true];
    }

    // 非免费单：订单已创建即清空购物车（防止重复下单；支付失败可从订单页重新发起支付）
    cart_clear();
    return ['ok' => true, 'order_id' => $orderId, 'total' => $total];
}

/**
 * 支付成功后标记订单完成 + 交付商品
 */
function cart_mark_completed(string $orderId, string $memberId, array $cart): void {
    // 更新订单状态
    $ordersFile = DATA_DIR . '/shop/orders.json';
    $orders = json_read($ordersFile);
    foreach ($orders as &$o) {
        if ($o['id'] === $orderId) {
            $o['status'] = 'paid';
            $o['paid_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($o);
    json_write($ordersFile, $orders);

    // 交付商品（根据类型调用对应系统）
    foreach ($cart['items'] as $item) {
        switch ($item['type'] ?? '') {
            case 'course':
                // 课程：调用 ShopSystem 的 mark_paid
                if (function_exists('shop_mark_paid')) shop_mark_paid($orderId, 'cart');
                break;
            case 'template':
            case 'skill':
            case 'plugin':
                // Skill/模板/插件：记录购买
                $purchasesFile = DATA_DIR . '/shop/purchases.json';
                $purchases = json_read($purchasesFile);
                $purchases[] = [
                    'member_id' => $memberId,
                    'item_id'   => $item['id'],
                    'type'      => $item['type'],
                    'order_id'  => $orderId,
                    'purchased_at' => date('Y-m-d H:i:s'),
                ];
                json_write($purchasesFile, $purchases);
                break;
        }
    }

    // 使用优惠码
    $couponCode = $cart['coupon'] ?? '';
    if ($couponCode && function_exists('coupon_by_code') && function_exists('coupon_mark_used')) {
        $coupon = coupon_by_code($couponCode);
        if ($coupon) coupon_mark_used($coupon['id'], $memberId, $orderId);
    }

    // 清空购物车
    cart_clear();
}

// ─── 购物车状态查询 ───

function cart_is_empty(): bool {
    $cart = cart_get();
    return empty($cart['items']);
}

function cart_item_count(): int {
    $cart = cart_get();
    return array_sum(array_column($cart['items'], 'qty'));
}
