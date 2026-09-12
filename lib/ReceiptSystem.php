<?php
/**
 * ReceiptSystem — 交易收据（A3）
 *
 * 支付成功后生成可打印收据 + 邮件发送给买家。收据号稳定可验证（OF+日期+订单派生），
 * 相同订单重复调用只生成一次（幂等）。仅对 status=paid 的订单出票，绝不伪造。
 *
 * 设计约束：
 * - 只在订单已支付时出票；未支付订单返回 null
 * - 邮件未配置 / 买家无邮箱时静默跳过，不影响支付主流程
 * - 收据落盘 data/shop/receipts.json，前台 /receipt?order=xxx 凭订单归属校验后展示
 */

function receipt_store_file(): string { return DATA_DIR . '/shop/receipts.json'; }

/** 收据号：OF-YYYYMMDD-<订单id后6位大写>，稳定可复现 */
function receipt_no(string $orderId, string $createdAt = ''): string {
    $date = $createdAt !== '' ? date('Ymd', strtotime($createdAt)) : date('Ymd');
    $tail = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $orderId), -6));
    return 'OF-' . $date . '-' . ($tail !== '' ? $tail : '000000');
}

/** 归一化收据记录 */
function receipt_build(array $order, ?array $member = null): array {
    $amount = (float)($order['amount'] ?? 0);
    $original = (float)($order['original_amount'] ?? $amount);
    $discount = (float)($order['coupon_discount'] ?? 0);
    return [
        'order_id'     => (string)($order['id'] ?? ''),
        'receipt_no'   => receipt_no((string)($order['id'] ?? ''), (string)($order['paid_at'] ?? ($order['created_at'] ?? ''))),
        'buyer_name'   => (string)($member['name'] ?? ($order['customer'] ?? '')),
        'buyer_email'  => (string)($order['email'] ?? ($member['email'] ?? '')),
        'buyer_id'     => (string)($order['member_id'] ?? ($member['id'] ?? '')),
        'goods_title'  => (string)($order['course_title'] ?? $order['goods_title'] ?? '商品'),
        'goods_type'   => (string)($order['goods_type'] ?? 'course'),
        'amount'       => $amount,
        'original_amount' => $original,
        'coupon_code'  => (string)($order['coupon_code'] ?? ''),
        'coupon_discount' => $discount,
        'currency'     => (string)($order['currency'] ?? 'CNY'),
        'payment_method' => (string)($order['payment_method'] ?? ''),
        'paid_at'      => (string)($order['paid_at'] ?? ''),
        'issued_at'    => date('Y-m-d H:i:s'),
    ];
}

/** 幂等出票：已存在直接返回；否则生成记录并存盘 */
function receipt_ensure(string $orderId): ?array {
    $order = function_exists('shop_get_order') ? shop_get_order($orderId) : null;
    if (!$order || ($order['status'] ?? '') !== 'paid') return null;

    $all = json_read(receipt_store_file());
    if (!empty($all[$orderId])) return $all[$orderId];

    $member = null;
    try {
        if (function_exists('member_get') && !empty($order['member_id'])) $member = member_get((string)$order['member_id']);
    } catch (Throwable $e) {}

    $rec = receipt_build($order, $member);
    $all[$orderId] = $rec;
    json_write(receipt_store_file(), $all);

    // 邮件发送（失败不影响主流程）
    try { receipt_send($rec); } catch (Throwable $e) {}

    return $rec;
}

/** 发送收据邮件 */
function receipt_send(array $rec): bool {
    if (!function_exists('mail_send') || !function_exists('mail_available')) {
        require_once __DIR__ . '/MailChannel.php';
    }
    if (!function_exists('mail_send') || !mail_available()) return false;
    $to = trim((string)($rec['buyer_email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $site = function_exists('site_config_get') ? (string)site_config_get('site_name') : 'OpenFlow';
    $subject = '收据 ' . $rec['receipt_no'] . ' · ' . $rec['goods_title'];
    $body = "您好，" . ($rec['buyer_name'] !== '' ? $rec['buyer_name'] : '感谢购买') . "：\n\n"
        . "感谢购买，以下是本次交易收据。\n\n"
        . "收据号：{$rec['receipt_no']}\n"
        . "订单号：{$rec['order_id']}\n"
        . "商品：{$rec['goods_title']}\n"
        . "金额：¥" . number_format((float)$rec['amount'], 2)
        . ((float)$rec['coupon_discount'] > 0 ? "（已优惠 ¥" . number_format((float)$rec['coupon_discount'], 2) . "）" : '') . "\n"
        . "支付方式：" . ($rec['payment_method'] !== '' ? $rec['payment_method'] : '在线支付') . "\n"
        . "支付时间：{$rec['paid_at']}\n\n"
        . "在线查看/打印收据：\n" . receipt_url($rec['order_id']) . "\n\n"
        . "—— {$site}";
    return mail_send($to, $subject, $body);
}

/** 收据前台地址（用于邮件与后台） */
function receipt_url(string $orderId): string {
    $base = function_exists('payment_site_base') ? payment_site_base() : '';
    if ($base === '' && function_exists('site_config_get')) $base = rtrim((string)site_config_get('site_url'), '/');
    return $base . '/receipt?order=' . urlencode($orderId);
}

/** 取收据（不触发创建） */
function receipt_get(string $orderId): ?array {
    $all = json_read(receipt_store_file());
    return $all[$orderId] ?? null;
}

/**
 * 收据可打印 HTML（前台 /receipt 与后台复用）
 */
function receipt_html(array $rec, bool $standalone = true): string {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $site = function_exists('site_config_get') ? (string)site_config_get('site_name') : 'OpenFlow';
    $siteUrl = function_exists('site_config_get') ? rtrim((string)site_config_get('site_url'), '/') : '';
    $discountRow = (float)$rec['coupon_discount'] > 0
        ? '<tr><td class="k">优惠（' . $e($rec['coupon_code']) . '）</td><td class="v">- ¥' . number_format((float)$rec['coupon_discount'], 2) . '</td></tr>'
        : '';
    $body = '
    <div class="rcpt">
      <div class="rcpt-head">
        <div><div class="rcpt-brand">' . $e($site) . '</div><div class="rcpt-sub">' . $e($siteUrl) . '</div></div>
        <div class="rcpt-title">收据 / RECEIPT<div class="rcpt-no">' . $e($rec['receipt_no']) . '</div></div>
      </div>
      <table class="rcpt-meta">
        <tr><td class="k">开票给</td><td class="v">' . $e($rec['buyer_name'] !== '' ? $rec['buyer_name'] : '—') . ($rec['buyer_email'] !== '' ? ' &lt;' . $e($rec['buyer_email']) . '&gt;' : '') . '</td></tr>
        <tr><td class="k">订单号</td><td class="v mono">' . $e($rec['order_id']) . '</td></tr>
        <tr><td class="k">支付时间</td><td class="v">' . $e($rec['paid_at']) . '</td></tr>
        <tr><td class="k">支付方式</td><td class="v">' . $e($rec['payment_method'] !== '' ? $rec['payment_method'] : '在线支付') . '</td></tr>
      </table>
      <table class="rcpt-items">
        <thead><tr><th>商品</th><th class="v">金额</th></tr></thead>
        <tbody>
          <tr><td>' . $e($rec['goods_title']) . '</td><td class="v">' . ((float)$rec['original_amount'] > (float)$rec['amount'] ? '<span class="orig">¥' . number_format((float)$rec['original_amount'], 2) . '</span> ' : '') . '¥' . number_format((float)$rec['amount'], 2) . '</td></tr>
          ' . $discountRow . '
        </tbody>
        <tfoot><tr><td class="k">实付合计</td><td class="v total">¥' . number_format((float)$rec['amount'], 2) . '</td></tr></tfoot>
      </table>
      <p class="rcpt-note">本收据由系统自动生成，可作交易凭证；如需正式发票请联系客服并提供收据号。</p>
      <div class="rcpt-actions no-print"><button onclick="window.print()">🖨 打印 / 存为 PDF</button></div>
    </div>';

    if (!$standalone) {
        return '<style>
        .rcpt{max-width:680px;margin:0 auto;background:#fff;color:#111;padding:34px 38px;border:1px solid #e5e7eb;border-radius:14px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
        .rcpt-head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #111;padding-bottom:16px;margin-bottom:18px}
        .rcpt-brand{font-size:20px;font-weight:800}
        .rcpt-sub{font-size:12px;color:#6b7280;margin-top:2px}
        .rcpt-title{font-size:13px;font-weight:700;letter-spacing:.08em;text-align:right;color:#374151}
        .rcpt-no{font-family:ui-monospace,monospace;font-size:15px;color:#111;margin-top:4px}
        .rcpt-meta{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:18px}
        .rcpt-meta td{padding:5px 0}
        .rcpt-meta .k{color:#6b7280;width:110px}
        .rcpt-meta .v{color:#111}
        .rcpt-items{width:100%;border-collapse:collapse;font-size:13.5px}
        .rcpt-items th{text-align:left;border-bottom:1px solid #d1d5db;padding:8px 6px;color:#6b7280;font-weight:600}
        .rcpt-items th.v,.rcpt-items td.v{text-align:right}
        .rcpt-items td{padding:11px 6px;border-bottom:1px solid #f0f1f3}
        .rcpt-items .orig{color:#9ca3af;text-decoration:line-through;margin-right:6px}
        .rcpt-items tfoot td{padding:14px 6px;font-size:15px}
        .rcpt-items .total{font-weight:800;font-size:18px}
        .rcpt-note{font-size:11.5px;color:#9ca3af;margin-top:18px;line-height:1.7}
        .rcpt-actions{margin-top:22px;text-align:right}
        .rcpt-actions button{padding:9px 20px;border-radius:9px;border:1px solid #111;background:#111;color:#fff;cursor:pointer;font-size:13px}
        @media print{.no-print{display:none}body{background:#fff!important}.rcpt{border:none;padding:0}}
        </style>' . $body;
    }
    return $body;
}
