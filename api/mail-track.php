<?php
/**
 * 邮件打开/点击统计端点
 * 打开：返回 1x1 透明 gif
 * 点击：302 跳转到目标 URL
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MailCampaign.php';

$campaign = trim($_GET['c'] ?? 'newsletter');
$emailId = trim($_GET['e'] ?? '');
$type = $_GET['t'] ?? 'open';

if ($campaign !== '' && $emailId !== '') {
    try { mailc_track($campaign, $emailId, $type === 'click' ? 'click' : 'open'); } catch (Throwable $e) {}
}

if ($type === 'click') {
    $url = $_GET['u'] ?? '/';
    $decoded = urldecode($url);
    if (preg_match('#^https?://#i', $decoded)) {
        header('Location: ' . $decoded, true, 302);
        exit;
    }
    header('Location: /');
    exit;
}

// 打开：返回 1x1 gif
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
// 43 字节透明 1x1 gif
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit;
