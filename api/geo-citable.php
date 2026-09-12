<?php
/**
 * GEO 可引用改写 API（后台专用）
 *   POST action=preview { id, ai }   → 生成可引用块（answer/关键事实/QA/表）+ 预览 HTML
 *   POST action=apply   { id, blocks } → 写回正文（幂等，含 FAQ 结构化数据）
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/GeoCitable.php';
require_login();
require_perm('geo');

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

$id = (string)($_POST['id'] ?? '');
$action = (string)($_POST['action'] ?? 'preview');
if ($id === '' || !function_exists('get_article')) { http_response_code(400); echo json_encode(['ok' => false, 'error' => '缺少文章 id'], JSON_UNESCAPED_UNICODE); exit; }
$a = get_article($id);
if (!$a) { http_response_code(404); echo json_encode(['ok' => false, 'error' => '文章不存在'], JSON_UNESCAPED_UNICODE); exit; }

if ($action === 'apply') {
    $blocks = json_decode((string)($_POST['blocks'] ?? ''), true);
    if (!is_array($blocks) || empty($blocks['answer'])) {
        $r = geo_citable_rewrite($a, false);   // 应用时用已有块或规则兜底
        $blocks = $r['blocks'];
    }
    $res = geo_citable_apply($id, $blocks);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

$r = geo_citable_rewrite($a, !empty($_POST['ai']));
$r['html'] = geo_citable_html($r['blocks']);
echo json_encode($r, JSON_UNESCAPED_UNICODE);
