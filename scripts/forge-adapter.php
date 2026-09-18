#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * 适配一条候选（CLI 端到端）：源接入 → 合成草稿 → 验证闸门 → 徽章
 *
 *   php scripts/forge-adapter.php binwiederhier/ntfy            # 模板兜底（离线可用）
 *   php scripts/forge-adapter.php binwiederhier/ntfy --ai       # 用 AiCenter 生成（需模型额度）
 *   php scripts/forge-adapter.php https://github.com/n8n-io/n8n --out=plugins/_drafts
 *
 * 产出目录：plugins/_drafts/<id>/{plugin.json, plugin.php, tests/…, verification-report.json}
 * 退出码：0 = passed · 1 = needs-review/blocked（草稿仍会保留，供人审）· 2 = 参数/网络错误
 */

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AdapterIntake.php';
require_once __DIR__ . '/../lib/AdapterForge.php';
require_once __DIR__ . '/../lib/AdapterVerify.php';

$args = array_slice($argv, 1);
$source = '';
$useAi = false;
$complete = false;
$outDir = dirname(__DIR__) . '/plugins/_drafts';
foreach ($args as $a) {
    if ($a === '--ai') { $useAi = true; continue; }
    if ($a === '--complete') { $complete = true; continue; }
    if (str_starts_with($a, '--out=')) { $outDir = (string) substr($a, 6); continue; }
    if ($a !== '' && $source === '') $source = $a;
}
if ($source === '') {
    fwrite(STDERR, "用法：php scripts/forge-adapter.php <owner/repo|url> [--ai] [--complete] [--out=DIR]\n");
    exit(2);
}

$src = adapter_parse_source($source);
if (!$src['ok']) { fwrite(STDERR, "✗ 来源解析失败：{$src['error']}\n"); exit(2); }
if ($src['kind'] !== 'github') { fwrite(STDERR, "✗ 目前只支持 GitHub 来源（收到 {$src['kind']}）\n"); exit(2); }

echo "① 源接入：{$src['slug']}\n";
$meta = adapter_intake_github($src['slug']);
if (!($meta['ok'] ?? false)) { fwrite(STDERR, "✗ 拉取仓库元数据失败：{$meta['error']}\n"); exit(2); }
echo "   许可证 {$meta['license']} · ★{$meta['stars']} · 最近提交 {$meta['pushed_at']}\n";

$profile = adapter_intake_profile($meta, (string) ($meta['readme'] ?? '') !== '' ? (string) $meta['readme'] : (string) ($meta['description'] ?? ''), '');
echo "   能力类：" . ($profile['class_need'] !== '' ? $profile['class_need'] : '未归类')
    . " · 落点：" . implode('/', (array) $profile['surface_hits'] !== [] ? array_keys((array) $profile['surface_hits']) : ['api_route'])
    . " · 证据：" . $profile['evidence_grade'] . "\n";
if ((string) ($meta['readme'] ?? '') !== '') echo "   README " . mb_strlen((string) $meta['readme']) . " 字（用于落点识别）\n";
$gate = (array) $profile['license_gate'];
echo "   许可证闸门：{$gate['level']} — {$gate['note']}\n";

echo "② 合成草稿" . ($useAi ? "（AiCenter）" : "（模板兜底）") . "…\n";
$ai = null;
if ($useAi && class_exists('AiCenter')) {
    $ai = static function (string $system, string $user, array $opts): array {
        $r = AiCenter::chat($system, $user, ['feature' => 'adapter_forge', 'max_tokens' => 2200]);
        return ['ok' => (bool) ($r['ok'] ?? false), 'text' => (string) ($r['text'] ?? ''), 'error' => (string) ($r['error'] ?? '')];
    };
}
$forged = adapter_forge_generate($profile, $ai);
$manifest = (array) $forged['manifest'];
$id = (string) ($manifest['id'] ?? 'adapter');
echo "   id={$id} · 生成方式={$forged['generated_by']} · 文件 " . count((array) $forged['files']) . " 个\n";
foreach ((array) $forged['plan']['notes'] as $n) echo "   ⚠ {$n}\n";

$dir = rtrim($outDir, '/') . '/' . $id;
$w = adapter_forge_write((array) $forged['files'], $dir);
if (!($w['ok'] ?? false)) { fwrite(STDERR, "✗ 落盘失败：{$w['error']}\n"); exit(2); }
echo "   已写入 {$w['dir']}（" . implode(', ', (array) $w['written']) . "）\n";

echo "③ 验证闸门…\n";
$report = adapter_verify_gate($dir, $manifest);
foreach ((array) $report['checks'] as $c) {
    $note = $c['ok'] ? '' : (string) $c['note'];
    printf("   %s %-26s %s\n", $c['ok'] ? '✓' : '✗', (string) $c['id'], $note);
}
adapter_verify_stamp($dir, $report);
if ($complete && class_exists('AiCenter')) {
    echo "④ 第二轮：AI 补全真实接口调用…\n";
    $ai2 = static function (string $system, string $user, array $opts): array {
        $r = AiCenter::chat($system, $user, ['feature' => 'adapter_forge_complete', 'max_tokens' => 8000]);
        return ['ok' => (bool) ($r['ok'] ?? false), 'text' => (string) ($r['text'] ?? ''), 'error' => (string) ($r['error'] ?? '')];
    };
    $done = adapter_forge_complete($profile, $dir, $ai2);
    echo "   " . ($done['applied'] ? '✓' : '⊘') . " {$done['note']}（" . number_format($done['bytes']) . " bytes）\n";
    if ($done['applied']) { $report = (array) $done['report']; }
    $todoLeft = is_file($dir . '/plugin.php') ? substr_count((string) file_get_contents($dir . '/plugin.php'), 'TODO') : 0;
    echo "   剩余 TODO(适配)：{$todoLeft} 处\n";
    echo "⑤ 闸门复核：\n";
    foreach ((array) ($report['checks'] ?? []) as $c) {
        printf("   %s %-26s %s\n", $c['ok'] ? '✓' : '✗', (string) $c['id'], $c['ok'] ? '' : (string) $c['note']);
    }
    adapter_verify_stamp($dir, $report);
}

echo "⑥ 结论：{$report['status']}（徽章 {$report['badge']}）→ 报告见 {$dir}/verification-report.json\n";
if ($report['status'] === 'passed') {
    echo "   可进入人审上架：把草稿从 plugins/_drafts 移入 plugins/ 并在后台启用\n";
    exit(0);
}
echo "   未通过：按上面 ✗ 项修复后重跑；草稿保留供人审\n";
exit(1);
