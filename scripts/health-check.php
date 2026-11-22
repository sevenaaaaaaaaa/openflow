#!/usr/bin/env php
<?php
/**
 * 上线体检（CLI）
 * 用法：php scripts/health-check.php
 * 退出码：0 = 全部通过；1 = 有需要处理的问题。
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$data = getenv('OF_DATA_DIR') ?: $root . '/data';
$uploads = getenv('OF_UPLOAD_DIR') ?: $root . '/uploads';
$issues = 0;

$print = static function (string $level, string $name, string $detail = ''): void {
    $mark = $level === 'ok' ? '✓' : ($level === 'warn' ? '!' : '✗');
    echo $mark, ' ', $name, $detail !== '' ? '：' . $detail : '', PHP_EOL;
};

$check = static function (bool $ok, string $name, string $okMsg, string $badMsg, bool $warnOnly = false) use ($print, &$issues): void {
    if ($ok) {
        $print('ok', $name, $okMsg);
        return;
    }
    $print($warnOnly ? 'warn' : 'err', $name, $badMsg);
    if (!$warnOnly) {
        $issues++;
    }
};

$check(version_compare(PHP_VERSION, '8.1.0', '>='), 'PHP 版本', PHP_VERSION, '需要 8.1+，当前 ' . PHP_VERSION);

$need = ['pdo_sqlite', 'gd', 'mbstring', 'curl', 'openssl'];
$missing = array_values(array_filter($need, static fn (string $e): bool => !extension_loaded($e)));
$check($missing === [], 'PHP 扩展', '齐全', '缺少：' . implode(' / ', $missing));

$check(is_dir($data) && is_writable($data), 'data 目录可写', $data, '不可写或不存在：' . $data);
$check(is_dir($uploads) && is_writable($uploads), 'uploads 目录可写', $uploads, '不可写或不存在：' . $uploads);

$settingsFile = $data . '/settings.json';
$settings = [];
if (is_file($settingsFile)) {
    $raw = file_get_contents($settingsFile);
    $settings = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
}
$installed = !empty($settings['installed']);
$siteUrl = (string) ($settings['site_url'] ?? '');
$check($installed, '安装状态', '已安装', '未完成安装：先访问 /setup-wizard.php');

$https = str_starts_with($siteUrl, 'https://');
$check($https, '站点地址（HTTPS）', $siteUrl, '站点地址不是 https：' . ($siteUrl !== '' ? $siteUrl : '未填写'), true);

$errLog = $data . '/php-error.log';
$errSize = is_file($errLog) ? (int) filesize($errLog) : 0;
if ($errSize > 0) {
    $print('warn', '错误日志', '存在 ' . $errSize . ' 字节，建议查看 ' . $errLog);
} else {
    $print('ok', '错误日志', '为空');
}

$check(is_file($root . '/VERSION'), '版本文件', trim((string) @file_get_contents($root . '/VERSION')), '缺少 VERSION 文件');

echo PHP_EOL, $issues === 0 ? '全部通过。' : '有 ' . $issues . ' 项需要处理。', PHP_EOL;
exit($issues === 0 ? 0 : 1);
