#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * 异地备份 CLI
 *
 *   php scripts/offsite-backup.php --status     # 看当前配置与上次结果
 *   php scripts/offsite-backup.php --test       # 上传一个探针对象，验证 endpoint/bucket/密钥
 *   php scripts/offsite-backup.php --latest     # 打包最近一次自动备份并上传
 *   php scripts/offsite-backup.php --name=auto_20260920_030001
 *
 * 密钥优先从环境变量读：OF_OFFSITE_KEY / OF_OFFSITE_SECRET
 */

$ROOT = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/admin/config.php';
require_once $ROOT . '/lib/OffsiteBackup.php';
require_once $ROOT . '/lib/BackupSystem.php';

$args = $argv ?? [];
$has = static fn(string $f): bool => in_array($f, $args, true);
$valueOf = static function (string $prefix) use ($args): string {
    foreach ($args as $a) if (str_starts_with($a, $prefix)) return substr($a, strlen($prefix));
    return '';
};

$cfg = offsite_config();
$problem = offsite_config_problem($cfg);

if ($has('--status') || count($args) <= 1) {
    $st = offsite_status();
    echo "异地备份配置\n";
    printf("  开启      %s\n", $cfg['enabled'] ? '是' : '否');
    printf("  endpoint  %s\n", $cfg['endpoint'] !== '' ? $cfg['endpoint'] : '（未设）');
    printf("  bucket    %s\n", $cfg['bucket'] !== '' ? $cfg['bucket'] : '（未设）');
    printf("  region    %s\n", $cfg['region']);
    printf("  prefix    %s\n", $cfg['prefix']);
    printf("  密钥      %s\n", $cfg['access_key'] !== '' && $cfg['secret_key'] !== ''
        ? '已配置（' . (getenv('OF_OFFSITE_KEY') ? '来自环境变量' : '来自 data/offsite-backup.json') . '）' : '未配置');
    printf("  可用性    %s\n", $problem === '' ? '配置完整' : '不可用：' . $problem);
    echo "\n上次结果\n";
    printf("  最近尝试  %s\n", $st['last_attempt'] !== '' ? $st['last_attempt'] : '从未');
    printf("  最近成功  %s\n", $st['last_ok'] !== '' ? $st['last_ok'] : '从未');
    if ($st['key'] !== '')   printf("  对象      %s（%.1f MB）\n", $st['key'], $st['bytes'] / 1048576);
    if ($st['error'] !== '') printf("  最近错误  %s\n", $st['error']);
    if (!$has('--status')) echo "\n用法见文件头注释（--test / --latest / --name=…）\n";
    exit($problem === '' ? 0 : 1);
}

if ($has('--test')) {
    $r = offsite_probe();
    if (!empty($r['ok'])) { echo "✓ 连通正常，已写入 {$r['key']}\n"; exit(0); }
    fwrite(STDERR, "✗ 连不通：{$r['error']}\n");
    exit(1);
}

$name = $valueOf('--name=');
if ($name === '' && $has('--latest')) {
    $dirs = glob((defined('BACKUP_DIR') ? BACKUP_DIR : DATA_DIR . '/backups') . '/auto_*') ?: [];
    $dirs = array_values(array_filter($dirs, 'is_dir'));
    usort($dirs, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
    $name = $dirs !== [] ? basename($dirs[0]) : '';
    if ($name === '') { fwrite(STDERR, "✗ 没有找到任何 auto_ 备份，先跑一次备份\n"); exit(1); }
}

if ($name === '') { fwrite(STDERR, "✗ 请给 --latest 或 --name=<备份名>\n"); exit(2); }

echo "正在打包并上传 {$name} …\n";
$r = offsite_sync($name);
if (($r['status'] ?? '') === 'done') {
    printf("✓ 已上传：%s（%.1f MB）\n", $r['key'], ((int) ($r['bytes'] ?? 0)) / 1048576);
    exit(0);
}
fwrite(STDERR, "✗ 失败（{$r['status']}）：" . ($r['detail'] ?? '') . "\n");
exit(1);
