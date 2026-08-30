<?php
/**
 * 导航站点详情页
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CommentSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/comment-widget.php';

$nav = json_read(DATA_DIR . '/navigation.json');
$sites = $nav['sites'] ?? [];
$categories = $nav['categories'] ?? [];
$siteId = $_GET['site'] ?? '';
$site = null;
foreach ($sites as $s) if ($s['id'] === $siteId) { $site = $s; break; }
if (!$site) { http_response_code(404); die('站点不存在'); }

$catNames = [];
foreach ($categories as $c) $catNames[$c['id']] = $c['name'];

// 相关站点（同分类）
$related = array_values(array_filter($sites, fn($s) => $s['id'] !== $siteId && ($s['category'] ?? '') === ($site['category'] ?? '')));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($site['name'])?>  | <?=site_config_get("site_name")?> 增长导航</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260830a" defer></script>
<style>
  body{background:var(--bg);font-family:-apple-system,'PingFang SC','Noto Sans SC',system-ui,sans-serif}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260826b" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-8" style="max-width:900px">
    <!-- 站点主卡 -->
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:28px;margin-bottom:24px;display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
      <div style="width:64px;height:64px;border-radius:16px;display:grid;place-items:center;font-size:30px;background:var(--bg)"><?=($site['region']??'')==='cn'?'🇨🇳':'🌍'?></div>
      <div style="flex:1;min-width:200px">
        <h1 class="text-2xl font-bold"><?=htmlspecialchars($site['name'])?>
          <?php if (!empty($site['featured'])): ?><span class="text-sm text-[#b45309]">⭐ 编辑推荐</span><?php endif; ?>
        </h1>
        <div class="text-sm text-gray-600 mt-2"><?=htmlspecialchars($catNames[$site['category'] ?? ''] ?? '未分类')?> · <?=($site['region']??'')==='cn'?'国内':'海外'?></div>
        <p class="text-gray-600 mt-4 leading-relaxed"><?=htmlspecialchars($site['description'] ?? '')?></p>
        <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
          <a href="<?=htmlspecialchars($site['url'] ?? '#')?>" target="_blank" rel="noopener" class="rounded-full px-7 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">访问网站 →</a>
          <button class="rounded-full px-7 py-3 font-bold border border-[var(--border)]" style="background:var(--surface);color:var(--fg)" onclick="copyURL()">复制链接</button>
        </div>
      </div>
    </div>

    <!-- 点评/评论（大众点评化） -->
    <div style="margin-top:40px">
      <?php fc_comment_widget('site', $site['id'], ['title' => '用户点评', 'rating' => true]); ?>
    </div>

    <!-- 相关站点 -->
    <?php if ($related): ?>
    <div>
      <h2 class="font-bold text-lg mb-4">🔗 相关推荐</h2>
      <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(250px,1fr))">
        <?php foreach (array_slice($related, 0, 4) as $r): ?>
        <a href="/navigation/<?=urlencode($r['id'])?>" style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;text-decoration:none;color:inherit;transition:.15s">
          <div class="font-bold"><?=htmlspecialchars($r['name'])?></div>
          <div class="text-sm text-gray-600 mt-1 line-clamp-2"><?=htmlspecialchars($r['description'] ?? '')?></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
<script>
function copyURL() {
  var url = <?=json_encode($site['url'] ?? '')?>;
  navigator.clipboard.writeText(url).then(function() { alert('链接已复制'); });
}
</script>
</body>
</html>
