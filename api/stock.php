<?php
/**
 * 免费图库 API 代理 — Pexels / Unsplash / Pixabay
 *
 * 用途：服务端代转发搜索请求，避免在前端暴露 API key；
 *      并支持把图片下载到本地 uploads 目录。
 *
 * 用法：
 *   GET  /api/stock.php?action=search&platform=pexels&q=office&page=1&per_page=12
 *   GET  /api/stock.php?action=config                  → 返回各平台是否已配置
 *   POST /api/stock.php?action=download                 → 下载图片到本地
 *        Body: { "platform":"pexels", "url":"https://...", "filename":"cover.jpg" }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$cfgFile = DATA_DIR . '/stock.json';
$cfg = json_read($cfgFile);

$action = $_GET['action'] ?? 'config';

// ─── 配置状态 ───
if ($action === 'config') {
    echo json_encode([
        'ok' => true,
        'pexels'  => !empty($cfg['pexels_key']),
        'unsplash'=> !empty($cfg['unsplash_key']),
        'pixabay' => !empty($cfg['pixabay_key']),
    ]);
    exit;
}

// 未配置时给出提示
$key = $cfg[$_GET['platform'] . '_key'] ?? '';
if (empty($key)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '该平台 API Key 未配置，请先在图库设置中填写'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── 搜索 ───
if ($action === 'search') {
    $platform = $_GET['platform'] ?? '';
    $q = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(30, max(1, (int)($_GET['per_page'] ?? 12)));
    if (empty($q)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请输入搜索关键词']); exit; }

    switch ($platform) {
        case 'pexels':
            $ch = curl_init('https://api.pexels.com/v1/search?query=' . urlencode($q) . '&per_page=' . $perPage . '&page=' . $page);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: ' . $key], CURLOPT_TIMEOUT => 15]);
            $resp = json_decode(curl_exec($ch), true);
            $photos = array_map(fn($p) => [
                'id' => 'pexels_' . $p['id'], 'platform' => 'pexels',
                'thumb' => $p['src']['small'] ?? '', 'medium' => $p['src']['medium'] ?? '',
                'full' => $p['src']['large2x'] ?? ($p['src']['original'] ?? ''),
                'width' => $p['width'] ?? 0, 'height' => $p['height'] ?? 0,
                'alt' => $p['alt'] ?? '', 'photographer' => $p['photographer'] ?? '',
                'page_url' => $p['url'] ?? '',
            ], $resp['photos'] ?? []);
            $total = $resp['total_results'] ?? 0;
            echo json_encode(['ok' => true, 'platform' => 'pexels', 'total' => $total, 'page' => $page, 'photos' => $photos], JSON_UNESCAPED_UNICODE);
            break;

        case 'unsplash':
            $ch = curl_init('https://api.unsplash.com/search/photos?query=' . urlencode($q) . '&page=' . $page . '&per_page=' . $perPage);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Client-ID ' . $key, 'Accept-Version: v1'], CURLOPT_TIMEOUT => 15]);
            $resp = json_decode(curl_exec($ch), true);
            $photos = array_map(fn($p) => [
                'id' => 'unsplash_' . $p['id'], 'platform' => 'unsplash',
                'thumb' => $p['urls']['small'] ?? '', 'medium' => $p['urls']['regular'] ?? '',
                'full' => $p['urls']['full'] ?? ($p['urls']['raw'] ?? ''),
                'width' => $p['width'] ?? 0, 'height' => $p['height'] ?? 0,
                'alt' => $p['alt_description'] ?? ($p['description'] ?? ''), 'photographer' => $p['user']['name'] ?? '',
                'page_url' => $p['links']['html'] ?? '',
            ], $resp['results'] ?? []);
            $total = $resp['total'] ?? 0;
            echo json_encode(['ok' => true, 'platform' => 'unsplash', 'total' => $total, 'page' => $page, 'photos' => $photos], JSON_UNESCAPED_UNICODE);
            break;

        case 'pixabay':
            $ch = curl_init('https://pixabay.com/api/?key=' . urlencode($key) . '&q=' . urlencode($q) . '&page=' . $page . '&per_page=' . $perPage . '&image_type=photo&safesearch=true');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
            $resp = json_decode(curl_exec($ch), true);
            $photos = array_map(fn($p) => [
                'id' => 'pixabay_' . $p['id'], 'platform' => 'pixabay',
                'thumb' => $p['previewURL'] ?? '', 'medium' => $p['webformatURL'] ?? '',
                'full' => $p['largeImageURL'] ?? ($p['imageURL'] ?? ''),
                'width' => $p['imageWidth'] ?? 0, 'height' => $p['imageHeight'] ?? 0,
                'alt' => $p['tags'] ?? '', 'photographer' => $p['user'] ?? '',
                'page_url' => $p['pageURL'] ?? '',
            ], $resp['hits'] ?? []);
            $total = $resp['totalHits'] ?? 0;
            echo json_encode(['ok' => true, 'platform' => 'pixabay', 'total' => $total, 'page' => $page, 'photos' => $photos], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => '不支持的平台']); exit;
    }
    exit;
}

// ─── 下载到本地 ───
if ($action === 'download') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $url = trim($input['url'] ?? '');
    $dir = preg_replace('/[^a-z0-9_-]/', '', $input['dir'] ?? 'general');
    if (empty($url)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'缺少图片 URL']); exit; }

    // 仅允许从三个平台的域名下载
    $host = parse_url($url, PHP_URL_HOST);
    $allowedHosts = ['images.pexels.com','images.unsplash.com','pixabay.com','cdn.pixabay.com','cdn.pixabay.com.au'];
    $okHost = false;
    foreach ($allowedHosts as $h) if (strpos($host ?? '', $h) !== false) $okHost = true;
    if (!$okHost) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'只允许下载 Pexels/Unsplash/Pixabay 图片']); exit; }

    $ch = curl_init($url);
    $fp = tmpfile();
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OpenFlow-CMS)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    if ($http !== 200) { fclose($fp); http_response_code(502); echo json_encode(['ok'=>false,'error'=>"下载失败 HTTP $http"]); exit; }

    // 确定扩展名
    $extMap = ['image/jpeg'=>'jpg','image/jpg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/svg+xml'=>'svg'];
    $ext = $extMap[strtolower(trim(explode(';', $contentType ?? '')[0]))] ?? 'jpg';

    $targetDir = UPLOAD_DIR . '/' . $dir;
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    $name = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
    $target = $targetDir . '/' . $name;

    $meta = stream_get_meta_data($fp);
    copy($meta['uri'], $target);
    fclose($fp);

    if (!file_exists($target) || filesize($target) === 0) {
        @unlink($target);
        http_response_code(502);
        echo json_encode(['ok'=>false,'error'=>'保存失败']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'path' => 'uploads/' . $dir . '/' . $name,
        'url' => SITE_URL . '/uploads/' . $dir . '/' . $name,
        'size' => filesize($target),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(['ok' => false, 'error' => '未知操作']);
