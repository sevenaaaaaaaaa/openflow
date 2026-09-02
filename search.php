<?php
/**
 * 前台搜索结果页 — 相关话题/课程/文章/资料/技能
 *
 * v7（2026-09-01）：迁到共享 archetype（hero-center 搜索框 + 分组 link-grid）。搜索逻辑原样保留。
 * /search?q=关键词
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$q = trim(req_str('q'));
$results = ['ok' => false];
if ($q) {
    // 直接本地调用搜索逻辑，避免依赖 site_url 的 HTTP 自调用（本地/线上均可）
    require_once __DIR__ . '/lib/SearchEngine.php';
    $results = SearchEngine::search($q);
}
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>搜索「<?=htmlspecialchars($q)?>」 | <?=site_config_get('site_name')?></title>
<meta name="description" content="搜索 <?=htmlspecialchars($q)?> 相关文章、专题、课程、资料与技能">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 搜索页零私有 CSS */

</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('search'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section id="top" class="reveal in" data-od-anchor data-od-id="search-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">搜索</span>
      <?php if ($q): ?><h1>「<?=htmlspecialchars($q)?>」<i class="si">的搜索结果</i></h1><?php else: ?><h1>找<i class="si">文章、课程、资料</i></h1><?php endif; ?>
      <form class="search" action="/search" method="get" role="search" style="display:flex;gap:10px;width:min(560px,100%)">
        <input class="inp" type="search" name="q" value="<?=htmlspecialchars($q)?>" placeholder="搜索文章、专题、课程、资料、技能…" aria-label="搜索" style="border-radius:999px;padding-left:20px">
        <button class="btn primary" type="submit" style="border-radius:999px">搜索</button>
      </form>
      <?php if ($q && !empty($results['ok'])): ?>
      <div class="trust"><span class="dot"></span>文章 <?=count($results['articles'])?> · 专题 <?=count($results['topics'])?> · 课程 <?=count($results['courses'])?> · 资料 <?=count($results['downloads'])?> · 技能 <?=count($results['skills'])?></div>
      <?php endif; ?>
    </div>
  </section>

  <section id="results" class="sec reveal" data-od-anchor data-od-id="search-results">
    <?php if (!$q): ?>
    <div class="empty">请输入关键词搜索</div>
    <?php elseif (empty($results['ok'])): ?>
    <div class="empty">搜索服务暂不可用，请稍后再试</div>
    <?php else: ?>
    <?php $groups = [
      ['articles','文章', fn($a)=>['/articles/'.htmlspecialchars($a['slug']), $a['title'], $a['category'] ?? '']],
      ['topics','专题', fn($t)=>['/topics/'.htmlspecialchars($t['slug']), $t['title'], mb_substr($t['description'] ?? '',0,80)]],
      ['courses','课程', fn($c)=>['/courses/'.htmlspecialchars($c['id']), $c['title'], $c['price'] ? '¥'.$c['price'] : '免费']],
      ['downloads','资料', fn($d)=>['/downloads', $d['title'], '资料下载']],
      ['skills','技能', fn($sk)=>['/marketplace?view=skill&id='.htmlspecialchars($sk['id']), $sk['title'], 'Skill']],
    ]; $any = false; foreach ($groups as [$key,$label,$fn]): if (empty($results[$key])) continue; $any = true; ?>
    <div class="sec-head row" style="margin-top:8px"><div><span class="kicker"><?=$label?> · <?=count($results[$key])?></span></div></div>
    <div class="link-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:28px">
      <?php foreach ($results[$key] as $it): [$href,$t,$sub] = $fn($it); ?>
      <a class="link-it" href="<?=$href?>"><span class="lt"><b><?=htmlspecialchars($t)?></b><span><?=htmlspecialchars($sub)?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$any): ?><div class="empty">没有找到与「<?=htmlspecialchars($q)?>」相关的内容，换个关键词试试。</div><?php endif; ?>
    <?php endif; ?>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
