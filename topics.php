<?php
/**
 * 专题聚合页 — 浏览所有专题及其下文章
 * /topic/{slug}  单个专题详情
 * /topics.php    专题列表
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

$topics = json_read(DATA_DIR . '/topics.json');
$articles = get_articles();
$articleMap = [];
foreach ($articles as $a) $articleMap[$a['id']] = $a;
$catNames = [];
foreach (get_categories('article') as $c) $catNames[$c['key']] = $c['name'];

$topics = array_values(array_filter($topics, fn($t) => ($t['status'] ?? '') === 'published'));
usort($topics, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));

// 单个专题详情模式
$topicSlug = trim(req_str('slug'));
$currentTopic = null;
if ($topicSlug) {
    foreach ($topics as $t) {
        if (($t['slug'] ?? '') === $topicSlug) { $currentTopic = $t; break; }
    }
}
if ($currentTopic) {
    $topicArticles = [];
    foreach (($currentTopic['article_ids'] ?? $currentTopic['articles'] ?? []) as $aid) {
        if (isset($articleMap[$aid]) && ($articleMap[$aid]['status'] ?? 'draft') === 'published') {
            $topicArticles[] = $articleMap[$aid];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $currentTopic ? (htmlspecialchars($currentTopic['title'] ?? '专题') . ' | ' . site_config_get('site_name')) : ('专题合集 | ' . site_config_get('site_name')) ?></title>
<link rel="stylesheet" href="/assets/tokens.css?v=20260816">
<link rel="stylesheet" href="/assets/modules.css?v=20260816">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .topic-card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:22px;transition:.15s;display:block;text-decoration:none;color:inherit}
  .topic-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(30,30,30,.08);border-color:var(--accent)}
  .art-row{display:flex;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--bg);text-decoration:none;color:inherit}
  .art-row:last-child{border-bottom:none}
</style>
<link rel="stylesheet" href="/assets/standalone.css?v=20260813ad">
</head>
<body>
<script src="/assets/site-shell.js?v=20260816" data-cfasync="false" data-page="topics"></script> class="min-h-screen">
  

  <div class="mx-auto px-5 py-10" style="max-width:1100px">
    <?php if ($currentTopic): ?>
    <!-- 单专题详情 -->
    <nav class="mb-6 text-sm text-gray-600"><a href="/topics" class="hover:text-[#2b5f7e]">← 全部专题</a></nav>
    <div class="rounded-3xl p-8 mb-8" style="background:linear-gradient(135deg,var(--ok-soft) 0%,var(--accent-soft) 100%)">
      <div class="text-xs font-bold tracking-widest text-green-600 uppercase mb-2">专题</div>
      <h1 class="text-3xl font-extrabold text-gray-900"><?=htmlspecialchars($currentTopic['title'] ?? '')?></h1>
      <p class="text-gray-600 mt-3 max-w-2xl leading-relaxed"><?=htmlspecialchars($currentTopic['description'] ?? '')?></p>
      <?php if (!empty($currentTopic['category'])): ?><span class="mt-4 inline-block text-[11px] px-3 py-1 rounded-full" style="background:var(--surface);color:var(--ok)"><?=htmlspecialchars($catNames[$currentTopic['category']] ?? $currentTopic['category'])?></span><?php endif; ?>
    </div>
    <div class="flex items-center gap-2 mb-5">
      <h2 class="text-lg font-bold">收录文章</h2>
      <span class="text-xs text-gray-400"><?=count($topicArticles)?> 篇</span>
    </div>
    <?php if (empty($topicArticles)): ?>
    <div class="rounded-3xl p-12 text-center" style="background:var(--surface);border:1px solid var(--border);color:var(--muted)">该专题下暂无文章</div>
    <?php else: ?>
    <div class="grid gap-3">
      <?php foreach ($topicArticles as $a): ?>
      <a href="/article/<?=htmlspecialchars($a['slug'])?>" class="art-row rounded-2xl px-5 py-4" style="background:var(--surface);border:1px solid var(--border)">
        <div style="flex:1">
          <div class="font-semibold text-gray-900"><?=htmlspecialchars($a['title'])?></div>
          <div class="text-xs text-gray-400 mt-1"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?> · <?=htmlspecialchars($catNames[$a['category'] ?? ''] ?? '')?></div>
        </div>
        <span class="text-[#2b5f7e] text-sm shrink-0">阅读 →</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="text-center py-4 mb-8">
      <h1 class="text-3xl font-extrabold"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span> 专题合集</h1>
      <p class="text-gray-600 mt-3 max-w-xl mx-auto">围绕核心议题的深度内容聚合，一次读透一个主题</p>
    </div>

    <?php if (empty($topics)): ?>
    <div class="rounded-3xl p-12 text-center" style="background:var(--surface);border:1px solid var(--border);color:var(--muted)">专题筹备中</div>
    <?php else: foreach ($topics as $t): $tArts = array_values(array_filter(array_map(fn($id) => $articleMap[$id] ?? null, $t['article_ids'] ?? []))); ?>
    <div class="topic-card mb-6">
      <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap">
        <?php if (!empty($t['cover'])): ?><img src="<?=htmlspecialchars(strpos($t['cover'],'http')===0?$t['cover']:'/'.ltrim($t['cover'],'/'))?>" style="width:140px;height:90px;object-fit:cover;border-radius:12px" onerror="this.style.display='none'"><?php endif; ?>
        <div style="flex:1;min-width:240px">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <h2 class="text-xl font-bold"><?=htmlspecialchars($t['title'] ?? '')?></h2>
            <?php if (!empty($t['category'])): ?><span class="text-[11px] px-3 py-1 rounded-full" style="background:var(--ok-soft);color:var(--ok)"><?=htmlspecialchars($catNames[$t['category']] ?? $t['category'])?></span><?php endif; ?>
            <span class="text-xs text-gray-400"><?=count($tArts)?> 篇文章</span>
          </div>
          <p class="text-sm text-gray-600 mt-2 leading-relaxed"><?=htmlspecialchars($t['description'] ?? '')?></p>
        </div>
      </div>
      <div class="mt-4">
        <?php foreach (array_slice($tArts, 0, 5) as $a): ?>
        <a href="/article/<?=htmlspecialchars($a['slug'])?>" class="art-row">
          <span class="text-[#2b5f7e] text-sm">▸</span>
          <span class="flex-1 text-sm font-medium"><?=htmlspecialchars($a['title'])?></span>
          <span class="text-xs text-gray-400"><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?></span>
        </a>
        <?php endforeach; ?>
        <?php if (count($tArts) > 5): ?><div class="text-xs text-[#2b5f7e] mt-2 font-semibold">+ <?=count($tArts)-5?> 篇更多…</div><?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
    <?php endif; /* end single-topic else */ ?>
  </div>

  <footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
    <div class="mx-auto px-5 text-center text-sm" style="max-width:1100px">
      <div class="mb-2"><?=site_config_get('site_name')?> · <?=site_config_get('site_slogan', '帮一人公司设计 Agent 能跑的增长系统')?></div>
      <div class="text-xs" style="color:var(--muted)"><?=site_copyright()?></div>
    </div>
  </footer>
</body>
</html>
