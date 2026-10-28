<?php
// 本地开发路由（仅用于 php -S，不部署）：复刻 .htaccess 的干净 URL 重写
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = urldecode($uri);

// 静态文件直接交给内置服务器
$root = __DIR__;
if ($path !== '/' && is_file($root . $path)) return false;

// 语言前缀
if (preg_match('#^/(en|ja|zh-CN|zh-TW|ru|fr)(/.*)$#', $path, $m)) { $path = $m[2] ?: '/'; }

// 文章详情
if (preg_match('#^/article/([^/]+)/?$#', $path, $m)) { $_GET['slug'] = $m[1]; require $root . '/article.php'; return; }
if (preg_match('#^/articles/([^/]+)/?$#', $path, $m)) { $_GET['slug'] = $m[1]; require $root . '/article.php'; return; }
if (preg_match('#^/articles/?$#', $path)) { require $root . '/articles.php'; return; }
if (preg_match('#^/tag/([^/]+)/?$#', $path, $m)) { header('Location: /articles?tag=' . urlencode($m[1]), true, 301); return; }
if (preg_match('#^/help/([a-z0-9-]+)/?$#', $path, $m)) { $_GET['slug'] = $m[1]; require $root . '/help-article.php'; return; }

// 根级 .php 页面：/about -> about.php
if (preg_match('#^/([a-z0-9-]+)/?$#', $path, $m) && is_file($root . '/' . $m[1] . '.php')) {
  require $root . '/' . $m[1] . '.php'; return;
}

// 兜底首页
require $root . '/index.php';
