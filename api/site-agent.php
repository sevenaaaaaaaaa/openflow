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

header('Content-Type: application/json; charset=utf-8');
if (function_exists('cors_headers')) cors_headers();
header('Cache-Control: no-cache');

$action = $_POST['action'] ?? ($_GET['action'] ?? 'ask');
$q = trim((string)($_POST['q'] ?? ($_GET['q'] ?? '')));
if (mb_strlen($q) > 500) $q = mb_substr($q, 0, 500);

if ($action === 'handoff') {
    echo json_encode(siteagent_handoff($q, (string)($_POST['email'] ?? ''), (string)($_POST['name'] ?? '')), JSON_UNESCAPED_UNICODE);
    exit;
}

$loggedIn = false;
if (function_exists('member_current')) { try { $loggedIn = (bool)member_current(); } catch (\Throwable $e) {} }

echo json_encode(siteagent_answer($q, ['logged_in' => $loggedIn]), JSON_UNESCAPED_UNICODE);
