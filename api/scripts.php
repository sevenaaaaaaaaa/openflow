<?php
/**
 * 脚本注入 API — 返回当前页面应启用的脚本 + AB 测试分流
 * GET /api/scripts.php?path=/article/xxx
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();
header('Cache-Control: no-cache');

$path = $_GET['path'] ?? (($_SERVER['HTTP_REFERER'] ?? '') ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) : '/');
if (empty($path)) $path = '/';

// ─── 脚本列表 ───
$scripts = json_read(DATA_DIR . '/scripts.json');
$enabledScripts = [];
foreach ($scripts as $s) {
    if (empty($s['enabled'])) continue;
    // 页面范围过滤
    if (($s['page_scope'] ?? 'all') === 'specific') {
        $paths = array_map('trim', explode(',', $s['page_paths'] ?? ''));
        $matched = false;
        foreach ($paths as $p) {
            if ($p !== '' && (strpos($path, $p) === 0)) { $matched = true; break; }
        }
        if (!$matched) continue;
    }
    $enabledScripts[] = $s;
}

// ─── AB 测试分流 ───
$abtests = json_read(DATA_DIR . '/abtests.json');
$activeTests = [];
$uid = $_COOKIE['fc_uid'] ?? '';
if ($uid === '') {
    $uid = 'u_' . bin2hex(random_bytes(8));
    setcookie('fc_uid', $uid, time() + 86400 * 365, '/');
}

foreach ($abtests as $t) {
    if (($t['enabled'] ?? false) !== true) continue;
    // 时间窗口
    $now = time();
    if (!empty($t['start_date']) && strtotime($t['start_date']) > $now) continue;
    if (!empty($t['end_date']) && strtotime($t['end_date'] . ' 23:59:59') < $now) continue;
    // 页面匹配
    if (($t['page_scope'] ?? 'all') === 'specific') {
        $paths = array_map('trim', explode(',', $t['page_paths'] ?? ''));
        $matched = false;
        foreach ($paths as $p) if ($p !== '' && strpos($path, $p) === 0) { $matched = true; break; }
        if (!$matched) continue;
    }
    // 确定性分流：基于 uid hash
    $hash = crc32($uid . '|' . $t['id']);
    $roll = abs($hash) % 100;
    $variantB = ($roll < (int)($t['traffic_b'] ?? 50));
    $activeTests[] = [
        'id' => $t['id'],
        'name' => $t['name'],
        'variant' => $variantB ? 'B' : 'A',
        'css' => $t['css_' . ($variantB ? 'b' : 'a')] ?? '',
        'js' => $t['js_' . ($variantB ? 'b' : 'a')] ?? '',
        'redirect' => $variantB ? ($t['url_b'] ?? '') : ($t['url_a'] ?? ''),
    ];
}

echo json_encode(['ok' => true, 'scripts' => $enabledScripts, 'abtests' => $activeTests, 'path' => $path], JSON_UNESCAPED_UNICODE);
