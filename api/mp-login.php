<?php
/**
 * 小程序登录 — wx.login 获取 code → code2session
 * POST /api/mp-login.php
 * Body: { "code": "wx.login 返回的 code", "userInfo": { "nickName": "...", "avatarUrl": "..." } }
 *
 * Response: { "ok": true, "openid": "xxx", "session_key": "xxx", "token": "mp_xxx" }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$cfg = json_read(DATA_DIR . '/wechat.json');
$mpCfg = $cfg['miniprogram'] ?? [];
$appid = $mpCfg['appid'] ?? '';
$secret = $mpCfg['secret'] ?? '';

if (empty($appid) || empty($secret)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => '小程序未配置'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$code = trim($input['code'] ?? '');

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '缺少 code 参数'], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$code}&grant_type=authorization_code";

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$resp = curl_exec($ch);

$data = json_decode($resp, true);

if (empty($data) || isset($data['errcode'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => ($data['errmsg'] ?? '登录失败'),
        'code' => $data['errcode'] ?? -1,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$openid = $data['openid'] ?? '';
$sessionKey = $data['session_key'] ?? '';

// Generate a server-side token for subsequent API calls
$token = 'mp_' . bin2hex(random_bytes(16));
$users = json_read(DATA_DIR . '/mp_users.json');
$users[$openid] = [
    'token' => $token,
    'session_key' => $sessionKey,
    'last_login' => date('Y-m-d H:i:s'),
    'user_info' => $input['userInfo'] ?? [],
];
json_write(DATA_DIR . '/mp_users.json', $users);

echo json_encode([
    'ok' => true,
    'openid' => $openid,
    'token' => $token,
], JSON_UNESCAPED_UNICODE);
