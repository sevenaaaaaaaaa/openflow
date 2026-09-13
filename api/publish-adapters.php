<?php
/**
 * 发布适配器配置 API（后台专用）
 *   POST action=save {id, ...fields}   保存某平台凭据
 *   POST action=test {id}              发一条测试消息验证凭据
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/SocialPublisher.php';
require_once __DIR__ . '/../lib/PublishAdapters.php';
require_login();
require_perm('channels');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$action = (string)($input['action'] ?? '');
$id = (string)($input['id'] ?? '');

$adapters = pub_adapters();
if (!isset($adapters[$id])) { http_response_code(400); echo json_encode(['ok' => false, 'error' => '未知平台'], JSON_UNESCAPED_UNICODE); exit; }

if ($action === 'save') {
    $vals = [];
    foreach (array_keys((array)($adapters[$id]['fields'] ?? [])) as $k) if (isset($input[$k])) $vals[$k] = trim((string)$input[$k]);
    pub_adapter_save($id, $vals);
    echo json_encode(['ok' => true, 'ready' => pub_adapter_ready($id)], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'test') {
    $test = ['id' => 'test', 'title' => 'OpenFlow 发布测试', 'excerpt' => '这是一条来自 OpenFlow 的测试消息，用于验证「' . $adapters[$id]['name'] . '」凭据是否可用。', 'slug' => 'openflow-publish-test'];
    $r = pub_adapter_publish($id, $test);
    echo json_encode(['ok' => !empty($r['ok']), 'message' => $r['message'] ?? ($r['ok'] ? '测试成功' : '测试失败')], JSON_UNESCAPED_UNICODE);
    exit;
}
http_response_code(400);
echo json_encode(['ok' => false, 'error' => '未知 action'], JSON_UNESCAPED_UNICODE);
