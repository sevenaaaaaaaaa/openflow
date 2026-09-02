<?php
/**
 * /category/{section}/{subkey} — 旧的「分类中转页」，现在只做 301。
 *
 * 为什么：这层中转页依赖一个不入库的 data/site-nav-content.php，服务器上没有或不全时整页 302 回首页；
 * 加上 PageCache 曾用同一个 key 缓存所有 /category/* ——匿名访客点导航任何一项都进到「别的入口」。
 * 导航（assets/site-shell.js）已改为直接指向真实页面，这里保留旧链接的去向，保住外链与 SEO。
 */
$section = preg_replace('/[^a-z]/', '', (string)($_GET['section'] ?? ''));
$subkey  = preg_replace('/[^a-z0-9_-]/', '', (string)($_GET['subkey'] ?? ''));

$map = [
    'products' => ['cms' => '/product#feat-canvas', 'ma' => '/product#feat-canvas', 'cdp' => '/capability#cap-insight', 'seo' => '/capability#cap-touch',
                   'crm' => '/capability#cap-sales', 'commerce' => '/capability#cap-sales', 'community' => '/community', 'data' => '/capability#cap-insight'],
    'capabilities' => ['content' => '/capability#cap-touch', 'growth' => '/capability#cap-touch', 'conversion' => '/capability#cap-personality',
                       'data' => '/capability#cap-insight', 'commerce' => '/capability#cap-sales', 'community' => '/community'],
    'courses' => ['big' => '/courses?f=' . rawurlencode('基石') . '#catalog', 'series' => '/courses#catalog', 'column' => '/articles', 'live' => '/events', 'free' => '/downloads'],
    'academy' => ['articles' => '/articles', 'downloads' => '/downloads', 'podcasts' => '/podcasts', 'topics' => '/topics', 'docs' => '/docs', 'tools' => '/tools'],
    'marketplace' => ['skills' => '/marketplace?type=skill', 'plugins' => '/marketplace?type=plugin', 'themes' => '/marketplace?type=theme', 'forum' => '/community'],
];
$sectionHome = ['products' => '/product', 'capabilities' => '/capability', 'courses' => '/courses', 'academy' => '/academy', 'marketplace' => '/marketplace'];

$to = $map[$section][$subkey] ?? ($sectionHome[$section] ?? '/');
header('Location: ' . $to, true, 301);
header('Cache-Control: public, max-age=86400');
exit;
