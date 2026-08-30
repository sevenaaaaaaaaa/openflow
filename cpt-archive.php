<?php
/**
 * 自定义内容类型 · 前台归档  /c/{type}
 * 列出该公开类型下所有「已发布」条目。轻量、自包含、主题自适应。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/CptSystem.php';

$typeSlug = preg_replace('/[^a-z0-9-]/', '', $_GET['type'] ?? '');
$type = $typeSlug ? cpt_type($typeSlug) : null;
$public = ($type && !empty($type['public']));
$entries = $public ? cpt_public_entries($typeSlug) : [];
if (!$public) http_response_code(404);
$title = $public ? ($type['name_plural'] ?? $type['name']) : '未找到';
// 首个字段作为卡片摘要
$firstKey = $type['fields'][0]['key'] ?? '';
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?></title>
<style>
  :root{color-scheme:light dark;--bg:#f7f7f8;--card:#fff;--text:#1a1a1a;--soft:#5b6470;--faint:#9aa3af;--border:#e6e8ec;--accent:#4f46e5}
  @media (prefers-color-scheme:dark){:root{--bg:#0f1115;--card:#171a21;--text:#e8eaed;--soft:#aab2bd;--faint:#6b7480;--border:#262b34;--accent:#8b90ff}}
  *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.7 system-ui,-apple-system,"Segoe UI",Roboto,"PingFang SC","Microsoft Yahei",sans-serif}
  .wrap{max-width:860px;margin:0 auto;padding:32px 20px 64px}
  a{color:inherit;text-decoration:none}
  h1{font-size:26px;margin:6px 0 4px}.kind{font-size:13px;color:var(--faint)}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-top:20px}
  .item{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px 18px;transition:border-color .15s}
  .item:hover{border-color:var(--accent)}
  .item h3{margin:0 0 6px;font-size:16px}.item p{margin:0;color:var(--soft);font-size:13px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .muted{color:var(--faint);text-align:center;padding:80px 0}
</style>
</head>
<body>
<div class="wrap">
  <?php if (!$public): ?>
    <div class="muted">该内容类型不存在或未公开。<br><a href="/">← 返回首页</a></div>
  <?php else: ?>
    <div class="kind"><?=htmlspecialchars($type['icon'] ?? '📄')?> <?=htmlspecialchars($type['name'])?></div>
    <h1><?=htmlspecialchars($title)?></h1>
    <?php if (!$entries): ?>
      <div class="muted">还没有已发布的内容。</div>
    <?php else: ?>
    <div class="grid">
      <?php foreach ($entries as $e): $summary = $firstKey ? strip_tags((string)($e['fields'][$firstKey] ?? '')) : ''; ?>
      <a class="item" href="/c/<?=htmlspecialchars($type['slug'])?>/<?=htmlspecialchars($e['slug'])?>">
        <h3><?=htmlspecialchars($e['title'])?></h3>
        <?php if ($summary !== ''): ?><p><?=htmlspecialchars($summary)?></p><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
