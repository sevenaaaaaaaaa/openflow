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

// 站点 logo（有则用，无则取 favicon）
$logo = !empty($site['logo']) ? $site['logo'] : (function() use ($site) {
    $host = parse_url($site['url'] ?? '', PHP_URL_HOST);
    return $host ? 'https://www.google.com/s2/favicons?domain=' . $host . '&sz=64' : '';
})();

// 相关站点（同分类 + 已上架）
$related = array_values(array_filter($sites, fn($s) => $s['id'] !== $siteId && ($s['status'] ?? 'published') === 'published' && ($s['category'] ?? '') === ($site['category'] ?? '')));
// 同分类站点数（用于"同类推荐"计数）
$similarCount = count($related);

// 相关站点 logo 兜底 favicon
function nav_name(array $d): string {
    $locale = function_exists('i18n_current') ? i18n_current() : 'zh-CN';
    if (strpos($locale, 'en') === 0 && !empty($d['name_en'])) return $d['name_en'];
    return $d['name'] ?? '';
}
function nav_related_logo(array $s): string {
    if (!empty($s['logo'])) return $s['logo'];
    $host = parse_url($s['url'] ?? '', PHP_URL_HOST);
    return $host ? 'https://www.google.com/s2/favicons?domain=' . $host . '&sz=64' : '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars(nav_name($site))?>  | <?=site_config_get("site_name")?> 增长导航</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:-apple-system,'PingFang SC','Noto Sans SC',system-ui,sans-serif}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260816" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-8" style="max-width:900px">
    <!-- 站点主卡 -->
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:28px;margin-bottom:24px;display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
      <div style="width:64px;height:64px;border-radius:16px;display:grid;place-items:center;font-size:30px;background:var(--bg);overflow:hidden"><?php if ($logo): ?><img src="<?=htmlspecialchars($logo)?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?=($site['region']??'')==='cn'?'🇨🇳':'🌍'?><?php endif; ?></div>
      <div style="flex:1;min-width:200px">
        <h1 class="text-2xl font-bold"><?=htmlspecialchars(nav_name($site))?>
          <?php if (!empty($site['featured'])): ?><span class="text-sm text-[#b45309]">⭐ 编辑推荐</span><?php endif; ?>
        </h1>
        <div class="text-sm text-gray-600 mt-2"><?=htmlspecialchars($catNames[$site['category'] ?? ''] ?? '未分类')?> · <?=($site['region']??'')==='cn'?'国内':'海外'?><?php if (isset($site['hits'])): ?> · 👁 <?=number_format((int)$site['hits'])?> 次访问<?php endif; ?></div>
        <p class="text-gray-600 mt-4 leading-relaxed"><?=htmlspecialchars($site['description'] ?? '')?></p>
        <?php if (!empty($site['reason'])): ?>
        <div style="margin-top:12px;padding:10px 14px;border-radius:10px;background:#fdfce9;border:1px solid #f0e9c0;font-size:13px;color:#7c6f2d"><b>💡 推荐理由：</b><?=htmlspecialchars($site['reason'])?></div>
        <?php endif; ?>
        <?php if (!empty($site['tags'])): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
          <?php foreach ($site['tags'] as $t): ?>
          <a href="/navigation.php?tag=<?=urlencode($t)?>" style="font-size:12px;padding:3px 10px;border-radius:999px;background:var(--bg);color:var(--muted)">#<?=htmlspecialchars($t)?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
          <a href="/api/nav-click.php?site=<?=urlencode($site['id'])?>" target="_blank" rel="noopener" class="rounded-full px-7 py-3 font-bold" style="background:var(--accent);color:var(--on-accent)">访问网站 →</a>
          <button class="rounded-full px-7 py-3 font-bold border border-[var(--border)]" style="background:var(--surface);color:var(--fg)" onclick="copyURL()">复制链接</button>
        </div>
      </div>
    </div>

    <!-- 站点截图（整页预览） -->
    <?php $shotUrl = isset($site['url']) && preg_match('#^https?://#i', $site['url']) ? 'https://s.wordpress.com/mshots/v1/' . urlencode($site['url']) . '?w=720' : ''; ?>
    <?php if ($shotUrl): ?>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:20px;margin-bottom:24px">
      <h2 class="font-bold text-lg mb-3">🖥 站点预览</h2>
      <a href="/api/nav-click.php?site=<?=urlencode($site['id'])?>" target="_blank" rel="noopener">
        <img src="<?=htmlspecialchars($shotUrl)?>" alt="<?=htmlspecialchars(nav_name($site))?> 截图" loading="lazy" style="width:100%;border-radius:12px;border:1px solid var(--border)" onerror="this.parentElement.parentElement.style.display='none'">
      </a>
    </div>
    <?php endif; ?>

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
        <a href="/navigation-site.php?site=<?=urlencode($r['id'])?>" style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;text-decoration:none;color:inherit;transition:.15s">
          <div style="display:flex;align-items:center;gap:8px"><div style="width:28px;height:28px;border-radius:8px;overflow:hidden;background:var(--bg);display:grid;place-items:center"><?php $rl = nav_related_logo($r); if ($rl): ?><img src="<?=htmlspecialchars($rl)?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: ?>🌐<?php endif; ?></div><div class="font-bold"><?=htmlspecialchars($r['name'])?></div></div>
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
