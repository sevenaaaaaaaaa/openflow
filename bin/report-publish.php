#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * 报告发布 CLI —— 给每日构建 / 部署脚本用
 *
 *   php bin/report-publish.php <slug> <源目录或文件> [版本说明]
 *   php bin/report-publish.php <slug> --create <名称> [--visibility=members]
 *
 * 例：php bin/report-publish.php openflow "/www/ops/openflow/report/pages" "每日构建 2026-10-28"
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ReportHub.php';

$args = array_slice($argv, 1);
$slug = $args[0] ?? '';
if ($slug === '') {
    fwrite(STDERR, "用法: report-publish.php <slug> <源路径> [说明]\n");
    exit(1);
}

if (($args[1] ?? '') === '--create') {
    $name = $args[2] ?? $slug;
    $vis = 'members';
    foreach ($args as $a) { if (str_starts_with($a, '--visibility=')) $vis = substr($a, 13); }
    $r = rh_create_project($slug, $name, '', $vis, 'cli');
    echo $r['ok'] ? "created {$slug}\n" : "create failed: {$r['error']}\n";
    exit($r['ok'] ? 0 : 2);
}

$src = $args[1] ?? '';
if ($src === '' || !file_exists($src)) {
    fwrite(STDERR, "源路径不存在: {$src}\n");
    exit(1);
}
$note = $args[2] ?? '';
$r = rh_publish($slug, $src, $note, 'cli');
if (!$r['ok']) { fwrite(STDERR, "publish failed: {$r['error']}\n"); exit(2); }
echo "published {$slug} v{$r['version']} — {$r['files']} files, " . number_format($r['bytes'] / 1024) . " KB, entry={$r['entry']}\n";
