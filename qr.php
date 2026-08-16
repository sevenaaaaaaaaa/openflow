<?php
/**
 * 二维码追踪落地页
 * 访问：/qr.php?t=qr_xxx&url=<目标URL>
 * 记录扫码后 302 到目标（带 utm_source=qr）
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/QrTrack.php';

$qrId = trim($_GET['t'] ?? '');
$target = trim($_GET['url'] ?? '');

// 校验目标 URL（只允许本站或 http(s) 外链）
if ($target && !preg_match('#^https?://#', $target)) {
    $target = site_config_get('site_url') . '/' . ltrim($target, '/');
}

if (!$qrId || !$target) {
    header('Location: /');
    exit;
}

// 记录扫码
qr_track_scan($qrId, $target, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

// 拼接 utm（避免重复）
$sep = (strpos($target, '?') !== false) ? '&' : '?';
if (strpos($target, 'utm_source=') === false) {
    $target .= $sep . 'utm_source=qr&utm_medium=' . urlencode($qrId);
}

header('Location: ' . $target, true, 302);
exit;
