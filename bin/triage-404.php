<?php
/**
 * 404 自动分诊 — 读 GSC/日志导出的 404 URL 清单，分类为：
 *   301 跳转 / 410 已移除 / 前端标记(待审) / 真 404(噪音)
 *
 *   php bin/triage-404.php <csv路径>            # 只分析，输出报告
 *   php bin/triage-404.php <csv路径> --apply    # 把高置信结果写进 redirects.json
 *
 * 安全：只对「当前确实 404 的精确路径」新增规则，绝不动现有路由；已有规则不重复写。
 */

require_once __DIR__ . '/../admin/config.php';

$csv = $argv[1] ?? '';
$apply = in_array('--apply', $argv, true);
if ($csv === '' || !is_file($csv)) { fwrite(STDERR, "用法: php bin/triage-404.php <csv> [--apply]\n"); exit(1); }

// ── 读 CSV（第一列为网址）──
$paths = [];
$fh = fopen($csv, 'r');
$first = true;
while (($row = fgetcsv($fh)) !== false) {
    if ($first) { $first = false; if (stripos((string)($row[0] ?? ''), 'http') === false) continue; }
    $u = trim((string)($row[0] ?? ''));
    if ($u === '' || stripos($u, 'http') !== 0) continue;
    $p = parse_url($u, PHP_URL_PATH);
    if ($p === null || $p === false) continue;
    $p = urldecode($p);
    if ($p === '') $p = '/';
    if (!mb_check_encoding($p, 'UTF-8')) $p = mb_convert_encoding($p, 'UTF-8', 'UTF-8');   // 丢弃无效字节，避免 json_encode 失败
    // 归一：去尾斜杠（根除外）
    if ($p !== '/') $p = rtrim($p, '/');
    $paths[$p] = ($paths[$p] ?? 0) + 1;
}
fclose($fh);

// ── 现有重定向（避免重复）──
function triage_existing(): array {
    $r = json_read(DATA_DIR . '/redirects.json');
    $out = [];
    foreach ((array)$r as $x) $out['/' . trim((string)($x['from'] ?? ''), '/')] = true;
    return $out;
}
$existingRedirects = triage_existing();

// ── 现有关键路径（不要跳转的目标）──
$livePrefixes = ['/article/', '/course/', '/course-player', '/downloads/', '/help/', '/tag/', '/navigation', '/community', '/docs', '/academy', '/tools', '/enterprise', '/live', '/marketplace'];

// ── 精确 301 映射（旧站路径 → 现站页面）──
$exactRedirects = [
    '/home' => '/', '/home/' => '/', '/en/home' => '/', '/en/home/' => '/', '/en' => '/',
    '/acad' => '/academy', '/flow-community' => '/community', '/forums' => '/community', '/forums-3' => '/community',
    '/download' => '/downloads', '/api-docs.php' => '/docs', '/docs/docs' => '/docs',
    '/plugin/example-plugin' => '/docs',
    // 旧多语言根 → 首页（i18n 未启用时这些是死路径）
    '/de' => '/', '/fr' => '/', '/es' => '/', '/pt' => '/', '/ko' => '/', '/ar' => '/', '/zh-TW' => '/',
];

// ── 明显应 410 的旧结构/噪音（前缀或正则）。
//    注意：/api/、/author/、/category/ 是现站活路由，绝不列入。──
$goneRules = [
    '#^/wp-?#i', '#^/wordpress#i', '#^/user-sign#i', '#^/resources/#i',
    '#^/mcv_course/#i', '#^/(logicboard|rengine)#i', '#^/all(/|$)#i',
    '#^/page/\d#i', '#^/tag/[^/]+/page/#i', '#^/navigation-site\.php#i',
    '#^/(graphql|feed|manifest\.json|asset-manifest\.json|info\.php|phpinfo\.php|admin\.php|xmlrpc|config\.json)#i',
    '#^/(marketing-static|dist|debug|actuator|proxy|oauth)/#i', '#^/\.(env|git)#i',
    '#^/[a-z]{2}(?:-[A-Za-z]{2})?/api/#i',   // 旧多语言下的接口
    '#^/[0-9a-f]{32}$#i', '#^/\*#', '#^/wp-content#i', '#^/wp-admin#i', '#^/wp-json#i',
];

// ── 文章索引（用于给旧 .html 找对应）──
$articles = function_exists('get_articles_list') ? get_articles_list() : json_read(ARTICLES_DIR . '/index.json');
$artIndex = [];
foreach ($articles as $a) {
    $slug = (string)($a['slug'] ?? '');
    if ($slug === '') continue;
    $artIndex[] = ['slug' => $slug, 'tokens' => triage_tokens((string)($a['title'] ?? '') . ' ' . $slug . ' ' . implode(' ', (array)($a['tags'] ?? [])))];
}
function triage_tokens(string $s): array {
    $s = mb_strtolower($s);
    $toks = [];
    if (preg_match_all('/[a-z0-9]{3,}/', $s, $m)) foreach ($m[0] as $w) $toks[$w] = true;
    // 中文 2-gram
    if (preg_match_all('/[\x{4e00}-\x{9fff}]{2,}/u', $s, $m)) foreach ($m[0] as $w) for ($i = 0; $i + 2 <= mb_strlen($w); $i++) $toks[mb_substr($w, $i, 2)] = true;
    return array_keys($toks);
}
function triage_match(array $tokens, array $index): ?array {
    if (count($tokens) < 3) return null;
    $best = null; $bestScore = 0;
    foreach ($index as $a) {
        $inter = count(array_intersect($tokens, $a['tokens']));
        if ($inter === 0) continue;
        $score = $inter / max(1, min(count($tokens), count($a['tokens'])));
        if ($score > $bestScore) { $bestScore = $score; $best = $a; }
    }
    return ($best && $bestScore >= 0.6) ? ['slug' => $best['slug'], 'score' => round($bestScore, 2)] : null;
}

// ── 分类 ──
$buckets = ['redirect' => [], 'gone' => [], 'review' => [], 'ignore' => []];
foreach ($paths as $p => $hits) {
    if ($existingRedirects[$p] ?? false) { $buckets['ignore'][$p] = ['hits' => $hits, 'why' => '已有重定向']; continue; }
    // 精确映射
    if (isset($exactRedirects[$p])) { $buckets['redirect'][$p] = ['to' => $exactRedirects[$p], 'hits' => $hits, 'why' => '旧站路径映射']; continue; }
    // 明显 410
    foreach ($goneRules as $re) if (preg_match($re, $p)) { $buckets['gone'][$p] = ['hits' => $hits, 'why' => '旧结构/噪音']; continue 2; }
    // 旧 .html 文章
    if (preg_match('/\.html$/i', $p)) {
        $slugBase = mb_strtolower(preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}]+/u', ' ', basename($p, '.html')));
        $m = triage_match(triage_tokens($slugBase), $artIndex);
        if ($m) { $buckets['redirect'][$p] = ['to' => '/article/' . $m['slug'], 'hits' => $hits, 'why' => '旧文匹配(分' . $m['score'] . ')']; }
        else { $buckets['gone'][$p] = ['hits' => $hits, 'why' => '旧站外文/无对应']; }
        continue;
    }
    // 其余：看是否命中现站前缀（属于软 404 / 需前端处理）
    foreach ($livePrefixes as $lp) if (strpos($p, $lp) === 0) { $buckets['review'][$p] = ['hits' => $hits, 'why' => '现站前缀但 404（软404/参数）']; continue 2; }
    $buckets['review'][$p] = ['hits' => $hits, 'why' => '需人工判断'];
}

// ── 报告 ──
$report = ['generated_at' => date('Y-m-d H:i:s'), 'total_paths' => count($paths),
    'counts' => ['redirect' => count($buckets['redirect']), 'gone' => count($buckets['gone']), 'review' => count($buckets['review']), 'ignore' => count($buckets['ignore'])],
    'redirect' => $buckets['redirect'], 'gone' => $buckets['gone'], 'review' => $buckets['review']];
json_write(DATA_DIR . '/404-triage.json', $report);

echo "404 分诊：共 " . count($paths) . " 条唯一路径\n";
echo "  301 跳转：" . count($buckets['redirect']) . " · 410 移除：" . count($buckets['gone']) . " · 待审：" . count($buckets['review']) . " · 已有： " . count($buckets['ignore']) . "\n";

if ($apply) {
    $r = json_read(DATA_DIR . '/redirects.json');
    if (!is_array($r)) $r = [];
    $have = [];
    foreach ($r as $x) $have['/' . trim((string)($x['from'] ?? ''), '/')] = true;
    $added = 0;
    foreach ($buckets['redirect'] as $from => $info) {
        if (isset($have[$from])) continue;
        $r[] = ['from' => ltrim($from, '/'), 'to' => $info['to'], 'created' => date('Y-m-d H:i:s')];
        $added++;
    }
    foreach ($buckets['gone'] as $from => $info) {
        if (isset($have[$from])) continue;
        $r[] = ['from' => ltrim($from, '/'), 'to' => '', 'gone' => true, 'created' => date('Y-m-d H:i:s')];
        $added++;
    }
    json_write(DATA_DIR . '/redirects.json', $r);
    echo "已写入 redirects.json：新增 {$added} 条（301 " . count($buckets['redirect']) . " + 410 " . count($buckets['gone']) . "）\n";
}
echo "完整报告：data/404-triage.json\n";
