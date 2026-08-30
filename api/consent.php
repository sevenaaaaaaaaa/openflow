<?php
/**
 * 同意管理 API（公开）
 *   GET  /api/consent.php            → 横幅配置（是否需要、文案、模式）
 *   POST /api/consent.php action=set choice=granted|denied → 写 cookie
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ConsentSystem.php';

header('Content-Type: application/json; charset=utf-8');
if (function_exists('cors_headers')) cors_headers();
header('Cache-Control: no-cache');

if (($_POST['action'] ?? '') === 'set') {
    $choice = ($_POST['choice'] ?? '') === 'granted' ? 'granted' : 'denied';
    // 1 年有效；SameSite=Lax，仅本站
    setcookie('of_consent', $choice, [
        'expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'), 'httponly' => false,
    ]);
    echo json_encode(['ok' => true, 'choice' => $choice]);
    exit;
}

echo json_encode(['ok' => true] + consent_banner_config(), JSON_UNESCAPED_UNICODE);
