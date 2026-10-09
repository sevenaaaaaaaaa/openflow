<?php
/**
 * 分段字典工具（页面内容多语言）
 *
 *   php scripts/i18n-content.php extract --base http://127.0.0.1:8777   # 从渲染后的页面 + nav.json 抽取待译中文片段 → scripts/i18n/segments.json
 *   php scripts/i18n-content.php check [locale...]                      # 校验各语言译文覆盖率（有漏译返回 1）
 *   php scripts/i18n-content.php todo  <locale>                         # 把某语言未译片段写到 scripts/i18n/todo.<locale>.json
 *   php scripts/i18n-content.php list [from] [to]                       # 按编号列出待译原文（翻译作业用）
 *   php scripts/i18n-content.php import <locale> <file>                 # 导入「编号|译文」行（编号与 list 一致），写入译文源
 *   php scripts/i18n-content.php build                                  # 译文源 → data/lang/content-<locale>.json（部署时随 data 通道上线）
 *
 * 译文源：scripts/i18n/content.<locale>.json  { "中文原文": "译文" }
 * 抽取与运行时共用 lib/I18nContent.php，key 必然一致。
 */
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../lib/I18nContent.php';

$ROOT = dirname(__DIR__);
$DIR = $ROOT . '/scripts/i18n';
$LOCALES = ['zh-TW', 'en', 'ja', 'ko'];
/** 参与抽取的页面（5 张 Studio 页 + 自带页脚/外壳文案） */
$PAGES = ['/product/linkto', '/product/litmus', '/product/liana', '/product/conflow', '/product/zerozen'];

function seg_file(string $dir): string { return $dir . '/segments.json'; }
function load_json(string $f): array { return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : []; }
function save_json(string $f, array $d): void {
    file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
}

$cmd = $argv[1] ?? '';
if ($cmd === 'extract') {
    $base = '';
    foreach ($argv as $i => $a) if ($a === '--base') $base = rtrim($argv[$i + 1] ?? '', '/');
    if ($base === '') { fwrite(STDERR, "需要 --base http://host:port\n"); exit(2); }
    $seg = [];
    foreach ($PAGES as $p) {
        $html = @file_get_contents($base . $p);
        if ($html === false || $html === '') { fwrite(STDERR, "取页面失败：$p\n"); exit(2); }
        foreach (i18n_segments($html) as $s) $seg[$s][] = $p;
    }
    $nav = json_decode((string)file_get_contents($ROOT . '/data/nav.json'), true);
    foreach (i18n_deep_segments($nav) as $s) $seg[$s][] = 'nav.json';
    ksort($seg);
    foreach ($seg as $k => $v) $seg[$k] = array_values(array_unique($v));
    if (!is_dir($DIR)) mkdir($DIR, 0775, true);
    save_json(seg_file($DIR), $seg);
    echo '抽取完成：' . count($seg) . " 段 → scripts/i18n/segments.json\n";
    exit(0);
}

$segments = array_keys(load_json(seg_file($DIR)));
if (!$segments) { fwrite(STDERR, "先运行 extract\n"); exit(2); }

if ($cmd === 'check') {
    $targets = array_slice($argv, 2) ?: $LOCALES;
    $bad = 0;
    foreach ($targets as $loc) {
        $tr = load_json("$DIR/content.$loc.json");
        $miss = array_values(array_filter($segments, fn($s) => !isset($tr[$s]) || trim((string)$tr[$s]) === ''));
        // 译文里不应残留汉字（zh-TW 除外；品牌名/专名统一用拉丁写法；ja 允许汉字）
        $leak = [];
        if (in_array($loc, ['en', 'ko'], true)) foreach ($tr as $k => $v) if (i18n_has_cjk((string)$v)) $leak[] = $k;
        $stale = array_values(array_diff(array_keys($tr), $segments));
        printf("%-6s 覆盖 %d/%d  漏译 %d  汉字残留(人工确认) %d  过期 %d\n", $loc, count($segments) - count($miss), count($segments), count($miss), count($leak), count($stale));
        foreach (array_slice($miss, 0, 8) as $m) echo "   · 漏译：$m\n";
        foreach (array_slice($leak, 0, 5) as $m) echo "   · 汉字残留：$m => {$tr[$m]}\n";
        if ($miss) $bad = 1; // 汉字残留仅提示：个别示例/音译注释有意保留
    }
    exit($bad);
}

if ($cmd === 'todo') {
    $loc = $argv[2] ?? '';
    if (!$loc) { fwrite(STDERR, "用法: todo <locale>\n"); exit(2); }
    $tr = load_json("$DIR/content.$loc.json");
    $todo = [];
    foreach ($segments as $s) if (!isset($tr[$s]) || trim((string)$tr[$s]) === '') $todo[$s] = '';
    save_json("$DIR/todo.$loc.json", $todo);
    echo "待译 " . count($todo) . " 段 → scripts/i18n/todo.$loc.json\n";
    exit(0);
}

if ($cmd === 'list') {
    $from = (int)($argv[2] ?? 1); $to = (int)($argv[3] ?? count($segments));
    foreach ($segments as $i => $s) { $n = $i + 1; if ($n >= $from && $n <= $to) printf("%03d|%s\n", $n, str_replace("\n", '\\n', $s)); }
    exit(0);
}

if ($cmd === 'import') {
    $loc = $argv[2] ?? ''; $file = $argv[3] ?? '';
    if (!$loc || !is_file($file)) { fwrite(STDERR, "用法: import <locale> <file>\n"); exit(2); }
    // locale=multi 时每行形如「en|001|译文」，一个文件可含多种语言
    $store = []; $n = 0; $bad = 0;
    foreach (preg_split('/\R/u', (string)file_get_contents($file)) as $line) {
        if (trim($line) === '') continue;
        $L = $loc;
        if ($loc === 'multi' && preg_match('/^(zh-TW|en|ja|ko)\|(.*)$/u', $line, $lm)) { $L = $lm[1]; $line = $lm[2]; }
        if (!in_array($L, $LOCALES, true) || !preg_match('/^(\d{3})\|(.*)$/u', $line, $m) || !isset($segments[(int)$m[1] - 1])) { echo "  ✗ 无法解析：" . mb_substr($line, 0, 40) . "\n"; $bad++; continue; }
        $store[$L] ??= load_json("$DIR/content.$L.json");
        $store[$L][$segments[(int)$m[1] - 1]] = str_replace('\\n', "\n", trim($m[2]));
        $n++;
    }
    foreach ($store as $L => $tr) { ksort($tr); save_json("$DIR/content.$L.json", $tr); }
    echo "导入 $n 段 → " . implode(', ', array_keys($store)) . ($bad ? "（$bad 行失败）" : '') . "\n";
    exit($bad ? 1 : 0);
}

if ($cmd === 'build') {
    $out = $ROOT . '/data/lang';
    if (!is_dir($out)) mkdir($out, 0775, true);
    foreach ($LOCALES as $loc) {
        $tr = load_json("$DIR/content.$loc.json");
        $keep = [];
        foreach ($segments as $s) if (isset($tr[$s]) && trim((string)$tr[$s]) !== '') $keep[$s] = $tr[$s];
        save_json("$out/content-$loc.json", $keep);
        echo "✓ data/lang/content-$loc.json  " . count($keep) . "/" . count($segments) . " 段\n";
    }
    exit(0);
}

fwrite(STDERR, "用法: extract --base URL | check [locale…] | todo <locale> | build\n");
exit(2);
