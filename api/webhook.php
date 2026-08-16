<?php
require_once __DIR__ . '/config.php';

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

// Webhook forwarding
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
