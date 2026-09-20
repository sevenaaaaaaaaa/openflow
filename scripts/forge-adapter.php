#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * 适配一条候选（CLI 端到端）：源接入 → 合成草稿 → 验证闸门 → 徽章
 *
 *   php scripts/forge-adapter.php binwiederhier/ntfy            # 模板兜底（离线可用）
 *   php scripts/forge-adapter.php binwiederhier/ntfy --ai       # 用 AiCenter 生成（需模型额度）
 *   php scripts/forge-adapter.php binwiederhier/ntfy --ai --complete   # 第二轮补全真实调用
 *   php scripts/forge-adapter.php https://github.com/n8n-io/n8n --out=plugins/_drafts
 *
 * 编排逻辑在 lib/AdapterPipeline.php（自助提交入口与 cron worker 用的是同一条），
 * 本文件只负责命令行参数与输出。
 *
 * 产出目录：plugins/_drafts/<id>/{plugin.json, plugin.php, tests/…, verification-report.json}
 * 退出码：0 = passed · 1 = needs-review/blocked（草稿仍会保留，供人审）· 2 = 参数/网络错误
 */

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AdapterPipeline.php';

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

$ai = null;
if ($useAi && class_exists('AiCenter')) {
    $ai = static function (string $system, string $user, array $opts): array {
        $max = (int) ($opts['max_tokens'] ?? 2200);
        $r = AiCenter::chat($system, $user, ['feature' => 'adapter_forge', 'max_tokens' => $max]);
        return ['ok' => (bool) ($r['ok'] ?? false), 'text' => (string) ($r['text'] ?? ''), 'error' => (string) ($r['error'] ?? '')];
    };
} elseif ($useAi) {
    fwrite(STDERR, "⚠ 未找到 AiCenter，回落到模板兜底\n");
}

$r = adapter_pipeline_run($source, [
    'out_dir'  => $outDir,
    'ai'       => $ai,
    'complete' => $complete,
]);

foreach ((array) $r['steps'] as $i => $s) {
    printf("%s %s\n", ['①', '②', '③', '④', '⑤', '⑥'][$i] ?? '·', $s);
}

if ((string) $r['status'] === '' && (string) $r['error'] !== '') {
    fwrite(STDERR, "✗ 在「{$r['stage']}」阶段失败：{$r['error']}\n");
    exit(2);
}

echo "闸门明细：\n";
foreach ((array) $r['checks'] as $c) {
    printf("   %s %-26s %s\n", !empty($c['ok']) ? '✓' : '✗', (string) ($c['id'] ?? '?'),
        !empty($c['ok']) ? '' : (string) ($c['note'] ?? ''));
}
if ((int) $r['todos'] > 0) echo "   剩余 TODO(适配)：{$r['todos']} 处（有 TODO 时闸门只会给 needs-review）\n";

echo "结论：{$r['status']}（徽章 {$r['badge']}）";
echo (string) $r['dir'] !== '' ? " → 报告见 {$r['dir']}/verification-report.json\n" : "\n";

if ((string) $r['status'] === 'passed') {
    echo "   可进入人审上架：后台 /xmp/ecosystem 或 php scripts/review-adapters.php\n";
    exit(0);
}
if ((string) $r['error'] !== '') echo "   {$r['error']}\n";
echo "   未通过：按上面 ✗ 项修复后重跑；草稿保留供人审\n";
exit(1);
