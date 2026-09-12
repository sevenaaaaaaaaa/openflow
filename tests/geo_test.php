<?php
/**
 * GEO 专家基准 契约 —— 实体图 / 竞品引用 / 可引用改写
 *   php tests/geo_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-geo-' . getmypid());
@mkdir(DATA_DIR . '/geo', 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
function site_config_get(string $k) { return $k === 'site_name' ? 'OpenFlow' : ''; }
$GLOBALS['ARTICLES'] = [
    ['id' => 'a1', 'title' => '增长系统入门', 'slug' => 'growth', 'status' => 'published', 'author' => 'Seven', 'category' => '增长', 'tags' => ['SEO'],
     'content' => '<p>增长系统让转化率提升 40%，这是核心结论。</p><h2>怎么开始？</h2><p>先搭内容与流程。</p><h2>要多久？</h2><p>通常 30 天见效。</p><p>竞品 competitor.com 也做增长。</p>'],
];
function get_articles_list(): array { return $GLOBALS['ARTICLES']; }
function get_article(string $id): ?array { foreach ($GLOBALS['ARTICLES'] as $a) if ($a['id'] === $id) return $a; return null; }
function save_article(array $a): bool { foreach ($GLOBALS['ARTICLES'] as $i => $x) if ($x['id'] === $a['id']) { $GLOBALS['ARTICLES'][$i] = $a; return true; } return false; }
function geo_get_topics(): array { return [['topic' => '增长自动化', 'angle' => 'competitor.com 的替代方案']]; }

require_once __DIR__ . '/../lib/GeoEntities.php';
require_once __DIR__ . '/../lib/GeoCompetitor.php';
require_once __DIR__ . '/../lib/GeoCitable.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "GEO 专家基准\n";

// 实体图
$g = geo_entities_build();
$types = array_column($g['nodes'], 'type');
$names = array_column($g['nodes'], 'name');
check('实体图含品牌', in_array('brand', $types, true) && in_array('OpenFlow', $names, true));
check('实体图含人物(作者)', in_array('Seven', $names, true));
check('实体图含话题(分类/标签)', in_array('增长', $names, true) || in_array('SEO', $names, true));
check('实体图含共现关系', count($g['edges']) >= 1);
$rep = geo_entities_report();
check('实体报告按类型计数/取Top', ($rep['total'] ?? 0) >= 3 && !empty($rep['top_nodes']));

// 竞品引用
geo_competitor_add('competitor.com', '竞品A');
$rows = geo_competitor_scan();
$self = null; $comp = null;
foreach ($rows as $r) { if (!empty($r['self'])) $self = $r; if (($r['domain'] ?? '') === 'competitor.com') $comp = $r; }
check('竞品被统计到提及', $comp && $comp['mentions'] >= 1);
check('本品牌份额存在', $self && array_key_exists('share', $self));
$sumShare = array_sum(array_column($rows, 'share'));
check('份额合计约 100%', abs($sumShare - 100) < 1.5, (string)$sumShare);

// 可引用改写（规则兜底）
$blocks = geo_citable_rules($GLOBALS['ARTICLES'][0]);
check('抽取结论段', $blocks['answer'] !== '' && strpos($blocks['answer'], '增长系统') !== false);
check('抽取关键事实(含数字)', !empty($blocks['key_facts']) && strpos(implode('', $blocks['key_facts']), '40') !== false);
check('抽取 Q&A', count($blocks['qa']) >= 2 && $blocks['qa'][0]['q'] !== '');
$html = geo_citable_html($blocks);
check('渲染可引用块', strpos($html, 'geo-citable') !== false && strpos($html, '关键事实') !== false);
$ld = geo_citable_faq_jsonld($blocks['qa']);
check('FAQ 结构化数据', ($ld['@type'] ?? '') === 'FAQPage' && count($ld['mainEntity']) >= 2);

// 一键写回（幂等）
$r1 = geo_citable_apply('a1', $blocks);
check('写回成功', !empty($r1['ok']));
check('正文含可引用块', strpos($GLOBALS['ARTICLES'][0]['content'], 'geo-citable') !== false);
check('记录了 faq 结构化', !empty($GLOBALS['ARTICLES'][0]['geo_faq']));
$r2 = geo_citable_apply('a1', $blocks);
check('重复写回不叠加(幂等)', substr_count($GLOBALS['ARTICLES'][0]['content'], 'class="geo-citable"') === 1);

// 结构守卫：API 鉴权
$ep = file_get_contents(__DIR__ . '/../api/geo-citable.php');
check('可引用API要登录', strpos($ep, 'require_login(') !== false);
check('可引用API要 geo 权限', strpos($ep, "require_perm('geo')") !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
