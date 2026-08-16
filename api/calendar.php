<?php
/**
 * 内容日历 API — 拖拽后更新发布日期
 * POST /api/calendar.php
 * Body: { "id": "xxx", "type": "article|download|event", "date": "2026-09-15" }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = $input['id'] ?? '';
$type = $input['type'] ?? '';
$date = $input['date'] ?? '';

if (empty($id) || empty($date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '缺少必要参数']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '日期格式错误']);
    exit;
}

$today = date('Y-m-d');
$isFuture = $date > $today;

switch ($type) {
    case 'article':
        $a = get_article($id);
        if (!$a) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'文章不存在']); exit; }
        if ($isFuture) {
            // 未来日期 = 定时发布
            $a['publish_at'] = $date . ' 09:00:00';
            $a['status'] = 'draft'; // 保持草稿，由 cron 定时发布
            $a['updated_at'] = date('Y-m-d H:i:s');
            save_article($id, $a);
            echo json_encode(['ok' => true, 'msg' => '已设为定时发布：' . $date]);
        } else {
            // 当天/过去 = 立即发布
            $a['status'] = 'published';
            $a['publish_at'] = '';
            $a['created_at'] = $date . ' ' . substr($a['created_at'] ?? '09:00:00', 11);
            $a['updated_at'] = date('Y-m-d H:i:s');
            save_article($id, $a);
            // 触发 IndexNow
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
            $url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/article/' . $a['slug'];
            indexnow_ping($url);
            echo json_encode(['ok' => true, 'msg' => '已发布：' . $date]);
        }
        break;

    case 'download':
        $downloads = json_read(DATA_DIR . '/downloads.json');
        foreach ($downloads as &$d) {
            if ($d['id'] === $id) {
                $d['created_at'] = $date . ' ' . substr($d['created_at'] ?? '09:00:00', 11);
                $d['status'] = 'published';
                break;
            }
        }
        unset($d);
        json_write(DATA_DIR . '/downloads.json', $downloads);
        echo json_encode(['ok' => true, 'msg' => '资料发布日期已更新：' . $date]);
        break;

    case 'event':
        $events = json_read(DATA_DIR . '/events/index.json');
        $found = false;
        foreach ($events as &$e) {
            if ($e['id'] === $id) {
                $found = true;
                $sDate = substr($e['start_date'] ?? '', 0, 10);
                $eDate = substr($e['end_date'] ?? '', 0, 10);
                if (empty($eDate)) $eDate = $sDate;

                if (!empty($input['resize'])) {
                    // 调整开始或结束日
                    if ($input['resize'] === 'start') {
                        // 新 start = date；若新 start > 旧 end 则顺带把 end 设为 date
                        if ($date > $eDate) { $e['end_date'] = $date . ' 18:00:00'; }
                        $e['start_date'] = $date . ' ' . substr($e['start_date'] ?? '09:00:00', 11);
                    } else {
                        // 新 end = date；若新 end < 旧 start 则顺带把 start 设为 date
                        if ($date < $sDate) { $e['start_date'] = $date . ' 09:00:00'; }
                        $e['end_date'] = $date . ' ' . substr($e['end_date'] ?? '18:00:00', 11);
                    }
                    $msg = ($input['resize'] === 'start' ? '开始日期已调整：' : '结束日期已调整：') . $date;
                } elseif (isset($input['move_span'])) {
                    // 整体移动：保持跨度
                    $spanDays = (int)$input['move_span'];
                    $newEnd = date('Y-m-d', strtotime($date . ' +' . $spanDays . ' day'));
                    $e['start_date'] = $date . ' ' . substr($e['start_date'] ?? '09:00:00', 11);
                    $e['end_date'] = $newEnd . ' ' . substr($e['end_date'] ?? '18:00:00', 11);
                    $msg = '活动已移动到：' . $date . ($spanDays > 0 ? ' ~ ' . $newEnd : '');
                } else {
                    // 兼容旧行为：只设开始日
                    $e['start_date'] = $date . ' ' . substr($e['start_date'] ?? '09:00:00', 11);
                    if (empty($e['end_date'])) $e['end_date'] = $date . ' 18:00:00';
                    $msg = '活动开始日期已更新：' . $date;
                }
                break;
            }
        }
        unset($e);
        if (!$found) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'活动不存在']); exit; }
        json_write(DATA_DIR . '/events/index.json', $events);
        echo json_encode(['ok' => true, 'msg' => $msg]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '不支持的类型']);
}
