<?php
/**
 * 站点 URL 巡检 API — 自动检测全站 403/404/5xx
 * 用法：build 生成清单 → scan&offset=N 分批检测
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MarketplaceSystem.php';

if (empty($_SESSION['admin_login'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

function site_base(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . ':' . SITE_URL;
}

function build_url_check_list(): array {
    $base = site_base();
    $urls = [];

    // 1. 前台静态路由（真实入口）
    foreach (['/', '/marketplace', '/events', '/courses', '/search', '/tools', '/navigation', '/member.php?view=login', '/articles', '/xmp'] as $p) {
        $urls[] = ['url' => $base . $p, 'label' => $p, 'group' => '前台'];
    }

    // 2. 技能
    foreach (json_read(DATA_DIR . '/skills/index.json') as $s) {
        if (!empty($s['id'])) $urls[] = ['url' => $base . '/skill/' . rawurlencode($s['id']), 'label' => '/skill/' . $s['id'], 'group' => '技能'];
    }

    // 3. 插件 / 主题
    foreach (mkt_assets() as $a) {
        $t = $a['type'] ?? '';
        if (in_array($t, ['plugin', 'theme'], true) && !empty($a['id'])) {
            $urls[] = ['url' => $base . '/' . $t . '/' . rawurlencode($a['id']), 'label' => '/' . $t . '/' . $a['id'], 'group' => ($t === 'plugin' ? '插件' : '主题')];
        }
    }

    // 4. 课程（已发布）
    foreach (json_read(DATA_DIR . '/courses/index.json') as $c) {
        if (($c['status'] ?? '') === 'published' && !empty($c['slug'])) {
            $urls[] = ['url' => $base . '/course/' . rawurlencode($c['slug']), 'label' => '/course/' . $c['slug'], 'group' => '课程'];
        }
    }

    // 5. 活动
    foreach (json_read(DATA_DIR . '/events.json') as $e) {
        if (!empty($e['slug'])) $urls[] = ['url' => $base . '/event/' . rawurlencode($e['slug']), 'label' => '/event/' . $e['slug'], 'group' => '活动'];
    }

    // 6. 落地页
    foreach (json_read(DATA_DIR . '/site-pages.json') as $p) {
        if (!empty($p['slug'])) $urls[] = ['url' => $base . '/lp/' . rawurlencode($p['slug']), 'label' => '/lp/' . $p['slug'], 'group' => '落地页'];
    }

    // 7. 文章（仅已发布）
    foreach (get_articles() as $a) {
        if (($a['status'] ?? '') === 'published' && !empty($a['slug'])) {
            $urls[] = ['url' => $base . '/article/' . rawurlencode($a['slug']), 'label' => '/article/' . $a['slug'], 'group' => '文章'];
        }
    }

    // 8. 导航站
    $nav = json_read(DATA_DIR . '/navigation.json');
    foreach (($nav['sites'] ?? []) as $s) {
        if (!empty($s['id'])) $urls[] = ['url' => $base . '/navigation-site.php?site=' . rawurlencode($s['id']), 'label' => '/navigation-site.php?site=' . $s['id'], 'group' => '导航站'];
    }

    // 9. 后台页面（未登录 302→登录页 = 路由存在）
    $cfg = file_get_contents(__DIR__ . '/../admin/config.php');
    preg_match_all('#href="/xmp/([a-z0-9\-_]+)"#i', $cfg, $m);
    foreach (array_unique($m[1] ?? []) as $page) {
        $urls[] = ['url' => $base . '/xmp/' . $page, 'label' => '/xmp/' . $page, 'group' => '后台'];
    }

    return $urls;
}

if ($action === 'build') {
    $_SESSION['url_check_list'] = build_url_check_list();
    echo json_encode(['ok' => true, 'total' => count($_SESSION['url_check_list'])]);
    exit;
}

if ($action === 'scan') {
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit = min(6, max(1, (int)($_GET['limit'] ?? 6)));
    $list = $_SESSION['url_check_list'] ?? build_url_check_list();
    $out = [];
    foreach (array_slice($list, $offset, $limit) as $item) {
        $code = 0; $ms = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($item['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_NOBODY => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'OpenFlow-SiteHealth',
            ]);
            $t = microtime(true);
            curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ms = (int)round((microtime(true) - $t) * 1000);
        } else {
            $t = microtime(true);
            $h = @get_headers($item['url']);
            $ms = (int)round((microtime(true) - $t) * 1000);
            $code = $h ? (int)explode(' ', $h[0])[1] : 0;
        }
        $ok = in_array($code, [200, 301, 302], true);
        $out[] = ['url' => $item['url'], 'label' => $item['label'], 'group' => $item['group'], 'code' => $code, 'ms' => $ms, 'ok' => $ok];
    }
    echo json_encode(['ok' => true, 'offset' => $offset, 'items' => $out, 'total' => count($list)]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'bad action']);
