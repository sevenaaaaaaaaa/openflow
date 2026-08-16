<?php
/**
 * Backup System — 备份、恢复、云同步
 */
require_once __DIR__ . '/../admin/config.php';

class BackupSystem {
    private static string $backupDir = DATA_DIR . '/backups';

    /**
     * 创建完整备份
     */
    public static function createFullBackup(string $name = ''): string {
        $name = $name ?: date('Y-m-d_His');
        $backupDir = self::$backupDir . '/' . $name;

        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

        // 1. 备份数据文件
        self::copyDir(DATA_DIR, $backupDir . '/data', ['cache', 'backups']);

        // 2. 备份配置文件
        $configFiles = [
            'admin/config.php',
            'composer.json',
            '.htaccess',
        ];
        foreach ($configFiles as $file) {
            $src = ROOT_DIR . '/' . $file;
            if (file_exists($src)) {
                $dst = $backupDir . '/config/' . $file;
                $dir = dirname($dst);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                copy($src, $dst);
            }
        }

        // 3. 创建备份元数据
        $meta = [
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'version' => site_config_get('version', '2.0.0'),
            'files' => self::countFiles($backupDir),
            'size' => self::dirSize($backupDir),
            'modules' => self::getModules(),
        ];
        file_put_contents($backupDir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $backupDir;
    }

    /**
     * 从备份恢复
     */
    public static function restore(string $name): bool {
        $backupDir = self::$backupDir . '/' . $name;

        if (!is_dir($backupDir)) return false;

        // 1. 恢复数据文件
        if (is_dir($backupDir . '/data')) {
            self::copyDir($backupDir . '/data', DATA_DIR, ['cache', 'backups']);
        }

        return true;
    }

    /**
     * 列出所有备份
     */
    public static function listBackups(): array {
        if (!is_dir(self::$backupDir)) return [];

        $backups = [];
        $dirs = array_diff(scandir(self::$backupDir), ['.', '..']);

        foreach ($dirs as $dir) {
            $backupDir = self::$backupDir . '/' . $dir;
            if (!is_dir($backupDir)) continue;

            $metaFile = $backupDir . '/meta.json';
            $meta = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : [];

            $backups[] = [
                'name' => $dir,
                'created_at' => $meta['created_at'] ?? date('Y-m-d H:i:s', filemtime($backupDir)),
                'size' => $meta['size'] ?? self::dirSize($backupDir),
                'files' => $meta['files'] ?? 0,
                'version' => $meta['version'] ?? 'unknown',
            ];
        }

        // 按创建时间倒序
        usort($backups, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $backups;
    }

    /**
     * 删除备份
     */
    public static function deleteBackup(string $name): bool {
        $backupDir = self::$backupDir . '/' . $name;
        if (!is_dir($backupDir)) return false;

        self::removeDir($backupDir);
        return true;
    }

    /**
     * 创建压缩包
     */
    public static function createZip(string $name): ?string {
        $backupDir = self::$backupDir . '/' . $name;
        if (!is_dir($backupDir)) return null;

        $zipFile = self::$backupDir . '/' . $name . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backupDir)
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $relativePath = substr($file->getRealPath(), strlen($backupDir) + 1);
                $zip->addFile($file->getRealPath(), $relativePath);
            }
        }

        $zip->close();
        return $zipFile;
    }

    /**
     * 获取当前系统模块列表
     */
    private static function getModules(): array {
        $modules = [];
        $moduleDir = dirname(__DIR__) . '/lib';
        $files = glob($moduleDir . '/*System.php');
        foreach ($files as $file) {
            $modules[] = basename($file, '.php');
        }
        return $modules;
    }

    /**
     * 复制目录
     */
    private static function copyDir(string $src, string $dst, array $exclude = []): void {
        if (!is_dir($dst)) mkdir($dst, 0755, true);

        $items = array_diff(scandir($src), ['.', '..']);
        foreach ($items as $item) {
            if (in_array($item, $exclude)) continue;

            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;

            if (is_dir($srcPath)) {
                self::copyDir($srcPath, $dstPath, $exclude);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }

    /**
     * 删除目录
     */
    private static function removeDir(string $dir): void {
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * 统计文件数量
     */
    private static function countFiles(string $dir): int {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );
        foreach ($iterator as $file) {
            if (!$file->isDir()) $count++;
        }
        return $count;
    }

    /**
     * 计算目录大小
     */
    private static function dirSize(string $dir): int {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );
        foreach ($iterator as $file) {
            if (!$file->isDir()) $size += $file->getSize();
        }
        return $size;
    }
}
