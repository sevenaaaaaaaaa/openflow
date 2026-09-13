<?php
/**
 * 热点雷达 API（后台专用）
 *   POST action=run               跑一次：多源抓取 → 聚类 → 存盘
 *   POST action=settings_save     保存关键词/来源设置
 *   POST action=promote {title,angle,why}  转成 GEO 选题
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/RateLimiter.php';
require_once __DIR__ . '/../lib/TrendRadar.php';
require_login();
require_perm('settings');

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();
try { RateLimiter::throttle('trendradar:' . md5((string)($_SESSION['admin_user'] ?? 'anon')), 20, 600); } catch (\Throwable $e) {}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$action = (string)($input['action'] ?? ($_GET['action'] ?? ''));

switch ($action) {
    case 'run':
        echo json_encode(trend_run(true), JSON_UNESCAPED_UNICODE);
        break;
    case 'settings_save': {
        $s = trend_settings();
        $raw = trim((string)($input['keywords'] ?? ''));
        if ($raw !== '') $s['keywords'] = array_filter(array_map('trim', preg_split('/[,，\n]/u', $raw)));
        $rssRaw = trim((string)($input['rss'] ?? ''));
        if ($rssRaw !== '') $s['rss'] = array_filter(array_map('trim', preg_split('/[\n,]/', $rssRaw)));
        $s['min_points'] = (int)($input['min_points'] ?? $s['min_points']);
        $s['max_age_days'] = (int)($input['max_age_days'] ?? $s['max_age_days']);
        $s['enabled'] = !empty($input['enabled']);
        trend_save_settings($s);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        break;
    }
    case 'promote':
        echo json_encode(trend_promote((string)($input['title'] ?? ''), (string)($input['angle'] ?? ''), (string)($input['why'] ?? '')), JSON_UNESCAPED_UNICODE);
        break;
    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '未知 action'], JSON_UNESCAPED_UNICODE);
}
