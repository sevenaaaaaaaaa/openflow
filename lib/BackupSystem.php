<?php
declare(strict_types=1);
/**
 * Backup System — 备份、恢复、云同步
 */
require_once __DIR__ . '/../admin/config.php';

// ROOT_DIR：备份时用来定位 admin/config.php、.htaccess 等项目根文件。
// 以前这个常量从未被定义 → createFullBackup() 一调用就抛 "Undefined constant ROOT_DIR"，
// 也就是说后台的「创建备份」按钮从来没成功过。这里补上定义（幂等）。
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));

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

/* ══════════════════════════════════════════════════════════════
 * 定时备份（2026-09-17 补）
 *
 * 背景：后台「⏰ 定时备份」表单以前是个**死表单**——它的 POST action=save_schedule
 * 没有任何处理器，配置存不下来；也没有任何执行者，所以永远不会有自动备份。
 * 这里补上：日程持久化 + 到期判定 + 保留份数清理，由 /api/cron.php（每分钟）驱动。
 * ══════════════════════════════════════════════════════════════ */

function backup_schedule_file(): string { return DATA_DIR . '/backup-schedule.json'; }

function backup_schedule_get(): array {
    $d = json_read(backup_schedule_file());
    return [
        'enabled'   => (bool)($d['enabled'] ?? false),
        'frequency' => in_array($d['frequency'] ?? 'daily', ['daily', 'weekly', 'monthly'], true) ? $d['frequency'] : 'daily',
        'keep'      => max(1, min(30, (int)($d['keep'] ?? 7))),
        'last_run'  => (string)($d['last_run'] ?? ''),
    ];
}

function backup_schedule_set(array $in): array {
    $cfg = backup_schedule_get();
    $cfg['enabled']   = !empty($in['enabled']);
    $cfg['frequency'] = in_array($in['frequency'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $in['frequency'] : $cfg['frequency'];
    $cfg['keep']      = max(1, min(30, (int)($in['keep'] ?? $cfg['keep'])));
    json_write(backup_schedule_file(), $cfg);
    return $cfg;
}

/** 是否到期：按频率比较 last_run */
function backup_is_due(array $cfg, ?int $now = null): bool {
    if (empty($cfg['enabled'])) return false;
    $now = $now ?? time();
    $last = strtotime($cfg['last_run'] ?: '1970-01-01');
    if ($last === false) $last = 0;
    return match ($cfg['frequency']) {
        'weekly'  => ($now - $last) >= 7 * 86400,
        'monthly' => ($now - $last) >= 28 * 86400,
        default   => ($now - $last) >= 86400,
    };
}

/** 清理旧的自动备份，只保留最近 keep 份（只动 auto_ 前缀，手动备份不碰） */
function backup_prune_auto(int $keep): int {
    $dir = defined('BACKUP_DIR') ? BACKUP_DIR : (DATA_DIR . '/backups');
    $items = [];
    foreach (glob($dir . '/auto_*') ?: [] as $p) $items[] = $p;
    usort($items, fn($a, $b) => filemtime($b) <=> filemtime($a));   // 新→旧
    $removed = 0;
    foreach (array_slice($items, max(0, $keep)) as $p) {
        if (is_dir($p)) { backup_rmdir_recursive($p); } else { @unlink($p); }
        $removed++;
    }
    return $removed;
}

/** 到期则执行一次自动备份（cron 调用；返回执行结果用于上报） */
function backup_run_if_due(): array {
    $cfg = backup_schedule_get();
    if (!backup_is_due($cfg)) return ['status' => 'skipped'];
    try {
        $name = 'auto_' . date('Ymd_His');
        $path = BackupSystem::createFullBackup($name);
        $cfg['last_run'] = date('Y-m-d H:i:s');
        json_write(backup_schedule_file(), $cfg);
        $pruned = backup_prune_auto((int)$cfg['keep']);
        // 备份完成 ≠ 备份安全：本地这份和站点同一块盘。配置了异地就顺手推一份出去。
        $offsite = ['status' => 'disabled'];
        try {
            require_once __DIR__ . '/OffsiteBackup.php';
            $offsite = offsite_sync($name);
        } catch (Throwable $e) { $offsite = ['status' => 'error', 'detail' => $e->getMessage()]; }
        return ['status' => 'done', 'file' => basename((string)$path), 'pruned' => $pruned, 'offsite' => $offsite];
    } catch (Throwable $e) {
        return ['status' => 'error', 'detail' => $e->getMessage()];
    }
}

/** 递归删除（备份目录清理用；避免在类外调用私有方法） */
function backup_rmdir_recursive(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? backup_rmdir_recursive($p) : @unlink($p);
    }
    @rmdir($dir);
}
