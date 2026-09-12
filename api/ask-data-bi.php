<?php
/**
 * 对话式 BI API（后台专用）
 *   POST { q, csrf }  → AI 选数据集 + 回答 + 图表(真实数据) + 追问建议
 *
 * 【鉴权】必须登录后台 + insights 权限 + 限流；走 AiCenter，不直连模型；
 * 数字全部来自 BiData 的真实数据集，模型只做选型与解读。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiCenter.php';
require_once __DIR__ . '/../lib/RateLimiter.php';
require_once __DIR__ . '/../lib/BiData.php';
require_once __DIR__ . '/../lib/AskData.php';

header('Content-Type: application/json; charset=utf-8');
require_login();
require_perm('insights');
try { RateLimiter::throttle('askdatabi:' . md5((string)($_SESSION['admin_user'] ?? 'anon')), 40, 600); } catch (\Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$q = trim((string)($input['q'] ?? ($_GET['q'] ?? '')));
if ($q === '') { http_response_code(400); echo json_encode(['ok' => false, 'error' => '请输入问题'], JSON_UNESCAPED_UNICODE); exit; }

$r = askdata_bi_answer($q);
unset($r['data']);   // 不回传整包数据集，减小载荷
echo json_encode($r, JSON_UNESCAPED_UNICODE);
