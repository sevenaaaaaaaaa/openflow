<?php
/**
 * 埋点 SDK 版本化交付端点
 * 通过版本管理 + 灰度发布提供 cdp-track.js
 *
 * GET /api/sdk.php?version=2        → 指定版本
 * GET /api/sdk.php                   → 当前默认版本（含灰度分流）
 * 附带 header: X-SDK-Version
 *
 * 版本存储：data/sdk-versions.json
 * {
 *   "versions": [
 *     {"version": 2, "file": "/assets/cdp-track.js", "note": "v2 公共属性+批量", "enabled": true, "weight": 100},
 *     {"version": 1, "file": "/assets/cdp-track-v1.js", "note": "v1 基础埋点", "enabled": true, "weight": 0}
 *   ],
 *   "default": 2
 * }
 */
require_once __DIR__ . '/../admin/config.php';

cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$cfgFile = DATA_DIR . '/sdk-versions.json';
$cfg = json_read($cfgFile);
$versions = $cfg['versions'] ?? [];
$defaultVer = (int)($cfg['default'] ?? 2);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store');

$requested = (int)($_GET['version'] ?? 0);

// 确定要服务的版本
$serveVersion = $defaultVer;
if ($requested > 0) {
    $serveVersion = $requested;
} else {
    // 灰度分流：按权重选择版本
    $r = random_int(1, 100);
    $cumulative = 0;
    foreach ($versions as $v) {
        if (empty($v['enabled'])) continue;
        $cumulative += (int)($v['weight'] ?? 0);
        if ($r <= $cumulative) { $serveVersion = (int)$v['version']; break; }
    }
}

// 查找版本文件
$file = '';
foreach ($versions as $v) {
    if ((int)$v['version'] === $serveVersion && !empty($v['file'])) { $file = $v['file']; break; }
}
if (!$file) $file = '/assets/cdp-track.js';

// 检查文件存在
$fsPath = __DIR__ . '/..' . $file;
if (!file_exists($fsPath)) {
    http_response_code(404);
    echo '/* SDK file not found */';
    exit;
}

header('X-SDK-Version: ' . $serveVersion);
// 附带头部注释，方便前端判断版本
echo "/* OpenFlow CDP SDK v{$serveVersion} */\n";
readfile($fsPath);
