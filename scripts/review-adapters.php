#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * 适配人审队列（CLI）
 *
 *   php scripts/review-adapters.php                      # 刷新队列并打印
 *   php scripts/review-adapters.php --approve <id> [--note="..."]
 *   php scripts/review-adapters.php --reject  <id> [--note="..."]
 *   php scripts/review-adapters.php --publish <id>       # 上架（默认 enabled=false）
 *
 * 退出码：0 成功；1 失败（不可上架/目标冲突等）；2 参数错误
 */

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AdapterIntake.php';
require_once __DIR__ . '/../lib/AdapterReview.php';

$root = dirname(__DIR__);
$drafts = $root . '/plugins/_drafts';
$plugins = $root . '/plugins';
$queueFile = DATA_DIR . '/ecosystem/review-queue.json';
$registry = DATA_DIR . '/plugins.json';

$args = array_slice($argv, 1);
$action = '';
$id = '';
$note = '';
foreach ($args as $a) {
    if ($a === '--approve' || $a === '--reject' || $a === '--publish') { $action = ltrim($a, '-'); continue; }
    if (str_starts_with($a, '--note=')) { $note = (string) substr($a, 7); continue; }
    if ($id === '' && !str_starts_with($a, '--')) $id = $a;
}

/** 刷新队列（保留已有的人审决定） */
function refresh_queue(string $drafts, string $plugins, string $queueFile): array
{
    $entries = adapter_review_scan($drafts);
    $old = adapter_review_queue_read($queueFile);
    $prev = [];
    foreach ((array) ($old['items'] ?? []) as $e) $prev[(string) ($e['id'] ?? '')] = $e;
    foreach ($entries as $i => $e) {
        $p = $prev[$e['id']] ?? null;
        if ($p) {
            $entries[$i]['decision'] = (string) ($p['decision'] ?? '');
            $entries[$i]['note'] = (string) ($p['note'] ?? '');
        }
        $entries[$i]['published'] = is_dir(rtrim($plugins, '/') . '/' . $e['id']);
    }
    adapter_review_queue_write($entries, $queueFile);
    return $entries;
}

if ($action === '' ) {
    $entries = refresh_queue($drafts, $plugins, $queueFile);
    echo "适配人审队列（" . count($entries) . " 条）\n\n";
    printf("  %-30s %-13s %-10s %-5s %-6s %s\n", 'ID', '状态', '许可证', 'TODO', '行数', '扩权建议 / 备注');
    foreach ($entries as $e) {
        $sug = [];
        foreach ((array) $e['suggestions'] as $s) $sug[] = $s['surface'];
        $tail = $sug !== [] ? ('建议扩权：' . implode(',', $sug)) : (string) ($e['decision'] !== '' ? ('人审：' . $e['decision'] . ' ' . $e['note']) : ((array) $e['failed'] !== [] ? ('失败项：' . implode(',', (array) $e['failed'])) : '—'));
        printf("  %-30s %-13s %-10s %-5d %-6d %s\n", $e['id'], $e['status'], $e['license'] ?: '—', (int) $e['todos'], (int) $e['lines'], mb_substr($tail, 0, 60));
    }
    echo "\n队列：data/ecosystem/review-queue.json\n上架：php scripts/review-adapters.php --publish <id>（needs-review 需先 --approve）\n";
    exit(0);
}

if ($id === '') { fwrite(STDERR, "✗ 需要 <id>（先跑 php scripts/review-adapters.php 看列表）\n"); exit(2); }
$entries = refresh_queue($drafts, $plugins, $queueFile);
$entry = null;
foreach ($entries as $e) if ($e['id'] === $id) { $entry = $e; break; }
if ($entry === null) { fwrite(STDERR, "✗ 未找到草稿：{$id}\n"); exit(2); }

if ($action === 'approve' || $action === 'reject') {
    $updated = adapter_review_decide($id, $action, $note, $queueFile);
    if ($updated === []) { fwrite(STDERR, "✗ 记录失败\n"); exit(1); }
    echo ($action === 'approve' ? '✓ 已批准' : '✓ 已拒绝') . "：{$id}" . ($note !== '' ? "（{$note}）" : '') . "\n";
    exit(0);
}

if ($action === 'publish') {
    $res = adapter_review_publish($entry, $plugins, $registry);
    if (!($res['ok'] ?? false)) { fwrite(STDERR, '✗ 上架失败：' . $res['error'] . "\n"); exit(1); }
    adapter_review_decide($id, 'published', $note !== '' ? $note : '已上架', $queueFile);
    echo "✓ 已上架：plugins/{$id}（" . count((array) $res['written']) . " 个文件）\n";
    echo "  已写入注册表 data/plugins.json（enabled=false）→ 到后台「插件」页手动启用\n";
    exit(0);
}

fwrite(STDERR, "✗ 未知操作：{$action}\n");
exit(2);
