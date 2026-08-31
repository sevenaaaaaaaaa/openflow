<?php
/**
 * 站点 Agent API（公开）
 *   POST action=ask     q=问题                → 站内知识现答 + 是否转人工 + CTA
 *   POST action=handoff q=问题 email= name=   → 转人工：落 CRM 线索
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/KnowledgeSystem.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_once __DIR__ . '/../lib/SiteAgent.php';
require_once __DIR__ . '/../lib/RateLimiter.php';

header('Content-Type: application/json; charset=utf-8');
if (function_exists('cors_headers')) cors_headers();
header('Cache-Control: no-cache');

$action = $_POST['action'] ?? ($_GET['action'] ?? 'ask');
$q = trim((string)($_POST['q'] ?? ($_GET['q'] ?? '')));
if (mb_strlen($q) > 500) $q = mb_substr($q, 0, 500);

/**
 * 单 IP 限流。
 *
 * 【为什么必须有】这是一个**公开、免登录、每次调用都要花钱**的 AI 接口。
 * 没有限流，任何人写个 while 循环就能把站长这个月的 AI 预算烧光，
 * 顺便把 PHP-FPM 的处理位全占住让整站打不开。
 * 额度那层（AiBudget）管的是"一天最多花多少"，这一层管的是"单个来源别刷"，
 * 两层都要有：只有额度，一个人也能在几分钟内把全天额度刷爆。
 */
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip = trim(explode(',', (string)$ip)[0]);
try {
    // 问答走模型、要花钱：10 分钟 20 次；转人工只落线索、不花钱：10 分钟 5 次
    $limit = $action === 'handoff' ? 5 : 20;
    RateLimiter::throttle('siteagent:' . md5($ip), $limit, 600);
} catch (\Throwable $e) {}

if ($action === 'handoff') {
    echo json_encode(siteagent_handoff($q, (string)($_POST['email'] ?? ''), (string)($_POST['name'] ?? '')), JSON_UNESCAPED_UNICODE);
    exit;
}

$loggedIn = false;
if (function_exists('member_current')) { try { $loggedIn = (bool)member_current(); } catch (\Throwable $e) {} }

echo json_encode(siteagent_answer($q, ['logged_in' => $loggedIn]), JSON_UNESCAPED_UNICODE);
