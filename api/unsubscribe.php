<?php
/**
 * 邮件退订端点 — 一键退订（链接含 HMAC token）
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MailCampaign.php';

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

$done = false;
if ($email !== '' && $token !== '' && mailc_verify_unsub($email, $token)) {
    $done = mailc_unsubscribe($email);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>退订</title></head>
<body style="font-family:-apple-system,sans-serif;background:#f8fafc;display:grid;place-items:center;min-height:100vh;margin:0">
  <div style="background:#fff;padding:40px;border-radius:16px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.06);max-width:420px">
    <div style="font-size:40px"><?=$done ? '✅' : 'ℹ️'?></div>
    <h1 style="font-size:20px;margin:16px 0 8px"><?=$done ? '已成功退订' : ($email ? '处理失败，请重试' : '无效的退订链接')?></h1>
    <p style="color:#64748b;font-size:14px;margin:0"><?=$done ? '你将不再收到我们的邮件。如误操作，可随时重新订阅。' : '请从邮件中的退订链接访问，或联系管理员。'?></p>
  </div>
</body>
</html>
