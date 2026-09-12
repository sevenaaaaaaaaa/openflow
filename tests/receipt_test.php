<?php
/**
 * A3 收据体系契约 —— 支付成功后出票 + 邮件 + 幂等 + 未付不出票
 *   php tests/receipt_test.php
 *
 * 用 stub 替代 ShopSystem/MailChannel/数据库，只验证 ReceiptSystem 的纯逻辑：
 * 收据号稳定、买家信息带入、金额与优惠保留、邮件一次、HTML 完整、未付订单不出票。
 */

$root = sys_get_temp_dir() . '/of-receipt-' . getmypid();
@mkdir($root . '/shop', 0777, true);
define('DATA_DIR', $root);

function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    @mkdir(dirname($f), 0777, true);
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}

$GLOBALS['ORDER'] = [
    'id' => 'ord_abc123456', 'member_id' => 'm1', 'course_title' => '增长课·进阶',
    'amount' => 199.0, 'original_amount' => 299.0, 'coupon_code' => 'WELCOME20', 'coupon_discount' => 100.0,
    'goods_type' => 'course', 'status' => 'paid', 'payment_method' => 'wechat',
    'paid_at' => '2026-09-12 10:00:00', 'email' => 'buyer@example.com',
];
function shop_get_order(string $id): ?array { return $id === $GLOBALS['ORDER']['id'] ? $GLOBALS['ORDER'] : null; }
function member_get(string $id): ?array { return ['id' => 'm1', 'name' => '张三', 'email' => 'buyer@example.com']; }
$GLOBALS['MAILED'] = [];
function mail_available(): bool { return true; }
function mail_send(string $to, string $subj, string $body, string $ch = ''): bool { $GLOBALS['MAILED'][] = ['to' => $to, 'subj' => $subj, 'body' => $body]; return true; }
function site_config_get(string $k) { return $k === 'site_name' ? 'OpenFlow' : ($k === 'site_url' ? 'https://nownexts.com' : ''); }
function payment_site_base(): string { return 'https://nownexts.com'; }

require_once __DIR__ . '/../lib/ReceiptSystem.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else { $fail++; echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

echo "A3 收据体系\n";

$rec = receipt_ensure('ord_abc123456');
check('已付订单出票', is_array($rec));
check('收据号格式 OF-日期-尾6', (bool)preg_match('/^OF-\d{8}-[A-Z0-9]{1,6}$/', $rec['receipt_no'] ?? ''), $rec['receipt_no'] ?? '');
check('买家信息带入', ($rec['buyer_name'] ?? '') === '张三' && ($rec['buyer_email'] ?? '') === 'buyer@example.com');
check('金额/优惠保留', (float)$rec['amount'] === 199.0 && (float)$rec['coupon_discount'] === 100.0);
check('邮件已发送', count($GLOBALS['MAILED']) === 1 && $GLOBALS['MAILED'][0]['to'] === 'buyer@example.com');
check('邮件含收据号', str_contains($GLOBALS['MAILED'][0]['body'] ?? '', $rec['receipt_no']));
check('邮件含在线收据链接', str_contains($GLOBALS['MAILED'][0]['body'] ?? '', '/receipt?order=ord_abc123456'));

$before = count($GLOBALS['MAILED']);
$rec2 = receipt_ensure('ord_abc123456');
check('幂等：重复出票不重复发信', count($GLOBALS['MAILED']) === $before && ($rec2['receipt_no'] ?? '') === $rec['receipt_no']);

$html = receipt_html($rec, false);
check('HTML 含商品/金额/收据号', str_contains($html, '增长课·进阶') && str_contains($html, '199.00') && str_contains($html, $rec['receipt_no']));
check('HTML 含优惠行', str_contains($html, 'WELCOME20') && str_contains($html, '- ¥100.00'));
check('HTML 含打印按钮', str_contains($html, 'window.print()'));

$GLOBALS['ORDER']['status'] = 'pending';
check('未付订单不出票', receipt_ensure('ord_abc123456') === null);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
