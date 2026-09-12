<?php
/**
 * 收据页 — /receipt?order={id}（A3）
 *
 * 支付成功后凭订单归属查看 / 打印收据。归属校验：订单买家本人 或 有订单管理权限的后台用户。
 * 收据由 ReceiptSystem 幂等生成（支付回调时已出票 + 发邮件），本页仅展示。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/ShopSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/ReceiptSystem.php';

$orderId = trim((string)($_GET['order'] ?? ''));
$order = $orderId !== '' ? shop_get_order($orderId) : null;

// 归属校验
$member = member_current();
$isOwner = $order && $member && (string)($order['member_id'] ?? '') === (string)($member['id'] ?? '');
$isStaff = function_exists('has_perm') && (has_perm('shop-settings') || has_perm('commerce'));

if (!$order || (!$isOwner && !$isStaff)) {
    http_response_code($order ? 403 : 404);
    $msg = $order ? '你没有权限查看该收据。' : '收据不存在。';
    ?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>收据</title>
    <style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f9fafb;color:#111}
    .b{text-align:center;padding:40px}.b a{color:#2563eb;text-decoration:none}</style></head>
    <body><div class="b"><h1>无法打开收据</h1><p><?=htmlspecialchars($msg)?></p><p><a href="/">返回首页</a></p></div></body></html><?php
    exit;
}

if (($order['status'] ?? '') !== 'paid') {
    ?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>收据</title>
    <style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f9fafb;color:#111}
    .b{text-align:center;padding:40px}</style></head>
    <body><div class="b"><h1>订单尚未支付</h1><p>收据将在支付成功后生成。</p><p><a href="/order-success?order=<?=urlencode($orderId)?>">返回订单</a></p></div></body></html><?php
    exit;
}

$rec = receipt_get($orderId) ?: receipt_ensure($orderId);
if (!$rec) { http_response_code(500); exit('收据生成失败'); }

$site = (string)site_config_get('site_name');
?><!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>收据 <?=htmlspecialchars($rec['receipt_no'])?> · <?=htmlspecialchars($site)?></title>
<style>
  body{margin:0;background:#f3f4f6;padding:32px 16px;-webkit-font-smoothing:antialiased}
</style>
</head>
<body>
<?=receipt_html($rec, false)?>
</body>
</html>
