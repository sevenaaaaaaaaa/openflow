<?php
/**
 * AI 业务助手 API
 * POST { action: optimize_article|score_lead|sentiment, ... }
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AIBusiness.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_login'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '需要登录']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? '';

if ($action === 'optimize_article') {
    $id = $input['id'] ?? '';
    $article = get_article($id);
    if (!$article) { http_response_code(404); echo json_encode(['ok' => false, 'error' => '文章不存在']); exit; }
    $result = AIBusiness::optimizeArticle($article);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'score_lead') {
    $lead = $input['lead'] ?? [];
    if (empty($lead)) { http_response_code(400); echo json_encode(['ok' => false, 'error' => '缺少线索数据']); exit; }
    $result = AIBusiness::scoreLead($lead);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'test_provider') {
    // 测试指定供应商连接
    $providerId = $input['provider_id'] ?? '';
    $providers = AiCenter::providers();
    $provider = null;
    foreach ($providers as $p) if ($p['id'] === $providerId) { $provider = $p; break; }
    if (!$provider) { http_response_code(404); echo json_encode(['ok' => false, 'error' => '供应商不存在'], JSON_UNESCAPED_UNICODE); exit; }
    // 测的必须是**选中的那个**供应商。原来这里只传了 model，供应商仍取默认——
    // 结果是：默认供应商正常时，一个坏掉的供应商也会报「连接成功」，
    // 自检等于没检。现在用 provider_id 明确指定。
    $r = AiCenter::chat('你是一个连接测试助手，请只回复：连接成功', '测试', [
        'provider_id' => $providerId,
        'model'   => $provider['model'] ?? '',
        'feature' => 'provider_test',
        'tier'    => 'admin',
        'timeout' => 20,
    ]);
    echo json_encode(['ok' => $r['ok'], 'error' => $r['error'] ?? '', 'text' => $r['text'] ?? ''], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'ai_usage') {
    // AI 用量统计（从 ai 日志）
    $logFile = DATA_DIR . '/ai-log.json';
    $log = json_read($logFile);
    $recent = array_slice(array_reverse($log), 0, 200);
    $byProvider = [];
    $total = count($recent);
    $errors = 0;
    foreach ($recent as $l) {
        $pid = $l['provider'] ?? 'unknown';
        $byProvider[$pid] = ($byProvider[$pid] ?? 0) + 1;
        if (!empty($l['error'])) $errors++;
    }
    echo json_encode(['ok' => true, 'total' => $total, 'errors' => $errors, 'by_provider' => $byProvider], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => '未知 action']);
