<?php
/**
 * 文章编辑器 AI 助手 —— AiCenter::chat 统一调用（记账 + 额度闸门）
 * POST /api/ai-article.php  {action: rewrite|continue|title|summary, content, title}
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiCenter.php';

// 只有登录且有文章编辑权限的管理员能调（防公开直连 AI 烧额度）
require_login();
require_perm('articles');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = trim((string)($input['action'] ?? ''));
$content = trim((string)($input['content'] ?? ''));
$title = trim((string)($input['title'] ?? ''));

if (!in_array($action, ['rewrite','continue','title','summary'], true)) {
    echo json_encode(['ok'=>false,'error'=>'未知操作']); exit;
}
if (!AiCenter::isConfigured()) {
    echo json_encode(['ok'=>false,'error'=>'AI 未配置（请在系统设置配置 AiCenter）']); exit;
}

$prompts = [
    'rewrite'  => '你是专业中文编辑。请润色以下文章正文，保持原意、提升可读性与逻辑，输出润色后的完整内容（不要说明）。',
    'continue' => '你是专业中文编辑。请接着下面的文章内容续写 2-3 段，风格一致，自然收束，输出续写部分（不要重复原文）。',
    'title'    => '你是标题优化专家。请基于文章内容给出 3 个吸引人、准确的中文标题，每行一个，直接输出标题。',
    'summary'  => '你是摘要专家。请为以下文章写一段 80 字以内的中文摘要，直接输出摘要。',
];
$user = ($action === 'title' || $action === 'summary' ? ("原标题：" . ($title ?: '') . "\n") : '') . "内容：\n" . mb_substr($content, 0, 6000);

$r = AiCenter::chat($prompts[$action], $user, ['feature'=>'ai_article', 'tier'=>'admin', 'max_tokens'=>2000]);
echo json_encode([
    'ok' => $r['ok'] ?? false,
    'text' => $r['text'] ?? '',
    'error' => $r['error'] ?? '',
], JSON_UNESCAPED_UNICODE);
