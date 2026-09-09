<?php
/**
 * 文章详情页 — /article/{slug}（由 .htaccess 重写）
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/CommentSystem.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/comment-widget.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/MembershipSystem.php';
require_once __DIR__ . '/lib/ShortcodeSystem.php';
require_once __DIR__ . '/lib/ArticleStats.php';
require_once __DIR__ . '/lib/AdSystem.php';
require_once __DIR__ . '/lib/ShareTrack.php';
require_once __DIR__ . '/lib/CoverRenderer.php';

$slug = trim($_GET['slug'] ?? '');
$article = null;
foreach (get_articles() as $a) {
    if (($a['slug'] ?? '') === $slug && ($a['status'] ?? 'draft') === 'published') { $article = $a; break; }
}

if (!$article) {
    http_response_code(404);
    $notFound = true;
} else {
    $notFound = false;
    $article = PluginSystem::apply_filters('article_output_before', $article, $slug);
    // 内容多语言：按当前 locale 解析译文（覆盖 title/content/seo，其余保持 base）
    require_once __DIR__ . '/lib/ContentI18n.php';
    $article = ci18n_resolve($article);
    // 记录阅读数
    if (function_exists('art_stats_add')) {
        @art_stats_add($article['slug'], 'view');
    }
    // 分享传播链：检测 ?ref=share_key 并记录访问
    $shareRef = trim($_GET['ref'] ?? '');
    if ($shareRef && function_exists('share_track_valid') && share_track_valid($shareRef)) {
        $visitorId = '';
        if (function_exists('member_current')) {
            $visitor = member_current();
            $visitorId = $visitor['id'] ?? '';
        }
        @share_track_visit($shareRef, $visitorId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }
}

// ─── 会员专享拦截 ───
$memberGate = false;   // 是否被会员墙拦截（仍可看摘要）
$member = member_current();
if (!$notFound && !empty($article['member_only']) && !member_can($member, 'articles_member')) {
    $memberGate = true;
}
// 分层付费门禁（BACKLOG T1-6）：required_tier 指定"某套餐及以上"才能看全文
$paidTier = trim((string)($article['required_tier'] ?? ''));
$paidGate = false; $paidHint = '';
if (!$notFound && $paidTier !== '') {
    require_once __DIR__ . '/lib/PaidContent.php';
    if (!paid_can_view($member, $paidTier)) {
        $paidGate = true;
        $paidHint = paid_upgrade_hint($paidTier);
    }
}

// ─── 兜底：文章不存在时 $article 为空数组，避免后续所有 null 访问 ───
if (!$article) {
    $article = [];
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'example.com';
$baseUrl = $protocol . '://' . $host;
$articleUrl = $baseUrl . '/article/' . $slug;

// ─── 内容处理：短代码 → 标题锚点 → 阅读时间 ───
// 短代码此前只是被 require 进来，shortcode_render() 从来没有被调用过：
// 正文里写 [card type="course" id="x"] 会原样显示成方括号文本。先展开再做锚点，
// 这样短代码产出的标题也能进目录。
$rawContent = $article['content'] ?? '';
$content = shortcode_render($rawContent);
$usedShortcode = $content !== $rawContent;   // 用到了才注入短代码样式，没用到不给每篇文章加 CSS
$toc = [];
$hIdx = 0;
$content = preg_replace_callback('/<h([23])([^>]*)>(.*?)<\/h\1>/is', function ($m) use (&$toc, &$hIdx) {
    $hIdx++;
    $id = 'sec-' . $hIdx;
    $text = trim(strip_tags($m[3]));
    $toc[] = ['id' => $id, 'text' => $text, 'level' => (int)$m[1]];
    return '<h' . $m[1] . $m[2] . ' id="' . $id . '">' . $m[3] . '</h' . $m[1] . '>';
}, $content);

$wordCount = mb_strlen(strip_tags($content));
$readMins = max(1, (int)ceil($wordCount / 400));

// ─── 互动数据：阅读量 / 评论数 / 点赞数 ───
$viewCount = 0;
$commentCount = 0;
$likeCount = 0;
if (!empty($article['id'])) {
    try {
        $vc = Database::query("SELECT COUNT(*) AS c FROM events WHERE event='page_view' AND page LIKE ?", ['/article/' . ($article['slug'] ?? '')]);
        $viewCount = (int)($vc[0]['c'] ?? 0);
    } catch (Exception $e) {}
    $cs = comment_stats('article', $article['id']);
    $commentCount = $cs['count'] ?? 0;
    $likeCount = $cs['likes'] ?? 0;
}
$interactionCount = $viewCount + $commentCount + $likeCount;

// ─── 互动统计（点赞/收藏/分享）───
$artStats = ['views' => $viewCount, 'likes' => 0, 'favorites' => 0, 'shares' => 0];
if (function_exists('art_stats_get') && !empty($article['slug'])) {
    $artStats = array_merge($artStats, art_stats_get($article['slug']));
}

// ─── 相关文章：同分类优先，其次同标签，混合个性化推荐 ───
$related = [];
if (!$notFound) {
    $cat = $article['category'] ?? '';
    $tags = $article['tags'] ?? [];
    foreach (get_articles() as $a) {
        if ($a['id'] === $article['id'] || ($a['status'] ?? 'draft') !== 'published') continue;
        $score = 0;
        if ($cat && ($a['category'] ?? '') === $cat) $score += 2;
        $score += count(array_intersect($tags, $a['tags'] ?? []));
        if ($score > 0) $related[] = ['score' => $score, 'a' => $a];
    }
    usort($related, fn($x, $y) => $y['score'] <=> $x['score'] ?: strcmp($y['a']['created_at'] ?? '', $x['a']['created_at'] ?? ''));
    $related = array_slice($related, 0, 3);

    // 混合个性化推荐：若不足3篇，用画像偏好补充
    if (count($related) < 3) {
        try {
            require_once __DIR__ . '/lib/Personalizer.php';
            $vid = $_COOKIE['fc_uid'] ?? '';
            $mid = $_COOKIE['member_id'] ?? '';
            $pref = Personalizer::buildProfile($vid, $mid);
            $recs = Personalizer::recommendArticles($pref, 3, $article['id'] ?? '');
            $existing = array_column(array_column($related, 'a'), 'id');
            foreach ($recs as $rid => $rscore) {
                if (in_array($rid, $existing, true)) continue;
                $ra = get_article($rid);
                if ($ra) { $related[] = ['score' => 50 + $rscore, 'a' => $ra]; $existing[] = $rid; }
            }
            $related = array_slice($related, 0, 3);
        } catch (Exception $e) {}
    }
}

// ─── 分类名映射 ───
$catNames = [];
foreach (get_categories('article') as $c) $catNames[$c['key']] = $c['name'];
$catName = $catNames[$article['category'] ?? ''] ?? '';

// ─── SEO ───
$pageTitle = !empty($article['seo_title']) ? $article['seo_title'] : ($article['title'] ?? '文章') . ' | OpenFlow';
$pageDesc = !empty($article['seo_desc']) ? $article['seo_desc'] : mb_substr(strip_tags($content), 0, 120);
$cover = $article['cover'] ?? '';
$coverUrl = $cover ? (strpos($cover, 'http') === 0 ? $cover : $baseUrl . '/' . ltrim($cover, '/')) : $baseUrl . '/assets/images/logos/openflow-symbol-primary.png';

// JSON-LD
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $article['title'] ?? '',
    'description' => $pageDesc,
    'image' => $coverUrl,
    'datePublished' => $article['created_at'] ?? '',
    'dateModified' => $article['updated_at'] ?? ($article['created_at'] ?? ''),
    'author' => ['@type' => 'Person', 'name' => $article['author'] ?? 'OpenFlow', 'url' => $baseUrl . '/author/' . urlencode($article['author'] ?? 'OpenFlow')],
    'publisher' => ['@type' => 'Organization', 'name' => 'OpenFlow', 'logo' => ['@type' => 'ImageObject', 'url' => $baseUrl . '/assets/images/logos/openflow-symbol-primary.png']],
    'mainEntityOfPage' => $articleUrl,
];
if ($catName) $jsonLd['articleSection'] = $catName;
if (!empty($article['tags'])) $jsonLd['keywords'] = implode(',', $article['tags']);

// ─── GEO：AI 摘要 + FAQ 结构化 ───
$geoAnswer = trim(strip_tags(mb_substr($content, 0, 300)));
if (mb_strlen($geoAnswer) > 40) {
    $jsonLd['description'] = mb_substr($geoAnswer, 0, 150);
}
$faqItems = [];
// 从内容提取 h3 作为 FAQ 候选（若含问句）
if (preg_match_all('/<h3[^>]*>(.*?)<\/h3>/i', $content, $h3m)) {
    foreach ($h3m[1] as $i => $h) {
        $q = trim(strip_tags($h));
        if (mb_strpos($q, '？') === false && mb_strpos($q, '?') === false && mb_strpos($q, '如何') === false) continue;
        // 找该标题后的一段文字作为答案
        $p = '';
        if (preg_match('/<h3[^>]*>' . preg_quote($h, '/') . '<\/h3>\s*<p[^>]*>(.*?)<\/p>/is', $content, $pm)) {
            $p = trim(strip_tags($pm[1]));
        }
        if (mb_strlen($p) > 20) $faqItems[] = ['q' => mb_substr($q, 0, 80), 'a' => mb_substr($p, 0, 200)];
        if (count($faqItems) >= 3) break;
    }
}
// 补充：通用 FAQ（若不足）
$defaultFaqs = [
    ['q' => '这篇文章讲的是什么？', 'a' => mb_substr($geoAnswer, 0, 150)],
    ['q' => '如何应用到我的网站？', 'a' => '可结合网站增长诊断评估现状，再针对性优化内容与转化链路。'],
];
foreach ($defaultFaqs as $fq) { if (count($faqItems) < 3) $faqItems[] = $fq; }
$faqLd = null;
if (count($faqItems) >= 2) {
    $faqLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn($f) => ['@type'=>'Question','name'=>$f['q'],'acceptedAnswer'=>['@type'=>'Answer','text'=>$f['a']]], $faqItems),
    ];
}

$breadcrumbLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $baseUrl . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => '社区', 'item' => $baseUrl . '/community'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title'] ?? ''],
    ],
];

// ─── 侧栏组件数据：Top10 / 标签云 / Newsletter ───
$allArticles = get_articles();
$publishedAll = array_values(array_filter($allArticles, fn($a) => ($a['status'] ?? 'draft') === 'published'));
usort($publishedAll, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$top10 = array_slice($publishedAll, 0, 10);

$tagCounts = [];
foreach ($publishedAll as $a) foreach (($a['tags'] ?? []) as $t) $tagCounts[$t] = ($tagCounts[$t] ?? 0) + 1;
arsort($tagCounts);
$tagCloud = array_slice($tagCounts, 0, 30);

// Newsletter 表单存在性
$formsData = json_read(DATA_DIR . '/forms/index.json');
$newsletterForm = null;
foreach ($formsData as $f) if (($f['type'] ?? '') === 'newsletter') { $newsletterForm = $f; break; }
if (!$newsletterForm) foreach ($formsData as $f) if (($f['type'] ?? '') === 'lead' || ($f['type'] ?? '') === 'download') { $newsletterForm = $f; break; }
$newsletterFormId = $newsletterForm['id'] ?? 'form_lead_default';
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($pageTitle)?></title>
<meta name="description" content="<?=htmlspecialchars($pageDesc)?>">
<link rel="canonical" href="<?=htmlspecialchars($articleUrl)?>">
<?php if (!$notFound && function_exists('ci18n_hreflang') && count(ci18n_locales($article)) > 1):
    $__o = parse_url($articleUrl ?? ''); $__origin = (!empty($__o['scheme']) && !empty($__o['host'])) ? $__o['scheme'] . '://' . $__o['host'] : '';
    if ($__origin) echo ci18n_hreflang($article, $__origin) . "\n"; endif; ?>
<meta property="og:title" content="<?=htmlspecialchars($article['title'] ?? '')?>">
<meta property="og:description" content="<?=htmlspecialchars($pageDesc)?>">
<meta property="og:image" content="<?=htmlspecialchars($coverUrl)?>">
<meta property="og:url" content="<?=htmlspecialchars($articleUrl)?>">
<meta name="twitter:card" content="summary_large_image">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<script type="application/ld+json"><?=json_encode($jsonLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<?php if ($faqLd): ?><script type="application/ld+json"><?=json_encode($faqLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script><?php endif; ?>
<script type="application/ld+json"><?=json_encode($breadcrumbLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<link rel="stylesheet" id="of-fonts-css" href="/assets/fonts/fonts.css?v=20260903a">
<link rel="stylesheet" id="of-tokens-css" href="/assets/tokens.css?v=20260903a">
<link rel="stylesheet" id="of-modules-css" href="/assets/modules.css?v=20260909a">
<style>
/* 文章页独有：标签云。其余（阅读版式 .reader/.prose、动作条、付费墙）全部来自 modules.css。 */
.art-tags{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0 0}
.art-tags a{font-size:12.5px;color:var(--muted);padding:6px 14px;border-radius:999px;border:1px solid var(--border);transition:border-color .2s,color .2s}
.art-tags a:hover{border-color:var(--accent);color:var(--accent)}
.not-found{text-align:center;padding:60px 0;display:flex;flex-direction:column;align-items:center;gap:12px}
/* 阅读进度条 */
.read-progress{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,var(--accent),var(--ok));z-index:9999;transition:width .12s linear}
/* 桌面目录轨（≥1320px 才不压正文） */
.toc-rail{position:fixed;right:max(20px,calc(50% - 690px));top:calc(var(--chrome-h,72px) + 44px);width:224px;z-index:5;display:none}
@media(min-width:1320px){.toc-rail{display:block}}
.toc-rail .toc-box{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 12px;backdrop-filter:blur(14px) saturate(150%);max-height:60vh;overflow:auto}
.toc-rail h4{font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--faint);margin:0 8px 8px}
.toc-rail a{display:block;font-size:12.5px;color:var(--muted);padding:5px 9px;border-radius:8px;border-left:2px solid transparent;line-height:1.5;transition:color .15s,background .15s}
.toc-rail a:hover{color:var(--fg);background:var(--hover)}
.toc-rail a.on{color:var(--accent-strong);border-left-color:var(--accent);background:var(--accent-soft);font-weight:600}
.toc-rail a.lv3{padding-left:22px;font-size:12px;color:var(--faint)}
/* 相关阅读封面卡 */
.rel-grid{display:grid;gap:16px;grid-template-columns:repeat(3,1fr);margin-top:18px}
@media(max-width:720px){.rel-grid{grid-template-columns:1fr}}
.rel-grid .a-card .cov{aspect-ratio:16/8}
/* 发现更多：Top10 + 标签云 */
.disc-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px;margin-top:18px}
@media(max-width:720px){.disc-grid{grid-template-columns:1fr}}
.disc-box{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);padding:18px 20px;backdrop-filter:blur(16px) saturate(150%)}
.disc-box h3{font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--faint);margin:0 0 10px}
.disc-box ol{list-style:none;margin:0;padding:0;display:flex;flex-direction:column}
.disc-box ol a{display:flex;gap:10px;align-items:baseline;padding:7px 0;font-size:13.5px;color:var(--muted);border-bottom:1px dashed var(--border-soft);transition:color .15s}
.disc-box ol li:last-child a{border-bottom:none}
.disc-box ol a:hover{color:var(--accent)}
.disc-box .rn{font-family:var(--font-mono);font-size:11px;font-weight:800;color:var(--faint);width:18px;flex:0 0 auto}
.disc-box ol li:nth-child(-n+3) .rn{color:var(--accent-strong)}
.disc-box ol a span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tag-cloud{display:flex;flex-wrap:wrap;gap:7px}
.tag-cloud a{font-size:12px;color:var(--muted);padding:5px 12px;border-radius:999px;border:1px solid var(--border);transition:border-color .2s,color .2s}
.tag-cloud a:hover{border-color:var(--accent);color:var(--accent)}
.tag-cloud a b{font-family:var(--font-mono);font-size:10px;color:var(--faint);margin-left:3px}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('articles'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">
  <?php if ($notFound): ?>
  <section class="reader reveal in">
    <div class="not-found">
      <span class="kicker">404</span>
      <h1 class="h3" style="font-size:28px">文章不存在</h1>
      <p class="lead" style="color:var(--muted)">这篇文章可能已被删除或链接有误。</p>
      <a href="/articles" class="btn primary">返回文章列表</a>
    </div>
  </section>
  <?php else: ?>
  <div class="read-progress" id="readProg" aria-hidden="true"></div>
  <?php if (count($toc) >= 3): ?>
  <nav class="toc-rail" aria-label="本文目录">
    <div class="toc-box">
      <h4>本文目录</h4>
      <?php foreach ($toc as $t): ?>
      <a href="#<?=$t['id']?>" class="lv<?=$t['level']?>" data-toc="<?=$t['id']?>"><?=htmlspecialchars($t['text'])?></a>
      <?php endforeach; ?>
    </div>
  </nav>
  <?php endif; ?>
  <article class="reader reveal in" data-od-id="article">
    <?php if (CoverRenderer::usesCssCover($article)): ?>
    <?php $article['_read_mins'] = $readMins; ?>
    <?=CoverRenderer::renderDetail($article)?>
    <?php else: ?>
    <div class="art-head">
      <div class="art-meta">
        <?php if ($catName): ?><span class="badge ok"><?=htmlspecialchars($catName)?></span><?php endif; ?>
        <a href="/author/<?=urlencode($article['author'] ?? 'OpenFlow')?>"><?=htmlspecialchars($article['author'] ?? 'OpenFlow')?></a>
        <span class="sep"></span>
        <span><?=htmlspecialchars(substr($article['created_at'] ?? '', 0, 10))?></span>
        <span class="sep"></span>
        <span><?=$readMins?> 分钟阅读</span>
      </div>
      <h1><?=htmlspecialchars($article['title'] ?? '')?></h1>
    </div>

    <?=CoverRenderer::renderDetailCover($article)?>
    <?php endif; ?>
    <?php if (function_exists('ads_render')): ?><div style="margin-bottom:24px"><?=ads_render('article_top')?></div><?php endif; ?>

    <div class="prose">
      <?php if ($memberGate): ?>
        <div class="card gate-box">
          <span class="kicker">会员专享</span>
          <h2>这是一篇会员专享文章</h2>
          <p>开通会员即可阅读全文</p>
          <a href="member.php?view=subscribe" class="btn primary">开通会员 →</a>
        </div>
      <?php elseif ($paidGate): ?>
        <?php $pv = paid_preview($content); ?>
        <?=article_render($pv['preview'])?>
        <div class="gate"><div class="card gate-box">
          <span class="kicker">付费内容</span>
          <h2>继续阅读全文</h2>
          <p><?=htmlspecialchars($paidHint)?></p>
          <a href="member.php?view=subscribe" class="btn primary">立即升级 →</a>
        </div></div>
      <?php else: ?>
        <?php if ($usedShortcode) echo shortcode_style(); ?>
        <?=article_render($content)?>
      <?php endif; ?>
    </div>

    <?php if (function_exists('ads_render')): ?><div style="margin-top:24px"><?=ads_render('article_bottom')?></div><?php endif; ?>

    <div class="actions">
      <button class="act" id="likeBtn"><?=htmlspecialchars((int)($artStats['likes'] ?? 0))?> 赞</button>
      <button class="act" id="favBtn">收藏</button>
      <button class="act" id="shareBtn">分享</button>
      <button class="act" id="posterBtn" title="生成分享海报">生成海报</button>
      <button class="act" id="cmtBtn" onclick="document.querySelector('[data-od-id=article-comments]').scrollIntoView({behavior:'smooth',block:'start'})"><?=(int)$commentCount?> 评论</button>
      <button class="act" id="viewBtn"><?=number_format((int)($artStats['views'] ?? 0))?> 阅读</button>
    </div>

    <?php if (!empty($article['tags'])): ?>
    <div class="art-tags"><?php foreach ($article['tags'] as $t): ?><a href="/articles?tag=<?=urlencode($t)?>"># <?=htmlspecialchars($t)?></a><?php endforeach; ?></div>
    <?php endif; ?>
  </article>

  <section class="reader reveal" data-od-id="article-comments">
    <?php fc_comment_widget('article', (string)($article['id'] ?? $slug), ['title' => '评论']); ?>
  </section>

  <?php if (!empty($related)): ?>
  <section class="reader reveal" data-od-id="article-related">
    <div class="sec-head row"><div><span class="kicker">相关阅读</span><h2>接着看</h2></div></div>
    <div class="rel-grid">
      <?php foreach ($related as $r): $ra = $r['a']; ?>
      <a class="a-card" href="/article/<?=htmlspecialchars($ra['slug'])?>">
        <div class="cov"><?=CoverRenderer::renderCard($ra)?></div>
        <div class="bd" style="padding:14px 16px">
          <h3 style="font-size:14.5px;line-height:1.5"><?=htmlspecialchars($ra['title'])?></h3>
          <div class="meta" style="margin-top:8px"><?=htmlspecialchars(substr($ra['created_at'] ?? '', 0, 10))?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($top10) || !empty($tagCloud)): ?>
  <section class="reader reveal" data-od-id="article-discover">
    <div class="disc-grid">
      <?php if (!empty($top10)): ?>
      <div class="disc-box">
        <h3>最新文章 Top 10</h3>
        <ol>
          <?php foreach ($top10 as $ti => $ta): if (($ta['id'] ?? '') === ($article['id'] ?? '')) continue; ?>
          <li><a href="/article/<?=htmlspecialchars($ta['slug'])?>"><span class="rn"><?=str_pad((string)($ti+1), 2, '0', STR_PAD_LEFT)?></span><span><?=htmlspecialchars($ta['title'])?></span></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>
      <?php if (!empty($tagCloud)): ?>
      <div class="disc-box">
        <h3>标签云</h3>
        <div class="tag-cloud">
          <?php foreach ($tagCloud as $tag => $cnt): ?>
          <a href="/articles?tag=<?=urlencode($tag)?>"><?=htmlspecialchars($tag)?><b><?=$cnt?></b></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($related)): ?>
  <section class="reader reveal" id="personalizedRecs" hidden data-od-id="article-recs">
    <div class="sec-head row"><div><span class="kicker">猜你喜欢</span><h2>为你挑的</h2></div></div>
    <div class="link-grid" id="personalizedRecsGrid" style="margin-top:18px;grid-template-columns:repeat(2,1fr)"></div>
  </section>
  <script>
  (function(){
    fetch('/api/recommend.php?type=articles&limit=3&exclude=<?=htmlspecialchars($article['id'] ?? '')?>', {credentials:'include'})
      .then(function(r){return r.json();})
      .then(function(d){
        if (!d.ok || !d.recommendations || d.recommendations.length === 0) return;
        var box = document.getElementById('personalizedRecs');
        var grid = document.getElementById('personalizedRecsGrid');
        d.recommendations.forEach(function(a){
          var el = document.createElement('a'); el.className = 'link-it'; el.href = a.url;
          var lt = document.createElement('span'); lt.className = 'lt';
          var b = document.createElement('b'); b.textContent = a.title;
          var sp = document.createElement('span'); sp.textContent = (a.category || '') + ' · ' + (a.tags || []).slice(0,2).join(' ');
          lt.appendChild(b); lt.appendChild(sp); el.appendChild(lt); grid.appendChild(el);
        });
        box.hidden = false;
      });
  })();
  </script>
  <?php endif; ?>

  <section class="reader reveal" data-od-id="article-newsletter">
    <div class="cta-band">
      <span class="kicker">订阅</span>
      <h2>订阅内容更新</h2>
      <p class="lead">每周获取网站增长与 AI 运营最新洞察，绝无打扰。</p>
      <form onsubmit="return ofNewsletter(this,event)">
        <input class="inp" type="email" placeholder="你的邮箱" required aria-label="邮箱">
        <button class="btn primary" type="submit">订阅</button>
      </form>
    </div>
  </section>
  <?php endif; ?>

  <footer class="foot" data-od-id="site-footer">
    <div class="fb">
      <div class="brand"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>芭乐派 · OpenFlow</div>
      <p class="f-about">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
      <p class="note">核心能力永久开源 · 鱼与渔相结合</p>
    </div>
    <div class="fb"><h4>站点导航</h4><a href="/product">产品</a><a href="/capability">能力</a><a href="/courses">课程</a><a href="/academy">学院</a><a href="/community">门派社区</a><a href="/about">关于我们</a></div>
    <div class="fb"><h4>资源</h4><a href="/courses">芭乐派课程</a><a href="/docs">文档中心</a><a href="/downloads">模板库</a><a href="/academy">内容学院</a></div>
    <div class="fb"><h4>联系</h4><a href="mailto:hello@openflow.dev">hello@openflow.dev</a><a href="/login">管理后台</a><a href="/community">门派社区</a></div>
    <div class="f-bottom"><span>© 2026 芭乐派 · OpenFlow 增长操作系统</span><span>帮一人公司设计 Agent 能跑的增长系统</span></div>
  </footer>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>

<script>
var OF_SLUG = <?=json_encode($slug)?>;
/* 内容浏览 → CDP 事件 + 行为触发 */
if (window.fcTrack) { try { fcTrack('article_view', { slug: OF_SLUG, category: <?=json_encode($article['category'] ?? '')?>, title: document.title }); } catch (e) {} }
function ofNewsletter(f,e){e.preventDefault();var em=f.querySelector('input').value;fetch('/api/newsletter.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:em,source:'article'})}).then(function(r){return r.json();}).then(function(d){var b=f.querySelector('button');b.textContent=d.ok?'✅ 已订阅':'⚠️ '+(d.error||'失败');});return false;}
function ofStat(action){return fetch('/api/article-stats.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:action,slug:OF_SLUG})}).then(function(r){return r.json();});}
var liked=false;
if(document.getElementById('likeBtn')){
document.getElementById('likeBtn').addEventListener('click',function(){
  if(!liked){liked=true;ofStat('like').then(function(d){document.getElementById('likeBtn').textContent=(d.stats?d.stats.likes:0)+' 赞';});this.classList.add('on');}
  else{liked=false;ofStat('like').then(function(d){document.getElementById('likeBtn').textContent=(d.stats?d.stats.likes:0)+' 赞';});this.classList.remove('on');}
});
document.getElementById('favBtn').addEventListener('click',function(){ofStat('favorite').then(function(d){document.getElementById('favBtn').textContent=d.active?'已收藏':'收藏';});});
document.getElementById('shareBtn').addEventListener('click',function(){
  ofStat('share');
  var url=location.href;
  if(navigator.share){navigator.share({title:document.title,url:url}).catch(function(){});}
  else{navigator.clipboard.writeText(url).then(function(){alert('链接已复制，可追踪传播效果');});}
});
document.getElementById('posterBtn').addEventListener('click',function(){
  ofStat('share');
  window.open('/share-card.php?type=article&id=<?=htmlspecialchars(urlencode($article['id'] ?? $article['slug'] ?? ''))?>', '_blank', 'width=640,height=1100');
});
document.getElementById('viewBtn').addEventListener('click',function(){ofStat('view');});
}
/* 阅读进度条 + 目录滚动高亮 */
(function(){
  var prog=document.getElementById('readProg');
  var links=Array.prototype.slice.call(document.querySelectorAll('.toc-rail a[data-toc]'));
  var heads=[];
  links.forEach(function(l){var h=document.getElementById(l.getAttribute('data-toc'));if(h)heads.push({h:h,l:l});});
  if(!prog&&!heads.length)return;
  function onScroll(){
    if(prog){var max=document.documentElement.scrollHeight-window.innerHeight;prog.style.width=(max>0?Math.min(100,window.scrollY/max*100):0)+'%';}
    if(heads.length){var cur=null;heads.forEach(function(o){if(o.h.getBoundingClientRect().top<150)cur=o;});links.forEach(function(l){l.classList.remove('on');});if(cur)cur.l.classList.add('on');}
  }
  window.addEventListener('scroll',onScroll,{passive:true});onScroll();
})();
</script>
</body>
</html>
