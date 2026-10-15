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

// ─── 第三方数据源导入（P1-2）：Segment / GA4 / 神策 格式感知 ───
// 不配 Inbound connector、但 payload 是已知第三方格式 → 归一化落 CDP（幂等）
if (!empty($data)) {
    $fmt = function_exists('ingest_detect_format') ? ingest_detect_format($data) : 'generic';
    if ($fmt !== 'generic') {
        require_once __DIR__ . '/../lib/IngestAdapters.php';
        // 兼容：预留一条空白连接器路径，格式自动识别走 ingest
        $r = ingest_handle_formatted($data);
        if ($r['ok']) {
            json_out(['ok' => true, 'message' => 'Formatted ingest processed', 'detail' => $r]);
        } else {
            json_out(['ok' => false, 'message' => $r['error'] ?? 'ingest failed'], 400);
        }
    }
}

// ─── 入站连接器处理（InboundReceiver） ───
// 通过 header X-Inbound-Id 指定连接器，X-Inbound-Signature 携带 HMAC 签名
$inboundId = $_SERVER['HTTP_X_INBOUND_ID'] ?? '';
if ($inboundId !== '') {    $conn = inbound_connector($inboundId);
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

// ─── Insight Flow 洞察误投保护 ───
// 没带 X-Inbound-Id 的 IF 洞察以前会走下面的传统分支并返回 ok:true（静默丢弃）。
// 契约 v1.1：禁止假成功——明确 400 并指向插件 inbox。
if (is_array($data) && function_exists('inbound_looks_like_insight') && inbound_looks_like_insight($data)) {
    json_out(['ok' => false, 'message' => 'Insight Flow 洞察请推送到 POST /api/plugin/insight-flow/inbox（insight.upsert，X-IF-Signature 验签），或带 X-Inbound-Id 指向 type=insight 的入站连接器'], 400);
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
