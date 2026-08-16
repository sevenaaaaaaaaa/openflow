<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('media');

header('Content-Type: application/json; charset=utf-8');

$dir = $_GET['dir'] ?? 'articles';
$allowedDirs = ['logo', 'qrcode', 'cases', 'experts', 'logos', 'articles', 'thumbs', 'general', 'documents'];
if (!in_array($dir, $allowedDirs)) $dir = 'articles';

$targetDir = UPLOAD_DIR . '/' . $dir;
if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

if (!isset($_FILES['file'])) {
    echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'Upload error: ' . $file['error']]);
    exit;
}

// File size limit (20MB for documents, 10MB for images)
$maxSize = ($dir === 'documents') ? 20 * 1024 * 1024 : 10 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['ok' => false, 'error' => '文件大小超过限制 (最大 ' . ($maxSize/1024/1024) . 'MB)']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Allowed file types based on directory
$allowedImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
$allowedDocs = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'rar'];

if ($dir === 'documents') {
    $allowed = $allowedDocs;
} else {
    $allowed = $allowedImage;
}

if (!in_array($ext, $allowed)) {
    echo json_encode(['ok' => false, 'error' => '不支持的文件格式: ' . $ext]);
    exit;
}

// MIME type validation
$allowedMimes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'gif' => 'image/gif',
    'webp' => 'image/webp', 'ico' => 'image/x-icon',
    'svg' => 'image/svg+xml',
    'pdf' => 'application/pdf',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'csv' => 'text/csv',
    'txt' => 'text/plain',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// For documents, be more lenient with MIME check (some have incorrect MIME)
if ($dir === 'documents') {
    // Skip strict MIME check for documents
} elseif (!isset($allowedMimes[$ext]) || $mimeType !== $allowedMimes[$ext]) {
    echo json_encode(['ok' => false, 'error' => '文件类型不匹配']);
    exit;
}

// Magic bytes validation
$handle = fopen($file['tmp_name'], 'rb');
$header = fread($handle, 8);
fclose($handle);

$validSignatures = [
    'jpg' => ["\xff\xd8\xff"],
    'jpeg' => ["\xff\xd8\xff"],
    'png' => ["\x89PNG\r\n\x1a\n"],
    'gif' => ["GIF87a", "GIF89a"],
    'webp' => ["RIFF"],
    'ico' => ["\x00\x00\x01\x00"],
    'svg' => ["<svg", "<?xml"],
];

$valid = false;
if (isset($validSignatures[$ext])) {
    foreach ($validSignatures[$ext] as $sig) {
        if (substr($header, 0, strlen($sig)) === $sig) {
            $valid = true;
            break;
        }
    }
}

if (!$valid) {
    echo json_encode(['ok' => false, 'error' => '文件内容无效']);
    exit;
}

$name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest = $targetDir . '/' . $name;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
    exit;
}

// SVG sanitization
if ($ext === 'svg') {
    $svgContent = file_get_contents($dest);
    // Remove script tags and event handlers
    $svgContent = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $svgContent);
    $svgContent = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $svgContent);
    $svgContent = preg_replace('/javascript\s*:/i', '', $svgContent);
    file_put_contents($dest, $svgContent);
}

$url = SITE_URL . '/uploads/' . $dir . '/' . $name;
echo json_encode(['ok' => true, 'url' => $url, 'name' => $name, 'path' => 'uploads/' . $dir . '/' . $name]);
