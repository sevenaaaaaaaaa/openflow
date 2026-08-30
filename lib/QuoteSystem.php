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

// ─── 交付阶段 + 待办（Sales 交付跟踪）──────────────────
//
// 收款状态（pending/paid/refunded）之外，再挂一条独立的「交付阶段」，
// 两条轴一交叉就能自动回答一人公司最关心的两件事：
//   · 钱到了活没完 = 已付 × 未交付   → 赶紧干活
//   · 活完钱没清   = 已交付 × 未付清 → 该收钱 / 催尾款
// 再加每单的待办清单，管细节提醒。

/** 交付阶段：key => 中文名 */
function quote_stages(): array {
    return ['not_started'=>'待启动','in_progress'=>'进行中','review'=>'待验收','delivered'=>'已交付'];
}

/** 在 orders.json 里定位一张收款单（返回 [下标, 订单] 或 null）。 */
function quote_locate(string $id): ?array {
    $orders = json_read(shop_orders_file());
    foreach ($orders as $i => $o) {
        if (($o['id'] ?? '') === $id && ($o['goods_type'] ?? '') === 'quote') return [$i, $o];
    }
    return null;
}

/** 读改写一张收款单。$changes 直接合并进订单。 */
function quote_update(string $id, array $changes): bool {
    $orders = json_read(shop_orders_file());
    $hit = false;
    foreach ($orders as &$o) {
        if (($o['id'] ?? '') === $id && ($o['goods_type'] ?? '') === 'quote') {
            $o = array_merge($o, $changes);
            $o['updated_at'] = date('Y-m-d H:i:s');
            $hit = true;
            break;
        }
    }
    unset($o);
    return $hit ? json_write(shop_orders_file(), $orders) : false;
}

/** 设置交付阶段。 */
function quote_set_stage(string $id, string $stage): bool {
    if (!isset(quote_stages()[$stage])) return false;
    return quote_update($id, ['delivery_stage' => $stage]);
}

/** 加一条待办。 */
function quote_add_todo(string $id, string $text, string $due = ''): bool {
    $text = trim($text);
    if ($text === '') return false;
    $loc = quote_locate($id);
    if (!$loc) return false;
    $todos = (array)($loc[1]['todos'] ?? []);
    $todos[] = ['text' => $text, 'due' => trim($due), 'done' => false, 'created_at' => date('Y-m-d H:i:s')];
    return quote_update($id, ['todos' => $todos]);
}

/** 勾选/取消一条待办。 */
function quote_toggle_todo(string $id, int $idx): bool {
    $loc = quote_locate($id);
    if (!$loc) return false;
    $todos = (array)($loc[1]['todos'] ?? []);
    if (!isset($todos[$idx])) return false;
    $todos[$idx]['done'] = empty($todos[$idx]['done']);
    return quote_update($id, ['todos' => $todos]);
}

/** 删除一条待办。 */
function quote_remove_todo(string $id, int $idx): bool {
    $loc = quote_locate($id);
    if (!$loc) return false;
    $todos = (array)($loc[1]['todos'] ?? []);
    if (!isset($todos[$idx])) return false;
    array_splice($todos, $idx, 1);
    return quote_update($id, ['todos' => array_values($todos)]);
}

/** 一张单的交付阶段（缺省视为待启动）。 */
function quote_stage_of(array $q): string {
    $s = (string)($q['delivery_stage'] ?? 'not_started');
    return isset(quote_stages()[$s]) ? $s : 'not_started';
}

/**
 * 需要盯的三桶——首页顶部用它把该动手的单挑出来。
 *   paid_undelivered 已付但没交付：赶紧干活
 *   delivered_unpaid 已交付但没付清：该收钱 / 催尾款
 *   todo_due        有到期未完成的待办
 */
function quote_attention(): array {
    $today = date('Y-m-d');
    $out = ['paid_undelivered'=>[], 'delivered_unpaid'=>[], 'todo_due'=>[]];
    foreach (quote_all() as $q) {
        $status = $q['status'] ?? 'pending';
        $stage  = quote_stage_of($q);
        if ($status === 'paid' && $stage !== 'delivered') $out['paid_undelivered'][] = $q;
        if ($stage === 'delivered' && !in_array($status, ['paid','refunded'], true)) $out['delivered_unpaid'][] = $q;
        foreach ((array)($q['todos'] ?? []) as $t) {
            if (empty($t['done']) && !empty($t['due']) && $t['due'] <= $today) { $out['todo_due'][] = $q + ['_todo'=>$t]; break; }
        }
    }
    return $out;
}

/** 一张单里未完成待办数。 */
function quote_open_todos(array $q): int {
    return count(array_filter((array)($q['todos'] ?? []), fn($t) => empty($t['done'])));
}
