<?php
/**
 * QuoteSystem —— 收款链接 / 报价单（Sales）
 *
 * 面向一人公司 / 超级个体：没有销售团队，也要能把一笔生意收上钱。
 * 从一条线索（或直接填个邮箱）开一张收款单，拿到一个公开支付链接发给客户，
 * 客户点开就能付。付款成功后自动把对应 CRM 线索推进到「已成交」，
 * 金额记进管道——整条 线索→报价→收款→成交 打通，全程一个人搞定。
 *
 * 复用现有支付栈：收款单本质是一张 goods_type='quote' 的订单，存进
 * data/shop/orders.json，走 payment_channel_create 收款、shop_mark_paid 结算，
 * 因此它天然出现在「订单与退款」里，也能退款。不另起一套支付逻辑。
 */
require_once __DIR__ . '/ShopSystem.php';

/** 站点根地址（用于拼支付链接） */
function quote_site_url(): string {
    $u = function_exists('site_config_get') ? (string)site_config_get('site_url') : '';
    return rtrim($u ?: (( (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '')), '/');
}

/** 收款单的公开支付链接 */
function quote_pay_url(array $order): string {
    return quote_site_url() . '/pay?t=' . rawurlencode((string)($order['pay_token'] ?? ''));
}

/**
 * 开一张收款单。
 *
 * @param array $d ['title','amount','email','customer','items'=>[['name','qty','price']],'note','expires_at']
 *   amount 留空时按 items 求和。
 * @return array ['ok','error','order','pay_url']
 */
function quote_create(array $d): array {
    $items = [];
    foreach ((array)($d['items'] ?? []) as $it) {
        $name = trim((string)($it['name'] ?? ''));
        if ($name === '') continue;
        $qty = max(1, (int)($it['qty'] ?? 1));
        $price = round((float)($it['price'] ?? 0), 2);
        $items[] = ['name' => $name, 'qty' => $qty, 'price' => $price, 'subtotal' => round($qty * $price, 2)];
    }
    $amount = round((float)($d['amount'] ?? 0), 2);
    if ($amount <= 0 && $items) $amount = round(array_sum(array_column($items, 'subtotal')), 2);
    if ($amount <= 0) return ['ok' => false, 'error' => '金额必须大于 0（可直接填总额，或添加明细项）'];

    $title = trim((string)($d['title'] ?? '')) ?: '收款';
    $email = mb_strtolower(trim((string)($d['email'] ?? '')));

    $order = [
        'id'            => 'quote_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'goods_type'    => 'quote',
        'goods_title'   => $title,
        'course_title'  => $title,                 // 支付网关取这个字段当标题
        'amount'        => $amount,
        'status'        => 'pending',
        'payment_method' => '',
        'crm_email'     => $email,
        'email'         => $email,
        'customer'      => trim((string)($d['customer'] ?? '')),
        'items'         => $items,
        'note'          => trim((string)($d['note'] ?? '')),
        'pay_token'     => bin2hex(random_bytes(16)),
        'member_id'     => '',
        'referrer_id'   => '',
        'commission'    => 0,
        'created_by'    => $_SESSION['admin_user'] ?? '',
        'created_at'    => date('Y-m-d H:i:s'),
        'paid_at'       => '',
        'expires_at'    => trim((string)($d['expires_at'] ?? '')),
    ];

    $orders = json_read(shop_orders_file());
    $orders[] = $order;
    json_write(shop_orders_file(), $orders);

    // 若填了邮箱，顺手确保 CRM 里有这条线索（把生意纳入管道）
    if ($email !== '' && function_exists('crm_ensure_lead')) {
        try { crm_ensure_lead($email, $order['customer']); } catch (\Throwable $e) {}
    }

    return ['ok' => true, 'order' => $order, 'pay_url' => quote_pay_url($order)];
}

/** 按公开 token 取收款单（供 pay.php / 支付 API 用）。 */
function quote_get_by_token(string $token): ?array {
    $token = trim($token);
    if ($token === '') return null;
    foreach (shop_all_orders() as $o) {
        if (($o['goods_type'] ?? '') === 'quote' && hash_equals((string)($o['pay_token'] ?? ''), $token)) {
            return $o;
        }
    }
    return null;
}

/** 是否已过期（未设置则永不过期）。 */
function quote_is_expired(array $order): bool {
    $exp = trim((string)($order['expires_at'] ?? ''));
    return $exp !== '' && strtotime($exp) !== false && strtotime($exp) < time();
}

/** 全部收款单（新→旧）。 */
function quote_all(): array {
    $out = array_filter(shop_all_orders(), fn($o) => ($o['goods_type'] ?? '') === 'quote');
    usort($out, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return array_values($out);
}
