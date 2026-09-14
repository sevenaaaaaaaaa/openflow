<?php
/**
 * 晨会简报 & 利润推演 API — briefing / sensitivity / whatif
 * 后台专用(登录 + dashboard 权限);briefing 的 AI 增强失败自动回退模板。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MorningBriefing.php';
require_once __DIR__ . '/../lib/ProfitLab.php';
require_once __DIR__ . '/../lib/RateLimiter.php';

header('Content-Type: application/json; charset=utf-8');
$action = (string)($_GET['action'] ?? 'briefing');

try { RateLimiter::throttle('morningbrief:' . md5((string)($_SESSION['admin_user'] ?? 'anon')), 20, 600); } catch (\Throwable $e) {}

switch ($action) {
    case 'briefing':
        echo json_encode(morning_briefing_ai(), JSON_UNESCAPED_UNICODE);
        break;

    case 'sensitivity':
        $b = profitlab_baseline();
        echo json_encode(['ok' => true, 'baseline' => $b, 'levers' => profitlab_sensitivity($b)], JSON_UNESCAPED_UNICODE);
        break;

    case 'whatif':
        $delta = [];
        foreach (['uv', 'lead_rate', 'close_rate', 'aov', 'repeat_rate'] as $f) {
            if (isset($_GET[$f])) $delta[$f] = max(-0.9, min(9, (float)$_GET[$f]));
        }
        echo json_encode(['ok' => true, 'result' => profitlab_whatif($delta)], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => '未知操作']);
}
