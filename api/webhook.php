<?php
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/InboundReceiver.php';

$apiKeyData = json_read(DATA_DIR . '/api_key.json');
if (empty($apiKeyData['key'])) {
    $apiKeyData['key'] = bin2hex(random_bytes(16));
    json_write(DATA_DIR . '/api_key.json', $apiKeyData);
}

$webhookFile = DATA_DIR . '/webhook.json';
$webhook = json_read($webhookFile);

// Read new lead data from POST (aliased same as form-handler.php)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;
if (empty($data)) {
    json_out(['ok' => false, 'message' => 'No data received'], 400);
}

// ─── 入站连接器处理（InboundReceiver） ───
// 通过 header X-Inbound-Id 指定连接器，X-Inbound-Signature 携带 HMAC 签名
$inboundId = $_SERVER['HTTP_X_INBOUND_ID'] ?? '';
if ($inboundId !== '') {
    $conn = inbound_connector($inboundId);
    if (!$conn) json_out(['ok' => false, 'message' => 'Inbound connector not found'], 404);
    if (!empty($conn['secret'])) {
        $sig = $_SERVER['HTTP_X_INBOUND_SIGNATURE'] ?? '';
        if (!inbound_verify($raw, $sig, $conn['secret'])) {
            inbound_log($inboundId, ['status' => 'auth_fail', 'type' => $conn['type'] ?? 'lead', 'error' => '签名校验失败']);
            json_out(['ok' => false, 'message' => 'Signature verification failed'], 401);
        }
    }
    $r = inbound_handle($inboundId, $data);
    if (!$r['ok']) json_out(['ok' => false, 'message' => $r['error'] ?? '处理失败'], 400);
    json_out(['ok' => true, 'message' => 'Inbound processed', 'detail' => $r]);
}

// ─── 传统 Webhook 转发（保持向后兼容） ───
if (!empty($webhook['url'])) {
    $payload = json_encode([
        'event' => 'new_lead',
        'time' => date('Y-m-d H:i:s'),
        'data' => $data,
        'secret' => $webhook['secret'] ?? '',
    ]);
    $ch = curl_init($webhook['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
}

json_out(['ok' => true, 'message' => 'Webhook triggered']);

function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
