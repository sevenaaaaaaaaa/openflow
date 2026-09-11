<?php
require_once __DIR__ . '/config.php';
// 清除开发标记 cookie（前台的浏览将恢复计入统计）
setcookie('of_dev', '', time() - 3600, '/', '', !empty($_SERVER['HTTPS']), false);
session_destroy();
header('Location: /xmp/login');
exit;
