<?php
/**
 * SEO 内容健康分 + 内链引擎 契约
 *   php tests/seo_audit_test.php
 */

require_once __DIR__ . '/../lib/SeoAudit.php';

// stubs：文章集合
$GLOBALS['ARTICLES'] = [
    ['id' => 'a1', 'title' => '增长系统入门', 'slug' => 'growth-intro', 'status' => 'published', 'tags' => ['增长', 'SEO']],
    ['id' => 'a2', 'title' => '内容运营实战', 'slug' => 'content-ops', 'status' => 'published', 'tags' => ['内容']],
];
function get_articles_list(): array { return $GLOBALS['ARTICLES']; }
function get_article(string $id): ?array { foreach ($GLOBALS['ARTICLES'] as $a) if ($a['id'] === $id) return $a; return null; }
function save_article(array $a): bool { foreach ($GLOBALS['ARTICLES'] as $i => $x) if ($x['id'] === $a['id']) { $GLOBALS['ARTICLES'][$i] = $a; return true; } return false; }

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "SEO 内容健康分 + 内链\n";

// 健康分：优秀 vs 差
$good = [
    'title' => '增长系统入门：从流量思维到价值复利', 'seo_desc' => str_repeat('这是一段合格的 SEO 描述，用于说明这篇文章要讲什么。', 2),
    'slug' => 'growth-intro', 'seo_keywords' => '增长',
    'content' => '<h2>一</h2><h2>二</h2>' . str_repeat('增长系统需要内容与流程协同，这是一段足够长的正文。', 30)
        . '<a href="/article/content-ops">内容运营</a><a href="/article/x">x</a><a href="/article/y">y</a><img src="/a.png" alt="图">',
];
$rg = seo_audit_article($good);
check('优质文章得高分', $rg['score'] >= 85, (string)$rg['score']);

$bad = ['title' => '短', 'seo_desc' => '', 'slug' => '', 'content' => '<p>太短</p>'];
$rb = seo_audit_article($bad);
check('劣质文章得低分', $rb['score'] < 60, (string)$rb['score']);
$badKeys = array_column(array_filter($rb['checks'], fn($c) => !$c['pass']), 'key');
check('劣质文章暴露问题项', in_array('desc', $badKeys, true) && in_array('words', $badKeys, true) && in_array('internal', $badKeys, true) && in_array('slug', $badKeys, true));

// 内链计数（去重 URL）
$links = seo_internal_link_targets('<a href="/article/a">A</a><a href="/article/a">A2</a><a href="/article/b">B</a><a href="https://x.com">外</a>');
check('内链去重计数', count($links) === 2);

// anchor 内判定
$html = '前文 <a href="/x">增长</a> 后文 增长';
check('已链接位置识别', seo_pos_inside_anchor($html, mb_strpos($html, '增长')) === true);
check('未链接位置识别', seo_pos_inside_anchor($html, mb_strrpos($html, '增长')) === false);

// 内链建议：识别未链接提及
$content = '<p>我们在讲增长系统时，也会聊到内容运营。</p>';
$sug = seo_internal_link_suggestions($content, '');
$urls = array_column($sug, 'url');
check('识别未链接提及', in_array('/article/growth-intro', $urls, true) || in_array('/article/content-ops', $urls, true));
$first = $sug[0];
check('建议含 anchor 与标题', $first['anchor'] !== '' && $first['article_title'] !== '');

// 已链接 URL 不应再建议（标记 already_linked）
$content2 = '<p>增长系统入门 已经链接过：<a href="/article/growth-intro">增长系统入门</a></p>';
$sug2 = seo_internal_link_suggestions($content2, '');
$gi = null; foreach ($sug2 as $s) if ($s['url'] === '/article/growth-intro') $gi = $s;
check('已链接 URL 标记为 already_linked', $gi && $gi['already_linked'] === true);

// 一键插链：包第一处未链接提及
$GLOBALS['ARTICLES'][0]['content'] = '<p>先讲增长系统入门，再讲别的。</p>';
$r = seo_apply_internal_link('a1', '/article/content-ops', '增长系统入门');
check('一键插链成功', !empty($r['ok']));
check('插链后内容含锚点', strpos($GLOBALS['ARTICLES'][0]['content'], '<a href="/article/content-ops"') !== false);

// 已有链接时不再重复插
$GLOBALS['ARTICLES'][0]['content'] = '<p><a href="/article/content-ops">增长系统入门</a></p>';
$r2 = seo_apply_internal_link('a1', '/article/content-ops', '增长系统入门');
check('无未链接提及则拒绝', empty($r2['ok']));

// 结构守卫：hreflang x-default
$sh = file_get_contents(__DIR__ . '/../lib/SeoHead.php');
check('SeoHead 输出 x-default', strpos($sh, 'x-default') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
