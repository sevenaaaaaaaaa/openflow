<?php
/**
 * 调研问卷 AI 生成 API
 * POST /api/survey-ai.php
 * Body: { "topic": "网站满意度调研", "question_count": 10, "include_rating": true }
 * 返回：生成的题目数组
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$topic = trim($input['topic'] ?? '');
if (empty($topic)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '请描述调研主题']);
    exit;
}
$qCount = max(3, min(20, (int)($input['question_count'] ?? 10)));
$includeRating = !empty($input['include_rating']);

// 读取 AI 供应商配置
$ai = json_read(DATA_DIR . '/ai-config.json');
$provider = null;
$defaultId = $ai['default_provider'] ?? '';
foreach (($ai['providers'] ?? []) as $p) if ($p['id'] === $defaultId && $p['enabled'] && !empty($p['api_key'])) { $provider = $p; break; }
if (!$provider) foreach (($ai['providers'] ?? []) as $p) if ($p['enabled'] && !empty($p['api_key'])) { $provider = $p; break; }

if (!$provider) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '请先在「AI Agent 配置」中启用一个供应商并填写 API Key'], JSON_UNESCAPED_UNICODE);
    exit;
}

$systemPrompt = '你是专业的调研问卷设计专家。请根据用户给出的调研主题，设计一份高质量的调研问卷。';
$ratingLabel = $includeRating ? '是，包含 1-5 评分题' : '否，不需要评分题';
$userPrompt = <<<PROMPT
调研主题：{$topic}
题目数量：{$qCount} 题
是否需要评分题：{$ratingLabel}

请输出严格的 JSON 数组，不要包含任何其他文字或 markdown 代码块标记。数组每个元素格式：
{"title":"题目文字","type":"single|multi|rating|text","options":["选项1","选项2"],"required":true}

规则：
- single/multi 题必须提供 3-6 个选项
- rating 题 type 用 "rating"，options 留空
- text 题 options 留空
- 混合使用题型，不要全部是单选
- 题目要专业、贴合组织调研场景
PROMPT;

$model = $provider['model'] ?? 'gpt-4o';
$apiUrl = rtrim($provider['api_url'], '/');

$payload = json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt],
    ],
    'temperature' => 0.7,
    'max_tokens' => 4000,
]);

if ($provider['id'] === 'minimax') {
    $endpoint = $apiUrl . '/text/chatcompletion_v2';
    $headers = ['Authorization: Bearer ' . $provider['api_key'], 'Content-Type: application/json'];
} else {
    $endpoint = $apiUrl . '/chat/completions';
    $headers = ['Authorization: Bearer ' . $provider['api_key'], 'Content-Type: application/json'];
}

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

if ($error) {
    echo json_encode(['ok' => false, 'error' => '请求失败: ' . $error]);
    exit;
}
if ($http !== 200) {
    echo json_encode(['ok' => false, 'error' => "AI 服务返回异常 (HTTP $http): " . mb_substr($resp, 0, 200)], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($resp, true);
$text = '';
if (isset($data['choices'][0]['message']['content'])) $text = $data['choices'][0]['message']['content'];
elseif (isset($data['output_text'])) $text = $data['output_text'];
elseif (isset($data['data'][0]['output_text'])) $text = $data['data'][0]['output_text'];

if (empty($text)) {
    echo json_encode(['ok' => false, 'error' => 'AI 未返回有效内容'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 提取 JSON（兼容 AI 输出带 markdown 代码块）
$json = $text;
if (preg_match('/```(?:json)?\s*(\[.*?\])\s*```/s', $text, $m)) $json = $m[1];
elseif (preg_match('/\[.*\]/s', $text, $m)) $json = $m[0];

$questions = json_decode($json, true);
if (!is_array($questions)) {
    echo json_encode(['ok' => false, 'error' => 'AI 返回内容无法解析为问卷', 'raw' => mb_substr($text, 0, 300)], JSON_UNESCAPED_UNICODE);
    exit;
}

// 清洗：过滤无效，限制数量，补默认
$clean = [];
foreach ($questions as $q) {
    $title = trim($q['title'] ?? '');
    if (empty($title)) continue;
    $clean[] = [
        'title' => $title,
        'type' => in_array($q['type'] ?? '', ['single', 'multi', 'rating', 'text']) ? $q['type'] : 'text',
        'options' => array_values(array_filter(array_map('trim', $q['options'] ?? []))),
        'required' => !empty($q['required']),
        'scale' => ($q['type'] ?? '') === 'rating' ? 5 : 5,
    ];
}
$clean = array_slice($clean, 0, $qCount);

if (empty($clean)) {
    echo json_encode(['ok' => false, 'error' => 'AI 生成的问卷为空'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'questions' => $clean, 'count' => count($clean), 'provider' => $provider['id']], JSON_UNESCAPED_UNICODE);
