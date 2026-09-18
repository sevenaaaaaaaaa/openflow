<?php
/**
 * 专题聚合页 — 浏览所有专题及其下文章
 *
 * v7（2026-09-01）：迁到共享 archetype；顺手修掉 <body> 标签被 site-shell 脚本截断的错误。数据逻辑原样保留。
 * /topics/{slug}  单个专题详情
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

// ── 话题枢纽：工具网格与产品映射的数据（内容决策；样式全部复用 modules.css 现有 archetype）──
$navData  = json_read(DATA_DIR . '/navigation.json');
$navSites = array_values(array_filter($navData['sites'] ?? [], fn($s) => ($s['status'] ?? 'published') === 'published'));
$plus     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>';
// 分类 → 导航分类（工具）+ 承载产品。导航分类取自 navigation.json：content/agent/open/growth/crm…
$TOPIC_MAP = [
    'ai-create'    => ['nav' => 'content', 'products' => [
        ['/product/mflow', 'MFlow · 内容生产与分发', '生成 + 多平台分发，不换 CMS'],
        ['/product/webs-flow', 'Webs Flow · 落地页专精', '承接页以小时计上线'],
        ['/product', 'OpenFlow · 增长操作系统', '四力合一，内容/数据/触达一处'],
    ]],
    'ai-marketing' => ['nav' => 'growth', 'products' => [
        ['/product/webs-flow', 'Webs Flow · 落地页专精', '投放承接页与 A/B'],
        ['/product/userloop', 'UserLoop · 全域营销数据中枢', '接上任何 MA，数据一处清'],
        ['/product', 'OpenFlow · 增长操作系统', '内容 + 数据 + 销售一条链路'],
    ]],
    'ai-code'      => ['nav' => 'agent', 'products' => [
        ['/product', 'OpenFlow · 增长操作系统', '画布编排 + Agent 运行时'],
        ['/product/userloop', 'UserLoop · 全域营销数据中枢', '行为数据进一处'],
        ['/demo/capabilities', '能力全景 · 42 个模块', '看每项能力归谁承载'],
    ]],
    'ai-ops'       => ['nav' => 'growth', 'products' => [
        ['/product/userloop', 'UserLoop · 全域营销数据中枢', '行为驱动分群'],
        ['/product', 'OpenFlow · 增长操作系统', '流程自动化与审批闸门'],
        ['/demo/capabilities', '能力全景 · 42 个模块', '看每项能力归谁承载'],
    ]],
    'ai-sell'      => ['nav' => 'crm', 'products' => [
        ['/product/payflow', 'PayFlow · 商业变现引擎', '一行嵌入收款'],
        ['/product/learnflow', 'LearnFlow · 课程与交付', '开营到结业一个后台'],
        ['/product', 'OpenFlow · 增长操作系统', 'CRM + 商城 + 会员'],
    ]],
    'ai-build'     => ['nav' => 'open', 'products' => [
        ['/product/webs-flow', 'Webs Flow · 落地页专精', '44 种区块 + 组件工厂'],
        ['/product', 'OpenFlow · 增长操作系统', '全站底座与开源'],
        ['/product/payflow', 'PayFlow · 商业变现引擎', '上线即能收钱'],
    ]],
    'trend'        => ['nav' => 'growth', 'products' => [
        ['/product/inflow', 'inFlow · 情报增长站', '趋势 / 舆情 / 竞品'],
        ['/product/mflow', 'MFlow · 内容生产与分发', '把趋势变成内容'],
        ['/product', 'OpenFlow · 增长操作系统', '情报接进选题与发布'],
    ]],
    'agent'        => ['nav' => 'agent', 'products' => [
        ['/product', 'OpenFlow · 增长操作系统', 'Agent 运行时与工作流'],
        ['/product/userloop', 'UserLoop · 全域营销数据中枢', '数据底座'],
        ['/demo/capabilities', '能力全景 · 42 个模块', '看每项能力归谁承载'],
    ]],
    'product'      => ['nav' => 'open', 'products' => [
        ['/product', 'OpenFlow · 增长操作系统', '全站底座'],
        ['/product/payflow', 'PayFlow · 商业变现引擎', '一行嵌入收款'],
        ['/product/webs-flow', 'Webs Flow · 落地页专精', '承接页以小时计上线'],
    ]],
];
$PICON = [
    'of' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 6.5a9.5 9.5 0 1 1-9.5 9.5"/><path d="M11.5 10.5v12M11.5 14h7.6"/></svg>',
    'p'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2.5"/><path d="M3 10h18"/></svg>',
    'm'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v14H4z"/><path d="M7 9h10M7 13h6"/></svg>',
    'w'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 6h14v12H5z"/><path d="M9 10h6M9 14h4"/></svg>',
    'u'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="3.2"/><path d="M5 20c1.6-3.4 4-5 7-5s5.4 1.6 7 5"/></svg>',
    'i'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-3.6-3.6"/></svg>',
    'l'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/></svg>',
];
$picon = function (string $href) use ($PICON): string {
    foreach ([['/product/mflow', 'm'], ['/product/webs-flow', 'w'], ['/product/userloop', 'u'], ['/product/inflow', 'i'], ['/product/payflow', 'p'], ['/product/learnflow', 'l']] as [$h, $k]) {
        if (str_starts_with($href, $h)) return $PICON[$k];
    }
    return $PICON['of'];
};

// 单个专题详情模式
$topicSlug = trim(req_str('slug'));
$currentTopic = null;
if ($topicSlug) {
    foreach ($topics as $t) {
        if (($t['slug'] ?? '') === $topicSlug) { $currentTopic = $t; break; }
    }
}
$topicArticles = [];
if ($currentTopic) {
    $topicArticles = [];
    foreach (($currentTopic['article_ids'] ?? $currentTopic['articles'] ?? []) as $aid) {
        if (isset($articleMap[$aid]) && ($articleMap[$aid]['status'] ?? 'draft') === 'published') {
            $topicArticles[] = $articleMap[$aid];
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $currentTopic ? (htmlspecialchars($currentTopic['title'] ?? '专题') . ' | ' . site_config_get('site_name')) : ('专题合集 | ' . site_config_get('site_name')) ?></title>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 专题页独有：专题卡。其余全部来自 modules.css。 */
.tp-card{display:flex;flex-direction:column;gap:14px}
.tp-card .hd{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
.tp-card .cov{width:140px;height:90px;object-fit:cover;border-radius:12px;flex:0 0 auto}
.tp-card .hd>div{flex:1;min-width:240px}
.tp-card h2{font-size:20px;font-weight:800;letter-spacing:-.01em;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.tp-card p{font-size:14px;color:var(--muted);line-height:1.75;margin-top:6px}
.tp-card .more{font-size:12.5px;color:var(--accent);font-weight:600}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('topics'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <?php if ($currentTopic): ?>
  <section id="top" class="reveal in" data-od-anchor data-od-id="topic-head">
    <nav class="art-meta" aria-label="面包屑" style="justify-content:center"><a href="/topics" style="color:var(--faint)">← 全部专题</a></nav>
    <div class="hero-center" style="padding-top:18px;padding-bottom:0">
      <span class="kicker">专题</span>
      <h1><?=htmlspecialchars($currentTopic['title'] ?? '')?></h1>
      <p class="lead"><?=htmlspecialchars($currentTopic['description'] ?? '')?></p>
      <?php if (!empty($currentTopic['category'])): ?><span class="badge ok"><?=htmlspecialchars($catNames[$currentTopic['category']] ?? $currentTopic['category'])?></span><?php endif; ?>
    </div>
  </section>
  <section class="sec reveal reader" data-od-anchor data-od-id="topic-articles">
    <div class="sec-head row"><div><span class="kicker">收录文章 · <?=count($topicArticles)?> 篇</span><h2>按顺序读</h2></div></div>
    <?php if (empty($topicArticles)): ?>
    <div class="empty">该专题下暂无文章</div>
    <?php else: ?>
    <div class="rank">
      <?php foreach ($topicArticles as $i => $a): ?>
      <a href="/articles/<?=htmlspecialchars($a['slug'])?>"><span class="n"><?=$i+1?></span><span class="t"><b><?=htmlspecialchars($a['title'])?></b><span><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?> · <?=htmlspecialchars($catNames[$a['category'] ?? ''] ?? '')?></span></span></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php
  $rootCat  = explode('/', (string) ($currentTopic['category'] ?? ''))[0];
  $map      = $TOPIC_MAP[$rootCat] ?? ['nav' => 'growth', 'products' => [['/product', 'OpenFlow · 增长操作系统', '全站底座']]];
  $topicTools = array_slice(array_values(array_filter($navSites, fn($s) => (($s['category'] ?? $s['cat'] ?? '') === $map['nav']))), 0, 6);
  $catLabel = $catNames[$currentTopic['category'] ?? ''] ?? ($currentTopic['category'] ?? '');
  $lastDate = substr((string) ($currentTopic['updated_at'] ?? ''), 0, 10);
  ?>
  <section id="topic-tools" class="sec reveal" data-od-anchor data-od-id="topic-tools">
    <div class="sec-head center">
      <span class="kicker">工具</span>
      <h2>做这个话题，常用的工具</h2>
      <p class="lead">来自增长导航的已收录站点，按本话题所属分类匹配——收录理由与评分在导航页可见。</p>
    </div>
    <?php if (empty($topicTools)): ?>
    <div class="empty">该分类下暂无收录工具</div>
    <?php else: ?>
    <div class="toolgrid">
      <?php foreach ($topicTools as $s): ?>
      <div>
        <span class="tg-tag"><?=htmlspecialchars($s['sub'] ?: ($s['category'] ?? ''))?></span>
        <h3><a href="/navigation/<?=htmlspecialchars($s['id'])?>"><?=htmlspecialchars($s['name'])?></a></h3>
        <p><?=htmlspecialchars($s['description'] ?? '')?></p>
        <a href="/navigation/<?=htmlspecialchars($s['id'])?>">看详情 →</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section id="topic-products" class="sec reveal" data-od-anchor data-od-id="topic-products">
    <div class="sec-head center">
      <span class="kicker">该用什么产品</span>
      <h2>这个话题落到工具上，是这几件</h2>
    </div>
    <div class="link-grid">
      <?php foreach ($map['products'] as $tp): ?>
      <a class="link-it" href="<?=htmlspecialchars($tp[0])?>"><span class="ic"><?=$picon($tp[0])?></span><span class="lt"><b><?=htmlspecialchars($tp[1])?></b><span><?=htmlspecialchars($tp[2])?></span></span><span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span></a>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="topic-faq" class="sec reveal" data-od-anchor data-od-id="topic-faq">
    <div class="sec-head center">
      <span class="kicker">常见问题</span>
      <h2>关于这个专题</h2>
    </div>
    <div class="faq">
      <div class="fq"><button class="fq-q" aria-expanded="false"><span>这个专题适合谁读？</span><span class="fx"><?=$plus?></span></button><div class="fq-a"><div><p>面向正在做「<?=htmlspecialchars($currentTopic['title'] ?? '')?>」的人。本专题已收录 <?=count($topicArticles)?> 篇已发布内容，按时间倒序排列，从第一篇开始读即可。</p></div></div></div>
      <div class="fq"><button class="fq-q" aria-expanded="false"><span>多久更新一次？</span><span class="fx"><?=$plus?></span></button><div class="fq-a"><div><p>不按固定周期：新内容只要归入「<?=htmlspecialchars($catLabel)?>」，就会自动出现在这里，无需人工维护列表。最近一次更新：<?=htmlspecialchars($lastDate)?>。</p></div></div></div>
      <div class="fq"><button class="fq-q" aria-expanded="false"><span>读完该从哪里动手？</span><span class="fx"><?=$plus?></span></button><div class="fq-a"><div><p>挑一件产品把动作接上：<?php foreach ($map['products'] as $tp): ?><b><?=htmlspecialchars(explode(' · ', $tp[1])[0])?></b> <?php endforeach;?>。工具给方法，产品给落地。</p></div></div></div>
    </div>
  </section>

  <section class="reveal" data-od-anchor data-od-id="topic-cta">
    <div class="cta-band">
      <span class="kicker">下一步</span>
      <h2>读完之后，挑一件产品把动作接上</h2>
      <p class="lead">订阅更新，新内容进这个专题时收到；或者直接看七个产品怎么按场景组合。</p>
      <div class="cta-row"><a href="/newsletter" class="btn primary">订阅更新</a><a class="btn ghost" href="/demo/products">看产品矩阵</a></div>
    </div>
  </section>

  <?php else: ?>
  <section id="top" class="reveal in" data-od-anchor data-od-id="topics-hero">
    <div class="hero-center" style="padding-bottom:0">
      <span class="kicker">专题</span>
      <h1>一次读透<i class="si">一个主题</i></h1>
      <p class="lead">围绕核心议题的深度内容聚合，一次读透一个主题</p>
    </div>
  </section>
  <section class="sec reveal" data-od-anchor data-od-id="topics-list">
    <?php if (empty($topics)): ?>
    <div class="empty">专题筹备中</div>
    <?php else: foreach ($topics as $t): $tArts = array_values(array_filter(array_map(fn($id) => $articleMap[$id] ?? null, $t['article_ids'] ?? []))); ?>
    <div class="card tp-card">
      <div class="hd">
        <?php if (!empty($t['cover'])): ?><img class="cov" src="<?=htmlspecialchars(strpos($t['cover'],'http')===0?$t['cover']:'/'.ltrim($t['cover'],'/'))?>" alt="" onerror="this.remove()"><?php endif; ?>
        <div>
          <h2><a href="/topic/<?=htmlspecialchars($t['slug'] ?? '')?>"><?=htmlspecialchars($t['title'] ?? '')?></a><?php if (!empty($t['category'])): ?><span class="badge ok"><?=htmlspecialchars($catNames[$t['category']] ?? $t['category'])?></span><?php endif; ?><span class="note"><?=count($tArts)?> 篇文章</span></h2>
          <p><?=htmlspecialchars($t['description'] ?? '')?></p>
        </div>
      </div>
      <div class="rank">
        <?php foreach (array_slice($tArts, 0, 5) as $i => $a): ?>
        <a href="/articles/<?=htmlspecialchars($a['slug'])?>"><span class="n"><?=$i+1?></span><span class="t"><b><?=htmlspecialchars($a['title'])?></b><span><?=htmlspecialchars(substr($a['created_at'] ?? '', 0, 10))?></span></span></a>
        <?php endforeach; ?>
      </div>
      <?php if (count($tArts) > 5): ?><a class="more" href="/topic/<?=htmlspecialchars($t['slug'] ?? '')?>">+ <?=count($tArts)-5?> 篇更多 →</a><?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
  </section>
  <?php endif; ?>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<script>
(function(){
  document.querySelectorAll('.fq').forEach(function(el){
    var q=el.querySelector('.fq-q'); if(!q) return;
    q.addEventListener('click',function(){var on=el.classList.toggle('open');q.setAttribute('aria-expanded',on?'true':'false');});
  });
})();
</script>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
