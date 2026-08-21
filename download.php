<?php
/**
 * 资料详情页 — 单个资料的完整展示 + 门禁表单下载
 * /download/{slug}
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$slug = req_str('slug', '');
$all = json_read(DATA_DIR . '/downloads.json');
$dl = null;
foreach ($all as $d) {
    if (($d['status'] ?? 'draft') === 'published' && (($d['slug'] ?? '') === $slug || ($d['id'] ?? '') === $slug)) { $dl = $d; break; }
}

if (!$dl) {
    header('Location: /downloads');
    exit;
}

$catDefs = get_categories('download');
$catNames = [];
foreach ($catDefs as $c) $catNames[$c['key']] = $c['name'];
$catName = $catNames[$dl['category'] ?? ''] ?? $dl['category'] ?? '资料';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($dl['title'])?> | <?=site_config_get('site_name')?></title>
<meta name="description" content="<?=htmlspecialchars(mb_substr($dl['description'] ?? '', 0, 120))?>">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:var(--font-body);color:var(--fg)}
  .dl-detail{border:1px solid var(--border);background:var(--surface);border-radius:24px}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260823" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-10" style="max-width:900px">
    <a href="/downloads" style="color:var(--muted);text-decoration:none;font-size:13px">← 返回资料中心</a>

    <div class="dl-detail mt-6 p-8">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div style="width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--ok));display:grid;place-items:center;font-size:28px">📄</div>
        <div>
          <div class="text-xs font-bold" style="color:var(--accent);letter-spacing:.1em">资料 · <?=htmlspecialchars($catName)?></div>
          <h1 class="text-2xl font-extrabold mt-1"><?=htmlspecialchars($dl['title'])?></h1>
        </div>
      </div>

      <p class="text-gray-600 leading-relaxed text-[15px]"><?=nl2br(htmlspecialchars($dl['description'] ?? ''))?></p>

      <?php if (!empty($dl['tags'])): ?>
      <div class="flex gap-1.5 flex-wrap mt-4">
        <?php foreach ($dl['tags'] as $t): ?>
        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold" style="background:var(--accent-soft);color:var(--accent)">#<?=htmlspecialchars($t)?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:20px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border);font-size:12.5px;color:var(--muted)">
        <span>📥 已下载 <?=(int)($dl['download_count'] ?? 0)?> 次</span>
        <span>🕒 更新于 <?=htmlspecialchars(substr($dl['updated_at'] ?? $dl['created_at'] ?? '', 0, 10))?></span>
      </div>

      <!-- 下载门禁表单 -->
      <div class="mt-6 rounded-2xl p-6" style="background:var(--bg-soft);border:1px solid var(--border)">
        <h2 class="font-bold mb-1">获取下载链接</h2>
        <p class="text-xs text-gray-600 mb-4">填写信息后即可获取下载</p>
        <form onsubmit="return submitDl(event)" class="grid gap-3">
          <input type="hidden" name="download_id" value="<?=htmlspecialchars($dl['id'])?>">
          <input type="text" name="name" required placeholder="你的姓名" class="w-full px-4 py-3 rounded-xl text-sm" style="border:1.5px solid var(--border);background:var(--surface)">
          <input type="email" name="email" required placeholder="工作邮箱" class="w-full px-4 py-3 rounded-xl text-sm" style="border:1.5px solid var(--border);background:var(--surface)">
          <input type="text" name="company" placeholder="公司 / 组织" class="w-full px-4 py-3 rounded-xl text-sm" style="border:1.5px solid var(--border);background:var(--surface)">
          <input type="text" name="title" placeholder="职位（选填）" class="w-full px-4 py-3 rounded-xl text-sm" style="border:1.5px solid var(--border);background:var(--surface)">
          <button type="submit" class="w-full rounded-full py-3 font-bold text-sm" style="background:var(--accent);color:var(--on-accent)">获取下载链接</button>
          <div id="dlMsg" class="text-sm text-center"></div>
        </form>
      </div>
    </div>
  </div>

<script>
function submitDl(e) {
  e.preventDefault();
  var msg = document.getElementById('dlMsg');
  var body = new FormData(e.target);
  fetch('/api/download.php', { method: 'POST', body: body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) {
        msg.innerHTML = '<span style="color:var(--ok)">✅ 下载开始…</span>';
        setTimeout(function(){ location.href = d.url; }, 800);
      } else {
        msg.innerHTML = '<span style="color:#dc2626">😅 ' + (d.error || '下载失败') + '</span>';
      }
    }).catch(function(){ msg.innerHTML = '<span style="color:#dc2626">网络异常</span>'; });
}
</script>
</body>
</html>
