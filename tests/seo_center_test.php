<?php
/**
 * B1 验收：SEO 中心的合并契约
 *
 *   php tests/seo_center_test.php
 *
 * 不启动 web 环境，改为静态校验合并的三条契约：
 *   1. 7 个子页都带了 OF_EMBED 守卫，且独立访问路径未被破坏
 *   2. seo-center 的 tab 表与磁盘文件、与 301 规则三方一致
 *   3. 侧栏只剩 1 个 SEO 入口，且 seo-functions.php 未被当成页面并入
 */

$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else     { $fail++; echo "  ✗ {$name}" . ($detail ? "  → {$detail}" : '') . "\n"; }
}

$subpages = ['seo.php','seo-tools.php','seo-batch.php','seo-console.php',
             'structured-data.php','image-seo.php','redirects.php'];

echo "\n── 1. 子页的 OF_EMBED 守卫 ──\n";
foreach ($subpages as $f) {
    $s = file_get_contents("{$root}/admin/{$f}");
    $hasHeaderGuard = (bool)preg_match("/if \(!defined\('OF_EMBED'\)\) admin_header\(/", $s);
    $hasOpenGuard   = strpos($s, "<?php if (!defined('OF_EMBED')): ?>") !== false;
    $hasFooterGuard = (bool)preg_match("/admin_footer\(\);? ?endif;|if \(!defined\('OF_EMBED'\)\) admin_footer\(/", $s);
    check("{$f} 三处守卫齐全", $hasHeaderGuard && $hasOpenGuard && $hasFooterGuard,
          "header=" . (int)$hasHeaderGuard . " open=" . (int)$hasOpenGuard . " footer=" . (int)$hasFooterGuard);
}

echo "\n── 2. 独立访问路径未被破坏 ──\n";
foreach ($subpages as $f) {
    $s = file_get_contents("{$root}/admin/{$f}");
    // 未嵌入时仍应输出完整外壳：三个关键调用都还在文件里
    $intact = strpos($s, 'admin_header(') !== false
           && strpos($s, 'admin_sidebar(') !== false
           && strpos($s, 'admin_footer(') !== false
           && strpos($s, '<div class="admin-layout">') !== false;
    check("{$f} 外壳调用完整保留", $intact);
}

echo "\n── 3. tab 表与磁盘文件一致 ──\n";
$center = file_get_contents("{$root}/admin/seo-center.php");
preg_match_all("/'([a-z-]+)'\s*=>\s*\['([^']+)',\s*'([a-z-]+\.php)',\s*'([a-z-]+)'\]/", $center, $m, PREG_SET_ORDER);
check('解析出 7 个 tab', count($m) === 7, '实际 ' . count($m));
$tabKeys = []; $tabFiles = [];
foreach ($m as $row) {
    $tabKeys[] = $row[1]; $tabFiles[] = $row[3];
    check("tab {$row[1]} → {$row[3]} 存在", is_file("{$root}/admin/{$row[3]}"));
}
check('7 个子页全部被收录', empty(array_diff($subpages, $tabFiles)),
      '缺 ' . implode(',', array_diff($subpages, $tabFiles)));

echo "\n── 4. 301 规则与 tab 表一致 ──\n";
$ht = file_get_contents("{$root}/.htaccess");
foreach ($m as $row) {
    $slug = basename($row[3], '.php');
    $has = (bool)preg_match('#\^xmp/' . preg_quote($slug, '#') . '/\?\\$.*tab=' . preg_quote($row[1], '#') . '#', $ht);
    check("旧 URL /xmp/{$slug} → tab={$row[1]}", $has);
}

echo "\n── 5. 侧栏入口收敛 ──\n";
$cfg = file_get_contents("{$root}/admin/config.php");
check('新增 seo-center 入口', strpos($cfg, 'href="/xmp/seo-center"') !== false);
foreach (['seo','seo-tools','seo-batch','seo-console','redirects','structured-data','image-seo'] as $slug) {
    check("侧栏不再有 /xmp/{$slug} 独立入口", strpos($cfg, 'href="/xmp/' . $slug . '"') === false);
}

echo "\n── 6. seo-functions.php 是函数库，不能被并入 ──\n";
check('未出现在 tab 表中', !in_array('seo-functions.php', $tabFiles, true));
check('文件仍在原处', is_file("{$root}/admin/seo-functions.php"));
$sf = file_get_contents("{$root}/admin/seo-functions.php");
check('未被加 OF_EMBED 守卫', strpos($sf, 'OF_EMBED') === false);

echo "\n── 7. 语法 ──\n";
foreach (array_merge($subpages, ['seo-center.php','config.php']) as $f) {
    exec('php -l ' . escapeshellarg("{$root}/admin/{$f}") . ' 2>&1', $out, $rc);
    check("{$f} 语法通过", $rc === 0);
    $out = [];
}

echo "\n" . str_repeat('─', 44) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
