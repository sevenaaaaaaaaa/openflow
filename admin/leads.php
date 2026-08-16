<?php
/**
 * 线索管理已合并至 CRM 线索（admin/crm.php?tab=raw）
 * 本文件保留作跳转兼容
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('leads');
header('Location: /xmp/crm?tab=raw');
exit;
