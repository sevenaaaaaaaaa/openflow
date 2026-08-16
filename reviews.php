<?php
/**
 * 点评榜单 — 「网站增长领域的大众点评」首页
 * 支持：网站/产品/书籍/活动 四类点评，按评分/热度排行，分类筛选
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CommentSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';

// 点评目标数据源
$targets = [];
// 网站（导航站）
foreach (json_read(DATA_DIR . '/navigation.json')['sites'] ?? [] as $s) {
    $targets[] = ['type' => 'site', 'id' => $s['id'], 'name' => $s['name'], 'desc' => $s['description'] ?? '', 'cover' => '', 'url' => $s['url'] ?? '', 'icon' => '🌐'];
}
// 产品
foreach (json_read(DATA_DIR . '/courses/index.json') as $c) {
    $targets[] = ['type' => 'product', 'id' => $c['id'], 'name' => $c['title'], 'desc' => $c['description'] ?? '', 'cover' => $c['cover'] ?? '', 'url' => '/course/' . urlencode($c['id']), 'icon' => '🎓'];
}
// 书籍（静态示例，可从 downloads 或后续扩展）
foreach (json_read(DATA_DIR . '/downloads.json') as $d) {
    $targets[] = ['type' => 'book', 'id' => $d['id'], 'name' => $d['title'], 'desc' => $d['desc'] ?? '', 'cover' => '', 'url' => '#', 'icon' => '📚'];
}
// 活动（events）
foreach (json_read(DATA_DIR . '/events/index.json') as $e) {
    $targets[] = ['type' => 'event', 'id' => $e['id'], 'name' => $e['title'] ?? '', 'desc' => $e['summary'] ?? '', 'cover' => $e['cover'] ?? '', 'url' => $e['link'] ?? '#', 'icon' => '🎉'];
}

// 聚合评分
$ranked = [];
foreach ($targets as $t) {
    $r = comment_rating_summary($t['type'], $t['id']);
    $t['rating'] = $r['avg'];
    $t['count'] = $r['count'];
    $ranked[] = $t;
}

// 筛选
$typeFilter = $_GET['type'] ?? 'all';
$sort = $_GET['sort'] ?? 'rating';
if ($typeFilter !== 'all') $ranked = array_values(array_filter($ranked, fn($t) => $t['type'] === $typeFilter));
if ($sort === 'rating') usort($ranked, fn($a, $b) => $b['rating'] <=> $a['rating']);
elseif ($sort === 'count') usort($ranked, fn($a, $b) => $b['count'] <=> $a['count']);
$ranked = array_values(array_filter($ranked, fn($t) => $t['count'] > 0)); // 只显示有点评的

$typeNames = ['site' => '网站', 'product' => '产品', 'book' => '书籍', 'event' => '活动'];
$typeIcons = ['site' => '🌐', 'product' => '🎓', 'book' => '📚', 'event' => '🎉'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>点评榜单 | 芭乐派 · OpenFlow</title>
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .rv-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:18px;transition:.15s;display:block;text-decoration:none;color:inherit}
  .rv-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(30,30,30,.08);border-color:var(--accent)}
  .stars{color:var(--warn);letter-spacing:1px}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260813ad" data-cfasync="false" data-page="home"></script>

  <div class="mx-auto px-5 py-10" style="max-width:1100px">
    <div class="text-center py-6 mb-6">
      <h1 class="text-3xl font-extrabold">⭐ 点评榜单</h1>
      <p class="text-gray-600 mt-3">网站增长领域的大众点评 · 用户真实打分与体验</p>
    </div>

    <!-- 筛选 -->
    <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
      <div class="flex gap-2 flex-wrap">
        <a href="?type=all&sort=<?=$sort?>" class="px-4 py-2 rounded-full text-sm font-semibold <?=$typeFilter==='all'?'':'bg-white border'?>" style="<?=$typeFilter==='all'?'background:var(--accent);color:var(--on-accent)':''?>">全部</a>
        <?php foreach ($typeNames as $tk => $tv): ?>
        <a href="?type=<?=$tk?>&sort=<?=$sort?>" class="px-4 py-2 rounded-full text-sm font-semibold <?=$typeFilter===$tk?'':'bg-white border'?>" style="<?=$typeFilter===$tk?'background:var(--accent);color:var(--on-accent)':''?>"><?=$typeIcons[$tk]?> <?=$tv?></a>
        <?php endforeach; ?>
      </div>
      <div class="flex gap-2">
        <a href="?type=<?=$typeFilter?>&sort=rating" class="px-4 py-2 rounded-full text-sm font-semibold <?=$sort==='rating'?'':'bg-white border'?>" style="<?=$sort==='rating'?'background:var(--ok);color:var(--surface)':''?>">评分最高</a>
        <a href="?type=<?=$typeFilter?>&sort=count" class="px-4 py-2 rounded-full text-sm font-semibold <?=$sort==='count'?'':'bg-white border'?>" style="<?=$sort==='count'?'background:var(--ok);color:var(--surface)':''?>">最多点评</a>
      </div>
    </div>

    <?php if (empty($ranked)): ?>
    <div class="rounded-3xl p-12 text-center" style="background:var(--surface);border:1px solid var(--border);color:var(--muted)">
      暂无点评，<a href="/navigation.php" class="text-[#2b5f7e] underline">去导航站看看</a> 给网站打分吧
    </div>
    <?php else: ?>
    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr))">
      <?php foreach ($ranked as $i => $t): ?>
      <a href="<?=htmlspecialchars($t['type'] === 'site' ? '/navigation-site.php?site=' . urlencode($t['id']) : $t['url'])?>" class="rv-card">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#7dd3fc,#86efac);display:grid;place-items:center;font-size:20px;flex-shrink:0"><?=htmlspecialchars($t['icon'])?></div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:8px">
              <span class="font-bold">#<?=$i+1?> <?=htmlspecialchars($t['name'])?></span>
              <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:var(--bg);color:var(--muted)"><?=$typeNames[$t['type']]?></span>
            </div>
            <div class="stars text-sm mt-1"><?=str_repeat('★', max(0, min(5, (int)round($t['rating']))))?><?=str_repeat('☆', max(0, 5 - (int)round($t['rating'])))?> <b style="color:#b45309"><?=number_format($t['rating'], 1)?></b></div>
          </div>
          <div style="text-align:right;font-size:12px;color:var(--faint)"><?=$t['count']?> 条点评</div>
        </div>
        <p class="text-sm text-gray-600 mt-3 line-clamp-2"><?=htmlspecialchars($t['desc'] ?? '')?></p>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 点评说明 -->
    <div class="rounded-3xl p-6 mt-10 text-center text-sm" style="background:var(--surface);border:1px solid var(--border);color:var(--muted)">
      <span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 1 3.5 10.9c-.8.6-1.5 1.4-1.5 2.6h-4c0-1.2-.7-2-1.5-2.6A6 6 0 0 1 12 3Z"/></svg></span> 点评数据来自用户对网站 / 产品 / 书籍 / 活动的真实打分。<a href="/navigation.php" class="text-[#2b5f7e] underline">去导航站</a> 可点评网站，<a href="/courses" class="text-[#2b5f7e] underline">去课程页</a> 可点评课程。
    </div>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:1100px">
      <div class="mb-2"><?=site_config_get("site_name")?> · <?=site_config_get("site_slogan", '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
    </div>
  </footer>
</body>
</html>
