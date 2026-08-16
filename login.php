<?php
/**
 * 登录兜底页 — 支持无 rewrite 环境
 * /login.php?key=xxx  → 转发到 admin/login.php
 * 若服务器已配置 rewrite，/login 会直接走此文件
 */
$key = trim($_GET['key'] ?? '');
$url = 'admin/login.php';
if ($key) $url .= '?key=' . urlencode($key);
header('Location: ' . $url);
exit;
