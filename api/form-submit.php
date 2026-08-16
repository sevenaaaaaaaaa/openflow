<?php
/**
 * Unified Form Submission API
 *
 * POST /api/form-submit.php
 * Body: { "slug": "form-slug", "data": { "name": "...", "email": "...", ... } }
 *
 * Returns: { "ok": true, "message": "...", "redirect": "..." }
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../admin/ma-sync-lib.php';
require_once __DIR__ . '/../lib/AutomationSystem.php';
require_once __DIR__ . '/../lib/CanvasSystem.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_once __DIR__ . '/../lib/FlowSystem.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$slug = trim($input['slug'] ?? $input['form_slug'] ?? '');
$formData = $input['data'] ?? [];
// 兼容平铺提交：FormData 直接 POST 字段（form_slug + 各字段平铺）
if (empty($formData) && $slug) {
    foreach ($input as $k => $v) {
        if ($k === 'slug' || $k === 'form_slug') continue;
        if (is_array($v) && isset($v[0])) $formData[$k] = $v[0]; else $formData[$k] = $v;
    }
}

if (empty($slug) || empty($formData)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => '缺少表单 slug 或提交数据']);
    exit;
}

// Find form by slug
$forms = json_read(DATA_DIR . '/forms/index.json');
$form = null;
foreach ($forms as $f) {
    if (($f['slug'] ?? '') === $slug && ($f['status'] ?? 'draft') === 'published') {
        $form = $f;
        break;
    }
}

if (!$form) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => '表单不存在或未发布']);
    exit;
}

// Validate required fields
$errors = [];
foreach (($form['fields'] ?? []) as $fld) {
    if (!empty($fld['required']) && empty($formData[$fld['key']] ?? '')) {
        $errors[] = $fld['label'] . ' 为必填项';
    }
}
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => implode('; ', $errors), 'errors' => $errors]);
    exit;
}

// Honeypot check
if (!empty($formData['website'])) {
    echo json_encode(['ok' => true, 'message' => $form['success_message'] ?? '提交成功', 'honeypot' => true]);
    exit;
}

// Save submission
$submission = [
    'id' => 'sub_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
    'form_id' => $form['id'],
    'slug' => $slug,
    'type' => $form['type'],
    'data' => $formData,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'created_at' => date('Y-m-d H:i:s'),
];

$subsFile = DATA_DIR . '/submissions/index.json';
$subs = json_read($subsFile);
$subs[] = $submission;
json_write($subsFile, $subs);
PluginSystem::do_action('form_submitted', $form['id'], $form['type'], $formData, $submission);

// CRM：表单提交自动生成线索 + 数据流联动（CDP 建档/打标/自动化）
try { crm_from_submission($submission); } catch (Exception $e) {}
try {
    if (!empty($formData['email'])) {
        flow_lead_from_form([
            'email' => $formData['email'],
            'name' => $formData['name'] ?? '',
            'phone' => $formData['phone'] ?? '',
            'company' => $formData['company'] ?? '',
        ], $form['id'] ?? '');
    }
} catch (Exception $e) {}

// 行为追踪事件
try {
    Database::insert('events', [
        'event' => 'form_submit',
        'label' => $form['title'] ?? '',
        'variant' => '',
        'page' => (($_SERVER['HTTP_REFERER'] ?? '') ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) : '/'),
        'uid' => $_COOKIE['fc_uid'] ?? '',
        'member_id' => $_SESSION['member_id'] ?? '',
        'member_email' => $formData['email'] ?? '',
        'props' => json_encode(['form_id'=>$form['id'],'type'=>$form['type']], JSON_UNESCAPED_UNICODE),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
    ]);
} catch (Exception $e) {}

// Also save to leads.csv for backward compatibility
$leadLine = date('Y-m-d H:i:s') . ',' . $form['type'] . ',' . $form['title'] . ','
    . ($formData['name'] ?? '') . ',' . ($formData['email'] ?? '') . ',' . ($formData['company'] ?? '') . ','
    . ($formData['phone'] ?? '') . ',' . ($formData['title'] ?? '') . ',' . json_encode($formData, JSON_UNESCAPED_UNICODE) . "\n";
file_put_contents(__DIR__ . '/../leads.csv', $leadLine, FILE_APPEND | LOCK_EX);

// ─── Type-specific handling ───

$response = ['ok' => true, 'message' => $form['success_message'] ?? '提交成功'];

// ─── MA 融合同步（Mautic + BillionMail）───
ma_sync_form_submission($form, $formData);
$syncCfg = ma_sync_config();
if (!empty($syncCfg['enabled']) && (!empty($syncCfg['mautic_enabled']) || !empty($syncCfg['bm_enabled']))) {
    $response['synced'] = true;
}

// ─── 营销自动化触发 ───
automation_trigger('form_submit', [
    'form_slug' => $slug,
    'form_type' => $form['type'],
    'email' => $formData['email'] ?? '',
    'name' => $formData['name'] ?? '',
    'company' => $formData['company'] ?? '',
    'phone' => $formData['phone'] ?? '',
]);
canvas_trigger('form_submit', [
    'form_slug' => $slug,
    'form_type' => $form['type'],
    'email' => $formData['email'] ?? '',
    'name' => $formData['name'] ?? '',
    'company' => $formData['company'] ?? '',
]);

// Type: download — return download URL
if ($form['type'] === 'download') {
    $downloadSlug = $form['download_slug'] ?? $formData['download_slug'] ?? '';
    if ($downloadSlug) {
        $downloads = json_read(DATA_DIR . '/downloads.json');
        foreach ($downloads as $d) {
            if (($d['slug'] ?? '') === $downloadSlug && ($d['status'] ?? 'draft') === 'published') {
                $fileUrl = $d['file'] ?? '';
                if ($fileUrl && substr($fileUrl, 0, 4) !== 'http') {
                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
                    $fileUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/' . $fileUrl;
                }
                $response['download_url'] = $fileUrl;
                $response['download_title'] = $d['title'];

                // Increment download count
                $d['download_count'] = ($d['download_count'] ?? 0) + 1;
                foreach ($downloads as &$dd) { if ($dd['id'] === $d['id']) { $dd = $d; break; } }
                json_write(DATA_DIR . '/downloads.json', $downloads);
                break;
            }
        }
    }
}

// Webhook
if (!empty($form['webhook_url'])) {
    $ch = curl_init($form['webhook_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'event' => 'form_submit',
            'form' => $form['title'],
            'type' => $form['type'],
            'data' => $formData,
            'time' => date('Y-m-d H:i:s'),
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
}

// Create notification
notify($form['type'], "新{$form['title']}提交", ($formData['name'] ?? '匿名') . ' · ' . ($formData['company'] ?? '') . ' · ' . ($formData['email'] ?? $formData['phone'] ?? ''), 'admin/submissions.php?form_id=' . $form['id']);

echo json_encode($response, JSON_UNESCAPED_UNICODE);
