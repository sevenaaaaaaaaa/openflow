<?php
/**
 * Academy · 内容学院首页 — 统一 文章 / 资料下载 / 播客 / 视频教程
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（300 秒）
if (PageCache::begin('academy', 1800)) exit;

$cfg = json_read(DATA_DIR . '/community.json');
$floors = $cfg['floors'] ?? [];
$hotReadCount = (int)($cfg['hot_read_count'] ?? 5);
$showReport = $cfg['show_report_section'] ?? true;

// 文章（已发布 + 有标题的草稿作为预告）
$allArticles = get_articles_list();
$published = array_values(array_filter($allArticles, fn($a) => ($a['status'] ?? '') === 'published'));
// 有标题的草稿（预告内容，无正文）
$drafts = array_values(array_filter($allArticles, fn($a) => ($a['status'] ?? '') !== 'published' && !empty(trim($a['title'] ?? ''))));
$catNames = [];
foreach (get_categories('article') as $c) $catNames[$c['key']] = $c['name'];

// 楼层定义（分类聚合）
$floorDefs = [
    'insight'    => ['label' => '增长洞察', 'desc' => '增长方法论 · 案例拆解 · 数据驱动', 'cats' => ['insight', 'report']],
    'ai'         => ['label' => 'AI 工具与实践', 'desc' => 'AI 工具推荐 · 设计 · 视频 · Agent · 副业', 'cats' => ['ai-tools', 'ai-design', 'ai-video', 'ai-image', 'ai-agent', 'ai-business', 'ai-trend']],
    'content'    => ['label' => '内容与 SEO 实践', 'desc' => '内容策略 · SEO · 分发 · 自动化', 'cats' => ['content', 'productivity']],
    'industry'   => ['label' => '行业实践', 'desc' => '行业深度案例 · 组织与领导力', 'cats' => ['industry', 'news']],
];
// 合并后台配置楼层
foreach ($floors as $fk => $fv) {
    if (!isset($floorDefs[$fk]) || empty($fv['categories'])) continue;
    if (isset($fv['enabled']) && !$fv['enabled']) continue;
    $floorDefs[$fk]['title'] = $fv['title'] ?? $floorDefs[$fk]['label'];
    $floorDefs[$fk]['desc'] = $fv['desc'] ?? $floorDefs[$fk]['desc'];
    $floorDefs[$fk]['cats'] = $fv['categories'];
    $floorDefs[$fk]['label'] = $floorDefs[$fk]['title'];
}

function floor_articles(array $articles, array $cats, int $limit = 4): array {
    $out = [];
    foreach ($articles as $a) {
        $aCats = array_map('strtolower', $a['tags'] ?? []);
        $aCat = strtolower($a['category'] ?? '');
        $hit = in_array($aCat, $cats) || count(array_intersect($cats, $aCats)) > 0;
        if ($hit) $out[] = $a;
    }
    return array_slice($out, 0, $limit);
}

// 资料下载
$allDls = json_read(DATA_DIR . '/downloads.json');
$downloads = array_values(array_filter($allDls, fn($d) => ($d['status'] ?? 'draft') === 'published'));
usort($downloads, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$downloads = array_slice($downloads, 0, 4);
$dlCats = ['whitepaper' => '白皮书', 'template' => '模板', 'report' => '报告', 'ebook' => '电子书', 'toolkit' => '工具包'];

// 播客
$pod = json_read(DATA_DIR . '/podcasts.json');
$podItems = array_values(array_filter($pod['items'] ?? [], fn($p) => ($p['status'] ?? 'published') === 'published'));
usort($podItems, fn($a, $b) => strcmp($b['pub_date'] ?? '', $a['pub_date'] ?? ''));
$podItems = array_slice($podItems, 0, 4);

// 视频教程（已发布课程）
$allCourses = json_read(DATA_DIR . '/courses/index.json');
$courses = array_values(array_filter($allCourses, fn($c) => ($c['status'] ?? 'draft') === 'published'));
usort($courses, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$courses = array_slice($courses, 0, 4);

// 热读榜
$hot = $published;
usort($hot, fn($a, $b) => (($b['views'] ?? 0) <=> ($a['views'] ?? 0)));
$hot = array_slice($hot, 0, $hotReadCount);

$siteName = site_config_get('site_name', 'OpenFlow');
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$baseUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>学院 · 门派知识库 | <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="芭乐派增长方法论内容库：文章 · 资料下载 · 播客 · 视频教程，从利润公式到 Agent 系统，把增长讲清楚、用起来">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" defer></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .sec-floor{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:22px;transition:.15s}
  .sec-floor:hover{box-shadow:var(--shadow-sm)}
  .card-hover{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;transition:.15s;text-decoration:none;color:inherit;display:flex;flex-direction:column}
  .card-hover:hover{transform:translateY(-3px);box-shadow:var(--shadow)}
  .acd-tab{padding:8px 18px;border-radius:999px;font-size:13.5px;font-weight:600;text-decoration:none;transition:.15s}

  /* 设计语言统一：token 语义工具类（终版契约） */
  .text-faint{color:var(--faint)}.text-muted{color:var(--muted)}.text-fg{color:var(--fg)}
  .text-ok{color:var(--ok)}.text-accent{color:var(--accent)}.text-danger{color:var(--danger)}
  .bg-surface{background:var(--surface)}
</style>
<link rel="stylesheet" href="/assets/fonts/fonts.css">
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260826b" data-cfasync="false" data-page="articles"></script>

<section style="padding:clamp(20px,4vw,44px) 0 clamp(28px,4vw,48px)">
  <div class="mx-auto px-5" style="max-width:1120px">
    <div style="display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(24px,4vw,48px);align-items:center">
      <div style="display:flex;flex-direction:column;gap:16px">
        <span class="kicker" style="font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;color:var(--accent);text-transform:uppercase">CONTENT · ACADEMY</span>
        <h1 style="font-size:clamp(32px,4.5vw,48px);font-weight:800;letter-spacing:-.035em;line-height:1.1;color:var(--fg)">门派的知识库<br><span style="font-family:var(--font-display);font-style:italic">随时可学、随时可用</span></h1>
        <p style="color:var(--muted);font-size:15px;line-height:1.8;max-width:560px">文章 · 资料 · 播客 · 视频，芭乐派增长方法论的完整内容库。从利润公式到 Agent 系统，把增长讲清楚、用起来。</p>
        <form action="/search" method="get" style="display:flex;gap:8px;max-width:480px;margin-top:6px">
          <input type="search" name="q" placeholder="搜索文章、课程、资料…" style="flex:1;padding:12px 18px;border-radius:999px;font-size:14px;outline:none;border:1.5px solid var(--border);background:var(--surface)">
          <button style="padding:12px 24px;border-radius:999px;font-weight:700;font-size:14px;background:var(--accent);color:var(--on-accent);border:0;cursor:pointer">搜索</button>
        </form>
        <div style="display:flex;gap:18px;margin-top:8px;color:var(--faint);font-size:12.5px;flex-wrap:wrap">
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span> <b style="color:var(--fg)"><?=count($published)?></b> 篇文章</span>
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span> <b style="color:var(--fg)"><?=count($downloads)?></b> 份资料</span>
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></span> <b style="color:var(--fg)"><?=count($podItems ?? [])?></b> 期播客</span>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <?php
        $heroCards = [
          ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M4 5h13v14H4zM17 8h3v9a2 2 0 0 1-2 2h-1"/><path d="M7 9h7M7 12h7M7 15h4"/></svg>', '文章精选', '增长实践与深度方法论', '/category/academy/articles', 'var(--accent-soft)', 'var(--accent)'],
          ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg>', '资料下载', '白皮书 · 模板 · 报告', '/category/academy/downloads', 'var(--ok-soft)', 'var(--ok)'],
          ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>', '播客视频', '干货音视频内容', '/category/academy/podcasts', 'oklch(70% .13 305/.14)', 'oklch(60% .18 300)'],
          ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4L15 12l-3-3 2.7-2.7Z"/><path d="m15 3 6 6"/></svg>', '工具箱', 'SEO 检查 · Meta · LTV', '/category/academy/tools', 'oklch(70% .13 75/.14)', 'oklch(62% .15 70)'],
        ];
        foreach ($heroCards as $hc): ?>
        <a href="<?=$hc[3]?>" style="display:flex;flex-direction:column;gap:10px;padding:18px;border-radius:var(--r-md);background:var(--surface);border:1px solid var(--border);backdrop-filter:blur(16px) saturate(150%);text-decoration:none;transition:transform .25s var(--ease-spring),box-shadow .25s,border-color .25s" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-sm)';this.style.borderColor='var(--border-strong)'" onmouseout="this.style.transform='none';this.style.boxShadow='none';this.style.borderColor='var(--border)'">
          <span style="width:38px;height:38px;border-radius:12px;background:<?=$hc[4]?>;color:<?=$hc[5]?>;display:grid;place-items:center;font-size:18px"><?=$hc[0]?></span>
          <b style="font-size:14.5px;color:var(--fg)"><?=$hc[1]?></b>
          <span style="font-size:12px;color:var(--muted);line-height:1.5"><?=$hc[2]?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<div class="mx-auto px-5 py-10" style="max-width:1120px">
  <!-- 门派学习路径联动 -->
  <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:18px 22px;margin-bottom:24px">
    <div style="display:flex;align-items:center;gap:14px;min-width:0">
      <span style="width:42px;height:42px;border-radius:12px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:20px"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span></span>
      <div>
        <b style="font-size:15px;color:var(--fg);display:block">想看系统的？先走芭乐派学习路径</b>
        <span style="font-size:12.5px;color:var(--muted)">New-1~4 基石课免费开放 → R.B.E 训练营带你 8 周设计出增长系统</span>
      </div>
    </div>
    <a href="/courses" style="flex-shrink:0;padding:10px 22px;border-radius:999px;font-weight:700;font-size:13.5px;background:var(--accent);color:var(--on-accent);text-decoration:none">前往课程 →</a>
  </div>

  <!-- 分类导航 -->
  <div class="flex gap-2 flex-wrap mb-8 items-center">
    <a href="#articles" class="rounded-full px-4 py-2 text-[13px] font-semibold" style="background:var(--accent);color:var(--on-accent)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h13v14H4zM17 8h3v9a2 2 0 0 1-2 2h-1"/><path d="M7 9h7M7 12h7M7 15h4"/></svg></span> 文章</a>
    <a href="#downloads" class="rounded-full px-4 py-2 text-[13px] font-semibold bg-surface" style="border:1px solid var(--border)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span> 资料下载</a>
    <a href="#podcasts" class="rounded-full px-4 py-2 text-[13px] font-semibold bg-surface" style="border:1px solid var(--border)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></span> 播客</a>
    <a href="#videos" class="rounded-full px-4 py-2 text-[13px] font-semibold bg-surface" style="border:1px solid var(--border)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg></span> 视频教程</a>
  </div>

  <!-- 文章楼层 -->
  <div id="articles">
    <?php
    // 顶层精选（最新发布横排）
    $featured = array_slice($published, 0, 3);
    ?>
    <div class="sec-floor mb-8">
      <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:4px">
        <h2 class="text-xl font-extrabold" style="display:flex;align-items:center;gap:6px"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-3px;margin-right:6px"><path d="M12 3v4m0 10v4M3 12h4m10 0h4M6 6l2.5 2.5m7 7L18 18M18 6l-2.5 2.5m-7 7L6 18"/><circle cx="12" cy="12" r="2.5"/></svg>精选内容</h2>
        <span class="text-xs text-faint">最新发布的深度文章</span>
      </div>
      <div class="grid gap-4 mt-4" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
        <?php foreach ($featured as $a): $cv = $a['cover'] ?? ''; $cvUrl = $cv ? (strpos($cv,'http')===0 ? $cv : $baseUrl.'/'.ltrim($cv,'/')) : ''; ?>
        <a href="/articles/<?=htmlspecialchars($a['slug'])?>" class="card-hover">
          <div style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--border),var(--accent-soft));overflow:hidden">
            <?php if ($cvUrl): ?><img src="<?=htmlspecialchars($cvUrl)?>" alt="" class="w-full h-full object-cover" loading="lazy"><?php else: ?><div class="h-full grid place-items-center text-3xl"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span></div><?php endif; ?>
          </div>
          <div class="p-4 flex flex-col gap-1.5 flex-1">
            <div class="text-[11px] font-semibold" style="color:var(--ok)"><?=htmlspecialchars($catNames[$a['category'] ?? ''] ?? '文章')?></div>
            <div class="font-bold leading-snug text-[15px]"><?=htmlspecialchars($a['title'])?></div>
            <div class="text-xs text-faint mt-auto pt-1"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?> · 👁 <?=$a['views'] ?? 0?></div>
          </div>
        </a>
        <?php endforeach; ?>
        <?php if (empty($featured)): ?>
        <div class="text-sm text-faint py-8 text-center" style="border:1px dashed var(--border);border-radius:12px">内容准备中，敬请期待</div>
        <?php endif; ?>
      </div>
    </div>

    <?php foreach ($floorDefs as $fk => $fd):
      $arts = floor_articles($published, $fd['cats']);
      // 补充该楼层的预告草稿（有标题无正文）
      $previews = floor_articles($drafts, $fd['cats'], 3);
      $hasContent = !empty($arts) || !empty($previews);
    ?>
    <div class="sec-floor mb-6">
      <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:4px">
        <h2 class="text-xl font-extrabold text-fg"><?=htmlspecialchars($fd['label'])?></h2>
        <span class="text-xs text-faint"><?=htmlspecialchars($fd['desc'])?></span>
        <?php if (!$hasContent): ?><span class="text-[11px] text-faint ml-auto">即将上线</span><?php endif; ?>
      </div>
      <?php if (!$hasContent): ?>
      <div class="grid gap-4 mt-4" style="grid-template-columns:repeat(auto-fill,minmax(255px,1fr))">
        <?php
        // 空楼层引导：指向真实可学内容（New 课程 / 门派）
        $fallbacks = [
          ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2 0-2.8-.8-.7-2-.7-3 0Z"/><path d="M12 15l-3-3c2-5.5 5-9 9-9s3 6-1 11l-5 1Z"/><path d="M9 12c-2.5 1-4 3-4.5 5"/></svg>', '从 New-1 开始', '一人公司冷启动，免费课稿', '/articles/ai-bonus-opc-cold-start'],
          ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1" fill="currentColor"/></svg>', '学方法论', '利润公式 + 四引擎，边学边用', '/courses'],
          ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg>', '进门派聊聊', '提问、交作业、晒增长数据', '/community'],
        ];
        foreach ($fallbacks as $fb): ?>
        <a href="<?=$fb[3]?>" class="rounded-2xl p-5 flex flex-col items-center justify-center gap-2 text-center" style="border:1px dashed var(--border);background:var(--surface);min-height:140px;text-decoration:none;transition:.15s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
          <span class="text-3xl"><?=$fb[0]?></span>
          <span class="text-sm font-semibold text-muted"><?=$fb[1]?></span>
          <span class="text-xs text-faint"><?=$fb[2]?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="grid gap-4 mt-4" style="grid-template-columns:repeat(auto-fill,minmax(255px,1fr))">
        <?php
        // 合并：已发布在前，预告在后
        $merged = array_merge($arts, $previews);
        foreach ($merged as $a):
          $isPreview = ($a['status'] ?? '') !== 'published';
          $cv = $a['cover'] ?? ''; $cvUrl = $cv ? (strpos($cv,'http')===0 ? $cv : $baseUrl.'/'.ltrim($cv,'/')) : '';
          $link = $isPreview ? '/academy' : '/articles/'.htmlspecialchars($a['slug']);
        ?>
        <a href="<?=$link?>" class="card-hover">
          <div style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--border),var(--accent-soft));overflow:hidden">
            <?php if ($cvUrl): ?><img src="<?=htmlspecialchars($cvUrl)?>" alt="" class="w-full h-full object-cover" loading="lazy"><?php else: ?><div class="h-full grid place-items-center text-3xl"><?=$isPreview?'🕒':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg>'?></div><?php endif; ?>
          </div>
          <div class="p-4 flex flex-col gap-1.5 flex-1">
            <div class="text-[11px] font-semibold" style="color:<?=$isPreview?'var(--faint)':'var(--ok)'?>"><?=$isPreview?'🕒 即将发布':(htmlspecialchars($catNames[$a['category'] ?? ''] ?? '文章'))?></div>
            <div class="font-bold leading-snug text-[15px]"><?=htmlspecialchars($a['title'])?></div>
            <div class="text-xs text-faint mt-auto pt-1"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?> · 👁 <?=$a['views'] ?? 0?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid gap-6" style="grid-template-columns:2fr 1fr;align-items:start" id="acad-grid">
    <div class="grid gap-6">
      <!-- 资料下载 -->
      <div class="sec-floor" id="downloads">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
          <h2 class="text-xl font-extrabold text-fg"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span> 资料下载</h2>
          <a href="/downloads" class="ml-auto text-xs font-semibold text-ok hover:underline">全部 →</a>
        </div>
        <?php if (empty($downloads)): ?><div class="text-sm text-faint py-4 text-center" style="border:1px dashed var(--border);border-radius:12px">暂无资料</div>
        <?php else: ?>
        <div class="grid gap-3" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
          <?php foreach ($downloads as $d): ?>
          <a href="/downloads" class="card-hover p-4">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:38px;height:38px;border-radius:var(--r-sm);background:linear-gradient(135deg,var(--accent),var(--ok));display:grid;place-items:center"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span></div>
              <div class="min-w-0">
                <div class="font-bold text-[13.5px] truncate"><?=htmlspecialchars($d['title'])?></div>
                <div class="text-[11px] text-faint"><?=htmlspecialchars($dlCats[$d['category'] ?? ''] ?? '资料')?></div>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- 播客 -->
      <div class="sec-floor" id="podcasts">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
          <h2 class="text-xl font-extrabold text-fg"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></span> 播客</h2>
          <a href="/podcasts" class="ml-auto text-xs font-semibold text-ok hover:underline">全部 →</a>
        </div>
        <?php if (empty($podItems)): ?><div class="text-sm text-faint py-4 text-center" style="border:1px dashed var(--border);border-radius:12px">播客即将上线</div>
        <?php else: ?>
        <div class="grid gap-3" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
          <?php foreach ($podItems as $p): ?>
          <a href="/podcasts" class="card-hover p-4">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:38px;height:38px;border-radius:var(--r-sm);background:linear-gradient(135deg,var(--warn),var(--warn));display:grid;place-items:center"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></span></div>
              <div class="min-w-0">
                <div class="font-bold text-[13.5px] truncate"><?=htmlspecialchars($p['title'] ?? '')?></div>
                <div class="text-[11px] text-faint"><?=htmlspecialchars($p['duration'] ?? ($p['pub_date'] ?? ''))?></div>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- 视频教程 -->
      <div class="sec-floor" id="videos">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
          <h2 class="text-xl font-extrabold text-fg"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg></span> 视频教程</h2>
          <a href="/courses" class="ml-auto text-xs font-semibold text-ok hover:underline">全部 →</a>
        </div>
        <?php if (empty($courses)): ?><div class="text-sm text-faint py-4 text-center" style="border:1px dashed var(--border);border-radius:12px">暂无课程</div>
        <?php else: ?>
        <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(255px,1fr))">
          <?php foreach ($courses as $c): $cc = $c['cover'] ?? ''; $ccUrl = $cc ? (strpos($cc,'http')===0 ? $cc : $baseUrl.'/'.ltrim($cc,'/')) : ''; ?>
          <a href="/courses/<?=urlencode($c['slug'])?>" class="card-hover">
            <div style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--border),var(--warn)55);overflow:hidden;position:relative">
              <?php if ($ccUrl): ?><img src="<?=htmlspecialchars($ccUrl)?>" alt="" class="w-full h-full object-cover" loading="lazy"><?php else: ?><div class="h-full grid place-items-center text-3xl"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor" stroke="none"/></svg></span></div><?php endif; ?>
              <div style="position:absolute;inset:0;display:grid;place-items:center"><span class="grid place-items-center w-11 h-11 rounded-full text-white" style="background:rgba(0,0,0,.55)">▶</span></div>
            </div>
            <div class="p-4 flex flex-col gap-1 flex-1">
              <div class="text-[11px] font-semibold" style="color:var(--danger)"><?=htmlspecialchars($c['type'] ?? '视频教程')?></div>
              <div class="font-bold leading-snug text-[14.5px]"><?=htmlspecialchars($c['title'])?></div>
              <div class="text-xs text-faint mt-auto pt-1"><?=count($c['chapters'] ?? [])?> 章 · <?=htmlspecialchars(substr($c['created_at'] ?? '', 0, 10))?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- 侧栏：热读榜 + 论坛 -->
    <div class="grid gap-6">
      <div class="sec-floor">
        <h2 class="text-lg font-extrabold text-fg mb-4"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4.4 0 7-2.8 7-6.5 0-3.2-2-5.5-3.5-7.5C14 6 13 4 12 2c0 0-1 4-3 6-1.5 1.5-3 3.6-3 6.5C6 19.2 7.6 22 12 22Z"/></svg></span> 热读榜</h2>
        <?php if (empty($hot)): ?><div class="text-sm text-faint">暂无数据</div>
        <?php else: ?>
        <div class="grid gap-2.5">
          <?php foreach ($hot as $i => $a): ?>
          <a href="/articles/<?=htmlspecialchars($a['slug'])?>" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-surface transition" style="text-decoration:none;color:inherit">
            <span class="font-extrabold text-lg <?=$i<3?'':'text-faint'?>" style="<?=$i<3?'color:var(--warn)':''?>"><?=$i+1?></span>
            <span class="min-w-0">
              <span class="block text-[13.5px] font-semibold truncate"><?=htmlspecialchars($a['title'])?></span>
              <span class="block text-[11px] text-faint" style="display:inline-flex;align-items:center;gap:4px"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:3px"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="2.8"/></svg><?=$a['views'] ?? 0?></span>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="sec-floor" style="background:linear-gradient(135deg,var(--bg-soft),var(--accent-soft));border:1px solid var(--border)">
        <h2 class="text-lg font-extrabold mb-2" style="color:var(--fg)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V11.5a8.5 8.5 0 0 1 17 0Z"/></svg></span> 论坛</h2>
        <p class="text-[13px] leading-relaxed mb-4" style="color:var(--faint)">和同行一起讨论增长话题、分享经验、提问求助。真正的社区在这里。</p>
        <a href="/community" class="block text-center rounded-full py-2.5 font-bold text-sm" style="background:var(--accent-soft);color:var(--accent)">进入论坛 →</a>
      </div>

      <?php
      // ─── 为你推荐（形态感知，随站点生长而变化） ───
      $recPref = class_exists('GrowthEngine') ? GrowthEngine::recommendPreferences() : ['shape_type'=>'seedling','shape_label'=>'综合','prefs'=>['categories'=>[],'tags'=>[]]];
      $recArticles = [];
      if (class_exists('Personalizer')) {
          $pref = [
              'categories' => array_fill_keys($recPref['prefs']['categories'] ?? [], 2),
              'tags' => array_fill_keys($recPref['prefs']['tags'] ?? [], 1),
              'member_level' => 'guest',
          ];
          $recArticles = Personalizer::recommendArticles($pref, 3);
      }
      if (!empty($recArticles)): ?>
      <div class="sec-floor">
        <h2 class="text-lg font-extrabold mb-1" style="color:var(--fg)"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1" fill="currentColor"/></svg></span> 为你推荐</h2>
        <p class="text-[11px] mb-4" style="color:var(--faint)">根据「<?=htmlspecialchars($recPref['shape_label'])?>」形态为你挑选</p>
        <div class="grid gap-2.5">
          <?php foreach ($recArticles as $rid => $rscore): $ra = get_article($rid); if (!$ra) continue; ?>
          <a href="/articles/<?=htmlspecialchars($ra['slug'] ?? '')?>" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-surface transition" style="text-decoration:none;color:inherit">
            <span style="width:30px;height:30px;border-radius:var(--r-sm);background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:14px;flex-shrink:0"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1" fill="currentColor"/></svg></span></span>
            <span class="min-w-0">
              <span class="block text-[13px] font-semibold truncate"><?=htmlspecialchars($ra['title'])?></span>
              <span class="block text-[11px] text-faint"><?=htmlspecialchars($ra['category'] ?? '')?></span>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($showReport): ?>
      <div class="sec-floor">
        <h2 class="text-lg font-extrabold text-fg mb-2"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M9 13h6M9 17h4"/></svg></span> 报告与白皮书</h2>
        <p class="text-[13px] text-muted mb-4">完整报告下载，掌握网站增长一手数据</p>
        <a href="/downloads?cat=whitepaper" class="block text-center rounded-full py-2.5 font-bold text-sm" style="background:var(--accent);color:var(--on-accent)">查看全部报告 →</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

  <!-- CTA band -->
  <div style="background:linear-gradient(135deg,var(--accent),oklch(58% .16 295));border-radius:var(--r-lg);padding:clamp(28px,4vw,48px);color:#fff;margin-top:40px;text-align:center">
    <div style="font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;opacity:.75">芭乐派 · 学院</div>
    <h2 style="font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em;margin:10px 0 8px">读到这里，不如直接开始设计你的增长系统</h2>
    <p style="opacity:.85;font-size:14.5px;line-height:1.7;max-width:560px;margin:0 auto 22px">方法论在学院，工具在 OpenFlow，落地在 R.B.E 训练营——三条路，最后都通向同一个地方。</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="/courses" style="padding:12px 26px;border-radius:999px;font-weight:700;font-size:14px;background:#fff;color:var(--accent);text-decoration:none">浏览课程</a>
      <a href="/community" style="padding:12px 26px;border-radius:999px;font-weight:700;font-size:14px;background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,.6);text-decoration:none">进入门派社区</a>
    </div>
  </div>
</div>

<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
  <div class="mx-auto px-5" style="max-width:1120px">
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:28px;padding-bottom:22px;border-bottom:1px solid var(--border)">
      <div>
        <div style="font-weight:800;font-size:15px;color:var(--fg)">芭乐派 · OpenFlow</div>
        <p style="font-size:12.5px;color:var(--muted);line-height:1.7;margin-top:8px;max-width:320px">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
        <p style="font-size:12px;color:var(--faint);margin-top:6px">核心能力永久开源 · 鱼与渔相结合</p>
      </div>
      <div>
        <div style="font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;margin-bottom:10px">站点导航</div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <a href="/product" style="color:var(--muted);text-decoration:none;font-size:13px">产品</a>
          <a href="/capability" style="color:var(--muted);text-decoration:none;font-size:13px">能力</a>
          <a href="/courses" style="color:var(--muted);text-decoration:none;font-size:13px">课程</a>
          <a href="/community" style="color:var(--muted);text-decoration:none;font-size:13px">门派社区</a>
          <a href="/about" style="color:var(--muted);text-decoration:none;font-size:13px">关于我们</a>
        </div>
      </div>
      <div>
        <div style="font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;margin-bottom:10px">资源</div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <a href="/academy" style="color:var(--muted);text-decoration:none;font-size:13px">学院</a>
          <a href="/docs" style="color:var(--muted);text-decoration:none;font-size:13px">文档中心</a>
          <a href="/downloads" style="color:var(--muted);text-decoration:none;font-size:13px">资料下载</a>
          <a href="/podcasts" style="color:var(--muted);text-decoration:none;font-size:13px">播客</a>
          <a href="/marketplace" style="color:var(--muted);text-decoration:none;font-size:13px">生态市场</a>
        </div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding-top:16px;flex-wrap:wrap">
      <div style="font-size:12px;color:var(--muted)">© 2026 芭乐派 · OpenFlow 增长操作系统</div>
      <div style="font-size:12px;color:var(--faint)">帮一人公司设计 Agent 能跑的增长系统</div>
    </div>
  </div>
</footer>

<script>
(function(){
  var b = document.getElementById('burger'), m = document.getElementById('mobile-menu');
  if (b && m) b.addEventListener('click', function() {
    var open = m.classList.toggle('hidden') === false;
    b.setAttribute('aria-expanded', open);
  });
})();
</script>
</body>
</html>
<?php PageCache::end('academy', 1800); ?>
