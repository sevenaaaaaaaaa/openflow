<?php
/**
 * 站内营销投放 API（公开）
 *   GET  /api/promo.php?path=/article/x&type=article&utm=weibo  → 命中的通知条/弹窗
 *   POST /api/promo.php  action=hit id=... kind=impression|click|dismiss
 *
 * 服务端判定 页面/定时/登录/UTM/分群；前端按每条的频次（session/daily/once）
 * 决定展示与否，并回传曝光/点击埋点。inline 版位由服务端直接渲染，不走这里。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/PromoSystem.php';

header('Content-Type: application/json; charset=utf-8');
if (function_exists('cors_headers')) cors_headers();
header('Cache-Control: no-cache');

$action = $_GET['action'] ?? ($_POST['action'] ?? 'serve');

// 埋点回传
if ($action === 'hit') {
    $id = trim($_POST['id'] ?? ($_GET['id'] ?? ''));
    $kind = trim($_POST['kind'] ?? ($_GET['kind'] ?? ''));
    echo json_encode(['ok' => promo_hit($id, $kind)]);
    exit;
}

// 命中投放（bar / popup）
$loggedIn = false;
if (function_exists('member_current')) { try { $loggedIn = (bool)member_current(); } catch (\Throwable $e) {} }

$segments = [];
$uid = $_COOKIE['fc_uid'] ?? '';
if ($uid !== '' && class_exists('CdpSystem')) {
    try {
        $prof = CdpSystem::getProfile($uid);
        if ($prof) foreach ((array)($prof['segment_memberships'] ?? []) as $sid => $on) if ($on) $segments[] = $sid;
    } catch (\Throwable $e) {}
}

$ctx = [
    'path'       => (string)($_GET['path'] ?? '/'),
    'page_type'  => (string)($_GET['type'] ?? ''),
    'logged_in'  => $loggedIn,
    'visitor'    => (($_COOKIE['fc_seen'] ?? '') !== '') ? 'return' : 'new',
    'segments'   => $segments,
    'utm_source' => (string)($_GET['utm'] ?? ''),
    'now'        => date('Y-m-d H:i:s'),
];

$serve = function (array $p): array {
    // 只把前端需要的字段吐出去，别泄露定向/统计内部
    return [
        'id' => $p['id'], 'type' => $p['type'],
        'title' => $p['title'] ?? '', 'body' => $p['body'] ?? '', 'image' => $p['image'] ?? '',
        'cta_text' => $p['cta_text'] ?? '', 'cta_link' => $p['cta_link'] ?? '',
        'color' => $p['color'] ?? '', 'dismissible' => !empty($p['dismissible']),
        'position' => $p['position'] ?? '', 'trigger' => $p['trigger'] ?? 'immediate',
        'trigger_delay' => (int)($p['trigger_delay'] ?? 5), 'scroll_pct' => (int)($p['scroll_pct'] ?? 50),
        'frequency' => $p['frequency'] ?? 'session',
    ];
};

$bars   = array_map($serve, promo_serve($ctx, 'bar'));
$popups = array_map($serve, promo_serve($ctx, 'popup'));

echo json_encode(['ok' => true, 'bars' => array_values($bars), 'popups' => array_values($popups)], JSON_UNESCAPED_UNICODE);
