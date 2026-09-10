<?php
/**
 * 已合并：全量导出并入「数据导出」中心（/xmp/data-export）。
 * 保留本文件仅为兼容旧链接/书签，301 跳转。批D3（2026-09-10）去重。
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('export');
header('Location: /xmp/data-export', true, 301);
exit;
