<?php
/**
 * 分群即服务 API — /api/segments.php?key=<API_KEY>
 *
 * 对标 LinkFlow/Segment 的「分群即服务」：外部系统按令牌读取分群与成员，
 * 用于在自有站点/触达工具里做人群激活。只读、脱敏、令牌闸门。
 *
 *   action=list                         → 分群列表 + 人数
 *   action=count&segment=<id>           → 单分群人数
 *   action=members&segment=<id>&limit=  → 分群成员（脱敏：访客ID + 掩码邮箱 + 关键属性）
 *   action=profile&uid=<visitor_id>     → 某人所属分群
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_once __DIR__ . '/../lib/SegmentApi.php';

header('Content-Type: application/json; charset=utf-8');
if (function_exists('cors_headers')) cors_headers();

// ── 令牌闸门（与 api/leads.php 同一套 api_key.json；README/后台可见）──
$apiKey = (string)(json_read(DATA_DIR . '/api_key.json')['key'] ?? '');
$given = (string)($_GET['key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? ''));
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if ($auth === '' && function_exists('getallheaders')) { $h = getallheaders(); $auth = (string)($h['Authorization'] ?? $h['authorization'] ?? ''); }
if (stripos($auth, 'Bearer ') === 0) $given = substr($auth, 7);
if ($apiKey === '' || $given === '' || !hash_equals($apiKey, $given)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid API key'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string)($_GET['action'] ?? 'list');

try {
    if ($action === 'list') {
        $segs = CdpSystem::allSegments();
        $counts = segapi_counts(CdpSystem::allProfiles());
        $out = [];
        foreach ($segs as $s) {
            $id = (string)($s['id'] ?? '');
            if ($id === '') continue;
            $out[] = ['id' => $id, 'name' => (string)($s['name'] ?? ''), 'count' => (int)($counts[$id] ?? 0), 'updated_at' => (string)($s['updated_at'] ?? '')];
        }
        echo json_encode(['ok' => true, 'count' => count($out), 'segments' => $out], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'count') {
        $sid = (string)($_GET['segment'] ?? '');
        $counts = segapi_counts(CdpSystem::allProfiles());
        echo json_encode(['ok' => true, 'segment' => $sid, 'count' => (int)($counts[$sid] ?? 0)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'members') {
        $sid = (string)($_GET['segment'] ?? '');
        $limit = max(1, min(1000, (int)($_GET['limit'] ?? 100)));
        $members = segapi_members(CdpSystem::allProfiles(), $sid, $limit);
        echo json_encode(['ok' => true, 'segment' => $sid, 'returned' => count($members), 'members' => $members], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'profile') {
        $uid = (string)($_GET['uid'] ?? '');
        $p = $uid !== '' ? CdpSystem::getProfile($uid) : null;
        if (!$p) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'profile not found'], JSON_UNESCAPED_UNICODE); exit; }
        $segNames = [];
        foreach (CdpSystem::allSegments() as $s) $segNames[(string)($s['id'] ?? '')] = (string)($s['name'] ?? '');
        $ids = array_keys((array)($p['segment_memberships'] ?? []));
        echo json_encode(['ok' => true, 'visitor_id' => $uid, 'segments' => array_map(fn($i) => ['id' => $i, 'name' => $segNames[$i] ?? $i], $ids)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '未知 action'], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
