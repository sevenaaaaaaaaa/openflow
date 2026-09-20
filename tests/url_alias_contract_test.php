<?php
declare(strict_types=1);
/**
 * URL 别名契约（S4：同一屏只保留一个规范地址）
 *
 *   php tests/url_alias_contract_test.php
 *
 * 背景：站内有 19 个 `/xmp/<旧地址>` 用 301 跳到 tab 规范地址（例如 /xmp/articles → content-hub?tab=articles）。
 * 这些 301 是兼容层，必须满足三件事，否则就会出现"点旧地址跳走 / 点旧地址 404 / 代码里到处是两套地址"：
 *   1) 每个别名都有 301，且目标不是另一个别名（不许链式跳转）
 *   2) 目标页真实存在（对应 admin/*.php）
 *   3) 代码里不再新增指向别名地址的链接（一律用规范地址）
 */

$ROOT = dirname(__DIR__);
$ht = (string) file_get_contents($ROOT . '/.htaccess');

$pass = 0; $fail = 0;
function ok(bool $c, string $n, string $d = ''): void
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "URL 别名契约\n";

$aliases = [];
if (preg_match_all('#^RewriteRule\s+\^xmp/([a-z0-9-]+)/\?\$\s+(\S+)\s+\[R=301#mi', $ht, $m, PREG_SET_ORDER)) {
    foreach ($m as $x) $aliases[(string) $x[1]] = (string) $x[2];
}
ok(count($aliases) >= 15, '解析出别名规则（≥15 个）', (string) count($aliases));

/* 1. 目标不是别名本身（不许链式） */
$chained = [];
foreach ($aliases as $from => $to) {
    $toPath = (string) parse_url($to, PHP_URL_PATH);
    $toBase = ltrim(substr($toPath, 5), '/');          // 去掉 /xmp/
    if (isset($aliases[$toBase])) $chained[] = "{$from} → {$to}";
}
ok($chained === [], '不存在链式跳转（别名 → 别名）', implode('；', $chained));

/* 2. 目标页真实存在 */
$missing = [];
foreach ($aliases as $from => $to) {
    $toPath = (string) parse_url($to, PHP_URL_PATH);
    $toBase = ltrim(substr($toPath, 5), '/');
    if ($toBase === '') { $missing[] = "{$from} → {$to}"; continue; }
    if (!is_file($ROOT . '/admin/' . $toBase . '.php')) $missing[] = "{$from} → {$to}";
}
ok($missing === [], '每个别名的目标页都存在', implode('；', $missing));

/* 3. 代码里不指向别名地址（排除 .htaccess 自身与测试） */
$offenders = [];
foreach (array_merge(glob($ROOT . '/admin/*.php') ?: [], glob($ROOT . '/includes/*.php') ?: [], glob($ROOT . '/*.php') ?: []) as $f) {
    $src = (string) file_get_contents($f);
    foreach ($aliases as $a => $_) {
        // 只查"真的把用户送到那个地址"的写法：href / Location / 引号里的 /xmp/<别名>
        if (preg_match('#(href=|Location:\s*|[\'"])/xmp/' . preg_quote($a, '#') . '(?![a-z0-9-])#i', $src)) {
            $offenders[] = basename($f) . ' → /xmp/' . $a;
        }
    }
}
ok($offenders === [], '代码里没有指向别名地址的链接', implode('；', array_slice($offenders, 0, 6)));

/* 4. 别名页不应进入命令面板索引（否则用户会点到会跳走的地址） */
$lib = (string) file_get_contents($ROOT . '/lib/CommandPalette.php');
ok(str_contains($lib, 'R=301') || str_contains($lib, 'aliases'), '面板索引里排除别名的规则仍在（防回归）');

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
