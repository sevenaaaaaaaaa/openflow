<?php
/**
 * 前端遥测上报 API — 收集 JS 错误 / 页面性能 / 404
 * POST /api/evolution-report.php  Body: { type: "js_error|perf|route_404", ... }
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$type = $input['type'] ?? '';

// 限流：单 IP 每分钟最多 30 条
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rl = json_read(DATA_DIR . '/evolution-rate.json');
$key = $ip . ':' . (int)floor(time() / 60);
$rl[$key] = ($rl[$key] ?? 0) + 1;
if ($rl[$key] > 30) { echo json_encode(['ok' => false, 'error' => 'rate limited']); exit; }
// 清理旧 key
if (count($rl) > 200) { $rl = array_slice($rl, -100); }
json_write(DATA_DIR . '/evolution-rate.json', $rl);

switch ($type) {
    case 'js_error':
        $errors = json_read(DATA_DIR . '/evolution-js-errors.json');
        $errors[] = [
            'msg' => mb_substr((string)($input['msg'] ?? ''), 0, 300),
            'page' => mb_substr((string)($input['page'] ?? ''), 0, 120),
            'line' => (int)($input['line'] ?? 0),
            'ts' => time(),
        ];
        if (count($errors) > 2000) $errors = array_slice($errors, -1500);
        json_write(DATA_DIR . '/evolution-js-errors.json', $errors);
        echo json_encode(['ok' => true]);
        break;

    case 'perf':
        $perf = json_read(DATA_DIR . '/evolution-perf.json');
        $perf[] = [
            'page' => mb_substr((string)($input['page'] ?? ''), 0, 120),
            'load_ms' => (int)($input['load_ms'] ?? 0),
            'ts' => time(),
        ];
        if (count($perf) > 2000) $perf = array_slice($perf, -1500);
        json_write(DATA_DIR . '/evolution-perf.json', $perf);
        echo json_encode(['ok' => true]);
        break;

    case 'route_404':
        $f404 = json_read(DATA_DIR . '/evolution-404.json');
        $path = mb_substr((string)($input['path'] ?? ''), 0, 160);
        if ($path) {
            $f404[$path] = ($f404[$path] ?? 0) + 1;
            if (count($f404) > 200) $f404 = array_slice($f404, -150);
            json_write(DATA_DIR . '/evolution-404.json', $f404);
        }
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'unknown type']);
}
