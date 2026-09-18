<?php
/**
 * 设计护栏（design-guard）—— 保证「增量不破版」
 *
 * 校验三件事：
 *  1. 页面用到的每个 class 都必须在共享 CSS（tokens/modules）或本页 <style> 里有定义
 *     （即：不引入未定义样式 = 没有悄悄新造视觉）
 *  2. 与上一版（git HEAD）对比，列出本次「新增用到的 class」（= 视觉增量的白名单，人工过一眼）
 *  3. 页面里是否出现内联 style 新增（提示：优先用既有 archetype，而不是就地写样式）
 *
 * 用法：
 *   php scripts/design-guard.php home-v2.php product.php        # 检查若干文件
 *   php scripts/design-guard.php --all                          # 检查所有前台 PHP 页
 *   php scripts/design-guard.php --json product.php
 *
 * 退出码：有「新增且未定义」的 class 时为 1（可用于 CI / pre-commit）
 */

$ROOT = dirname(__DIR__);

/* ── 收集 CSS 里定义的 class 选择器 ── */
function css_classes(string $css): array {
    preg_match_all('/\.([A-Za-z_][\w-]*)/', $css, $m);
    return array_unique($m[1]);
}

$shared = [];
foreach (['assets/tokens.css', 'assets/modules.css'] as $f) {
    $p = $ROOT . '/' . $f;
    if (is_file($p)) $shared = array_merge($shared, css_classes((string) file_get_contents($p)));
}
$shared = array_flip(array_unique($shared));

/* ── 从 HTML/PHP 中抽取使用的 class ── */
function used_classes(string $src): array {
    preg_match_all('/class\s*=\s*"([^"]*)"/', $src, $m);
    $out = [];
    foreach ($m[1] as $raw) {
        if (str_contains($raw, '<?') || str_contains($raw, '$')) continue;   // 动态拼接跳过
        foreach (preg_split('/\s+/', trim($raw)) as $c) {
            if ($c === '') continue;
            $out[$c] = true;
        }
    }
    return array_keys($out);
}

/* ── 上一版内容（git HEAD）── */
function prev_version(string $rel): ?string {
    global $ROOT;
    $cmd = 'cd ' . escapeshellarg($ROOT) . ' && git show HEAD:' . escapeshellarg($rel) . ' 2>/dev/null';
    $out = shell_exec($cmd);
    return is_string($out) && $out !== '' ? $out : null;
}

$files = []; $asJson = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--json') { $asJson = true; continue; }
    if ($a === '--all') {
        foreach (glob($ROOT . '/*.php') as $f) $files[] = basename($f);
        continue;
    }
    $files[] = $a;
}
if (!$files) { fwrite(STDERR, "用法：php scripts/design-guard.php <file.php> [--all] [--json]\n"); exit(2); }

$report = []; $bad = 0;
foreach ($files as $rel) {
    $abs = $ROOT . '/' . $rel;
    if (!is_file($abs)) { $report[$rel] = ['error' => '文件不存在']; continue; }
    $src = (string) file_get_contents($abs);

    // 本页 <style> 里定义的 class 也算已定义
    $local = [];
    if (preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $src, $mm)) {
        foreach ($mm[1] as $css) $local = array_merge($local, css_classes($css));
    }
    $local = array_flip($local);

    $used = used_classes($src);
    $prev = prev_version($rel);
    $prevUsed = $prev !== null ? array_flip(used_classes($prev)) : [];

    $undefined = [];
    $newClasses = [];
    foreach ($used as $c) {
        if ($prev !== null && !isset($prevUsed[$c])) $newClasses[] = $c;
        if (!isset($shared[$c]) && !isset($local[$c])) $undefined[] = $c;
    }
    // 只看「新增且未定义」= 本次改动引入的样式风险
    $risky = array_values(array_intersect($newClasses, $undefined));

    // 内联 style 增量（提示级）
    $newInline = 0;
    if ($prev !== null) {
        $newInline = max(0, substr_count($src, 'style="') - substr_count($prev, 'style="'));
    }
    if ($risky) $bad++;

    $report[$rel] = [
        'classes_used' => count($used),
        'new_classes' => $newClasses,
        'undefined' => $undefined,
        'risky_new_undefined' => $risky,
        'inline_style_delta' => $newInline,
    ];
}

if ($asJson) { echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n"; exit($bad ? 1 : 0); }

echo "═══ 设计护栏（增量不破版）═══\n\n";
foreach ($report as $f => $r) {
    if (isset($r['error'])) { echo "✗ $f  {$r['error']}\n"; $bad++; continue; }
    $flag = $r['risky_new_undefined'] ? '✗' : '✓';
    printf("%s %-24s class %d · 新增 %d · 未定义 %d · 内联 style %+d\n",
        $flag, $f, $r['classes_used'], count($r['new_classes']), count($r['undefined']), $r['inline_style_delta']);
    if ($r['risky_new_undefined']) {
        echo "    风险：新增且 CSS 未定义的 class → " . implode(', ', $r['risky_new_undefined']) . "\n";
    }
    if ($r['undefined']) {
        echo "    提示：未定义 class（历史遗留/动态）→ " . implode(', ', array_slice($r['undefined'], 0, 12))
            . (count($r['undefined']) > 12 ? ' …' : '') . "\n";
    }
}
printf("\n合计：%d 个文件，%d 个有风险\n", count($report), $bad);
exit($bad ? 1 : 0);
