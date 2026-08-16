<?php
require_once __DIR__ . '/../admin/config.php';

$apiFile = DATA_DIR . '/api_key.json';
$apiKeyData = json_read($apiFile);
$apiKey = $apiKeyData['key'] ?? '';

// If no API key set, generate one
if (!$apiKey) {
    $apiKey = bin2hex(random_bytes(16));
    json_write($apiFile, ['key' => $apiKey]);
}

$key = $_GET['key'] ?? '';
if ($key !== $apiKey) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

$format = $_GET['format'] ?? 'json';
$leads = get_leads();

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-api-' . date('Ymd') . '.csv"');
    if (!empty($leads)) {
        echo "\xEF\xBB\xBF";
        fputcsv(fopen('php://output', 'w'), array_keys($leads[0]));
        foreach ($leads as $l) fputcsv(fopen('php://output', 'w'), $l);
    }
    exit;
}

header('Content-Type: application/json; charset=utf-8');
cors_headers();
echo json_encode(['ok' => true, 'count' => count($leads), 'leads' => $leads], JSON_UNESCAPED_UNICODE);
