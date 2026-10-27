<?php
declare(strict_types=1);
/**
 * Report Hub 出流：/r/{slug}[/{path...}]
 *
 * 权限四档在 lib/ReportHub.php；这里只做三件事：鉴权 → 定位文件 → 带正确 MIME 输出。
 * 目录穿越用 realpath 前缀校验挡掉；版本号可用 /r/{slug}/v{version}/... 显式指定（需登录）。
 */

$root = __DIR__;
require_once $root . '/admin/config.php';
require_once $root . '/lib/ReportHub.php';

$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = (string)parse_url($uri, PHP_URL_PATH);
$rest = preg_replace('~^/r/~', '', $path);
$rest = trim($rest, '/');

if ($rest === '') {
    http_response_code(404);
    exit('Not found');
}

$segments = explode('/', $rest);
$slug = array_shift($segments) ?: '';
$project = rh_project($slug);
if (!$project) {
    http_response_code(404);
    echo '<h1>404</h1><p>报告不存在。</p>';
    exit;
}

// ── 版本：/r/slug/v3/... 显式指定，否则用 active ──
$versionRow = rh_active_version((int)$project['id']);
if ($segments && preg_match('/^v(\d+)$/', $segments[0], $m)) {
    if (empty($_SESSION['admin_login'])) { http_response_code(403); exit('指定版本需要登录'); }
    $vr = Database::query("SELECT * FROM rh_versions WHERE project_id = ? AND version = ?", [(int)$project['id'], (int)$m[1]]);
    if (!$vr) { http_response_code(404); exit('版本不存在'); }
    $versionRow = $vr[0];
    array_shift($segments);
}
if (!$versionRow) {
    http_response_code(404);
    echo '<h1>还没有发布内容</h1>';
    exit;
}

// ── 鉴权 ──
$vis = (string)$project['visibility'];
$token = (string)($_GET['t'] ?? '');
$share = rh_share_valid($token);
$isAdmin = !empty($_SESSION['admin_login']);
$isMember = !empty($_SESSION['member_id']);
$unlocked = !empty($_SESSION['rh_unlock'][$slug]);

$allowed = $isAdmin || $vis === 'public';
if (!$allowed && $vis === 'members') $allowed = $isMember;
if (!$allowed && $vis === 'link') $allowed = (bool)$share;
if (!$allowed && $share) $allowed = true;              // 任何一档，有效分享链接都能看
if (!$allowed && $vis === 'password') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw'])) {
        if (!empty($project['password_hash']) && password_verify((string)$_POST['pw'], (string)$project['password_hash'])) {
            $_SESSION['rh_unlock'][$slug] = true;
            $unlocked = true; $allowed = true;
        }
    }
    if (!$allowed && $unlocked) $allowed = true;
}

if (!$allowed) {
    if ($vis === 'password') {
        http_response_code(401);
        echo '<!doctype html><meta charset="utf-8"><title>需要口令</title>'
           . '<div style="font:15px -apple-system,system-ui;max-width:360px;margin:18vh auto;padding:0 20px">'
           . '<h2 style="font-size:18px">' . htmlspecialchars((string)$project['name']) . '</h2>'
           . '<p style="color:#888">该报告需要访问口令。</p>'
           . '<form method="post"><input name="pw" type="password" placeholder="口令" autofocus '
           . 'style="width:100%;padding:9px 11px;border:1px solid #ccc;border-radius:8px;margin:8px 0">'
           . '<button style="padding:9px 16px;border:0;border-radius:8px;background:#111;color:#fff">打开</button>'
           . '</form></div>';
        exit;
    }
    http_response_code(403);
    echo '<h1>无权访问</h1><p>请用有效链接，或先登录。</p>';
    exit;
}

// ── 定位文件 ──
$base = rh_root() . '/' . $slug . '/' . (int)$versionRow['version'];
$rel = $segments ? implode('/', $segments) : (string)($versionRow['entry'] ?: 'index.html');
$rel = preg_replace('~\.\./~', '', $rel);                 // 基础防穿越
$file = realpath($base . '/' . $rel);
$baseReal = realpath($base);
if ($file === false || $baseReal === false || strpos($file, $baseReal) !== 0 || is_dir($file)) {
    // 目录请求 → 找 index.html
    $try = realpath($base . '/' . $rel . '/index.html');
    if ($try && strpos($try, $baseReal) === 0) { $file = $try; }
    else {
        http_response_code(404);
        echo '<h1>404</h1><p>文件不存在。</p>';
        exit;
    }
}

rh_log_view((int)$project['id'], (int)$versionRow['version'], '/' . $rel);

// ── 输出 ──
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
header('Content-Type: ' . rh_mime($file));
header('X-Content-Type-Options: nosniff');
// 报告页强缓存 5 分钟，资源 1 天；版本目录不可变，可安全长缓存
header($ext === 'html' ? 'Cache-Control: private, max-age=300' : 'Cache-Control: public, max-age=86400');
header('Content-Length: ' . (string)filesize($file));
readfile($file);
