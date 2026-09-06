<?php
/**
 * 画布编辑器 AI 助手 —— 需求描述 → 建议节点类型序列
 * POST /api/ai-canvas.php  {desc}  →  AiCenter::chat 输出 ["trigger","send_email",...]
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiCenter.php';

require_login();
require_perm('settings');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$desc = trim((string)($input['desc'] ?? ''));
if ($desc === '') { echo json_encode(['ok'=>false,'error'=>'请输入流程需求']); exit; }
if (!AiCenter::isConfigured()) { echo json_encode(['ok'=>false,'error'=>'AI 未配置']); exit; }

$valid = ['trigger','send_email','condition','delay','notify','tag','score','stage','webhook','split'];
$system = '你是营销自动化流程设计专家。根据需求给出画布节点类型序列。只输出 JSON 数组（不要解释），元素只能来自：' . implode(',', $valid) . '。第一个必须是 trigger（如 form_submit/member_register 触发）；可含 send_email/notify/tag/score/stage/webhook/delay；需要在 A/B 时用 split；需要分流判断时用 condition。输出 2-8 个节点，直接输出 JSON 数组。';
$user = '需求：' . $desc;
$r = AiCenter::chat($system, $user, ['feature'=>'ai_canvas', 'tier'=>'admin', 'max_tokens'=>1000]);

$nodes = [];
$text = trim((string)($r['text'] ?? ''));
if ($r['ok'] ?? false) {
    $text = preg_replace('/^```(json)?\s*|\s*```$/m', '', $text);
    $parsed = json_decode($text, true);
    if (is_array($parsed)) $nodes = $parsed;
    elseif (preg_match('/\[.*\]/s', $text, $m)) { $parsed = json_decode($m[0], true); if (is_array($parsed)) $nodes = $parsed; }
    // 过滤非法节点
    $nodes = array_values(array_filter($nodes, fn($n) => in_array($n, $valid, true)));
    if ($nodes && $nodes[0] !== 'trigger') array_unshift($nodes, 'trigger');
}
echo json_encode(['ok'=>(bool)($r['ok'] ?? false), 'nodes'=>$nodes, 'error'=>$r['error'] ?? ''], JSON_UNESCAPED_UNICODE);
