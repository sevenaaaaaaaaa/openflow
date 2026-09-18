<?php
/**
 * 页面故事线审计 —— docs/PAGE-STORYLINES.md §七 的可复现工具
 *
 * 干什么：把每个页面（PHP 页 + builder 页）的真实段落序列映射成「本体」，
 *         对照命名故事线检查四类漂移：
 *           ① 缺失必需本体（如产品页没有收口 CTA / FAQ）
 *           ② 出现禁用本体（命中该页面类型的「不用清单」）
 *           ③ 本体重复（同一本体 ≥2 段，装饰类不计）
 *           ④ primary CTA 数 >1（违反 docs/BRAND-VOICE.md）
 *
 * 用法：
 *   php scripts/storyline-audit.php            # 全量报告
 *   php scripts/storyline-audit.php product    # 只看某页（模糊匹配文件名/slug）
 *   php scripts/storyline-audit.php --json     # 机器可读
 *
 * 退出码：0 = 无 ERROR；1 = 有 ERROR（可用于 CI / pre-commit）
 */

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/BlockRegistry.php';

const E = 'ERROR';
const W = 'WARN';

/** 区块 type → 本体（首选；同名区块只归一个本体） */
const BLOCK_ROLE = [
    'hero' => '首屏', 'hero-deck' => '首屏', 'deck' => '首屏', 'banner' => '装饰',
    'cluster' => '多栏特性区', 'features' => '多栏特性区', 'bento' => '多栏特性区',
    'portrait' => '多栏特性区', 'text' => '多栏特性区', 'checklist' => '多栏特性区',
    'tabs' => '能力Tab',
    'tool-grid' => '卡片网格', 'gallery' => '卡片网格',
    'feature-detail' => '特性大卡', 'image-text' => '特性大卡',
    'showcase' => '动态图文', 'video' => '动态图文', 'canvas-wall' => '动态图文', 'marquee' => '装饰',
    'form' => '试用输入', 'diagnose' => '试用输入', 'prompt' => '试用输入', 'newsletter' => '试用输入',
    'proof' => '证明', 'stats' => '证明', 'ticker' => '证明', 'logo-wall' => '证明',
    'cmp' => '对比', 'comparison' => '对比', 'before-after' => '对比',
    'journey' => '步骤', 'timeline' => '步骤', 'runlog' => '步骤',
    'quote-wall' => '证言', 'testimonials' => '证言',
    'accordion' => 'FAQ', 'faq' => 'FAQ',
    'blog-grid' => '内容簇', 'changelog' => '内容簇',
    'cta' => '收口CTA', 'countdown' => '限时', 'pricing' => '定价',
    'team' => '人物', 'contact' => '试用输入',
    'code' => '装饰', 'kbd' => '装饰', 'spotlight' => '装饰',
];

/** 页面类型：必需本体 / 禁用本体 */
const PAGE_TYPES = [
    'home'       => ['name' => '首页',        'require' => ['首屏'], 'require_any' => ['收口CTA', '试用输入'], 'forbid' => ['定价']],
    'product'    => ['name' => '产品页',      'require' => ['首屏', '证言', 'FAQ', '收口CTA'],      'forbid' => ['试用输入']],
    'capability' => ['name' => '能力页',      'require' => ['首屏', '能力Tab', 'FAQ', '收口CTA'],   'forbid' => ['定价']],
    'matrix'     => ['name' => '矩阵聚合页',  'require' => ['首屏', '收口CTA'], 'require_any' => ['卡片网格', '多栏特性区'], 'forbid' => []],
    'landing'    => ['name' => '落地页',      'require' => ['首屏', '试用输入', '收口CTA'],         'forbid' => ['定价']],
    'course'     => ['name' => '课程页',      'require' => ['首屏', '收口CTA'],                    'forbid' => []],
    'pricing'    => ['name' => '定价页',      'require' => ['首屏', '定价', 'FAQ', '收口CTA'],      'forbid' => []],
    'topic'      => ['name' => '内容枢纽',    'require' => ['首屏'],                               'forbid' => ['定价']],
    'directory'  => ['name' => '工具目录',    'require' => ['首屏', '卡片网格', '收口CTA'],         'forbid' => ['定价']],
    'unknown'    => ['name' => '未分类',      'require' => [],                                     'forbid' => []],
];

/** PHP 页 section id 关键词 → 本体（启发式，按最长关键词优先） */
const ID_ROLE = [
    'hero' => '首屏', 'top' => '首屏', 'head' => '首屏', 'topic-head' => '装饰', 'banner' => '装饰', 'box' => '卡片网格',
    'pain' => '多栏特性区', 'story' => '多栏特性区', 'founder' => '人物', 'team' => '人物',
    'principles' => '多栏特性区', 'thinking' => '内容簇', 'timeline' => '步骤', 'join' => '试用输入',
    'proof' => '证明', 'stats' => '证明', 'metrics' => '证明', 'value' => '证明',
    'touch' => '能力Tab', 'tips' => '能力Tab', 'caps' => '能力Tab', 'features' => '特性大卡',
    'toolbox' => '卡片网格', 'publish' => '收口CTA',
    'index' => 'FAQ', 'modules' => 'FAQ', 'connectors' => '证明', 'integrations' => '证明',
    'deploy' => '多栏特性区', 'open' => '多栏特性区', 'scenes' => '多栏特性区', 'fit' => '多栏特性区',
    'real' => '图库', 'gallery' => '图库', 'works' => '图库', 'carousel' => '图库', 'waterfall' => '图库',
    'loop' => '步骤', 'path' => '步骤', 'nav' => '卡片网格', 'catalog' => '卡片网格',
    'latest' => '内容簇', 'stream' => '内容簇', 'topics' => '内容簇', 'library' => '内容簇',
    'compare' => '对比', 'plans' => '定价', 'compare' => '对比',
    'reviews' => '证言', 'voices' => '证言', 'insights' => '内容簇', 'blog' => '内容簇',
    'faq' => 'FAQ', 'start' => 'FAQ', 'contact' => '试用输入', 'apply' => '试用输入',
    'book' => '试用输入', 'form' => '试用输入', 'list' => '卡片网格', 'browse' => '卡片网格',
    'cta' => '收口CTA',
];

function role_of_block(string $type): string { return BLOCK_ROLE[$type] ?? '装饰'; }

function role_of_id(string $id): string {
    $best = '';
    foreach (ID_ROLE as $kw => $role) {
        if (stripos($id, $kw) !== false && strlen($kw) > strlen($best)) $best = $kw;
    }
    return $best ? ID_ROLE[$best] : '装饰';
}

function classify(string $name): string {
    $n = strtolower($name);
    // 七个产品 + 独立产品页 slug（builder 页用 slug 命名）
    if (preg_match('/^(mflow|webs-flow|userloop|inflow|payflow|learnflow|openflow|in-flow|webs-flow)$/', $n)) return 'product';
    if ($n === 'growth-os-tour' || str_contains($n, 'tour')) return 'landing';
    if ($n === 'landing') return 'directory';
    if (preg_match('/^navigation-(compare|site)$/', $n)) return 'unknown';
    if ($n === 'hub-capabilities') return 'capability';
    if ($n === 'hub-products') return 'matrix';
    foreach (['product', 'capability', 'courses', 'course', 'pricing', 'articles', 'article',
              'topics', 'tools', 'navigation', 'marketplace', 'landing', 'index', 'home',
              'demo', 'hub-', 'author', 'asset', 'downloads', 'academy'] as $kw) {
        if (str_contains($n, $kw)) {
            return match (true) {
                str_contains($n, 'pricing') => 'pricing',
                str_contains($n, 'course') => 'course',
                str_contains($n, 'demo') || str_contains($n, 'hub-') => 'matrix',
                str_contains($n, 'capability') => 'capability',
                str_contains($n, 'landing') => 'landing',
                str_contains($n, 'product') => 'product',
                str_contains($n, 'article') || str_contains($n, 'topic') => 'topic',
                str_contains($n, 'tool') || str_contains($n, 'navigation') || str_contains($n, 'marketplace') => 'directory',
                str_contains($n, 'index') || str_contains($n, 'home') => 'home',
                default => 'unknown',
            };
        }
    }
    return 'unknown';
}

$SKIP = ['member', 'messages', 'activate', 'download', 'thank-you', 'search', 'asset', 'article',
         'course-player', 'community-post', 'nav-submit', 'navigation-compare', 'navigation-site',
         'live', 'seo-board', 'consultation', 'checkout'];

$filter = null; $asJson = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--json') $asJson = true; else $filter = $a;
}

$findings = [];   // slug => [['level','code','msg']]
$seqs = [];

/* ── 1) builder 页 ── */
$bp = $ROOT . '/data/builder-pages.json';
if (is_file($bp)) {
    $data = json_decode((string) file_get_contents($bp), true) ?: [];
    foreach ($data as $p) {
        $slug = $p['slug'] ?? '?';
        if ($filter && stripos($slug, $filter) === false) continue;
        $roles = [];
        foreach (($p['blocks'] ?? []) as $b) {
            $t = (string) ($b['_type'] ?? '');
            $title = (string) ($b['title'] ?? '') . ' ' . (string) ($b['subtitle'] ?? '');
            if ($t === 'tabs' && (str_contains($title, '部署') || str_contains($title, '方式'))) { $roles[] = '定价'; continue; }
            if ($t === 'tabs' && (str_contains($title, '场景') || str_contains($title, '人群'))) { $roles[] = '多栏特性区'; continue; }
            $roles[] = role_of_block($t);
        }
        $seqs[$slug] = $roles;
        $pt = classify($slug);
        $findings[$slug] = ['type' => $pt, 'roles' => $roles];
    }
}

/* ── 2) PHP 页面 ── */
foreach (glob($ROOT . '/*.php') as $file) {
    $name = basename($file, '.php');
    if (in_array($name, ['hub-products', 'hub-capabilities'], true)) { /* 独立页也审 */ }
    if ($filter && stripos($name, $filter) === false) continue;
    if (!$filter && in_array($name, $SKIP, true)) continue;
    $src = (string) file_get_contents($file);
    if (!preg_match_all('/<section[^>]*?data-od-id="([^"]+)"/', $src, $m)) continue;
    $roles = array_map('role_of_id', $m[1]);
    if (preg_match('/<h1[\s>]/', $src) && !in_array('首屏', $roles, true)) array_unshift($roles, '首屏');
    $key = $name . '.php';
    $seqs[$key] = $roles;
    $findings[$key] = ['type' => classify($name), 'roles' => $roles];
    $body = $src;
    if (preg_match('/<main[^>]*>(.*)<\/main>/s', $src, $mm)) $body = $mm[1];
    $secs = preg_split('/<section/', $body);
    $findings[$key]['primary_cta'] = count(array_filter($secs, fn($x) => str_contains($x, 'btn primary')));
}

/* ── 3) 判定 ── */
$report = [];
foreach ($findings as $page => $info) {
    $pt = $info['type'];
    $spec = PAGE_TYPES[$pt] ?? PAGE_TYPES['unknown'];
    $roles = array_values(array_filter($info['roles'], fn($r) => $r !== '装饰' && $r !== ''));
    $issues = [];

    foreach ($spec['require'] as $need) {
        if (!in_array($need, $roles, true)) $issues[] = [E, 'MISSING', "缺必需本体：{$need}"];
    }
    if (!empty($spec['require_any'])) {
        $hit = array_intersect($spec['require_any'], $roles);
        if (!$hit) $issues[] = [E, 'MISSING', '缺收口（需其一）：' . implode(' / ', $spec['require_any'])];
    }
    // 顺序：收口 CTA 之后不应再有信息本体（装饰除外）
    $pos = array_keys($roles, '收口CTA', true);
    if ($pos) {
        $after = array_slice($roles, end($pos) + 1);
        if ($after) $issues[] = [W, 'ORDER', '收口 CTA 之后仍有段落：' . implode(' → ', $after)];
    }
    foreach ($spec['forbid'] as $bad) {
        if (in_array($bad, $roles, true)) $issues[] = [W, 'FORBIDDEN', "出现禁用本体：{$bad}"];
    }
    // 只有「单例本体」重复才算漂移：多栏特性区/证明/内容簇/证言/步骤 允许多段
    $singleton = ['首屏', '收口CTA', '定价', '能力Tab', '对比'];
    $counts = array_count_values($roles);
    foreach ($counts as $role => $n) {
        if ($n >= 2 && in_array($role, $singleton, true)) $issues[] = [W, 'DUPLICATE', "本体重复 {$n} 次：{$role}"];
    }
    $ctaTypes = ['首页', '产品页', '能力页', '矩阵聚合页', '落地页', '定价页'];
    if (($info['primary_cta'] ?? 0) > 1 && in_array($spec['name'], $ctaTypes, true)) {
        $issues[] = [W, 'MULTI_CTA', "{$info['primary_cta']} 段各带一个主 CTA（品牌规范：一页一个）"];
    }

    $report[$page] = ['type' => $spec['name'], 'roles' => $roles, 'issues' => $issues];
}

/* ── 4) 输出 ── */
if ($asJson) { echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n"; exit(0); }

$errs = 0; $warns = 0;
echo "═══ 页面故事线审计（docs/PAGE-STORYLINES.md）═══\n\n";
foreach ($report as $page => $r) {
    $tag = $r['issues'] ? (array_reduce($r['issues'], fn($c, $i) => $c || $i[0] === E, false) ? '✗' : '△') : '✓';
    printf("%s %-26s [%s]  %s\n", $tag, $page, $r['type'], implode(' → ', $r['roles']));
    foreach ($r['issues'] as [$lv, $code, $msg]) {
        if ($lv === E) $errs++; else $warns++;
        printf("    %s %-10s %s\n", $lv === E ? '·' : '·', $code, $msg);
    }
}
printf("\n合计：ERROR %d · WARN %d · 页面 %d\n", $errs, $warns, count($report));
exit($errs > 0 ? 1 : 0);
