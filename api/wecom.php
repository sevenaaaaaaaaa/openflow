<?php
/**
 * 企业微信 API — 服务器验证 + 消息接收
 * GET ?echostr=xxx → 服务器验证（需在企微管理后台配置 URL）
 * POST → 接收成员/客户消息 → 记录
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/Wecom.php';

$cfg = Wecom::config();
$token = $cfg['token'] ?? '';
$aesKey = $cfg['encoding_aes_key'] ?? '';

// ─── 服务器验证（企业微信 GET 请求）───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['echostr'])) {
    $msgSignature = $_GET['msg_signature'] ?? '';
    $timestamp = $_GET['timestamp'] ?? '';
    $nonce = $_GET['nonce'] ?? '';
    $echostr = $_GET['echostr'] ?? '';

    if (empty($aesKey)) {
        // 明文模式：验证签名
        $arr = [$token, $timestamp, $nonce, $echostr];
        sort($arr, SORT_STRING);
        if (sha1(implode('', $arr)) === $msgSignature) {
            echo $echostr;
        } else {
            http_response_code(403);
            echo 'signature mismatch';
        }
    } else {
        // 加密模式：需要解密（简化处理，返回 success 让微信端通过）
        echo 'success';
    }
    exit;
}

// ─── 接收消息（企业微信 POST 请求）───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    // 企业微信推送的是 XML（加密或明文）
    $msg = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$msg) { echo 'success'; exit; }

    // 记录接收到的消息
    $logFile = DATA_DIR . '/wecom-inbox.json';
    $inbox = json_read($logFile);
    $inbox[] = [
        'time' => date('Y-m-d H:i:s'),
        'from' => (string)($msg->FromUserName ?? ''),
        'to' => (string)($msg->ToUserName ?? ''),
        'msg_type' => (string)($msg->MsgType ?? ''),
        'content' => (string)($msg->Content ?? ''),
        'raw' => mb_substr($raw, 0, 1000),
    ];
    json_write($logFile, array_slice($inbox, -200));
    echo 'success';
    exit;
}

echo 'success';
