<?php
/**
 * B1 + B2 合并契约通用检查器
 *
 *   php tests/hub_merge_test.php
 *
 * 对每个「中心页」校验同一组契约，B3 及以后新增中心页只需往 $HUBS 加一条。
 */

$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else     { $fail++; echo "  ✗ {$name}" . ($detail ? "  → {$detail}" : '') . "\n"; }
}

/** 中心页 => [中心文件, tab 表函数名, 侧栏入口 slug] */
$HUBS = [
    'SEO 中心'  => ['seo-center.php',  'seo_center_tabs',  'seo-center'],
    '内容中心'  => ['content-hub.php', 'content_hub_tabs', 'content-hub'],
];

$cfg = file_get_contents("{$root}/admin/config.php");
$ht  = file_get_contents("{$root}/.htaccess");

foreach ($HUBS as $label => [$hubFile, $tabFn, $slug]) {
    echo "\n══ {$label}（{$hubFile}）══\n";
    $hubPath = "{$root}/admin/{$hubFile}";
    check('中心页存在', is_file($hubPath));
    if (!is_file($hubPath)) continue;
    $hub = file_get_contents($hubPath);

    // 解析 tab 表
    preg_match_all("/'([a-z-]+)'\s*=>\s*\['([^']+)',\s*'([a-z-]+\.php)',\s*'([a-z-]+)'\]/", $hub, $m, PREG_SET_ORDER);
    check('tab 表可解析', count($m) > 0);
    check('中心页定义了 ' . $tabFn . '()', strpos($hub, "function {$tabFn}(") !== false);
    check('中心页 define(OF_EMBED)', strpos($hub, "define('OF_EMBED'") !== false);
    check('按权限过滤 tab', strpos($hub, 'has_perm') !== false);

    foreach ($m as [$_, $key, $title, $file, $perm]) {
        $sub = "{$root}/admin/{$file}";
        check("[{$key}] 子页 {$file} 存在", is_file($sub));
        if (!is_file($sub)) continue;
        $s = file_get_contents($sub);

        // 三处守卫
        $g1 = (bool)preg_match("/if \(!defined\('OF_EMBED'\)\) admin_header\(/", $s);
        $g2 = strpos($s, "<?php if (!defined('OF_EMBED')): ?>") !== false;
        $g3 = (bool)preg_match("/admin_footer\(\);? ?endif;|if \(!defined\('OF_EMBED'\)\) admin_footer\(/", $s);
        check("[{$key}] 三处 OF_EMBED 守卫齐全", $g1 && $g2 && $g3,
              'header=' . (int)$g1 . ' open=' . (int)$g2 . ' footer=' . (int)$g3);

        // 独立访问仍完整
        check("[{$key}] 独立访问外壳未破坏",
              strpos($s, 'admin_header(') !== false
           && strpos($s, 'admin_sidebar(') !== false
           && strpos($s, 'admin_footer(') !== false
           && strpos($s, '<div class="admin-layout">') !== false);

        // 守卫必须成对：if 数量 == endif 数量
        $ifs    = preg_match_all("/if \(!defined\('OF_EMBED'\)\): \?>/", $s);
        $endifs = preg_match_all("/<\?php endif; \?>|admin_footer\(\); endif; \?>/", $s);
        check("[{$key}] 守卫 if/endif 配平", $ifs <= $endifs, "if={$ifs} endif={$endifs}");

        // 301
        $oldSlug = basename($file, '.php');
        check("[{$key}] 旧 URL /xmp/{$oldSlug} 有 301",
              (bool)preg_match('#\^xmp/' . preg_quote($oldSlug, '#') . '/\?\\$.*tab=' . preg_quote($key, '#') . '#', $ht));

        // 侧栏不再有独立入口
        check("[{$key}] 侧栏已无 /xmp/{$oldSlug} 直链",
              strpos($cfg, 'href="/xmp/' . $oldSlug . '"') === false);

        // 语法
        exec('php -l ' . escapeshellarg($sub) . ' 2>&1', $o, $rc); $o = [];
        check("[{$key}] 语法通过", $rc === 0);
    }

    check('侧栏有中心入口 /xmp/' . $slug, strpos($cfg, 'href="/xmp/' . $slug . '"') !== false);
    exec('php -l ' . escapeshellarg($hubPath) . ' 2>&1', $o, $rc); $o = [];
    check('中心页语法通过', $rc === 0);
}

echo "\n══ B3：浅 CRUD 归并父页子 tab ══\n";
/** 子页 => [父页, 子tab key, 301 目标片段] */
$B3 = [
    'page-categories.php'  => ['pages-list.php',   'cats', 'tab=pages&sub=cats'],
    'tags.php'             => ['pages-list.php',   'tags', 'tab=pages&sub=tags'],
    'payment-settings.php' => ['shop-settings.php','pay',  'shop-settings?sub=pay'],
    'mail-settings.php'    => ['email.php',        'smtp', 'email?sub=smtp'],
    'footer-links.php'     => ['site-builder.php', 'foot', 'site-builder?sub=foot'],
    'storage.php'          => ['health-check.php', 'stor', 'health-check?sub=stor'],
    'activity.php'         => ['audit-log.php',    'act',  'audit-log?sub=act'],
];
check('_subtabs.php 助手存在', is_file("{$root}/admin/_subtabs.php"));
foreach ($B3 as $child => [$parent, $key, $redir]) {
    $cs = @file_get_contents("{$root}/admin/{$child}");
    $ps = @file_get_contents("{$root}/admin/{$parent}");
    $cslug = basename($child, '.php');

    check("{$child} 有 OF_EMBED 守卫", $cs !== false && strpos($cs, 'OF_EMBED') !== false);
    check("{$child} 独立访问外壳保留",
          $cs !== false && strpos($cs, '<div class="admin-layout">') !== false
          && strpos($cs, 'admin_footer(') !== false);
    check("{$parent} 装了子 tab", $ps !== false && strpos($ps, 'of_subtab_begin') !== false);
    check("{$parent} 收录了 {$child}", $ps !== false && strpos($ps, "'{$child}'") !== false);
    check("{$parent} 有 self tab", $ps !== false && strpos($ps, "'self' =>") !== false);
    check("{$parent} if/else/endif 配平",
          $ps !== false && substr_count($ps, "if (\$__sub === 'self'):") === 1
          && substr_count($ps, 'else: of_subtab_include') === 1);
    check("旧 URL /xmp/{$cslug} 有 301", strpos($ht, $redir) !== false);
    check("侧栏已无 /xmp/{$cslug}", strpos($cfg, 'href="/xmp/' . $cslug . '"') === false);
    foreach ([$child, $parent] as $f) {
        exec('php -l ' . escapeshellarg("{$root}/admin/{$f}") . ' 2>&1', $o, $rc); $o = [];
        check("{$f} 语法通过", $rc === 0);
    }
}

echo "\n══ 嵌套嵌入：pages-list 既被中心引入，自身又引入子页 ══\n";
$pl = file_get_contents("{$root}/admin/pages-list.php");
check('pages-list 同时具备两种角色',
      strpos($pl, "if (!defined('OF_EMBED'))") !== false && strpos($pl, 'of_subtab_begin') !== false);
$sub = file_get_contents("{$root}/admin/_subtabs.php");
check('of_subtab_include 重复 define 前有守卫',
      strpos($sub, "if (!defined('OF_EMBED')) define('OF_EMBED', 1);") !== false);

echo "\n══ 跨中心：不能有子页被两个中心同时收录 ══\n";
$claimed = [];
$dup = [];
foreach ($HUBS as $label => [$hubFile, , ]) {
    $hub = @file_get_contents("{$root}/admin/{$hubFile}");
    if (!$hub) continue;
    preg_match_all("/'[a-z-]+'\s*=>\s*\['[^']+',\s*'([a-z-]+\.php)'/", $hub, $mm);
    foreach ($mm[1] as $f) {
        if (isset($claimed[$f])) $dup[] = "{$f}（{$claimed[$f]} 与 {$label}）";
        $claimed[$f] = $label;
    }
}
check('无重复收录', empty($dup), implode('; ', $dup));

echo "\n══ 函数库不得被当作页面并入 ══\n";
foreach (['seo-functions.php'] as $lib) {
    check("{$lib} 未被收录", !isset($claimed[$lib]));
    $ls = @file_get_contents("{$root}/admin/{$lib}");
    check("{$lib} 未被加守卫", $ls !== false && strpos($ls, 'OF_EMBED') === false);
}

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
