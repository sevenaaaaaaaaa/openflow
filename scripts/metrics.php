#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * 项目口径指标（单一来源）
 *
 *   php scripts/metrics.php            # 人读
 *   php scripts/metrics.php --json     # 机器读
 *   php scripts/metrics.php --sync     # 把文档里带标记的数字刷成当前值
 *   php scripts/metrics.php --check    # 校验文档标记与当前值一致（CI 用，不一致退出码 1）
 *
 * 【为什么要有】同一周里，后台页数在文档里出现过 226 / 222 / 192 三个版本，
 * API 出现过 113 / 116，MCP 工具被按文本 grep 误算成 31（实际 23）。
 * 数字一旦能各说各话，基于数字的结论就不可信。
 *
 * 【怎么用】文档里写成 `<!--m:key-->123<!--/m-->`，数字由本脚本维护；
 * tests/metrics_contract_test.php 会盯住它们，改了代码没同步文档就会红。
 */

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/AdminInventory.php';

/** 每个指标：值 + 口径说明（说明本身也是产物——数字不写清口径就是下一次漂移的起点） */
function of_metrics(string $ROOT): array
{
    $inv = admin_page_inventory($ROOT);

    $mcp = 0;
    if (is_file($ROOT . '/lib/McpTools.php')) {
        require_once $ROOT . '/lib/McpTools.php';
        if (function_exists('mcp_tools')) $mcp = count(mcp_tools());
    }

    $apiFiles = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/api', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') $apiFiles[] = $f->getPathname();
    }

    return [
        'admin_pages'     => [count($inv['pages']),      '后台真实页面（admin/*.php 中调用了 admin_header/footer、且不是 301 别名或片段）'],
        'admin_alias_301' => [count($inv['alias_301']),  '.htaccess 里指向新地址的 301 旧地址'],
        'admin_fragments' => [count($inv['fragments']),  '被 include 的片段与工具文件（不渲染页面）'],
        'api_endpoints'   => [count($apiFiles),          'api/ 下的 *.php（含 api/v1/）'],
        'mcp_tools'       => [$mcp,                      'lib/McpTools.php 注册表长度（与 mcp-server.php 同源）'],
        'lib_files'       => [count(glob($ROOT . '/lib/*.php') ?: []), 'lib/ 下的核心类库文件'],
        'php_tests'       => [count(glob($ROOT . '/tests/*_test.php') ?: []), 'tests/*_test.php 测试文件数'],
        'plugins'         => [count(array_filter(glob($ROOT . '/plugins/*') ?: [],
                          static fn(string $d): bool => is_dir($d) && is_file($d . '/plugin.json'))),
                      'plugins/ 下带 plugin.json 的目录（草稿/素材目录不算）'],
        'frontend_pages'  => [count(glob($ROOT . '/*.php') ?: []), '仓库根目录的前台 PHP 页'],
    ];
}

/** 文档里所有带标记的位置。新文档加进来即可被纳管。 */
function of_metric_docs(): array
{
    return ['README.md', 'docs/POSITIONING.md', 'docs/USABILITY-DIAGNOSIS.md', 'docs/METRICS.md'];
}

/**
 * 扫描/替换文档里的 `<!--m:key-->值<!--/m-->`
 * @return array{updated:int, mismatched:list<array{file:string,key:string,doc:string,now:string}>, unknown:list<string>}
 */
function of_metric_apply(string $ROOT, array $metrics, bool $write): array
{
    $updated = 0; $mismatched = []; $unknown = [];
    foreach (of_metric_docs() as $rel) {
        $path = $ROOT . '/' . $rel;
        if (!is_file($path)) continue;
        $src = (string) file_get_contents($path);
        $out = preg_replace_callback('/<!--m:([a-z0-9_]+)-->(.*?)<!--\/m-->/s',
            function (array $m) use ($metrics, $rel, &$mismatched, &$unknown, &$updated): string {
                $key = $m[1];
                if (!isset($metrics[$key])) { $unknown[] = "{$rel}:{$key}"; return $m[0]; }
                $now = (string) $metrics[$key][0];
                if (trim($m[2]) !== $now) {
                    $mismatched[] = ['file' => $rel, 'key' => $key, 'doc' => trim($m[2]), 'now' => $now];
                    $updated++;
                }
                return "<!--m:{$key}-->{$now}<!--/m-->";
            }, $src);
        if ($write && is_string($out) && $out !== $src) file_put_contents($path, $out);
    }
    return ['updated' => $updated, 'mismatched' => $mismatched, 'unknown' => $unknown];
}

// ── CLI ──
if (PHP_SAPI === 'cli' && realpath((string) ($argv[0] ?? '')) === realpath(__FILE__)) {
    $args = $argv;
    $m = of_metrics($ROOT);

    if (in_array('--json', $args, true)) {
        echo json_encode(array_map(static fn(array $v): array => ['value' => $v[0], 'definition' => $v[1]], $m),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
        exit(0);
    }

    if (in_array('--sync', $args, true) || in_array('--check', $args, true)) {
        $write = in_array('--sync', $args, true);
        $r = of_metric_apply($ROOT, $m, $write);
        foreach ($r['unknown'] as $u) echo "⚠ 未知指标标记：{$u}\n";
        foreach ($r['mismatched'] as $x) {
            printf("%s  %s：文档 %s → 实际 %s%s\n", $write ? '已更新' : '不一致',
                $x['file'] . ' · ' . $x['key'], $x['doc'], $x['now'], $write ? '' : '');
        }
        if ($r['mismatched'] === [] && $r['unknown'] === []) echo "文档数字与代码一致 ✓\n";
        exit($write ? 0 : (($r['mismatched'] === [] && $r['unknown'] === []) ? 0 : 1));
    }

    echo "项目口径指标（" . date('Y-m-d') . "）\n\n";
    foreach ($m as $k => [$v, $def]) printf("  %-16s %6d   %s\n", $k, $v, $def);
    echo "\n文档同步： php scripts/metrics.php --sync\n";
}
