<?php
/**
 * 调研问卷 AI 生成 API
 * POST /api/survey-ai.php
 * Body: { "topic": "网站满意度调研", "question_count": 10, "include_rating": true }
 * 返回：生成的题目数组
 *
 * 【鉴权】这是一个**每次调用都要花钱**的 AI 接口，而且它是给后台作者用的创作工具，
 * 不是访客功能。原来完全没有身份校验：任何人 POST 一个主题就能让站长掏钱，
 * 而且它自建 curl 绕过了 AI 电表和额度闸门，烧了也看不见。
 * 现在：登录 + survey 权限 + 单账号限流，并统一走 AiCenter。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiCenter.php';
require_once __DIR__ . '/../lib/RateLimiter.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
require_perm('survey');
try {
    RateLimiter::throttle('surveyai:' . md5((string)($_SESSION['admin_user'] ?? 'anon')), 30, 600);
} catch (\Throwable $e) {}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$topic = trim($input['topic'] ?? '');
if (empty($topic)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '请描述调研主题'], JSON_UNESCAPED_UNICODE);
    exit;
}
$qCount = max(3, min(20, (int)($input['question_count'] ?? 10)));
$includeRating = !empty($input['include_rating']);

if (!AiCenter::isConfigured()) {
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

// 统一走 AiCenter：记账 + 额度闸门 + 分档超时（后台交互档）
$r = AiCenter::chat($systemPrompt, $userPrompt, [
    'max_tokens' => 4000,
    'temperature' => 0.7,
    'feature' => 'survey_ai',
    'tier' => 'admin',
]);
if (empty($r['ok'])) {
    echo json_encode(['ok' => false, 'error' => $r['error'] ?? 'AI 生成失败'], JSON_UNESCAPED_UNICODE);
    exit;
}
$text = (string)($r['text'] ?? '');

if (empty($text)) {
    echo json_encode(['ok' => false, 'error' => 'AI 未返回有效内容'], JSON_UNESCAPED_UNICODE);
    exit;
}

$questions = AiCenter::extractJson($text);
if (!is_array($questions)) {
    echo json_encode(['ok' => false, 'error' => 'AI 返回内容无法解析为问卷', 'raw' => mb_substr($text, 0, 300)], JSON_UNESCAPED_UNICODE);
    exit;
}

// 清洗：过滤无效，限制数量，补默认
$clean = [];
foreach ($questions as $q) {
    if (!is_array($q)) continue;
    $title = trim($q['title'] ?? '');
    if (empty($title)) continue;
    $clean[] = [
        'title' => $title,
        'type' => in_array($q['type'] ?? '', ['single', 'multi', 'rating', 'text']) ? $q['type'] : 'text',
        'options' => array_values(array_filter(array_map('trim', (array)($q['options'] ?? [])))),
        'required' => !empty($q['required']),
        'scale' => 5,
    ];
}
$clean = array_slice($clean, 0, $qCount);

if (empty($clean)) {
    echo json_encode(['ok' => false, 'error' => 'AI 生成的问卷为空'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'questions' => $clean, 'count' => count($clean)], JSON_UNESCAPED_UNICODE);
