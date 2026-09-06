<?php
/**
 * 表单编辑器 AI 助手 —— 需求描述 → 结构化字段建议
 * POST /api/ai-form.php  {desc}  →  AiCenter::chat 输出 JSON 字段数组
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiCenter.php';

require_login();
require_perm('forms');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$desc = trim((string)($input['desc'] ?? ''));
if ($desc === '') { echo json_encode(['ok'=>false,'error'=>'请输入表单需求']); exit; }
if (!AiCenter::isConfigured()) { echo json_encode(['ok'=>false,'error'=>'AI 未配置']); exit; }

$system = '你是表单设计专家。根据需求生成表单字段，只输出 JSON 数组（不要解释）。每项：{"key":"英文键","label":"中文标签","type":"text|email|textarea|select|number|date|tel","required":true,"placeholder":"提示文案","options":["选项1","选项2"]}。select 必须有 options。输出一个 JSON 数组。';
$user = '需求：' . $desc . "\n请生成 3-8 个字段。";
$r = AiCenter::chat($system, $user, ['feature'=>'ai_form', 'tier'=>'admin', 'max_tokens'=>1500]);

$arr = [];
$text = trim((string)($r['text'] ?? ''));
if ($r['ok'] ?? false) {
    // 去掉可能的 ```json 围栏
    $text = preg_replace('/^```(json)?\s*|\s*```$/m', '', $text);
    $parsed = json_decode($text, true);
    if (is_array($parsed)) $arr = $parsed;
    elseif (preg_match('/\[.*\]/s', $text, $m)) { $parsed = json_decode($m[0], true); if (is_array($parsed)) $arr = $parsed; }
}
echo json_encode(['ok'=>(bool)($r['ok'] ?? false), 'fields'=>$arr, 'error'=>$r['error'] ?? ''], JSON_UNESCAPED_UNICODE);
