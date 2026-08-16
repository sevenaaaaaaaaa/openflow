<?php
/**
 * 批量随机封面分配 — 一次性工具
 * POST /api/assign-covers.php
 * Body: { "secret": "..." }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

$secretFile = DATA_DIR . '/import_secret.json';
$secretData = json_read($secretFile);
$secret = $secretData['secret'] ?? '';

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['secret']) || $input['secret'] !== $secret) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid secret']); exit;
}

// 封面池：assets/images/ 下适合做封面的图片
$covers = array_map(fn($f) => 'assets/images/' . $f, [
    'case-loreal.jpeg', 'case-jd.jpeg', 'case-siemens.jpeg',
    'case-ferrero.jpeg', 'case-huashan.jpeg', 'case-jiushi.jpeg',
    'fig-happy-org.jpeg', 'fig-happy-team.jpeg', 'fig-happy-employee.jpeg',
    'fig-model-center.jpeg',
]);

$articles = get_articles();
$count = 0;
foreach ($articles as &$a) {
    if (!empty(trim($a['cover'] ?? ''))) continue;
    $a['cover'] = $covers[array_rand($covers)];
    save_article($a['id'], $a);
    $count++;
}
unset($a);

echo json_encode([
    'ok' => true,
    'assigned' => $count,
    'total' => count($articles),
    'pool' => $covers,
], JSON_UNESCAPED_UNICODE);
