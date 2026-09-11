<?php
/**
 * 导航站点详情页
 *
 * v8（2026-09-10）：从「单卡 + 评论」扩建为完整产品档案页——
 * 结构化信息（子分类/标签/收录时间/访问量/评分）、GitHub 仓库面板（stars/语言/topics/
 * 最近更新，走 NavGithub 缓存）、编辑推荐理由、按 子分类+标签 打分的相似站点、对比页引导。
 * 外跳统一走 /api/nav-click 接通点击统计。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/includes/nav-icons.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/CommentSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/comment-widget.php';
require_once __DIR__ . '/lib/NavGithub.php';
require_once __DIR__ . '/lib/Markdown.php';

$nav = json_read(DATA_DIR . '/navigation.json');
$navIntros = json_read(DATA_DIR . '/nav-intros.json');
$sites = $nav['sites'] ?? [];
$categories = $nav['categories'] ?? [];
$siteId = $_GET['site'] ?? '';
$site = null;
foreach ($sites as $s) if ($s['id'] === $siteId) { $site = $s; break; }
if (!$site) { http_response_code(404); die('站点不存在'); }

$catNames = [];
foreach ($categories as $c) $catNames[$c['id']] = $c['name'];

// 兼容旧字段
$siteDesc = $site['description'] ?? $site['desc'] ?? '';
$siteCat  = $site['category'] ?? $site['cat'] ?? '';
$siteTags = array_values(array_filter((array)($site['tags'] ?? [])));
$rating   = comment_rating_summary('site', $site['id']);
$hits     = (int)($site['hits'] ?? 0);
$created  = substr($site['created_at'] ?? '', 0, 10);

// GitHub 仓库元数据（缓存）
$ghRepo = NavGithub::repoFromUrl($site['url'] ?? '');
$gh = $ghRepo ? NavGithub::repoMeta($ghRepo) : null;

// 相似站点打分：同子分类 +3 / 每个共同标签 +2 / 同分类 +1 / 同地区 +0.5
$scored = [];
foreach ($sites as $s) {
    if ($s['id'] === $siteId) continue;
    $score = 0;
    if (!empty($site['sub']) && ($s['sub'] ?? '') === $site['sub']) $score += 3;
    $score += 2 * count(array_intersect($siteTags, (array)($s['tags'] ?? [])));
    if (($s['category'] ?? $s['cat'] ?? '') === $siteCat) $score += 1;
    if (($s['region'] ?? '') === ($site['region'] ?? '')) $score += 0.5;
    if ($score > 0) $scored[] = ['s' => $s, 'score' => $score];
}
usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
$related = array_map(fn($x) => $x['s'], array_slice($scored, 0, 4));
// 打分全部为空时退化为同分类
if (!$related) {
    $related = array_values(array_filter($sites, fn($s) => $s['id'] !== $siteId && ($s['category'] ?? $s['cat'] ?? '') === $siteCat));
    $related = array_slice($related, 0, 4);
}
$compareIds = $site['id'] . ($related ? ',' . $related[0]['id'] : '');

// 产品截图（自动截取的官网首页）与深度介绍（data/nav-intros.json）
$siteShot = 'assets/images/nav-sites/' . $site['id'] . '.png';
$hasShot  = file_exists(__DIR__ . '/' . $siteShot);
$siteIntro = trim((string)($navIntros[$site['id']] ?? ''));

$siteTitle = $site['name'] . (!empty($site['name_en']) ? '（' . $site['name_en'] . '）' : '');
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($site['name'])?>  | <?=site_config_get("site_name")?> 增长导航</title>
<meta name="description" content="<?=htmlspecialchars(mb_substr($siteDesc, 0, 120))?>">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 站点详情独有样式；通用零件全部来自 modules.css */
.site-hd{display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap}
.site-hd .fav{position:relative;width:64px;height:64px;border-radius:16px;display:grid;place-items:center;background:var(--accent-soft);color:var(--accent-strong);flex:0 0 auto;overflow:hidden;font-weight:800;font-size:26px;font-family:var(--font-display)}
.site-hd .fav img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;padding:12px;background:var(--surface)}
.site-hd>div{flex:1;min-width:220px;display:flex;flex-direction:column;gap:10px}
.site-hd h1{font-size:clamp(24px,3.5vw,32px);font-weight:800;letter-spacing:-.02em;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.site-hd h1 .en{font-size:15px;font-weight:500;color:var(--faint);letter-spacing:0}
.site-hd p{font-size:15px;color:var(--muted);line-height:1.8;margin:0}
.site-meta{display:flex;flex-wrap:wrap;gap:8px 18px;font-size:12.5px;color:var(--faint);font-family:var(--font-mono)}
.site-meta b{color:var(--accent-strong);font-weight:700}
.chips{display:flex;flex-wrap:wrap;gap:6px}
.chips span{font-size:11.5px;padding:3px 10px;border-radius:999px;background:var(--accent-soft);color:var(--accent-strong);border:1px solid color-mix(in oklab,var(--accent),transparent 72%)}
/* GitHub 面板 */
.gh-panel{margin-top:22px}
.gh-panel .gh-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.gh-panel .gh-head h2{font-size:17px;font-weight:700;display:flex;align-items:center;gap:8px;margin:0}
.gh-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}
.gh-stats .st{background:var(--surface-2);border-radius:12px;padding:12px 14px}
.gh-stats .st b{display:block;font-size:20px;font-weight:800;font-family:var(--font-display);letter-spacing:-.02em}
.gh-stats .st span{font-size:11.5px;color:var(--faint)}
.gh-foot{display:flex;flex-wrap:wrap;gap:6px 16px;font-size:12.5px;color:var(--muted)}
.gh-desc{font-size:14px;color:var(--muted);line-height:1.8;margin:0 0 12px}
/* 推荐理由 */
.reason-card{margin-top:22px;border-left:3px solid var(--accent);background:linear-gradient(120deg,var(--accent-soft),transparent 65%)}
.reason-card h2{font-size:15px;margin:0 0 8px}
.reason-card p{margin:0;font-size:14.5px;line-height:1.9;color:var(--fg)}
/* 相似站点卡 */
.sim-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:12px}
.sim-card{display:flex;flex-direction:column;gap:8px;padding:16px;border:1px solid var(--border);border-radius:14px;background:var(--surface);transition:transform .25s,border-color .25s}
.sim-card:hover{transform:translateY(-2px);border-color:var(--accent)}
.sim-card h3{font-size:15px;margin:0;display:flex;align-items:center;gap:8px}
.sim-card p{font-size:12.5px;color:var(--muted);line-height:1.7;margin:0;flex:1}
.sim-card .ops{display:flex;gap:8px}
/* 产品截图（浏览器框） */
.shot-win{border:1px solid var(--border);border-radius:var(--r-md);overflow:hidden;background:var(--surface);box-shadow:var(--shadow)}
.shot-win .win-bar{display:flex;align-items:center;gap:6px;padding:9px 14px;border-bottom:1px solid var(--border-soft);background:var(--surface-2)}
.shot-win .win-bar .light{width:10px;height:10px;border-radius:50%}
.shot-win .win-bar .url{margin-left:8px;font-family:var(--font-mono);font-size:11px;color:var(--faint);background:var(--surface);border:1px solid var(--border-soft);border-radius:7px;padding:2px 10px;max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.shot-win img{display:block;width:100%;height:auto}
/* 产品介绍（Markdown 正文） */
.intro-card h2{font-size:17px;margin:0 0 12px}
.intro-body{font-size:14.5px;line-height:1.9;color:var(--fg)}
.intro-body h2{font-size:15px;font-weight:700;margin:20px 0 8px;color:var(--fg)}
.intro-body p{margin:0 0 12px;color:var(--muted)}
.intro-body ul{margin:0 0 12px;padding-left:20px;color:var(--muted)}
.intro-body li{margin-bottom:6px}
.intro-body strong{color:var(--fg)}
.intro-body code{font-family:var(--font-mono);font-size:12.5px;background:var(--surface-2);border:1px solid var(--border-soft);border-radius:6px;padding:1px 6px}
@media(max-width:720px){.gh-stats{grid-template-columns:repeat(2,1fr)}.sim-grid{grid-template-columns:1fr}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('navigation'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section class="reader reveal in" data-od-id="site">
    <nav class="art-meta" aria-label="面包屑" style="margin-bottom:18px"><a href="/navigation" style="color:var(--faint)">← 增长导航</a></nav>
    <div class="card site-hd">
      <?=nav_site_icon($site)?>
      <div>
        <span class="kicker"><?=htmlspecialchars($catNames[$siteCat] ?? '未分类')?><?=!empty($site['sub'])?' · ' . htmlspecialchars($site['sub']):''?> · <?=($site['region']??'')==='cn'?'国内':'海外'?></span>
        <h1><?=htmlspecialchars($site['name'])?><?php if (!empty($site['name_en'])): ?><span class="en"><?=htmlspecialchars($site['name_en'])?></span><?php endif; ?><?php if (!empty($site['featured'])): ?><span class="badge warn">编辑推荐</span><?php endif; ?></h1>
        <p><?=htmlspecialchars($siteDesc)?></p>
        <?php if ($siteTags): ?><div class="chips"><?php foreach ($siteTags as $t): ?><span><?=htmlspecialchars($t)?></span><?php endforeach; ?></div><?php endif; ?>
        <div class="site-meta">
          <?php if ($rating['count']): ?><span>评分 <b>★ <?=number_format($rating['avg'], 1)?></b>（<?=$rating['count']?> 条点评）</span><?php endif; ?>
          <?php if ($hits): ?><span><b><?=number_format($hits)?></b> 次访问</span><?php endif; ?>
          <?php if ($created): ?><span>收录于 <?=$created?></span><?php endif; ?>
        </div>
        <div class="cta-row">
          <a href="/api/nav-click?site=<?=urlencode($site['id'])?>" target="_blank" rel="noopener" class="btn primary">访问网站 →</a>
          <button class="btn ghost" onclick="copyURL()">复制链接</button>
          <?php if ($related): ?><a class="btn ghost" href="/navigation/compare?ids=<?=urlencode($compareIds)?>">⚖ 对比同类产品</a><?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($hasShot): ?>
    <div class="shot-win" data-od-id="site-shot">
      <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url"><?=htmlspecialchars($site['url'] ?? '')?></div></div>
      <img src="/<?=$siteShot?>" alt="<?=htmlspecialchars($site['name'])?> 官网首页截图" loading="lazy">
    </div>
    <?php endif; ?>

    <?php if ($siteIntro): ?>
    <div class="card intro-card" data-od-id="site-intro">
      <h2>📖 产品介绍</h2>
      <div class="intro-body"><?=Markdown::toHtml($siteIntro)?></div>
    </div>
    <?php endif; ?>

    <?php if ($gh): ?>
    <div class="card gh-panel" data-od-id="gh-panel">
      <div class="gh-head">
        <h2><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 .5A11.5 11.5 0 0 0 .5 12a11.5 11.5 0 0 0 7.9 10.9c.6.1.8-.2.8-.5v-2c-3.2.7-3.9-1.4-3.9-1.4-.5-1.3-1.3-1.7-1.3-1.7-1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1 1.8 2.7 1.3 3.4 1 .1-.8.4-1.3.7-1.6-2.6-.3-5.3-1.3-5.3-5.7 0-1.3.4-2.3 1.2-3.1-.1-.3-.5-1.5.1-3.1 0 0 1-.3 3.2 1.2a11 11 0 0 1 5.8 0C16.4 4.9 17.4 5.2 17.4 5.2c.6 1.6.2 2.8.1 3.1.8.8 1.2 1.8 1.2 3.1 0 4.4-2.7 5.4-5.3 5.7.4.4.8 1.1.8 2.2v3.2c0 .3.2.6.8.5A11.5 11.5 0 0 0 23.5 12 11.5 11.5 0 0 0 12 .5Z"/></svg>GitHub 仓库</h2>
        <a href="https://github.com/<?=htmlspecialchars($ghRepo)?>" target="_blank" rel="noopener" class="btn ghost" style="font-size:12px;padding:6px 14px"><?=htmlspecialchars($ghRepo)?> ↗</a>
      </div>
      <?php if ($gh['description'] && $gh['description'] !== $siteDesc): ?><p class="gh-desc"><?=htmlspecialchars($gh['description'])?></p><?php endif; ?>
      <div class="gh-stats">
        <div class="st"><b><?=NavGithub::compactNum($gh['stars'])?></b><span>Stars</span></div>
        <div class="st"><b><?=NavGithub::compactNum($gh['forks'])?></b><span>Forks</span></div>
        <div class="st"><b><?=NavGithub::compactNum($gh['open_issues'])?></b><span>Issues</span></div>
        <div class="st"><b><?=NavGithub::compactNum($gh['watchers'])?></b><span>Watchers</span></div>
      </div>
      <div class="gh-foot">
        <?php if ($gh['language']): ?><span>● <?=htmlspecialchars($gh['language'])?></span><?php endif; ?>
        <?php if ($gh['license'] && $gh['license'] !== 'NOASSERTION'): ?><span>  <?=htmlspecialchars($gh['license'])?></span><?php endif; ?>
        <?php if ($gh['pushed_at']): ?><span>最近更新 <?=NavGithub::relativeTime($gh['pushed_at'])?></span><?php endif; ?>
        <?php if ($gh['repo_created_at']): ?><span>创建于 <?=substr($gh['repo_created_at'], 0, 10)?></span><?php endif; ?>
      </div>
      <?php if ($gh['topics']): ?><div class="chips" style="margin-top:10px"><?php foreach ($gh['topics'] as $t): ?><span><?=htmlspecialchars($t)?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($site['reason'])): ?>
    <div class="card reason-card" data-od-id="reason">
      <h2>✍️ 编辑推荐理由</h2>
      <p><?=nl2br(htmlspecialchars($site['reason']))?></p>
    </div>
    <?php endif; ?>
  </section>

  <section class="reader reveal" data-od-id="site-reviews"><?php fc_comment_widget('site', $site['id'], ['title' => '用户点评', 'rating' => true]); ?></section>

  <?php if ($related): ?>
  <section class="reader reveal" data-od-id="site-related">
    <div class="sec-head row"><div><span class="kicker">相似推荐</span><h2>和 <?=htmlspecialchars($site['name'])?> 同类的工具</h2></div><a class="btn ghost" href="/navigation/compare?ids=<?=urlencode($compareIds)?>">进入对比 →</a></div>
    <div class="sim-grid">
      <?php foreach ($related as $r): ?>
      <div class="sim-card">
        <h3><a href="/navigation/<?=urlencode($r['id'])?>" style="color:inherit"><?=htmlspecialchars($r['name'])?></a><?php if (!empty($r['featured'])): ?><span class="badge warn" style="font-size:10px">推荐</span><?php endif; ?></h3>
        <p><?=htmlspecialchars(mb_substr($r['description'] ?? $r['desc'] ?? '', 0, 72))?></p>
        <div class="ops">
          <a class="btn ghost" style="font-size:12px;padding:5px 12px" href="/navigation/<?=urlencode($r['id'])?>">详情</a>
          <a class="btn ghost" style="font-size:12px;padding:5px 12px" href="/navigation/compare?ids=<?=urlencode($site['id'] . ',' . $r['id'])?>">⚖ 对比</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
<script>
function copyURL() {
  var url = <?=json_encode($site['url'] ?? '')?>;
  navigator.clipboard.writeText(url).then(function() { alert('链接已复制'); });
}
</script>
</body>
</html>
