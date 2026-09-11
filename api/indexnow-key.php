<?php
/**
 * IndexNow key 验证文件 — 动态输出，替代物理 {key}.txt
 * 路由：.htaccess 把 /{32位hex}.txt 重写到本文件
 * 好处：key 存在 data/indexnow.json，部署/rsync 不会丢验证文件
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../admin/seo-functions.php';

$key = preg_replace('/[^a-f0-9]/', '', (string)($_GET['key'] ?? ''));
$cfg = indexnow_config();

if ($key === '' || $key !== ($cfg['key'] ?? '')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'not found';
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo $key;
