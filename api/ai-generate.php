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

// Find provider
$provider = null;
foreach ($ai['providers'] as $p) {
    if ($p['id'] === $providerId && $p['enabled']) { $provider = $p; break; }
}
if (!$provider) {
    // Fallback to default
    $defaultId = $ai['default_provider'] ?? '';
    foreach ($ai['providers'] as $p) {
        if ($p['id'] === $defaultId && $p['enabled']) { $provider = $p; break; }
    }
}
if (!$provider || empty($provider['api_key'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'AI 供应商未配置或未启用，请在 AI Agent 配置中设置']);
    exit;
}

$model = $model ?: ($provider['model'] ?? 'gpt-4o');
$temperature = $input['temperature'] ?? ($ai['temperature'] ?? 0.7);
$apiUrl = rtrim($provider['api_url'], '/');

// Build request based on provider
$fullPrompt = $promptText . "\n\n---\n" . $content;

if ($provider['id'] === 'claude') {
    // Claude uses Anthropic's API format
    $payload = json_encode([
        'model' => $model,
        'max_tokens' => 4096,
        'messages' => [['role' => 'user', 'content' => $fullPrompt]],
    ]);
    $headers = [
        'x-api-key: ' . $provider['api_key'],
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json',
    ];
    $endpoint = $apiUrl . '/messages';
} elseif ($provider['id'] === 'minimax') {
    // MiniMax uses its own chat completion format
    $payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => '你是一个专业的网站内容编辑助手。请根据提示词处理以下内容，直接输出结果，不要添加额外说明。'],
            ['role' => 'user', 'content' => $fullPrompt],
        ],
        'temperature' => $temperature,
        'max_tokens' => 4096,
    ]);
    $headers = [
        'Authorization: Bearer ' . $provider['api_key'],
        'Content-Type: application/json',
    ];
    $endpoint = $apiUrl . '/text/chatcompletion_v2';
} elseif ($provider['id'] === 'openclaude') {
    // OpenClaude：聚合 API，OpenAI 兼容格式
    $payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => '你是一个专业的网站内容编辑助手。请根据提示词处理以下内容，直接输出结果，不要添加额外说明。'],
            ['role' => 'user', 'content' => $fullPrompt],
        ],
        'temperature' => $temperature,
        'max_tokens' => 4096,
    ]);
    $headers = [
        'Authorization: Bearer ' . $provider['api_key'],
        'Content-Type: application/json',
    ];
    $endpoint = $apiUrl . '/chat/completions';
} else {
    // OpenAI-compatible (OpenAI, DeepSeek, Kimi, GLM, Qwen, Doubao, OpenRouter, etc.)
    $payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => '你是一个专业的网站内容编辑助手。请根据提示词处理以下内容，直接输出结果，不要添加额外说明。'],
            ['role' => 'user', 'content' => $fullPrompt],
        ],
        'temperature' => $temperature,
        'max_tokens' => 4096,
    ]);
    $headers = [
        'Authorization: Bearer ' . $provider['api_key'],
        'Content-Type: application/json',
    ];
    $endpoint = $apiUrl . '/chat/completions';
}

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

if ($error) {
    echo json_encode(['ok' => false, 'error' => '请求失败: ' . $error]);
    exit;
}

$data = json_decode($resp, true);
if (!$data) {
    echo json_encode(['ok' => false, 'error' => '响应解析失败', 'raw' => mb_substr($resp, 0, 500)]);
    exit;
}

// Extract text from response (兼容多种供应商格式)
$resultText = '';
if ($provider['id'] === 'claude') {
    $resultText = $data['content'][0]['text'] ?? ($data['content'] ?? '');
} elseif (isset($data['choices'][0]['message']['content'])) {
    // OpenAI / DeepSeek / Kimi / GLM / Qwen / Doubao / OpenRouter
    $resultText = $data['choices'][0]['message']['content'];
} elseif (isset($data['choices'][0]['text'])) {
    $resultText = $data['choices'][0]['text'];
} elseif (isset($data['output_text'])) {
    // MiniMax
    $resultText = $data['output_text'];
} elseif (isset($data['data'][0]['output_text'])) {
    // MiniMax 新版
    $resultText = $data['data'][0]['output_text'];
} elseif (isset($data['output']['text'])) {
    $resultText = $data['output']['text'];
} else {
    $resultText = '';
}

echo json_encode(['ok' => true, 'result' => $resultText, 'provider' => $provider['id'], 'model' => $model], JSON_UNESCAPED_UNICODE);
