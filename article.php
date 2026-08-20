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

// ─── 兜底：文章不存在时 $article 为空数组，避免后续所有 null 访问 ───
if (!$article) {
    $article = [];
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'example.com';
$baseUrl = $protocol . '://' . $host;
$articleUrl = $baseUrl . '/article/' . $slug;

// ─── 内容处理：标题锚点 + 阅读时间 ───
$content = $article['content'] ?? '';
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
$coverUrl = $cover ? (strpos($cover, 'http') === 0 ? $cover : $baseUrl . '/' . ltrim($cover, '/')) : $baseUrl . '/assets/images/logo-wordmark.jpeg';

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
    'publisher' => ['@type' => 'Organization', 'name' => 'OpenFlow', 'logo' => ['@type' => 'ImageObject', 'url' => $baseUrl . '/assets/images/logo-wordmark.jpeg']],
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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($pageTitle)?></title>
<meta name="description" content="<?=htmlspecialchars($pageDesc)?>">
<link rel="canonical" href="<?=htmlspecialchars($articleUrl)?>">
<meta property="og:title" content="<?=htmlspecialchars($article['title'] ?? '')?>">
<meta property="og:description" content="<?=htmlspecialchars($pageDesc)?>">
<meta property="og:image" content="<?=htmlspecialchars($coverUrl)?>">
<meta property="og:url" content="<?=htmlspecialchars($articleUrl)?>">
<meta name="twitter:card" content="summary_large_image">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}try{if(matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('rm');}catch(e){}</script>
<script type="application/ld+json"><?=json_encode($jsonLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<?php if ($faqLd): ?><script type="application/ld+json"><?=json_encode($faqLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script><?php endif; ?>
<script type="application/ld+json"><?=json_encode($breadcrumbLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<script src="/assets/inject.js?v=20260813ad" data-cfasync="false" data-site-inject></script>
<style>
:root{
  --bg:oklch(96.5% .016 85); --bg-soft:oklch(94% .02 85);
  --surface:oklch(100% 0 0 / .62); --surface-strong:oklch(100% 0 0 / .88);
  --fg:oklch(22% .02 70); --muted:oklch(46% .016 70); --faint:oklch(60% .012 75);
  --border:oklch(86% .014 80); --border-strong:oklch(76% .02 80);
  --hover:oklch(22% .02 70 / .055); --hover-strong:oklch(22% .02 70 / .11);
  --accent:oklch(52% .17 258); --accent-strong:oklch(46% .17 258); --accent-soft:oklch(52% .17 258 / .12); --on-accent:oklch(100% 0 0);
  --ok:oklch(58% .17 152); --ok-soft:oklch(58% .17 152 / .12);
  --warn:oklch(66% .15 75); --warn-soft:oklch(66% .15 75 / .14);
  --danger:oklch(55% .2 25); --danger-soft:oklch(55% .2 25 / .12);
  --glass:oklch(100% 0 0 / .5); --glass-bright:oklch(100% 0 0 / .66); --glass-border:oklch(100% 0 0 / .68);
  --shadow:0 24px 60px -24px oklch(30% .04 80 / .28); --shadow-sm:0 10px 28px -14px oklch(30% .04 80 / .22);
  --blob-a:oklch(72% .12 262 / .30); --blob-b:oklch(70% .13 305 / .24); --blob-c:oklch(74% .11 200 / .22);
  --ease-spring:cubic-bezier(.34,1.56,.64,1); --ease-out:cubic-bezier(.22,1,.36,1);
  --font-display:'Songti SC','Iowan Old Style',Georgia,'Times New Roman',serif;
  --font-body:-apple-system,BlinkMacSystemFont,'PingFang SC','Segoe UI',system-ui,sans-serif;
  --font-mono:ui-monospace,'SF Mono','JetBrains Mono',Menlo,monospace;
  --r-lg:26px; --r-md:18px; --r-sm:12px;
  color-scheme:light;
}
[data-theme="dark"]{
  --bg:oklch(19% .014 70); --bg-soft:oklch(22.5% .014 72);
  --surface:oklch(27% .016 75 / .55); --surface-strong:oklch(30% .016 75 / .82);
  --fg:oklch(93% .008 85); --muted:oklch(70% .014 80); --faint:oklch(55% .012 80);
  --border:oklch(100% 0 0 / .1); --border-strong:oklch(100% 0 0 / .2);
  --hover:oklch(93% .008 85 / .07); --hover-strong:oklch(93% .008 85 / .13);
  --accent:oklch(74% .13 258); --accent-strong:oklch(80% .12 258); --accent-soft:oklch(74% .13 258 / .15); --on-accent:oklch(16% .03 260);
  --ok:oklch(74% .15 152); --ok-soft:oklch(74% .15 152 / .15);
  --warn:oklch(76% .13 75); --warn-soft:oklch(76% .13 75 / .16);
  --danger:oklch(72% .16 25); --danger-soft:oklch(72% .16 25 / .14);
  --glass:oklch(30% .014 75 / .5); --glass-bright:oklch(34% .014 75 / .62); --glass-border:oklch(100% 0 0 / .15);
  --shadow:0 24px 60px -24px oklch(0% 0 0 / .55); --shadow-sm:0 10px 28px -14px oklch(0% 0 0 / .5);
  --blob-a:oklch(62% .13 262 / .18); --blob-b:oklch(58% .14 305 / .15); --blob-c:oklch(60% .12 200 / .13);
  color-scheme:dark;
}
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0; font-family:var(--font-body); color:var(--fg); background:var(--bg); overflow-x:clip; -webkit-font-smoothing:antialiased; line-height:1.6}
a{color:inherit; text-decoration:none}
::selection{background:var(--accent-soft)}
:focus-visible{outline:2px solid var(--accent); outline-offset:2px}
h1,h2,h3,h4,p{margin:0}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-thumb{background:var(--border-strong); border-radius:99px; border:3px solid transparent; background-clip:padding-box}
.si{font-family:var(--font-display); font-style:italic; font-weight:700; letter-spacing:-.01em}
.kicker{font-family:var(--font-mono); font-size:11px; font-weight:700; letter-spacing:.18em; color:var(--accent); text-transform:uppercase}
#chrome{position:fixed; inset:0 0 auto 0; z-index:60; padding:8px 14px}
.bar{position:relative; height:56px; display:flex; align-items:center; gap:10px; padding:0 12px; border-radius:18px; background:var(--glass); -webkit-backdrop-filter:blur(22px) saturate(170%); backdrop-filter:blur(22px) saturate(170%); border:1px solid var(--border); box-shadow:var(--shadow-sm)}
.bar.scrolled{background:var(--glass-bright)}
.brand{display:flex; align-items:center; gap:9px; padding:0 6px; font-size:14px; font-weight:800; letter-spacing:-.01em}
.brand .ic{width:22px;height:22px; color:var(--accent); flex:0 0 auto}
.nav-spacer{flex:1}
.back-link{display:inline-flex; align-items:center; gap:6px; height:38px; padding:0 14px; border-radius:12px; font-size:13px; font-weight:600; color:var(--muted); transition:background .2s,color .2s}
.back-link:hover{background:var(--hover); color:var(--fg)}
.theme-btn{width:38px;height:38px; border-radius:12px; display:grid; place-items:center; color:var(--muted); transition:background .2s,color .2s}
.theme-btn:hover{background:var(--hover); color:var(--fg)}
.theme-btn svg{width:17px;height:17px}
main{padding-top:96px; padding-bottom:70px; position:relative; z-index:10; max-width:820px; margin:0 auto; padding-left:20px; padding-right:20px}
.art-head{margin-bottom:40px}
.art-meta{display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px}
.art-meta .pill{display:inline-flex; align-items:center; height:26px; padding:0 12px; border-radius:99px; font-size:12px; font-weight:700; background:var(--accent-soft); color:var(--accent)}
.art-meta .sep{width:4px;height:4px;border-radius:50%;background:var(--faint)}
.art-meta span{font-size:13px; color:var(--faint)}
.art-head h1{font-size:clamp(30px,4.5vw,46px); font-weight:800; letter-spacing:-.03em; line-height:1.2; margin-bottom:18px}
.art-cover{width:100%; border-radius:var(--r-md); margin-bottom:36px; object-fit:cover; max-height:440px; border:1px solid var(--border)}
.art-body{font-size:16.5px; line-height:1.9; color:var(--fg)}
.art-body h2{font-size:24px; font-weight:800; margin:36px 0 14px; letter-spacing:-.01em}
.art-body h3{font-size:19px; font-weight:700; margin:28px 0 12px}
.art-body p{margin:0 0 18px}
.art-body ul,.art-body ol{margin:0 0 18px; padding-left:24px}
.art-body li{margin-bottom:8px}
.art-body a{color:var(--accent); border-bottom:1px solid var(--accent-soft)}
.art-body img{max-width:100%; border-radius:14px; margin:18px 0}
.art-body blockquote{border-left:3px solid var(--accent); padding:4px 0 4px 18px; margin:20px 0; color:var(--muted); background:var(--accent-soft); border-radius:0 12px 12px 0; padding:14px 18px}
.art-body pre{background:var(--surface-strong); border:1px solid var(--border); border-radius:14px; padding:18px; overflow-x:auto; font-family:var(--font-mono); font-size:13px; line-height:1.7; margin:18px 0}
.art-body code{font-family:var(--font-mono); font-size:.92em; background:var(--hover); padding:2px 6px; border-radius:6px}
.art-body pre code{background:none; padding:0}
.art-body table{width:100%; border-collapse:collapse; margin:18px 0; font-size:14.5px}
.art-body th{text-align:left; padding:10px 12px; border-bottom:2px solid var(--border-strong); font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:var(--faint)}
.art-body td{padding:10px 12px; border-bottom:1px solid var(--border)}
.art-body hr{border:0; border-top:1px solid var(--border); margin:32px 0}
.art-actions{display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:36px 0; padding-top:24px; border-top:1px solid var(--border)}
.act-btn{display:inline-flex; align-items:center; gap:7px; height:38px; padding:0 16px; border-radius:99px; border:1px solid var(--border); background:var(--surface-strong); font-size:13px; font-weight:600; color:var(--muted); cursor:pointer; transition:background .2s,color .2s,border-color .2s}
.act-btn:hover{background:var(--hover); color:var(--fg)}
.act-btn.liked{background:var(--accent); color:var(--on-accent); border-color:transparent}
.act-btn svg{width:16px;height:16px}
.art-tags{display:flex; flex-wrap:wrap; gap:8px; margin:20px 0 36px}
.art-tags a{font-size:12.5px; color:var(--muted); padding:6px 14px; border-radius:99px; border:1px solid var(--border); transition:.2s}
.art-tags a:hover{border-color:var(--accent); color:var(--accent)}
.related{margin-top:48px}
.related h2{font-size:22px; font-weight:800; margin-bottom:20px}
.related .r-grid{display:grid; gap:14px; grid-template-columns:repeat(auto-fill,minmax(240px,1fr))}
.related .r-card{display:block; padding:20px; border:1px solid var(--border); border-radius:var(--r-md); background:var(--surface); transition:transform .25s var(--ease-spring), box-shadow .25s, border-color .25s}
.related .r-card:hover{transform:translateY(-3px); box-shadow:var(--shadow); border-color:var(--border-strong)}
.related .r-card h3{font-size:15px; font-weight:700; line-height:1.45; margin-bottom:8px}
.related .r-card p{font-size:12.5px; color:var(--faint)}
.nl-box{margin-top:48px; padding:28px; border-radius:var(--r-lg); background:linear-gradient(135deg,var(--accent-soft),transparent); border:1px solid var(--border); text-align:center}
.nl-box h3{font-size:20px; font-weight:800; margin-bottom:8px}
.nl-box p{font-size:13.5px; color:var(--muted); margin-bottom:16px}
.nl-box form{display:flex; gap:8px; max-width:400px; margin:0 auto}
.nl-box input{flex:1; height:42px; padding:0 16px; border:1px solid var(--border); border-radius:99px; background:var(--surface-strong); font-size:14px; outline:none}
.nl-box input:focus{border-color:var(--accent)}
.nl-box button{height:42px; padding:0 20px; border-radius:99px; background:var(--accent); color:var(--on-accent); font-weight:700; font-size:14px; cursor:pointer; border:0}
.foot{margin-top:70px; padding:56px 20px 40px; background:var(--surface-strong); border-top:1px solid var(--border)}
.foot .f-in{max-width:1100px; margin:0 auto; display:grid; gap:36px; grid-template-columns:1.4fr repeat(3,1fr)}
.foot h4{font-size:13px; font-weight:700; color:var(--fg); margin-bottom:14px}
.foot a{display:block; font-size:13px; color:var(--muted); margin-bottom:10px; transition:color .2s}
.foot a:hover{color:var(--fg)}
.foot .brand{display:flex; align-items:center; gap:9px; font-weight:800; font-size:16px; margin-bottom:12px}
.foot .brand .ic{width:22px;height:22px;color:var(--accent)}
.foot .f-about{font-size:13px; color:var(--faint); line-height:1.7; max-width:280px}
.foot .f-bottom{margin-top:40px; padding-top:20px; border-top:1px solid var(--border); display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; font-size:12px; color:var(--faint)}
@media(max-width:640px){.foot .f-in{grid-template-columns:1fr 1fr}}
.ambient{position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden}
.blob{position:absolute; border-radius:50%; filter:blur(72px)}
.blob-a{width:52vw;height:52vw; left:-10vw; top:-14vh; background:radial-gradient(circle,var(--blob-a),transparent 65%)}
.blob-b{width:44vw;height:44vw; right:-8vw; top:14vh; background:radial-gradient(circle,var(--blob-b),transparent 65%)}
.blob-c{width:40vw;height:40vw; left:24vw; bottom:-22vh; background:radial-gradient(circle,var(--blob-c),transparent 65%)}
</style>
</head>
<body>
<div class="ambient" aria-hidden="true"><div class="blob blob-a"></div><div class="blob blob-b"></div><div class="blob blob-c"></div></div>

<header id="chrome">
  <div class="bar" id="bar">
    <a class="brand" href="index.html"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>Open Flow</a>
    <div class="nav-spacer"></div>
    <a class="back-link" href="articles">← 返回文章</a>
    <button class="theme-btn" id="themeBtn" aria-label="切换主题"></button>
  </div>
</header>

<main>
  <?php if ($notFound): ?>
  <div style="text-align:center;padding:80px 0">
    <div style="font-size:60px;margin-bottom:20px">📄</div>
    <h1 style="font-size:28px;font-weight:800;margin-bottom:12px">文章不存在</h1>
    <p style="color:var(--muted);margin-bottom:24px">这篇文章可能已被删除或链接有误。</p>
    <a href="articles" class="act-btn" style="background:var(--accent);color:var(--on-accent);border:0;padding:12px 24px">返回文章列表</a>
  </div>
  <?php else: ?>
  <div class="art-head">
    <div class="art-meta">
      <?php if ($catName): ?><span class="pill"><?=htmlspecialchars($catName)?></span><?php endif; ?>
      <a href="/author/<?=urlencode($article['author'] ?? 'OpenFlow')?>" style="color:var(--accent)"><?=htmlspecialchars($article['author'] ?? 'OpenFlow')?></a>
      <span class="sep"></span>
      <span><?=htmlspecialchars(substr($article['created_at'] ?? '', 0, 10))?></span>
      <span class="sep"></span>
      <span><?=$readMins?> 分钟阅读</span>
    </div>
    <h1><?=htmlspecialchars($article['title'] ?? '')?></h1>
  </div>

  <?php if ($cover): ?><img class="art-cover" src="<?=htmlspecialchars($coverUrl)?>" alt="<?=htmlspecialchars($article['title'] ?? '')?>" loading="lazy"><?php endif; ?>

  <?php if (function_exists('ads_render')): ?><div style="margin-bottom:24px"><?=ads_render('article_top')?></div><?php endif; ?>

  <div class="art-body">
    <?php if ($memberGate): ?>
      <div style="padding:40px;text-align:center;border:1px solid var(--border);border-radius:var(--r-lg);background:var(--surface-strong);margin:20px 0">
        <div style="font-size:44px;margin-bottom:16px">💎</div>
        <h2 style="font-size:22px;font-weight:800;margin-bottom:10px">这是一篇会员专享文章</h2>
        <p style="color:var(--muted);font-size:14px;margin-bottom:20px">开通会员即可阅读全文</p>
        <a href="member.php?view=subscribe" class="act-btn" style="background:var(--accent);color:var(--on-accent);border:0;padding:12px 28px">开通会员 →</a>
      </div>
    <?php else: ?>
      <?=article_render($content)?>
    <?php endif; ?>
  </div>

  <?php if (function_exists('ads_render')): ?><div style="margin-top:24px"><?=ads_render('article_bottom')?></div><?php endif; ?>

  <div class="art-actions">
    <button class="act-btn" id="likeBtn"><?=htmlspecialchars((int)($artStats['likes'] ?? 0))?> 赞</button>
    <button class="act-btn" id="favBtn">收藏</button>
    <button class="act-btn" id="shareBtn">分享</button>
    <button class="act-btn" id="posterBtn" title="生成分享海报">🎨 生成海报</button>
    <button class="act-btn" id="viewBtn"><?=number_format((int)($artStats['views'] ?? 0))?> 阅读</button>
  </div>

  <?php if (!empty($article['tags'])): ?>
  <div class="art-tags">
    <?php foreach ($article['tags'] as $t): ?><a href="articles"># <?=htmlspecialchars($t)?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($related)): ?>
  <div class="related">
    <h2>相关阅读</h2>
    <div class="r-grid">
      <?php foreach ($related as $r): ?>
      <a class="r-card" href="/article/<?=htmlspecialchars($r['a']['slug'])?>">
        <h3><?=htmlspecialchars($r['a']['title'])?></h3>
        <p><?=htmlspecialchars(substr($r['a']['created_at'] ?? '', 0, 10))?></p>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="related" id="personalizedRecs" style="display:none">
    <h2>🎯 猜你喜欢</h2>
    <div class="r-grid" id="personalizedRecsGrid"></div>
  </div>
  <script>
  (function(){
    fetch('/api/recommend.php?type=articles&limit=3&exclude=<?=htmlspecialchars($article['id'] ?? '')?>', {credentials:'include'})
      .then(function(r){return r.json();})
      .then(function(d){
        if (!d.ok || !d.recommendations || d.recommendations.length === 0) return;
        var box = document.getElementById('personalizedRecs');
        var grid = document.getElementById('personalizedRecsGrid');
        d.recommendations.forEach(function(a){
          var el = document.createElement('a');
          el.className = 'r-card';
          el.href = a.url;
          var h = document.createElement('h3'); h.textContent = a.title;
          var p = document.createElement('p'); p.textContent = (a.category || '') + ' · ' + (a.tags || []).slice(0,2).join(' ');
          el.appendChild(h); el.appendChild(p);
          grid.appendChild(el);
        });
        box.style.display = '';
      });
  })();
  </script>
  <?php endif; ?>

  <div class="nl-box">
    <h3>✉️ 订阅内容更新</h3>
    <p>每周获取网站增长与 AI 运营最新洞察，绝无打扰。</p>
    <form onsubmit="return ofNewsletter(this,event)">
      <input type="email" placeholder="你的邮箱" required>
      <button type="submit">订阅</button>
    </form>
  </div>
  <?php endif; ?>
</main>

<footer class="foot">
  <div class="f-in">
    <div>
      <div class="brand"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/></svg></span>芭乐派 · OpenFlow</div>
      <p class="f-about">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
      <p class="note">核心能力永久开源 · 鱼与渔相结合</p>
    </div>
    <div><h4>站点导航</h4><a href="/product">产品</a><a href="/capability">能力</a><a href="/courses">课程</a><a href="/academy">学院</a><a href="/community">门派社区</a><a href="/about">关于我们</a></div>
    <div><h4>资源</h4><a href="/courses">芭乐派课程</a><a href="/docs">文档中心</a><a href="/downloads">模板库</a><a href="/academy">内容学院</a></div>
    <div><h4>联系</h4><a href="mailto:hello@openflow.dev">hello@openflow.dev</a><a href="/login">管理后台</a><a href="/community">门派社区</a></div>
  </div>
  <div class="f-bottom"><span>© 2026 芭乐派 · OpenFlow 增长操作系统</span><span>帮一人公司设计 Agent 能跑的增长系统</span></div>
</footer>

<script>
var OF_SLUG = <?=json_encode($slug)?>;
/* 内容浏览 → CDP 事件 + 行为触发 */
if (window.fcTrack) { try { fcTrack('article_view', { slug: OF_SLUG, category: <?=json_encode($article['category'] ?? '')?>, title: document.title }); } catch (e) {} }
function ofNewsletter(f,e){e.preventDefault();var em=f.querySelector('input').value;fetch('/api/newsletter.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:em,source:'article'})}).then(function(r){return r.json();}).then(function(d){var b=f.querySelector('button');b.textContent=d.ok?'✅ 已订阅':'⚠️ '+(d.error||'失败');});return false;}
function ofStat(action){return fetch('/api/article-stats.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:action,slug:OF_SLUG})}).then(function(r){return r.json();});}
var liked=false;
document.getElementById('likeBtn').addEventListener('click',function(){
  if(!liked){liked=true;ofStat('like').then(function(d){document.getElementById('likeBtn').textContent=(d.stats?d.stats.likes:0)+' 赞';});this.classList.add('liked');}
  else{liked=false;ofStat('like').then(function(d){document.getElementById('likeBtn').textContent=(d.stats?d.stats.likes:0)+' 赞';});this.classList.remove('liked');}
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
// 主题切换
(function(){var d=document.documentElement,b=document.getElementById('themeBtn');function render(){var dark=d.dataset.theme==='dark';b.innerHTML=dark?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/></svg>':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5Z"/></svg>';}render();b.addEventListener('click',function(){d.dataset.theme=d.dataset.theme==='dark'?'light':'dark';try{var s=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');s.theme=d.dataset.theme;localStorage.setItem('openflow-site-v3',JSON.stringify(s));}catch(e){}render();});})();
var bar=document.getElementById('bar');
window.addEventListener('scroll',function(){bar.classList.toggle('scrolled',window.scrollY>24);},{passive:true});
</script>
</body>
</html>
