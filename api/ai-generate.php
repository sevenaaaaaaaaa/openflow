<?php
/**
 * AI Generation API
 * POST /api/ai-generate.php
 * Body: { "prompt": "...", "content": "...", "provider": "openai", "model": "gpt-4o" }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

// Require authentication (admin or API token)
$authenticated = false;
if (!empty($_SESSION['admin_login'])) {
    $authenticated = true;
} else {
    // Check for API token in Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        $token = $matches[1];
        // Validate token against stored API tokens
        $apiTokens = json_read(DATA_DIR . '/api_tokens.json');
        if (in_array($token, $apiTokens ?? [], true)) {
            $authenticated = true;
        }
    }
}

if (!$authenticated) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '需要登录或有效的 API Token']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$promptText = trim($input['prompt'] ?? '');
$content = trim($input['content'] ?? '');
$providerId = $input['provider'] ?? '';
$model = $input['model'] ?? '';

if (empty($promptText) || empty($content)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '缺少 prompt 或 content']);
    exit;
}

$ai = json_read(DATA_DIR . '/ai-config.json');

// 统一走 AiCenter：记账 + 额度闸门 + 分档超时。
// 原来这里自建了一份 curl（自己的 60 秒超时、自己的多供应商分支、零记账），
// 是全站第 N 处绕过电表的 AI 直连；现在收口到一处。
require_once __DIR__ . '/../lib/AiCenter.php';
if (!AiCenter::isConfigured()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'AI 供应商未配置或未启用，请在 AI Agent 配置中设置'], JSON_UNESCAPED_UNICODE);
    exit;
}
$temperature = $input['temperature'] ?? ($ai['temperature'] ?? 0.7);
$opts = [
    'temperature' => (float)$temperature,
    'max_tokens'  => 4000,
    'feature'     => 'ai_generate',
    'tier'        => 'admin',
];
if ($model !== '') $opts['model'] = $model;

$r = AiCenter::chat($promptText, $content, $opts);
if (empty($r['ok'])) {
    echo json_encode(['ok' => false, 'error' => $r['error'] ?? 'AI 生成失败'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok' => true, 'result' => (string)($r['text'] ?? '')], JSON_UNESCAPED_UNICODE);
