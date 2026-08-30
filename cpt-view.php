<?php
/**
 * 自定义内容类型 · 前台单条视图  /c/{type}/{slug}
 * 轻量、主题自适应、自包含（主题可覆盖）。仅展示「公开」类型下「已发布」的条目。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/CptSystem.php';

$typeSlug = preg_replace('/[^a-z0-9-]/', '', $_GET['type'] ?? '');
$slug = (string)($_GET['slug'] ?? '');
$type = $typeSlug ? cpt_type($typeSlug) : null;
$entry = ($type && !empty($type['public'])) ? cpt_entry_by_slug($typeSlug, $slug) : null;
if ($entry && ($entry['status'] ?? '') !== 'published') $entry = null;

if (!$type || empty($type['public']) || !$entry) {
    http_response_code(404);
    $title = '未找到';
} else {
    $title = $entry['title'] . ' · ' . $type['name'];
}
$fieldLabel = [];
foreach (($type['fields'] ?? []) as $f) $fieldLabel[$f['key']] = ['label' => $f['label'], 'type' => $f['type']];
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?></title>
<?php if ($entry): ?><meta name="description" content="<?=htmlspecialchars(mb_substr(strip_tags((string)($entry['fields'][array_key_first($entry['fields'])] ?? '')), 0, 140))?>"><?php endif; ?>
<style>
  :root{color-scheme:light dark;--bg:#f7f7f8;--card:#fff;--text:#1a1a1a;--soft:#5b6470;--faint:#9aa3af;--border:#e6e8ec;--accent:#4f46e5}
  @media (prefers-color-scheme:dark){:root{--bg:#0f1115;--card:#171a21;--text:#e8eaed;--soft:#aab2bd;--faint:#6b7480;--border:#262b34;--accent:#8b90ff}}
  *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.7 system-ui,-apple-system,"Segoe UI",Roboto,"PingFang SC","Microsoft Yahei",sans-serif}
  .wrap{max-width:760px;margin:0 auto;padding:32px 20px 64px}
  a{color:var(--accent)}.back{font-size:13px;color:var(--soft);text-decoration:none}
  h1{font-size:26px;margin:14px 0 6px;line-height:1.3}
  .kind{font-size:13px;color:var(--faint)}
  .card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:22px;margin-top:18px}
  .frow{padding:10px 0;border-bottom:1px dashed var(--border);display:flex;gap:14px}
  .frow:last-child{border-bottom:none}
  .flabel{min-width:96px;color:var(--faint);font-size:13px}.fval{flex:1}
  .rich :is(h2,h3){margin:14px 0 6px}.rich img{max-width:100%;border-radius:8px}
  .muted{color:var(--faint);text-align:center;padding:80px 0}
</style>
</head>
<body>
<div class="wrap">
  <?php if (!$entry): ?>
    <div class="muted">内容不存在或未公开。<br><a class="back" href="/">← 返回首页</a></div>
  <?php else: ?>
    <a class="back" href="/c/<?=htmlspecialchars($type['slug'])?>">← <?=htmlspecialchars($type['name_plural'] ?? $type['name'])?></a>
    <div class="kind"><?=htmlspecialchars($type['icon'] ?? '📄')?> <?=htmlspecialchars($type['name'])?></div>
    <h1><?=htmlspecialchars($entry['title'])?></h1>
    <div class="card">
      <?php foreach (($entry['fields'] ?? []) as $k => $v):
        if ($v === '' || $v === null || $v === false) continue;
        $meta = $fieldLabel[$k] ?? ['label' => $k, 'type' => 'text']; ?>
      <div class="frow">
        <div class="flabel"><?=htmlspecialchars($meta['label'])?></div>
        <div class="fval">
          <?php if ($meta['type'] === 'richtext'): ?>
            <div class="rich"><?=$v /* 富文本：管理员录入，按原样渲染 */ ?></div>
          <?php elseif ($meta['type'] === 'image'): ?>
            <img src="<?=htmlspecialchars((string)$v)?>" alt="">
          <?php elseif ($meta['type'] === 'url'): ?>
            <a href="<?=htmlspecialchars((string)$v)?>" rel="nofollow"><?=htmlspecialchars((string)$v)?></a>
          <?php elseif ($meta['type'] === 'bool'): ?>
            <?=$v ? '是' : '否'?>
          <?php else: ?>
            <?=nl2br(htmlspecialchars((string)$v))?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
