<?php
/**
 * 商城系统 — 实体商品 + 积分商城
 *
 * 数据：
 *   data/shop/products.json          实体商品 [{id,title,desc,price,stock,image,shipping}]
 *   data/shop/points_products.json   积分商品 [{id,title,desc,points,stock,image}]
 *   data/shop/redemptions.json       积分兑换记录
 */

if (!function_exists('mall_products_file')) {

function mall_products_file(): string { return DATA_DIR . '/shop/products.json'; }
function mall_points_products_file(): string { return DATA_DIR . '/shop/points_products.json'; }
function mall_redemptions_file(): string { return DATA_DIR . '/shop/redemptions.json'; }

// ─── 实体商品 ───
function mall_products(): array { return json_read(mall_products_file()); }
function mall_product(string $id): ?array {
    foreach (mall_products() as $p) if ($p['id'] === $id) return $p;
    return null;
}
function mall_product_save(array $p): void {
    $products = mall_products();
    $found = false;
    foreach ($products as &$x) if ($x['id'] === $p['id']) { $x = $p; $found = true; break; }
    unset($x);
    if (!$found) $products[] = $p;
    json_write(mall_products_file(), $products);
}
function mall_product_delete(string $id): void {
    json_write(mall_products_file(), array_values(array_filter(mall_products(), fn($p) => $p['id'] !== $id)));
}

// 实体商品下单（写商城订单，走支付）
function mall_order_product(string $memberId, string $productId, int $qty = 1, array $shipping = []): array {
    $p = mall_product($productId);
    if (!$p) return ['ok' => false, 'error' => '商品不存在'];
    if (($p['stock'] ?? 0) < $qty) return ['ok' => false, 'error' => '库存不足'];
    $amount = ($p['price'] ?? 0) * $qty;

    $orders = json_read(shop_orders_file());
    $order = [
        'id' => 'order_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'member_id' => $memberId,
        'goods_type' => 'product',
        'goods_id' => $productId,
        'goods_title' => $p['title'],
        'qty' => $qty,
        'amount' => $amount,
        'shipping' => $shipping,
        'status' => 'pending',
        'payment_method' => '',
        'utm' => function_exists('shop_current_utm') ? shop_current_utm() : [],
        'created_at' => date('Y-m-d H:i:s'),
        'paid_at' => '',
    ];
    // 分销佣金
    $settings = function_exists('shop_settings') ? shop_settings() : [];
    $member = null;
    foreach (json_read(DATA_DIR . '/members/index.json') as $m) if ($m['id'] === $memberId) { $member = $m; break; }
    if ($member && !empty($member['referred_by'])) {
        $order['referrer_id'] = $member['referred_by'];
        $order['commission'] = round($amount * ($settings['commission_rate'] ?? 20) / 100, 2);
    }
    $orders[] = $order;
    json_write(shop_orders_file(), $orders);
    return ['ok' => true, 'order' => $order];
}

// 支付成功后扣减库存
function mall_stock_deduct(string $productId, int $qty): void {
    $products = mall_products();
    foreach ($products as &$p) {
        if ($p['id'] === $productId) {
            $p['stock'] = max(0, ($p['stock'] ?? 0) - $qty);
            break;
        }
    }
    unset($p);
    json_write(mall_products_file(), $products);
}

// ─── 积分商品 ───
function mall_points_products(): array { return json_read(mall_points_products_file()); }
function mall_points_product(string $id): ?array {
    foreach (mall_points_products() as $p) if ($p['id'] === $id) return $p;
    return null;
}
function mall_points_product_save(array $p): void {
    $list = mall_points_products();
    $found = false;
    foreach ($list as &$x) if ($x['id'] === $p['id']) { $x = $p; $found = true; break; }
    unset($x);
    if (!$found) $list[] = $p;
    json_write(mall_points_products_file(), $list);
}
function mall_points_product_delete(string $id): void {
    json_write(mall_points_products_file(), array_values(array_filter(mall_points_products(), fn($p) => $p['id'] !== $id)));
}

// 积分兑换
function mall_redeem_points(string $memberId, string $pointsProductId): array {
    $p = mall_points_product($pointsProductId);
    if (!$p) return ['ok' => false, 'error' => '积分商品不存在'];
    if (($p['stock'] ?? 0) < 1) return ['ok' => false, 'error' => '库存不足'];

    $members = json_read(DATA_DIR . '/members/index.json');
    $member = null;
    foreach ($members as &$m) if ($m['id'] === $memberId) { $member = &$m; break; }
    if (!$member) return ['ok' => false, 'error' => '会员不存在'];
    $points = $member['points'] ?? 0;
    if ($points < ($p['points'] ?? 0)) return ['ok' => false, 'error' => '积分不足'];

    // 扣积分
    $member['points'] = $points - ($p['points'] ?? 0);
    $member['points_log'] = $member['points_log'] ?? [];
    $member['points_log'][] = [
        'points' => -($p['points'] ?? 0),
        'reason' => '兑换「' . ($p['title'] ?? '') . '」',
        'time' => date('Y-m-d H:i:s'),
    ];
    unset($member);
    json_write(DATA_DIR . '/members/index.json', $members);

    // 扣库存 + 记录兑换
    $list = mall_points_products();
    foreach ($list as &$pp) if ($pp['id'] === $pointsProductId) { $pp['stock'] = max(0, ($pp['stock'] ?? 0) - 1); break; }
    unset($pp);
    json_write(mall_points_products_file(), $list);

    $reds = json_read(mall_redemptions_file());
    $reds[] = [
        'id' => 'rdm_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'member_id' => $memberId,
        'product_id' => $pointsProductId,
        'product_title' => $p['title'] ?? '',
        'points' => $p['points'] ?? 0,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_write(mall_redemptions_file(), $reds);

    return ['ok' => true, 'message' => '兑换成功'];
}

// 积分兑换记录
function mall_redemptions(): array { return json_read(mall_redemptions_file()); }

} // end if function_exists
