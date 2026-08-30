<?php
/**
 * Unified Content Preview — renders any content type in a clean preview layout
 * Usage: content-preview.php?type=article&id=xxx
 */
require_once __DIR__ . '/admin/config.php';

$type = req_str('type');
$id = req_str('id');

$title = '';
$content = '';
$meta = [];

if ($type === 'article') {
    $a = get_article($id);
    if ($a) { $title = $a['title']; $content = $a['content']; $meta = ['作者' => $a['author'] ?? '', '分类' => $a['category'] ?? '', '标签' => implode(', ', $a['tags'] ?? [])]; }
} elseif ($type === 'event') {
    $events = json_read(DATA_DIR . '/events/index.json');
    foreach ($events as $e) { if ($e['id'] === $id) { $title = $e['title']; $content = $e['content']; $meta = ['日期' => $e['start_date']??'', '地点' => $e['location']??'', '嘉宾' => count($e['speakers']??[]).'位']; break; } }
} elseif ($type === 'course') {
    $courses = json_read(DATA_DIR . '/courses/index.json');
    foreach ($courses as $c) { if ($c['id'] === $id) { $title = $c['title']; $content = $c['description']; $meta = ['类型' => $c['type']??'', '章节' => count($c['chapters']??[]).'章']; break; } }
} elseif ($type === 'topic') {
    $topics = get_topics();
    foreach ($topics as $t) { if ($t['id'] === $id) { $title = $t['title']; $content = $t['description'] ?? ''; $meta = ['slug' => $t['slug']??'']; break; } }
} elseif ($type === 'download') {
    $dls = json_read(DATA_DIR . '/downloads.json');
    foreach ($dls as $d) { if ($d['id'] === $id) { $title = $d['title']; $content = $d['description'] ?? ''; $meta = ['文件' => basename($d['file']??''), '下载' => ($d['download_count']??0).'次']; break; } }
} elseif ($type === 'landing') {
    $pages = get_landing_pages();
    foreach ($pages as $p) { if ($p['id'] === $id) { $title = $p['title']; $content = $p['description'] ?? ''; $meta = ['标签' => implode(', ', $p['aggregate_tags']??[]), '匹配文章' => count(json_read(DATA_DIR . '/articles/index.json')).'篇']; break; } }
}

if (empty($title)) { echo '<p style="padding:40px;text-align:center;color:#999">内容不存在</p>'; exit; }

?><!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>预览 - <?=htmlspecialchars($title)?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,'Inter','PingFang SC','Noto Sans SC',sans-serif;background:var(--bg);color:var(--fg);line-height:1.6;padding:0}
.bar{position:sticky;top:0;z-index:100;background:var(--surface);border-bottom:1px solid var(--border);padding:10px 24px;display:flex;align-items:center;gap:12px;font-size:13px}
.bar .tag{padding:2px 10px;border-radius:999px;background:var(--accent);font-weight:600;font-size:11px;text-transform:uppercase}
.main{max-width:800px;margin:0 auto;padding:32px 24px 80px}
h1{font-size:32px;font-weight:700;letter-spacing:-.02em;line-height:1.2;margin-bottom:8px}
.meta{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px;font-size:13px;color:#999}
.meta span{background:#ebe9e1;padding:3px 12px;border-radius:999px}
.body{background:var(--surface);border-radius:16px;padding:32px;border:1px solid rgba(0,0,0,.08)}
.body h2{font-size:22px;font-weight:600;margin:24px 0 8px}
.body h3{font-size:18px;font-weight:600;margin:16px 0 6px}
.body p{font-size:15px;color:var(--muted);margin-bottom:12px;line-height:1.7}
.body img{max-width:100%;border-radius:8px;margin:16px 0}
.body ul,.body ol{padding-left:24px;margin-bottom:12px;color:var(--muted)}
.body li{margin-bottom:4px}
.body blockquote{border-left:4px solid var(--accent);padding:8px 16px;margin:16px 0;background:var(--bg);border-radius:0 8px 8px 0;color:var(--muted)}
.body pre{background:var(--fg);color:var(--bg);padding:16px;border-radius:8px;overflow-x:auto;margin-bottom:16px;font-size:13px}
.body table{width:100%;border-collapse:collapse;margin:16px 0}
.body th,.body td{padding:8px 12px;border:1px solid var(--border);text-align:left;font-size:14px}
.body th{background:var(--bg);font-weight:600}
.not-found{padding:80px 24px;text-align:center;color:#999}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head><body>
<div class="bar"><span class="tag"><?=htmlspecialchars($type)?></span><span><?=htmlspecialchars($title)?></span><span style="margin-left:auto;color:#999">预览模式</span></div>
<div class="main">
  <h1><?=htmlspecialchars($title)?></h1>
  <div class="meta"><?php foreach ($meta as $k => $v): if (empty($v)) continue; ?><span><?=htmlspecialchars($k)?>: <?=htmlspecialchars($v)?></span><?php endforeach; ?></div>
  <div class="body"><?=$content ?: '<p style="color:#999">暂无内容</p>'?></div>
</div>
</body></html>
