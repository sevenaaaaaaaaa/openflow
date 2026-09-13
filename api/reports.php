<?php
/**
 * 自定义报表 API（后台专用）
 *   POST action=run    {source,metric,dimension,days,filters[]}   运行
 *   POST action=drill  {spec..., value}                           下钻
 *   POST action=save   {id?,name,spec}                            保存
 *   POST action=delete {id}
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ReportEngine.php';
require_login();
require_perm('analytics');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$action = (string)($input['action'] ?? ($_GET['action'] ?? ''));

switch ($action) {
    case 'run':
        echo json_encode(['ok' => true] + report_run((array)($input['spec'] ?? $input)), JSON_UNESCAPED_UNICODE);
        break;
    case 'drill':
        echo json_encode(['ok' => true] + report_drill((array)($input['spec'] ?? $input), (string)($input['value'] ?? '')), JSON_UNESCAPED_UNICODE);
        break;
    case 'save':
        echo json_encode(report_save(['id' => $input['id'] ?? '', 'name' => $input['name'] ?? '', 'spec' => $input['spec'] ?? []]), JSON_UNESCAPED_UNICODE);
        break;
    case 'delete':
        echo json_encode(['ok' => report_delete((string)($input['id'] ?? ''))], JSON_UNESCAPED_UNICODE);
        break;
    case 'meta':
        echo json_encode(['ok' => true, 'dimensions' => report_dimensions(), 'metrics' => report_metrics()], JSON_UNESCAPED_UNICODE);
        break;
    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '未知 action'], JSON_UNESCAPED_UNICODE);
}
