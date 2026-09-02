<?php
/**
 * 点评榜单 — 「网站增长领域的大众点评」首页
 *
 * v7（2026-09-01）：迁到共享 archetype（hero-center + tab 筛选 + 榜单卡）。排序 / 筛选逻辑原样保留。
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
    $targets[] = ['type' => 'product', 'id' => $c['id'], 'name' => $c['title'], 'desc' => $c['description'] ?? '', 'cover' => $c['cover'] ?? '', 'url' => '/courses/' . urlencode($c['id']), 'icon' => '🎓'];
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
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>点评榜单 | 芭乐派 · OpenFlow</title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 点评榜独有：榜单卡。其余全部来自 modules.css。 */
.rv{display:flex;flex-direction:column;gap:10px}
.rv .hd{display:flex;align-items:center;gap:12px}
.rv .em{width:44px;height:44px;border-radius:12px;background:var(--accent-soft);display:grid;place-items:center;font-size:20px;flex:0 0 auto}
.rv .ttl{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:15.5px;font-weight:700}
.rv .rk{font-family:var(--font-mono);color:var(--faint);font-weight:700}
.rv .stars{color:var(--warn);letter-spacing:.1em;font-size:13px;margin-top:3px}
.rv .stars b{color:var(--warn);margin-left:6px}
.rv .cnt{margin-left:auto;font-family:var(--font-mono);font-size:12px;color:var(--faint);white-space:nowrap}
.rv p{font-size:13.5px;color:var(--muted);line-height:1.7;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('navigation'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section id="top" class="reveal in" data-od-anchor data-od-id="reviews-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">点评榜单</span>
      <h1>网站增长领域的<i class="si">大众点评</i></h1>
      <p class="lead">用户真实打分与体验</p>
    </div>
  </section>
  <section id="list" class="sec reveal" data-od-anchor data-od-id="reviews-list">
    <div class="filters" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <div class="tab-bar" style="border-bottom:none;padding-bottom:0;justify-content:flex-start;flex:1">
        <a class="tab-p" href="?type=all&sort=<?=$sort?>" aria-selected="<?=$typeFilter==='all'?'true':'false'?>">全部</a>
        <?php foreach ($typeNames as $tk => $tv): ?><a class="tab-p" href="?type=<?=$tk?>&sort=<?=$sort?>" aria-selected="<?=$typeFilter===$tk?'true':'false'?>"><?=$tv?></a><?php endforeach; ?>
      </div>
      <div style="display:flex;gap:6px;margin-left:auto">
        <a class="pill <?=$sort==='rating'?'hl':'neutral'?>" href="?type=<?=$typeFilter?>&sort=rating">按评分</a>
        <a class="pill <?=$sort==='count'?'hl':'neutral'?>" href="?type=<?=$typeFilter?>&sort=count">按热度</a>
      </div>
    </div>
    <?php if (empty($ranked)): ?>
    <div class="empty">暂无点评，<a href="/navigation" style="color:var(--accent)">去导航站看看</a> 给网站打分吧</div>
    <?php else: ?>
    <div class="grid g3" style="gap:16px">
      <?php foreach ($ranked as $i => $t): ?>
      <a href="<?=htmlspecialchars($t['type'] === 'site' ? '/navigation/' . urlencode($t['id']) : $t['url'])?>" class="card rv">
        <div class="hd">
          <span class="em"><?=htmlspecialchars($t['icon'])?></span>
          <div style="min-width:0"><div class="ttl"><span class="rk">#<?=$i+1?></span><span><?=htmlspecialchars($t['name'])?></span><span class="pill neutral" style="height:24px"><?=$typeNames[$t['type']]?></span></div><div class="stars"><?=str_repeat('★', max(0, min(5, (int)round($t['rating']))))?><?=str_repeat('☆', max(0, 5 - (int)round($t['rating'])))?><b><?=number_format($t['rating'], 1)?></b></div></div>
          <span class="cnt"><?=$t['count']?> 条点评</span>
        </div>
        <p><?=htmlspecialchars($t['desc'] ?? '')?></p>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
