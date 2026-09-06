<?php
/**
 * 文章一键复用导出（A5 可用化）—— blockmodel_repurpose 接 UI
 * POST /api/ai-repurpose.php  {content, target}
 * target: outline(提纲)/social(社媒)/email(邮件)/script(口播脚本)
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/BlockModel.php';

require_login();
require_perm('articles');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$content = trim((string)($input['content'] ?? ''));
$target = (string)($input['target'] ?? 'outline');
$allowed = ['outline','social','email','script'];
if (!in_array($target, $allowed, true)) { echo json_encode(['ok'=>false,'error'=>'未知目标']); exit; }
if ($content === '') { echo json_encode(['ok'=>false,'error'=>'内容为空']); exit; }

// HTML → 块 → 重排为目标
$blocks = blockmodel_from_html($content);
$out = blockmodel_repurpose($blocks, $target);
echo json_encode(['ok'=>true, 'target'=>$target, 'text'=>$out], JSON_UNESCAPED_UNICODE);
