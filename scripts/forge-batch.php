#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * 批量跑适配流水线（survey）：候选池 → 草稿 → 闸门 → 报告
 *
 *   php scripts/forge-batch.php                        # 每类 1 个，含 PHPStan
 *   php scripts/forge-batch.php --per-class=2
 *   php scripts/forge-batch.php --no-phpstan           # 快跑（跳过静态分析）
 *   php scripts/forge-batch.php --classes=notify_reach,crm_leads
 *
 * 产物：
 *   plugins/_drafts/<id>/…                 草稿（含 verification-report.json）
 *   data/ecosystem/forge-report.json       机器可读汇总
 *   docs/ADAPTER-FORGE-RUN.md              人可读报告（通过率 + 失败归因）
 *
 * 退出码：0（survey 不阻塞）；用于观察「闸门与候选池的匹配度」
 */

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AdapterIntake.php';
require_once __DIR__ . '/../lib/AdapterForge.php';
require_once __DIR__ . '/../lib/AdapterVerify.php';

$opts = ['per_class' => 1, 'phpstan' => true, 'classes' => []];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--per-class=(\d+)$/', $a, $m) === 1) $opts['per_class'] = max(1, (int) $m[1]);
    elseif ($a === '--no-phpstan') $opts['phpstan'] = false;
    elseif (str_starts_with($a, '--classes=')) $opts['classes'] = array_filter(explode(',', (string) substr($a, 10)));
}

$candFile = dirname(__DIR__) . '/data/ecosystem/candidates.json';
if (!is_file($candFile)) {
    fwrite(STDERR, "✗ 缺少候选池：先跑 python3 scripts/screen-adapters.py\n");
    exit(2);
}
$all = (array) (json_decode((string) file_get_contents($candFile), true)['candidates'] ?? []);

// 每类取前 N 个
$byClass = [];
foreach ($all as $c) {
    $cls = (string) ($c['category'] ?? '');
    if ($opts['classes'] !== [] && !in_array($cls, (array) $opts['classes'], true)) continue;
    if (count($byClass[$cls] ?? []) >= $opts['per_class']) continue;
    $byClass[$cls][] = $c;
}
$picked = array_merge(...array_values($byClass));
echo "批量适配：" . count($picked) . " 个候选（" . count($byClass) . " 类 × ≤{$opts['per_class']}）"
    . ($opts['phpstan'] ? " · 含 PHPStan" : " · 跳过 PHPStan") . "\n\n";

$draftsRoot = dirname(__DIR__) . '/plugins/_drafts';
$rows = [];
foreach ($picked as $c) {
    $name = (string) ($c['name'] ?? '');
    $src = adapter_parse_source((string) ($c['url'] ?? ''));
    $line = ['name' => $name, 'class' => (string) ($c['category'] ?? ''), 'slug' => (string) ($src['slug'] ?? '')];

    if (!$src['ok'] || $src['kind'] !== 'github') {
        $line += ['status' => 'skipped', 'reason' => '非 GitHub 来源（需 docs/OpenAPI 接入路径）'];
        $rows[] = $line; printf("  ⊘ %-42s %s\n", $name, $line['reason']); continue;
    }

    $meta = adapter_intake_github($src['slug']);
    if (!($meta['ok'] ?? false)) {
        $line += ['status' => 'error', 'reason' => '元数据拉取失败：' . (string) ($meta['error'] ?? '')];
        $rows[] = $line; printf("  ✗ %-42s %s\n", $name, $line['reason']); continue;
    }

    $profile = adapter_intake_profile($meta, (string) ($meta['readme'] ?? '') !== '' ? (string) $meta['readme'] : (string) ($meta['description'] ?? ''), '');
    $forged = adapter_forge_generate($profile, null);
    $manifest = (array) $forged['manifest'];
    $dir = $draftsRoot . '/' . (string) ($manifest['id'] ?? 'draft');
    $w = adapter_forge_write((array) $forged['files'], $dir);
    if (!($w['ok'] ?? false)) {
        $line += ['status' => 'error', 'reason' => '落盘失败：' . (string) ($w['error'] ?? '')];
        $rows[] = $line; printf("  ✗ %-42s %s\n", $name, $line['reason']); continue;
    }

    $report = adapter_verify_gate($dir, $manifest, ['skip_phpstan' => !$opts['phpstan']]);
    adapter_verify_stamp($dir, $report);

    $failed = (array) ($report['failed'] ?? []);
    $phpPath = $dir . '/plugin.php';
    $todoCount = is_file($phpPath) ? substr_count((string) file_get_contents($phpPath), 'TODO') : 0;
    $line += [
        'status' => (string) $report['status'],
        'badge' => (string) $report['badge'],
        'license' => (string) ($meta['license'] ?? ''),
        'version' => (string) ($manifest['source']['version'] ?? ''),
        'surfaces' => implode('/', array_keys((array) ($manifest['surfaces'] ?? []))),
        'permissions' => implode(',', (array) ($manifest['permissions'] ?? [])),
        'failed' => $failed,
        'todos' => $todoCount,
        'dir' => 'plugins/_drafts/' . (string) ($manifest['id'] ?? ''),
    ];
    $rows[] = $line;
    printf("  %s %-42s %-12s %s\n", $report['status'] === 'passed' ? '✓' : ($report['status'] === 'needs-review' ? '△' : '✗'),
        $name, $report['status'], $failed !== [] ? ('失败项：' . implode(',', $failed)) : '');
}

/* ── 汇总 ── */
$counts = ['passed' => 0, 'needs-review' => 0, 'blocked' => 0, 'skipped' => 0, 'error' => 0];
foreach ($rows as $r) $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
$total = count($rows);
$rate = $total > 0 ? round($counts['passed'] / $total * 100) : 0;

$failReasons = [];
foreach ($rows as $r) foreach ((array) ($r['failed'] ?? []) as $f) $failReasons[$f] = ($failReasons[$f] ?? 0) + 1;

$outJson = dirname(__DIR__) . '/data/ecosystem/forge-report.json';
@mkdir(dirname($outJson), 0777, true);
file_put_contents($outJson, json_encode(['generated_at' => date('c'), 'counts' => $counts, 'pass_rate' => $rate, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

$md = "# 适配流水线批跑报告（Adapter Forge Run）\n\n"
    . "> 生成：" . date('c') . " · `php scripts/forge-batch.php --per-class=" . $opts['per_class'] . "`"
    . ($opts['phpstan'] ? '（含 PHPStan）' : '（跳过 PHPStan）') . "\n\n"
    . "**通过率：{$rate}%**（passed {$counts['passed']} / needs-review {$counts['needs-review']} / blocked {$counts['blocked']}"
    . " / skipped {$counts['skipped']} / error {$counts['error']}，共 {$total}）\n\n"
    . "| 候选 | 能力类 | 状态 | 许可证 | 版本 pin | 落点 | 待补 TODO | 失败项 |\n|---|---|---|---|---|---|---|---|\n";
foreach ($rows as $r) {
    $md .= '| ' . $r['name'] . ' | ' . $r['class'] . ' | ' . ($r['status'] ?? '-') . ' | ' . ($r['license'] ?? '-') . ' | '
        . ($r['version'] ?? '-') . ' | ' . ($r['surfaces'] ?? '-') . ' | ' . (($r['todos'] ?? 0) . ' 处') . ' | '
        . (($r['failed'] ?? []) !== [] ? implode(', ', (array) $r['failed']) : ($r['reason'] ?? '—')) . " |\n";
}
$md .= "\n## 失败归因\n\n";
if ($failReasons === []) $md .= "（无）\n";
else foreach ($failReasons as $k => $n) $md .= "- `{$k}`：{$n} 个\n";
$md .= "\n> 说明：`passed` 只代表**结构合法 + 可静态分析 + 契约自测通过**；真实接口调用仍以 `TODO(适配)` 标记，"
    . "需人审/AI 第二轮补齐。待补 TODO 数即剩余工作量。\n\n## 观察\n\n"
    . "- 草稿目录：`plugins/_drafts/<id>/`（含 `verification-report.json`；徽章只由闸门写入）\n"
    . "- 失败项集中在许可证/版本 pin 时，说明「上游信息不全」而非生成质量差 → 需补 intake 的 releases/OpenAPI 读取\n";
file_put_contents(dirname(__DIR__) . '/docs/ADAPTER-FORGE-RUN.md', $md);

echo "\n通过率 {$rate}% ｜ passed {$counts['passed']} · needs-review {$counts['needs-review']} · blocked {$counts['blocked']}"
    . " · skipped {$counts['skipped']} · error {$counts['error']}\n";
echo "报告：docs/ADAPTER-FORGE-RUN.md · data/ecosystem/forge-report.json\n";
if ($failReasons !== []) { echo "失败归因："; foreach ($failReasons as $k => $n) echo "{$k}×{$n} "; echo "\n"; }
exit(0);
