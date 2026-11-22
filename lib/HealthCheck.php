<?php
/**
 * 上线体检 —— 安装/部署后的运行时前置检查（CLI 与后台共用）
 *
 * 设计原则：只做只读探测 + 一个可写性探针；不依赖数据库；不修改任何数据。
 * 返回值：每行 ['level' => ok|warn|err, 'name' => string, 'detail' => string]
 */
declare(strict_types=1);

if (!function_exists('health_check_rows')) {
    function health_check_rows(string $root): array
    {
        $data = getenv('OF_DATA_DIR') ?: $root . '/data';
        $uploads = getenv('OF_UPLOAD_DIR') ?: $root . '/uploads';
        $rows = [];

        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        $rows[] = [
            'level' => $phpOk ? 'ok' : 'err',
            'name' => 'PHP 版本',
            'detail' => $phpOk ? PHP_VERSION : '需要 8.1+，当前 ' . PHP_VERSION,
        ];

        $need = ['pdo_sqlite', 'gd', 'mbstring', 'curl', 'openssl'];
        $missing = array_values(array_filter($need, static fn (string $e): bool => !extension_loaded($e)));
        $rows[] = [
            'level' => $missing === [] ? 'ok' : 'err',
            'name' => 'PHP 扩展',
            'detail' => $missing === [] ? '齐全' : '缺少：' . implode(' / ', $missing),
        ];

        foreach ([['data 目录可写', $data], ['uploads 目录可写', $uploads]] as [$label, $dir]) {
            $ok = is_dir($dir) && is_writable($dir);
            $probe = false;
            if ($ok) {
                $tmp = $dir . '/.health-probe-' . bin2hex(random_bytes(4));
                $probe = @file_put_contents($tmp, 'ok') === 2;
                @unlink($tmp);
            }
            $rows[] = [
                'level' => $ok && $probe ? 'ok' : 'err',
                'name' => $label,
                'detail' => $ok && $probe ? $dir : '不可写或不存在：' . $dir,
            ];
        }

        $settingsFile = $data . '/settings.json';
        $settings = [];
        if (is_file($settingsFile)) {
            $raw = @file_get_contents($settingsFile);
            $settings = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        }
        $installed = !empty($settings['installed']);
        $rows[] = [
            'level' => $installed ? 'ok' : 'err',
            'name' => '安装状态',
            'detail' => $installed ? '已安装' : '未完成安装：先访问 /setup-wizard.php',
        ];

        $siteUrl = (string) ($settings['site_url'] ?? '');
        $rows[] = [
            'level' => str_starts_with($siteUrl, 'https://') ? 'ok' : 'warn',
            'name' => '站点地址（HTTPS）',
            'detail' => $siteUrl !== '' ? $siteUrl : '未填写',
        ];

        $errLog = $data . '/php-error.log';
        $errSize = is_file($errLog) ? (int) filesize($errLog) : 0;
        $rows[] = [
            'level' => $errSize > 0 ? 'warn' : 'ok',
            'name' => '错误日志',
            'detail' => $errSize > 0 ? '存在 ' . $errSize . ' 字节：' . $errLog : '为空',
        ];

        $versionFile = $root . '/VERSION';
        $version = is_file($versionFile) ? trim((string) @file_get_contents($versionFile)) : '';
        $rows[] = [
            'level' => $version !== '' ? 'ok' : 'err',
            'name' => '版本文件',
            'detail' => $version !== '' ? $version : '缺少 VERSION 文件',
        ];

        return $rows;
    }
}

if (!function_exists('health_check_errors')) {
    function health_check_errors(array $rows): int
    {
        return count(array_filter($rows, static fn (array $r): bool => ($r['level'] ?? '') === 'err'));
    }
}
