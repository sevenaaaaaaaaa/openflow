<?php
/**
 * 把 scripts/i18n/lang-patch.json 合并进某个语言包目录（键级合并，其余键原样保留）。
 *   本地：php scripts/i18n-lang-patch.php data/lang
 *   线上：scp 本脚本 + lang-patch.json 到 /tmp 后 php /tmp/i18n-lang-patch.php /www/wwwroot/nownexts_com/data/lang /tmp/lang-patch.json
 * 语言包线上为准（后台可改），所以只补键、不整文件覆盖。
 */
$dir = rtrim($argv[1] ?? 'data/lang', '/');
$patch = json_decode((string)file_get_contents($argv[2] ?? __DIR__ . '/i18n/lang-patch.json'), true);
if (!is_array($patch)) { fwrite(STDERR, "patch 无法解析\n"); exit(2); }
foreach ($patch as $loc => $kv) {
    $f = "$dir/$loc.json";
    $cur = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $n = 0;
    foreach ($kv as $k => $v) if (($cur[$k] ?? null) !== $v) { $cur[$k] = $v; $n++; }
    if ($n) { copy($f, $f . '.bak-' . date('Ymd')) ; file_put_contents($f, json_encode($cur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n"); }
    echo "$loc: 更新 $n 个键\n";
}
