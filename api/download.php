<?php
/**
 * Download API — handles lead form submission + file download
 * POST /api/download.php
 * Body: { "download_id": "dl_xxx", "name": "...", "company": "...", "email": "..." }
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/FlowSystem.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$dlId = $input['download_id'] ?? '';

$downloads = json_read(DATA_DIR . '/downloads.json');
$dl = null;
foreach ($downloads as $d) { if ($d['id'] === $dlId) { $dl = $d; break; } }

if (!$dl || ($dl['status'] ?? 'draft') !== 'published') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => '资料不存在']);
    exit;
}

// Log download
$dl['download_count'] = ($dl['download_count'] ?? 0) + 1;
foreach ($downloads as &$d) { if ($d['id'] === $dlId) { $d = $dl; break; } }
json_write(DATA_DIR . '/downloads.json', $downloads);

// Save lead + 数据流联动（CDP 建档/打标/CRM/积分）
$leadLine = date('Y-m-d H:i:s') . ',下载,' . ($dl['title'] ?? '') . ',' . ($input['name'] ?? '') . ',' . ($input['email'] ?? '') . ',' . ($input['company'] ?? '') . ',' . ($input['phone'] ?? '') . ',' . ($input['title'] ?? '') . "\n";
file_put_contents(__DIR__ . '/../leads.csv', $leadLine, FILE_APPEND | LOCK_EX);
try {
    flow_lead_from_form([
        'email' => $input['email'] ?? '',
        'name' => $input['name'] ?? '',
        'phone' => $input['phone'] ?? '',
        'company' => $input['company'] ?? '',
    ], 'download:' . $dlId);
    flow_handle('download', ['email' => $input['email'] ?? '', 'label' => $dl['title'] ?? '']);
} catch (Exception $e) {}

// Return download URL
$fileUrl = $dl['file'] ?? '';
if ($fileUrl && substr($fileUrl, 0, 4) !== 'http') {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $fileUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/' . $fileUrl;
}

echo json_encode(['ok' => true, 'url' => $fileUrl, 'title' => $dl['title']], JSON_UNESCAPED_UNICODE);
