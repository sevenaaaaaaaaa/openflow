<?php
/**
 * 导航站收录申请 — 前台提交站点 → 待审核
 */
require_once __DIR__ . '/../admin/config.php';
header('Content-Type: application/json; charset=utf-8');

$name = trim($_POST['name'] ?? '');
$url = trim($_POST['url'] ?? '');
$description = trim($_POST['description'] ?? '');
$category = trim($_POST['category'] ?? '');
$contact = trim($_POST['contact'] ?? '');

if ($name === '' || $url === '') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'名称和网址必填']); exit; }
if (!preg_match('#^https?://#i', $url)) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'网址需以 http(s):// 开头']); exit; }
if (mb_strlen($name) > 60) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'名称过长']); exit; }

$navFile = DATA_DIR . '/navigation.json';
$nav = json_read($navFile);

// 去重（按 url 或 name）
foreach (($nav['sites'] ?? []) as $s) {
    if (strtolower(trim($s['url'] ?? '')) === strtolower($url) || ($s['name'] ?? '') === $name) {
        echo json_encode(['ok'=>false,'error'=>'该站点已收录（含待审核）'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$nav['sites'][] = [
    'id' => 'site_' . substr(bin2hex(random_bytes(4)), 0, 6),
    'name' => $name,
    'url' => $url,
    'description' => mb_substr($description, 0, 200),
    'category' => $category,
    'featured' => false,
    'region' => 'cn',
    'logo' => '',
    'tags' => [],
    'reason' => '',
    'weight' => 0,
    'status' => 'pending',
    'hits' => 0,
    'submitter' => $contact ?: '',
    'submitted_at' => date('Y-m-d H:i:s'),
    'created_at' => date('Y-m-d H:i:s'),
];
json_write($navFile, $nav);

// 通知后台
try { notify('navigation', '导航站收录申请', "新站点收录申请：{$name}", '/xmp/navigation'); } catch (Throwable $e) {}

echo json_encode(['ok'=>true, 'message'=>'提交成功，审核通过后上架展示'], JSON_UNESCAPED_UNICODE);
