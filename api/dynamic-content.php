<?php
/**
 * Dynamic Content API — 前端获取规则 + 曝光/点击追踪
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/DynamicContent.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'rules':
        // 获取当前页面适用的规则
        $pageType = $_GET['page'] ?? '';
        $pageId = $_GET['page_id'] ?? '';
        $params = DynamicContent::getURLParams();
        $rules = DynamicContent::matchingRules($pageType, $pageId, $params);

        // 记录曝光
        foreach ($rules as $rule) {
            DynamicContent::trackImpression($rule['id'], $pageType);
        }

        echo json_encode([
            'ok' => true,
            'rules' => $rules,
            'params' => $params,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'track_click':
        $ruleId = $_POST['rule_id'] ?? '';
        $actionType = $_POST['action_type'] ?? '';
        $selector = $_POST['selector'] ?? '';
        if ($ruleId) {
            DynamicContent::trackClick($ruleId, $actionType, $selector);
        }
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '未知操作']);
}
