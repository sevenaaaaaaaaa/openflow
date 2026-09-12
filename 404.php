<?php
/**
 * 404 兜底入口 — 由 .htaccess 的 ErrorDocument 404 触发。
 *
 * 顺序：
 *   1) 先让 admin/config.php 的 redirects.json 拦截器跑一遍（命中 exact path → 301 或 410）
 *   2) 未命中 → 记录 404（供「404 分诊」常态分析）+ 渲染友好 404 页
 *
 * 只处理真正的 404，不干扰任何现有路由。
 */
$__path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

// 1) 重定向/410 拦截（config 内的拦截器命中会自行 header+exit）
require_once __DIR__ . '/admin/config.php';

// 2) 记录 404
try {
    if (PHP_SAPI !== 'cli') {
        $log = json_read(DATA_DIR . '/404-log.json');
        if (!is_array($log)) $log = [];
        $key = mb_substr($__path, 0, 180);
        $log[$key] = ['c' => (int)($log[$key]['c'] ?? 0) + 1, 'last' => date('Y-m-d H:i:s'), 'ref' => mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 160)];
        if (count($log) > 3000) $log = array_slice($log, -3000, null, true);
        json_write(DATA_DIR . '/404-log.json', $log);
    }
} catch (\Throwable $e) {}

http_response_code(404);
header('Cache-Control: no-store, max-age=0');
$site = function_exists('site_config_get') ? (string)site_config_get('site_name') : 'OpenFlow';
?><!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,follow">
<title>页面不存在 · <?=htmlspecialchars($site)?></title>
<style>
body{margin:0;background:var(--bg,#f7f7f5);color:var(--fg,#111);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"PingFang SC","Microsoft YaHei",sans-serif;display:grid;place-items:center;min-height:100vh;padding:24px;text-align:center}
.wrap{max-width:520px}
.code{font-size:64px;font-weight:800;letter-spacing:-.03em;line-height:1}
h1{font-size:22px;margin:14px 0 8px}
p{color:var(--muted,#6b7280);font-size:14.5px;line-height:1.8;margin:0 0 22px}
.links{display:flex;gap:10px;flex-wrap:wrap;justify-content:center}
a.btn{padding:10px 20px;border-radius:10px;text-decoration:none;font-size:14px;border:1px solid var(--border,#e5e7eb)}
a.primary{background:var(--accent,#2563eb);color:#fff;border-color:transparent}
a.ghost{background:transparent;color:var(--fg,#111)}
.search{margin-top:22px;display:flex;gap:8px;justify-content:center}
.search input{flex:1;max-width:280px;padding:10px 12px;border:1px solid var(--border,#e5e7eb);border-radius:10px;font-size:14px}
</style>
</head>
<body>
<div class="wrap">
  <div class="code">404</div>
  <h1>这个页面已经不在原位了</h1>
  <p>你访问的链接可能已随站点改版迁移或下架。可以从下面这些入口继续，或直接搜索。</p>
  <div class="links">
    <a class="btn primary" href="/">返回首页</a>
    <a class="btn ghost" href="/articles">浏览文章</a>
    <a class="btn ghost" href="/help">帮助中心</a>
    <a class="btn ghost" href="/community">社区</a>
  </div>
  <form class="search" method="get" action="/articles">
    <input name="q" placeholder="搜索文章…" aria-label="搜索">
    <button class="btn primary" type="submit">搜索</button>
  </form>
</div>
</body>
</html>
