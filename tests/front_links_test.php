<?php
/**
 * 前台链接契约 —— 纯静态检查，php tests/front_links_test.php
 *
 * 前台源码（根目录页面 / includes / lib 里的前台部件 / assets/*.js）里每一个以 / 开头的站内链接目标
 * （href / action / location.href / fetch）都必须能被 .htaccess 路由到：要么命中某条 RewriteRule，
 * 要么是真实存在的文件或目录。历史教训：/account、/plugin/x、/skill/x、/events/x、/api/member 这些
 * 目标在页面上有按钮，却从来没有对应的路由规则，点开全是 404。
 *
 * PHP 片段（<?= … ?>、$var）视为通配段 x；含通配的动态链接只校验前缀能否命中规则。
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  ✗ $msg\n"; } }

// ── 1. 解析 .htaccess 里的路由规则（去掉后台 xmp 与静态放行）──
$rules = [];
foreach (file("$root/.htaccess", FILE_IGNORE_NEW_LINES) as $line) {
    if (!preg_match('/^\s*RewriteRule\s+(\S+)\s+(\S+)/', $line, $m)) continue;
    $pat = $m[1];
    if ($pat === '^' || $pat === '^$') continue;
    $rules[] = '~' . str_replace('~', '\~', $pat) . '~';
}
ok(count($rules) > 30, '.htaccess 路由规则数量异常（' . count($rules) . '）');

function routable(string $path): bool {
    global $rules, $root;
    $p = ltrim(parse_url($path, PHP_URL_PATH) ?? '', '/');
    if ($p === '' || $p === 'index.php') return true;
    if (is_file("$root/$p") || is_dir("$root/$p")) return true;
    if (str_starts_with($p, 'api/') && is_file("$root/$p.php")) return true; // /api/x → api/x.php（.htaccess 有对应规则，这里显式确认文件存在）
    foreach ($rules as $re) if (@preg_match($re, $p)) return true;
    return false;
}

// ── 2. 收集前台源码里的站内链接目标 ──
$files = array_merge(glob("$root/*.php"), glob("$root/includes/*.php"), glob("$root/assets/*.js"), ["$root/lib/comment-widget.php"]);
$skipFile = ['router.php', 'setup-wizard.php'];
$targets = [];
$pat = '~(?:href|action)\s*=\s*\\\\?["\']([^"\'<>\s\\\\]+)|(?:location(?:\.href)?\s*=\s*|location\.assign\(|location\.replace\(|fetch\()\s*\\\\?["\'`]([^"\'`<>\s\\\\]+)~';
foreach ($files as $f) {
    if (in_array(basename($f), $skipFile, true)) continue;
    $s = file_get_contents($f);
    $rel = substr($f, strlen($root) + 1);
    if (!preg_match_all($pat, $s, $mm, PREG_SET_ORDER)) continue;
    foreach ($mm as $m) {
        $h = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
        if ($h === '') continue;
        $h = preg_replace('~<\?(?:php|=)?.*?\?>~s', 'x', $h);
        $h = preg_replace('~\$\{[^}]*\}|\$[a-zA-Z_]\w*~', 'x', $h);
        $h = str_replace('&amp;', '&', $h);
        if (!str_starts_with($h, '/') || str_starts_with($h, '//')) continue;
        if (preg_match('~^/(xmp|uploads|assets|md-docs|data|vendor)(/|$)~', $h)) continue;
        // 模板拼接残留的纯前缀（'/article/' . $slug）→ 补一个通配段
        if (str_ends_with($h, '/')) $h .= 'x';
        // 兼容 JS 里的 + 拼接：'/course/' + id
        $targets[$h][] = $rel;
    }
}
ok(count($targets) > 60, '收集到的前台链接目标过少（' . count($targets) . '），提取正则可能失效');

// ── 3. 每个目标都必须可路由 ──
foreach ($targets as $h => $from) {
    // next= 回跳参数里的站内路径同样要可路由（登录后回跳到 404 页等于没回跳）
    if (preg_match('~[?&]next=(/[^&\s]*)~', $h, $nm)) {
        $n = urldecode($nm[1]);
        if (str_starts_with($n, '/') && !str_starts_with($n, '//')) ok(routable($n), "回跳目标 {$n} 无路由（来自 {$h}，" . implode(', ', array_unique($from)) . '）');
    }
    ok(routable($h), "链接目标 {$h} 无路由（" . implode(', ', array_unique($from)) . '）');
}

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
