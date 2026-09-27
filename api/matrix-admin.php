<?php
/**
 * 矩阵互通后台操作 — issue(签发/轮换 secret)
 * 表单 POST,走 config.php 统一关卡(登录+CSRF),另有 settings 权限自检。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MatrixTicket.php';

if (!is_logged_in()) { header('Location: /xmp/login'); exit; }
if (!has_perm('settings')) { http_response_code(403); exit('需要 settings 权限'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$action = (string)($_POST['action'] ?? '');
$back = '/xmp/matrix';
if ($action === 'issue') {
    $clientId = preg_replace('/[^a-z0-9-]/i', '', (string)($_POST['client_id'] ?? ''));
    $redirect = trim((string)($_POST['redirect_base'] ?? ''));
    if ($redirect !== '' && !preg_match('#^https?://#i', $redirect)) {
        header('Location: ' . $back . '?msg=' . urlencode('回跳地址必须以 http(s):// 开头') . '&kind=error'); exit;
    }
    $r = matrix_client_issue_secret($clientId, $redirect);
    if (!empty($r['ok'])) {
        $_SESSION['mtx_issued'] = ['client_id' => $clientId, 'secret' => $r['secret']];
        header('Location: ' . $back . '?msg=' . urlencode('secret 已生成,请立即复制保存') . '&kind=success'); exit;
    }
    header('Location: ' . $back . '?msg=' . urlencode($r['error'] ?? '签发失败') . '&kind=error'); exit;
}
http_response_code(400); exit('未知操作');
