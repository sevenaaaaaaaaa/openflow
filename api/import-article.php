<?php
/**
 * Article Import API — import from URL (web / WeChat article)
 * POST /api/import-article.php
 * Body: { "url": "https://...", "download_images": true }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$url = trim($input['url'] ?? '');
$downloadImages = !isset($input['download_images']) || $input['download_images'];

if (empty($url)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '缺少 URL']);
    exit;
}

// Fetch the page
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode !== 200 || empty($html)) {
    echo json_encode(['ok' => false, 'error' => "无法获取页面 (HTTP $httpCode)"]);
    exit;
}

// Extract title
$title = '';
if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
    $title = trim($m[1]);
}
// Remove site name suffix from title
$title = preg_replace('/\s*[—\-|·]\s*.+$/', '', $title);
$title = trim($title);

// Try to extract meta description
$description = '';
if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
    $description = trim($m[1]);
}

// Extract main content — try common article containers
$content = '';
$patterns = [
    '/<article[^>]*>([\s\S]+?)<\/article>/i',
    '/<div[^>]*class=["\'][^"\']*(?:article|post|content|main|rich_media|rich_media_content)[^"\']*["\'][^>]*>([\s\S]+?)<\/div>\s*<\/div>/i',
    '/<div[^>]*id=["\'][^"\']*(?:article|post|content|main|js_content)[^"\']*["\'][^>]*>([\s\S]+?)<\/div>/i',
    '/<section[^>]*class=["\'][^"\']*rich_media_content[^"\']*["\'][^>]*>([\s\S]+?)<\/section>/i',
];

foreach ($patterns as $pattern) {
    if (preg_match($pattern, $html, $m)) {
        $content = $m[1];
        break;
    }
}

// Fallback: try to get body content
if (empty($content)) {
    if (preg_match('/<body[^>]*>([\s\S]+?)<\/body>/i', $html, $m)) {
        $content = $m[1];
    } else {
        $content = $html;
    }
}

// ─── Clean HTML ───
// Remove scripts, styles, iframes, comments
$content = preg_replace('/<script[^>]*>[\s\S]*?<\/script>/i', '', $content);
$content = preg_replace('/<style[^>]*>[\s\S]*?<\/style>/i', '', $content);
$content = preg_replace('/<iframe[^>]*>[\s\S]*?<\/iframe>/i', '', $content);
$content = preg_replace('/<!--[\s\S]*?-->/', '', $content);
$content = preg_replace('/<nav[^>]*>[\s\S]*?<\/nav>/i', '', $content);
$content = preg_replace('/<footer[^>]*>[\s\S]*?<\/footer>/i', '', $content);
$content = preg_replace('/<header[^>]*>[\s\S]*?<\/header>/i', '', $content);
$content = preg_replace('/<aside[^>]*>[\s\S]*?<\/aside>/i', '', $content);

// Remove common WeChat public account noise
$content = preg_replace('/<div[^>]*class=["\'][^"\']*rich_media_area_extra[^"\']*["\'][^>]*>[\s\S]*?<\/div>/i', '', $content);
$content = preg_replace('/<div[^>]*class=["\'][^"\']*rich_media_tool[^"\']*["\'][^>]*>[\s\S]*?<\/div>/i', '', $content);

// ─── Process Images ───
$downloadedImages = [];
$baseUrl = preg_replace('/\/[^\/]+$/', '', $url);

if ($downloadImages) {
    $content = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function($m) use ($baseUrl, &$downloadedImages) {
        $src = $m[1];
        // Skip data URIs and already local images
        if (strpos($src, 'data:') === 0 || strpos($src, 'uploads/') === 0) return $m[0];

        // Make absolute URL
        if (strpos($src, '//') === 0) $src = 'https:' . $src;
        elseif (strpos($src, '/') === 0) {
            $parsed = parse_url($baseUrl);
            $src = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . $src;
        } elseif (strpos($src, 'http') !== 0) {
            $src = rtrim($baseUrl, '/') . '/' . ltrim($src, '/');
        }

        // Download image
        $ext = strtolower(pathinfo(parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) $ext = 'jpg';
        $name = date('Ymd_His') . '_' . substr(md5($src), 0, 8) . '.' . $ext;
        $dest = UPLOAD_DIR . '/articles/' . $name;

        $imgCh = curl_init($src);
        curl_setopt_array($imgCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ]);
        $imgData = curl_exec($imgCh);
        $imgHttp = curl_getinfo($imgCh, CURLINFO_HTTP_CODE);

        if ($imgHttp === 200 && $imgData && strlen($imgData) > 1000) {
            file_put_contents($dest, $imgData);
            $localUrl = '/uploads/articles/' . $name;
            $downloadedImages[] = $src . ' → ' . $localUrl;
            return str_replace($src, $localUrl, $m[0]);
        }
        return $m[0]; // Keep original if download fails
    }, $content);
} else {
    // Keep original URLs but mark them
    $content = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function($m) {
        $src = $m[1];
        if (strpos($src, '//') === 0) {
            return str_replace($src, 'https:' . $src, $m[0]);
        }
        return $m[0];
    }, $content);
}

// Clean up excessive whitespace
$content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
$content = trim($content);

// Limit title length
$title = mb_substr($title, 0, 80);

echo json_encode([
    'ok' => true,
    'title' => $title,
    'description' => $description,
    'content' => $content,
    'images_downloaded' => count($downloadedImages),
    'downloaded' => $downloadedImages,
    'source_url' => $url,
], JSON_UNESCAPED_UNICODE);
