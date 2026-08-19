<?php
/**
 * 统一行为追踪 API — fcTrack 上报
 * POST /api/track.php
 * Body: { event: "page_view"|"click"|"form_submit"|..., props: {...}, webhook: "可选覆盖" }
 *
 * 写入 SQLite events 表，并支持 Webhook 回传到第三方工具
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_once __DIR__ . '/../lib/FlowSystem.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$event = trim($input['event'] ?? '');
if (empty($event)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'event 不能为空']); exit; }

$uid = $_COOKIE['fc_uid'] ?? '';
if ($uid === '') {
    $uid = 'u_' . bin2hex(random_bytes(8));
    setcookie('fc_uid', $uid, time() + 86400 * 365, '/');
}

// Tracking Plan 校验（数据质量监控，不拦截）
try {
    $issues = EventDictionary::validate($event, $input['props'] ?? []);
    if (!empty($issues)) EventDictionary::logQualityIssue($event, $issues);
} catch (Throwable $e) {}

// CDP：确保匿名客户记录存在
try { cdp_get_or_create($uid, '', '', ''); } catch (Exception $e) {}

// UTM 归因：从 URL 参数捕获并存 cookie（供后续下单/线索归因）
$utmAttrs = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'];
$newUtm = false;
foreach ($utmAttrs as $a) {
    if (isset($_GET[$a])) {
        $_COOKIE['fc_utm_' . $a] = $_GET[$a];
        setcookie('fc_utm_' . $a, $_GET[$a], time() + 86400 * 90, '/');
        $newUtm = true;
    }
}
if ($newUtm) {
    // 记录一次 utm_landing 事件（首次来源落地）
    Database::insert('events', [
        'event' => 'utm_landing',
        'label' => implode('|', array_map(fn($a) => ($_COOKIE['fc_utm_'.$a] ?? ''), $utmAttrs)),
        'variant' => '', 'page' => (($_SERVER['HTTP_REFERER'] ?? '') ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) : '/'),
        'uid' => $uid, 'member_id' => '', 'member_email' => '',
        'props' => json_encode(array_filter($_COOKIE, fn($k) => strpos($k, 'fc_utm_') === 0, ARRAY_FILTER_USE_KEY), JSON_UNESCAPED_UNICODE),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'created_at' => date('Y-m-d H:i:s'),
    ]);
}

// 关联会员（若已登录）
$memberId = $_SESSION['member_id'] ?? '';
$memberEmail = $_SESSION['member_email'] ?? '';

// 写入 SQLite
Database::insert('events', [
    'event' => mb_substr($event, 0, 60),
    'label' => mb_substr((string)($input['label'] ?? ''), 0, 200),
    'variant' => mb_substr((string)($input['variant'] ?? ''), 0, 20),
    'page' => mb_substr((string)($input['page'] ?? (($_SERVER['HTTP_REFERER'] ?? '') ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) : '/')), 0, 200),
    'uid' => $uid,
    'member_id' => $memberId,
    'member_email' => $memberEmail,
    'props' => json_encode($input['props'] ?? [], JSON_UNESCAPED_UNICODE),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'created_at' => date('Y-m-d H:i:s'),
]);

// ── 数据流/价值流联动（打标/评分/积分/自动化/通知）──
try {
    flow_handle($event, [
        'uid' => $uid,
        'member_id' => $memberId,
        'email' => $memberEmail,
        'label' => $input['label'] ?? '',
        'page' => $input['page'] ?? '',
        'props' => $input['props'] ?? [],
    ]);
} catch (Exception $e) {}

// Webhook 回传
$cfg = json_read(DATA_DIR . '/tracking.json');
$webhooks = $cfg['webhooks'] ?? [];
if (!empty($input['webhook'])) {
    // SSRF protection: Validate webhook URL
    $whUrl = $input['webhook'];
    $whHost = parse_url($whUrl, PHP_URL_HOST);
    if ($whHost) {
        $whIp = gethostbyname($whHost);
        if ($whIp !== $whHost && filter_var($whIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            // Block private IPs
            $webhooks = [];
        } else {
            $webhooks = [$whUrl];
        }
    }
}
foreach ($webhooks as $wh) {
    if (empty($wh)) continue;
    $ch = curl_init($wh);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'event' => $event,
            'label' => $input['label'] ?? '',
            'props' => $input['props'] ?? [],
            'page' => $input['page'] ?? '',
            'uid' => $uid,
            'member_id' => $memberId,
            'member_email' => $memberEmail,
            'time' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    curl_exec($ch);
}

echo json_encode(['ok' => true, 'uid' => $uid]);
