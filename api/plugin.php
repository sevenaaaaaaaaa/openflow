<?php
/**
 * 插件 API 路由分发器 — /api/plugin/{插件ID}/{路径}
 *
 * 插件用 PluginSystem::register_api_route() 注册端点，这里统一分发。
 * 鉴权、异常旁路、JSON 包装都在 PluginSystem::dispatch_api_route() 里完成。
 */
require_once __DIR__ . '/../admin/config.php';
header('Content-Type: application/json; charset=utf-8');

$pluginId = preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['plugin'] ?? ''));
$path = (string)($_GET['path'] ?? '');

if ($pluginId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '缺少插件 ID']);
    exit;
}

// 插件必须已启用才允许路由（禁用即断 API；与加载器一致：未显式禁用时默认启用）
$registry = json_read(DATA_DIR . '/plugins.json');
$plugins = PluginSystem::get_plugins();
$meta = $plugins[$pluginId] ?? null;
$enabled = $registry['enabled'][$pluginId] ?? ($meta['enabled_by_default'] ?? true);
if (!$meta || !$enabled) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => '插件不存在或未启用']);
    exit;
}

$handled = PluginSystem::dispatch_api_route($pluginId, $_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
if (!$handled) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => '该插件未注册此端点']);
}
