<?php
/**
 * 语言包 API — 前端加载当前语言翻译字典
 * 用法：/api/lang.php?locale=en
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$loc = $_GET['locale'] ?? i18n_default_locale();
$supported = i18n_supported();
if (!in_array($loc, $supported, true)) $loc = i18n_default_locale();

$file = DATA_DIR . '/lang/' . $loc . '.json';
$d = is_file($file) ? (json_read($file) ?: []) : [];
echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
