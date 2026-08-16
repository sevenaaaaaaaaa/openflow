<?php
/**
 * 调研提交 API
 * POST /api/survey-submit.php?id=survey_xxx
 * Body: { name, email, company, department, ans: { q0: "val" | ["a","b"] } }
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../admin/survey-lib.php';

header('Content-Type: application/json; charset=utf-8');

$survey = survey_get_survey($_GET['id'] ?? '');
if (!$survey) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => '问卷不存在']);
    exit;
}
if (($survey['status'] ?? 'draft') !== 'active') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => '问卷已关闭']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$answers = $input['ans'] ?? [];
if (!is_array($answers)) $answers = [];

// 校验必答题
foreach ($survey['questions'] as $q) {
    if ($q['required'] && empty($answers[$q['id']] ?? '')) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '请完成必答题：' . $q['title']], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 归一化：多选 -> 数组，其余 -> 字符串
$cleanAnswers = [];
foreach ($survey['questions'] as $q) {
    $val = $answers[$q['id']] ?? null;
    if ($q['type'] === 'multi') {
        $cleanAnswers[$q['id']] = is_array($val) ? array_values($val) : [];
    } else {
        $cleanAnswers[$q['id']] = is_array($val) ? ($val[0] ?? '') : (string)$val;
    }
}

$response = [
    'id' => 'r_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
    'survey_id' => $survey['id'],
    'name' => trim($input['name'] ?? ''),
    'email' => trim($input['email'] ?? ''),
    'company' => trim($input['company'] ?? ''),
    'department' => trim($input['department'] ?? ''),
    'answers' => $cleanAnswers,
    'created_at' => date('Y-m-d H:i:s'),
];

survey_add_response($survey['id'], $response);

// 通知管理员有新回收
notify('调研', "「{$survey['title']}」收到新答卷", trim($input['company'] ?? '') . ' · ' . trim($input['department'] ?? '') . ' · ' . trim($input['email'] ?: $input['name'] ?: '匿名'), 'admin/survey-stats.php?survey=' . $survey['id']);

echo json_encode(['ok' => true, 'message' => '提交成功，感谢参与！'], JSON_UNESCAPED_UNICODE);
