<?php
/**
 * OAuth2 回调 —— 第三方授权完成后跳回这里
 *
 * 只做一件事：校验 state、用 code 换 token、存回连接、跳回后台。
 * 这里必须是已登录的后台用户（发起授权的是他），否则一个构造的回调链接
 * 能把别人的账号绑到你的连接上。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/OAuth2Client.php';

if (!is_logged_in()) { header('Location: /xmp/login'); exit; }

$code  = (string)($_GET['code'] ?? '');
$state = (string)($_GET['state'] ?? '');
$err   = (string)($_GET['error'] ?? '');

if ($err !== '') {
    $msg = '对方拒绝了授权：' . $err . (!empty($_GET['error_description']) ? '（' . $_GET['error_description'] . '）' : '');
    header('Location: /xmp/connections?msg=' . urlencode($msg) . '&kind=error'); exit;
}
$r = oauth2_finish($code, $state);
header('Location: /xmp/connections?' . ($r['ok']
    ? 'msg=' . urlencode('授权成功') . '&kind=success&edit=' . urlencode($r['conn_id'])
    : 'msg=' . urlencode($r['error']) . '&kind=error'));
exit;
