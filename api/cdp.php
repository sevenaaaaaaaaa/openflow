<?php
/**
 * CDP 行为追踪 API
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_once __DIR__ . '/../lib/EventIdentity.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'track':
        $event = $_POST['event'] ?? '';
        $data = json_decode($_POST['data'] ?? '{}', true) ?: [];
        $visitorId = $_POST['visitor_id'] ?? '';
        $eventId = event_identity($data + $_POST);

        if (empty($event)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => '缺少事件名']);
            exit;
        }

        CdpSystem::track($event, $data + ['_event_id'=>$eventId], $visitorId);

        // 触发营销自动化（行为事件 → MA 流程）
        $triggerEvents = ['page_view', 'article_view', 'element_click', 'download', 'purchase', 'course_complete', 'course_start', 'course_enroll', 'lesson_complete', 'role_selected', 'tool_use', 'user_register', 'user_login', 'form_submit'];
        if (in_array($event, $triggerEvents, true)) {
            try {
                require_once __DIR__ . '/../lib/FlowSystem.php';
                flow_handle($event, [
                    'uid' => $visitorId,
                    'member_id' => $data['member_id'] ?? ($_SESSION['member_id'] ?? ''),
                    'email' => $data['email'] ?? ($_SESSION['member_email'] ?? ''),
                    'label' => $data['label'] ?? $event,
                    'page' => $data['url_path'] ?? '',
                    'props' => $data,
                    'event_id' => $eventId,
                ]);
            } catch (Exception $e) {}
        }

        echo json_encode(['ok' => true]);
        break;

    case 'track_batch':
        $events = json_decode($_POST['events'] ?? '[]', true) ?: [];
        $count = CdpSystem::trackBatch($events);
        echo json_encode(['ok' => true, 'count' => $count]);
        break;

    case 'profile':
        $visitorId = $_GET['visitor_id'] ?? '';
        $profile = $visitorId ? CdpSystem::getProfile($visitorId) : null;
        if ($profile) {
            echo json_encode(['ok' => true, 'profile' => $profile], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => '用户不存在']);
        }
        break;

    case 'stats':
        $days = (int)($_GET['days'] ?? 7);
        $stats = CdpSystem::getEventStats($days);
        $topEvents = CdpSystem::getTopEvents();
        echo json_encode([
            'ok' => true,
            'stats' => $stats,
            'top_events' => $topEvents,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'funnel':
        $steps = json_decode($_GET['steps'] ?? '[]', true) ?: [];
        $days = (int)($_GET['days'] ?? 30);
        $funnel = CdpSystem::getFunnel($steps, $days);
        echo json_encode(['ok' => true, 'funnel' => $funnel], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '未知操作']);
}
