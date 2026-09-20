#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * 可用性审计（算出来的，不是感觉出来的）
 *
 *   php scripts/usability-audit.php [--json]
 *
 * 计算四类可核验证据：
 *   1) 页面在侧栏导航里的深度（以及"根本没有入口"的页面清单）
 *   2) 命令面板（⌘K）对后台页面的覆盖率
 *   3) 空态质量：列表页空的时候有没有给"下一步做什么"
 *   4) 后台页面对移动端的准备度（有无 media query / 视口自适应线索）
 *
 * 【为什么要绕权限】本地没有服务器真源的角色定义，导航会被权限过滤成空。
 * 这里临时造一个"全权限"角色（临时 OF_DATA_DIR），只为枚举结构，不碰任何真实数据。
 */

$ROOT = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/of-usability-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

require $ROOT . '/admin/config.php';
require_once $ROOT . '/includes/admin-nav.php';

// 全权限角色（仅用于枚举导航结构）
$reg = array_values(of_perm_registry());
json_write(DATA_DIR . '/roles.json', ['__audit_all' => ['label' => '审计全权', 'perms' => $reg]]);
$_SESSION = ['admin_login' => true, 'admin_role' => '__audit_all', 'admin_user' => 'audit'];

// ── 1. 导航结构：areas → groups → items(±subs) ──
$navPaths = [];      // path => ['label'=>, 'depth'=>1|2, 'area'=>]
foreach (admin_nav_build(true) as $area) {
    foreach ((array) ($area['groups'] ?? []) as $group) {
        foreach ((array) ($group['items'] ?? []) as $item) {
            $id = (string) ($item['id'] ?? '');
            $href = (string) ($item['href'] ?? ($id !== '' ? '/xmp/' . $id : ''));
            $path = (string) parse_url($href, PHP_URL_PATH);
            if ($path !== '' && !isset($navPaths[$path])) {
                $navPaths[$path] = ['label' => (string) ($item['label'] ?? $id), 'depth' => 1, 'area' => (string) ($area['label'] ?? '')];
            }
            foreach ((array) ($item['subs'] ?? []) as $sub) {
                $sid = (string) ($sub['id'] ?? '');
                if ($sid === '') continue;
                $sp = '/xmp/' . $sid;
                if (!isset($navPaths[$sp])) {
                    $navPaths[$sp] = ['label' => (string) ($sub['label'] ?? $sid), 'depth' => 2, 'area' => (string) ($area['label'] ?? '')];
                }
            }
        }
    }
}

// 后台页 → 期望路径（admin/foo.php → /xmp/foo；admin/index.php → /xmp/dashboard）
// 301 别名（.htaccess）与被 include 的片段都不算"真实页面"——
// 否则"⌘K 覆盖率"会因为别名而被虚高/虚低，指标就没意义了。
$aliases = [];
$htSrc = (string) @file_get_contents($ROOT . '/.htaccess');
if ($htSrc !== '' && preg_match_all('#^RewriteRule\s+\^xmp/([a-z0-9-]+)/\?\$\s+\S+\s+\[R=301#mi', $htSrc, $am)) {
    foreach ($am[1] as $a) $aliases[(string) $a] = true;
}
$pages = [];
$excludedAlias = 0;
$excludedFragment = 0;
foreach (glob($ROOT . '/admin/*.php') ?: [] as $f) {
    $base = basename($f, '.php');
    if (str_starts_with($base, '_')) { $excludedFragment++; continue; }
    if (in_array($base, ['config', 'login', 'logout'], true)) { $excludedFragment++; continue; }
    if (isset($aliases[$base])) { $excludedAlias++; continue; }
    $own = (string) @file_get_contents($f);
    if (!str_contains($own, 'admin_header(') && !str_contains($own, 'admin_footer(')) { $excludedFragment++; continue; }
    $pages[$base] = '/xmp/' . ($base === 'index' ? 'dashboard' : $base);
}

// ── 2. 入口来源：侧栏 / 命令面板 / 站内被链接 / 默认落地 ──
require_once $ROOT . '/lib/CommandPalette.php';
$cpPaths = [];
if (function_exists('cp_items')) {
    foreach (cp_items() as $it) {
        $p2 = (string) parse_url((string) ($it['url'] ?? ''), PHP_URL_PATH);
        if ($p2 !== '') $cpPaths[$p2] = true;
    }
}

// 站内被链接：扫后台与外壳代码里出现的 /xmp/<page>（胶囊导航、快速新建菜单、列表页里的行链接都算）
$haystack = '';
foreach (array_merge(glob($ROOT . '/admin/*.php') ?: [], glob($ROOT . '/includes/*.php') ?: [], [$ROOT . '/admin/config.php']) as $f) {
    $haystack .= "\n" . (string) @file_get_contents($f);
}
// 默认落地页
$landing = ['/xmp/dashboard', '/xmp/today'];

$entry = [];   // page => [sources]
foreach ($pages as $base => $path) {
    $srcs = [];
    if (isset($navPaths[$path])) $srcs[] = 'nav';
    if (isset($cpPaths[$path])) $srcs[] = 'palette';
    if (in_array($path, $landing, true)) $srcs[] = 'landing';
    // 代码里被引用（排除自身文件，避免自己链接自己造成假有入口）
    $probe = $haystack;
    $own = (string) @file_get_contents($ROOT . '/admin/' . $base . '.php');
    if ($own !== '') $probe = str_replace($own, '', $probe);
    if (preg_match('#' . preg_quote($path, '#') . '(?![-a-z0-9])#', $probe) === 1) $srcs[] = 'code-link';
    $entry[$base] = $srcs;
}
$withEntry = array_filter($entry, static fn(array $v): bool => $v !== []);
$navOnly = array_filter($entry, static fn(array $v): bool => $v === ['nav']);
$paletteOnly = array_filter($entry, static fn(array $v): bool => $v === ['palette']);
$orphan = array_keys(array_filter($entry, static fn(array $v): bool => $v === []));

// 清单里已经只剩"真实页面"，这里直接按孤岛分类
$fragments = [];
$orphanPages = $orphan;
$aliasPages = array_values(array_filter($orphanPages, static fn(string $b): bool => isset($aliases[$b])));
$orphanPages = array_values(array_filter($orphanPages, static fn(string $b): bool => !isset($aliases[$b])));

// 孤岛页面里再分：详情/编辑/导出这类本来就没入口 vs 看起来该有入口
$EXPLAINABLE = '/-(detail|edit|stats|students|trace|parts)$|^(demo-|debug$|safefix|migrate|provision|backup|export|import)/';
$orphanSuspicious = [];
$orphanTabLike = [];   // 像是某个 tab 页的独立版本（父页在侧栏，靠 ?tab= 进入）
foreach ($orphanPages as $b) {
    if (preg_match($EXPLAINABLE, $b) === 1) continue;
    $prefix = str_contains($b, '-') ? substr($b, 0, (int) strpos($b, '-')) : $b;
    $tabLike = false;
    foreach (array_keys($navPaths) as $np) if ($prefix !== '' && $prefix !== $b && str_contains($np, $prefix)) { $tabLike = true; break; }
    if ($tabLike) $orphanTabLike[] = $b; else $orphanSuspicious[] = $b;
}

// ── 3. 空态质量 + 4. 移动端准备度 ──
$emptyNoAction = [];
$emptyPrimary = [];   // 页面级空态（列表为空 = 死胡同，该给出路）
$emptyInline = [];    // 卡片里的一句话说明（解释"为什么暂时没有"，不该强加 CTA）
$emptyRefs = [];
$emptyWithAction = [];
$noEmptyState = [];
$noMediaQuery = [];
foreach ($pages as $base => $path) {
    $src = (string) @file_get_contents($ROOT . '/admin/' . $base . '.php');
    if ($src === '') continue;
    // 后台页是否处理窄屏
    if (!preg_match('/@media\s*\(/', $src) && !str_contains($src, 'max-width:') && !str_contains($src, 'overflow-x')) $noMediaQuery[] = $base;
    // 空态质量：优先看统一组件 empty_state()（标记 empty-next）——**必须真的带链接**，
    // 只有标记没有链接不算"给了出路"（防止为了指标好看只贴个标记）。
    // 三种写法都算：① 统一组件 empty_state() 且带了 CTA 参数（4 个参数）
    //                ② 手写的 empty-next 标记且块内有链接
    //                ③ 兜底：旧写法「暂无」附近 ±360 字符有链接/按钮
    $helperCalls = [];
    if (preg_match_all('/empty_state\(((?:[^()]|\([^()]*\))*)\)/s', $src, $hc)) $helperCalls = $hc[1];
    $helperWithCta = false;
    $helperInfoOnly = false;
    foreach ($helperCalls as $args) {
        $argc = substr_count($args, ',') + 1;
        if ($argc >= 4) $helperWithCta = true;      // 给了出路
        elseif ($argc === 2) $helperInfoOnly = true; // 作者明确判定"信息型"（解释为什么暂时没有）
    }
    $markerBlocks = [];
    if (preg_match_all('#<div class="empty empty-next">(.*?)</div>#s', $src, $mb)) $markerBlocks = $mb[1];
    if ($helperWithCta) {
        $emptyWithAction[] = $base;
    } elseif ($helperInfoOnly) {
        $emptyInline[] = $base;      // 信息型：不算缺出路（审计口径写进文档）
    } elseif ($markerBlocks !== []) {
        $hasLink = false;
        foreach ($markerBlocks as $blk) if (preg_match('/<a\s/', $blk)) { $hasLink = true; break; }
        if ($hasLink) $emptyWithAction[] = $base; else $emptyNoAction[] = $base;
    } elseif (preg_match_all('/(暂无|还没有|没有数据|尚无)[^<]{0,40}/u', $src, $m, PREG_OFFSET_CAPTURE)) {
        $has = false;
        foreach ($m[1] as [$_, $off]) {
            $win = substr($src, max(0, $off - 360), 720);
            if (preg_match('/<a\s|btn|href="/', $win)) { $has = true; break; }
        }
        if ($has) {
            $emptyWithAction[] = $base;
        } else {
            $emptyNoAction[] = $base;
            // 就近判定：220 字符窗口里是否是「表格空行 / .empty 容器」→ 页面级；否则是行内说明
            $primary = false;
            foreach ($m[1] as [$_, $off]) {
                // 看"最近的开始标签"：是 <td（表格空行）或带 empty/of-empty 的容器 → 页面级；
                // 否则（span / p.hint 等行内说明）→ 不算缺出路，不该强加 CTA
                $before = substr($src, max(0, $off - 240), 240);
                if (preg_match_all('/<([a-z]+)([^>]*)>/i', $before, $tags) && ($tags[0] ?? []) !== []) {
                    $tagName = strtolower((string) end($tags[1]));
                    $tagAttrs = (string) end($tags[2]);
                    if ($tagName === 'td' || str_contains($tagAttrs, 'empty')) $primary = true;
                }
            }
            if ($primary) $emptyPrimary[] = $base; else $emptyInline[] = $base;
        }
    } else {
        $noEmptyState[] = $base;
    }
}

$report = [
    'generated_at' => date('c'),
    'pages_total' => count($pages),
    'excluded' => ['alias_301' => $excludedAlias, 'fragments' => $excludedFragment],
    'nav' => [
        'areas' => count(admin_nav_build(true)),
        'items_depth1' => count(array_filter($navPaths, static fn(array $v): bool => $v['depth'] === 1)),
        'items_depth2' => count(array_filter($navPaths, static fn($v): bool => $v['depth'] === 2)),
    ],
    'entry' => [
        'with_entry' => count($withEntry),
        'orphan' => count($orphan),
        'orphan_suspicious' => count($orphanSuspicious),
        'orphan_tab_like' => count($orphanTabLike),
        'fragments_not_pages' => count($fragments),
        'alias_301' => count($aliasPages),
        'excluded_alias' => $excludedAlias,
        'excluded_fragments' => $excludedFragment,
        'nav_only' => count($navOnly),
        'palette_only' => count($paletteOnly),
        'palette_indexed' => count(array_filter($pages, static fn(string $p2): bool => isset($cpPaths[$p2]))),
    ],
    'empty_state' => [
        'with_next_action' => count($emptyWithAction),
        'without_next_action' => count($emptyNoAction),
        'primary_without_action' => count($emptyPrimary),
        'inline_hint' => count($emptyInline),
        'no_empty_state' => count($noEmptyState),
    ],
    'mobile_ready' => ['with_hint' => count($pages) - count($noMediaQuery), 'without_hint' => count($noMediaQuery)],
    'lists' => [
        'empty_primary_ranked' => (static function () use ($emptyPrimary, $haystack, $ROOT): array {
            $rows = [];
            foreach ($emptyPrimary as $b) {
                $path = '/xmp/' . $b;
                $own = (string) @file_get_contents($ROOT . '/admin/' . $b . '.php');
                $hay = $own !== '' ? str_replace($own, '', $haystack) : $haystack;
                $rows[] = ['page' => $b, 'refs' => (int) preg_match_all('#' . preg_quote($path, '#') . '(?![-a-z0-9])#', $hay)];
            }
            usort($rows, static fn(array $a, array $b): int => $b['refs'] <=> $a['refs']);
            return array_map(static fn(array $r): string => sprintf('%s (被引用 %d 次)', $r['page'], $r['refs']), $rows);
        })(),
        'empty_without_action_ranked' => (static function () use ($emptyNoAction, $haystack, $ROOT): array {
            $rows = [];
            foreach ($emptyNoAction as $b) {
                $path = '/xmp/' . $b;
                $own = (string) @file_get_contents($ROOT . '/admin/' . $b . '.php');
                $hay = $own !== '' ? str_replace($own, '', $haystack) : $haystack;
                $n = preg_match_all('#' . preg_quote($path, '#') . '(?![-a-z0-9])#', $hay);
                $rows[] = ['page' => $b, 'refs' => (int) $n];
            }
            usort($rows, static fn(array $a, array $b): int => $b['refs'] <=> $a['refs']);
            return array_map(static fn(array $r): string => sprintf('%s (被引用 %d 次)', $r['page'], $r['refs']), $rows);
        })(),
        'orphan' => array_map(static fn(string $b): string => $b . '  (' . $pages[$b] . ')', $orphan),
        'orphan_suspicious' => array_map(static fn(string $b): string => $b . '  (' . $pages[$b] . ')', $orphanSuspicious),
        'orphan_tab_like' => array_map(static fn(string $b): string => $b . '  (' . $pages[$b] . ')', $orphanTabLike),
        'alias_301' => array_map(static fn(string $b): string => $b . ' → ' . $aliases[$b] . '  (' . $pages[$b] . ')', $aliasPages),
        'palette_only' => array_map(static fn(string $b): string => $b . '  (' . $pages[$b] . ')', array_keys($paletteOnly)),
        'empty_without_action' => array_slice($emptyNoAction, 0, 60),
        'empty_without_action' => array_slice($emptyNoAction, 0, 60),
        'mobile_without_hint' => array_slice($noMediaQuery, 0, 60),
    ],
];

if (in_array('--json', $argv ?? [], true)) {
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
} else {
    $r = $report;
    printf("可用性审计（%d 个真实页面；另有 %d 个 301 别名与 %d 个片段文件不计入）\n\n",
        $r['pages_total'], $r['excluded']['alias_301'], $r['excluded']['fragments']);
    printf("① 侧栏导航：%d 个区 · 直达 %d · 二级 %d\n", $r['nav']['areas'], $r['nav']['items_depth1'], $r['nav']['items_depth2']);
    printf("   有入口 %d · 孤岛 %d（可疑 %d · 疑似 tab 页的独立版 %d · 301 旧地址别名 %d · 片段/工具非页面 %d）\n",
        $r['entry']['with_entry'], $r['entry']['orphan'], $r['entry']['orphan_suspicious'],
        $r['entry']['orphan_tab_like'], $r['entry']['alias_301'], $r['entry']['fragments_not_pages']);
    printf("   入口来源：仅侧栏 %d · 仅命令面板 %d · ⌘K 索引覆盖 %d/%d 页\n",
        $r['entry']['nav_only'], $r['entry']['palette_only'], $r['entry']['palette_indexed'], $r['pages_total']);
    printf("② 空态：有下一步 %d · 缺出路 %d（其中页面级 %d · 行内说明 %d）· 没写空态 %d\n",
        $r['empty_state']['with_next_action'], $r['empty_state']['without_next_action'],
        $r['empty_state']['primary_without_action'], $r['empty_state']['inline_hint'], $r['empty_state']['no_empty_state']);
    printf("③ 窄屏线索：有 %d · 无 %d\n", $r['mobile_ready']['with_hint'], $r['mobile_ready']['without_hint']);
    if ($r['lists']['orphan_suspicious']) {
        echo "\n可疑孤岛（不像详情/编辑页，却没有任何入口）前 20：\n";
        foreach (array_slice($r['lists']['orphan_suspicious'], 0, 20) as $line) echo "   {$line}\n";
    }
    if ($r['lists']['orphan_tab_like']) {
        echo "\n疑似 tab 页的独立版本（父页在侧栏，靠 ?tab= 进入）前 12：\n";
        foreach (array_slice($r['lists']['orphan_tab_like'], 0, 12) as $line) echo "   {$line}\n";
    }
    if ($r['lists']['palette_only']) {
        echo "\n仅命令面板可达前 12：\n";
        foreach (array_slice($r['lists']['palette_only'], 0, 12) as $line) echo "   {$line}\n";
    }
    if ($r['lists']['empty_primary_ranked']) {
        echo "\n页面级空态缺出路（按被引用次数排序，前 15）：\n";
        foreach (array_slice($r['lists']['empty_primary_ranked'], 0, 15) as $line) echo "   {$line}\n";
    }
}

@exec('rm -rf ' . escapeshellarg($tmp));
