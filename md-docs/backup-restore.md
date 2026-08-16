# 备份、迁移、恢复、云同步

## 一、备份系统

### 1. 本地备份

```php
// lib/BackupSystem.php
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
        self::copyDir(DATA_DIR, $backupDir . '/data');
        
        // 2. 备份上传文件
        if (is_dir(UPLOAD_DIR)) {
            self::copyDir(UPLOAD_DIR, $backupDir . '/uploads');
        }
        
        // 3. 创建备份元数据
        $meta = [
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'version' => '2.0.0',
            'files' => self::countFiles($backupDir),
            'size' => self::dirSize($backupDir),
        ];
        file_put_contents($backupDir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT));
        
        // 4. 创建压缩包
        $zipFile = self::$backupDir . '/' . $name . '.zip';
        self::createZip($backupDir, $zipFile);
        
        return $zipFile;
    }
    
    /**
     * 创建增量备份
     */
    public static function createIncrementalBackup(string $lastBackup = ''): string {
        $name = date('Y-m-d_His');
        $backupDir = self::$backupDir . '/' . $name;
        
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
        
        // 获取上次备份时间
        $lastTime = $lastBackup ? filemtime(self::$backupDir . '/' . $lastBackup) : 0;
        
        // 只备份修改过的文件
        self::copyDirModified(DATA_DIR, $backupDir . '/data', $lastTime);
        
        return $backupDir;
    }
    
    /**
     * 列出所有备份
     */
    public static function listBackups(): array {
        $backups = [];
        $files = glob(self::$backupDir . '/*.zip');
        
        foreach ($files as $file) {
            $name = basename($file, '.zip');
            $metaFile = self::$backupDir . '/' . $name . '/meta.json';
            $meta = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : [];
            
            $backups[] = [
                'name' => $name,
                'file' => $file,
                'size' => filesize($file),
                'created_at' => $meta['created_at'] ?? date('Y-m-d H:i:s', filemtime($file)),
            ];
        }
        
        return array_reverse($backups);
    }
    
    /**
     * 删除备份
     */
    public static function deleteBackup(string $name): bool {
        $backupDir = self::$backupDir . '/' . $name;
        $zipFile = self::$backupDir . '/' . $name . '.zip';
        
        if (is_dir($backupDir)) self::removeDir($backupDir);
        if (file_exists($zipFile)) unlink($zipFile);
        
        return true;
    }
    
    // 辅助函数
    private static function copyDir(string $src, string $dst): void {
        if (!is_dir($dst)) mkdir($dst, 0755, true);
        $files = array_diff(scandir($src), ['.', '..']);
        foreach ($files as $file) {
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                self::copyDir($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }
    
    private static function copyDirModified(string $src, string $dst, int $since): void {
        if (!is_dir($dst)) mkdir($dst, 0755, true);
        $files = array_diff(scandir($src), ['.', '..']);
        foreach ($files as $file) {
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                self::copyDirModified($srcPath, $dstPath, $since);
            } elseif (filemtime($srcPath) > $since) {
                copy($srcPath, $dstPath);
            }
        }
    }
    
    private static function removeDir(string $dir): void {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    private static function countFiles(string $dir): int {
        $count = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if (!$file->isDir()) $count++;
        }
        return $count;
    }
    
    private static function dirSize(string $dir): int {
        $size = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if (!$file->isDir()) $size += $file->getSize();
        }
        return $size;
    }
    
    private static function createZip(string $source, string $destination): void {
        $zip = new ZipArchive();
        $zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source));
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $relativePath = substr($file->getRealPath(), strlen($source) + 1);
                $zip->addFile($file->getRealPath(), $relativePath);
            }
        }
        
        $zip->close();
    }
}
```

## 二、云备份支持

### 1. WebDAV 备份

```php
// lib/backup/WebDAVBackup.php
class WebDAVBackup {
    private string $server;
    private string $username;
    private string $password;
    
    public function __construct(string $server, string $username, string $password) {
        $this->server = rtrim($server, '/');
        $this->username = $username;
        $this->password = $password;
    }
    
    public function upload(string $localFile, string $remotePath): bool {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->server . '/' . $remotePath);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, fopen($localFile, 'r'));
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localFile));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
    
    public function download(string $remotePath, string $localFile): bool {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->server . '/' . $remotePath);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            file_put_contents($localFile, $content);
            return true;
        }
        return false;
    }
    
    public function list(string $path = ''): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->server . '/' . $path);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Depth: 1']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        // 解析 XML 响应
        return $this->parsePropfindResponse($response);
    }
}
```

### 2. Dropbox 备份

```php
// lib/backup/DropboxBackup.php
class DropboxBackup {
    private string $accessToken;
    
    public function __construct(string $accessToken) {
        $this->accessToken = $accessToken;
    }
    
    public function upload(string $localFile, string $remotePath): bool {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://content.dropboxapi.com/2/files/upload');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Dropbox-API-Arg: {"path":"' . $remotePath . '","mode":"overwrite"}',
            'Content-Type: application/octet-stream',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($localFile));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true) !== null;
    }
    
    public function download(string $remotePath, string $localFile): bool {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://content.dropboxapi.com/2/files/download');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Dropbox-API-Arg: {"path":"' . $remotePath . '"}',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $content = curl_exec($ch);
        curl_close($ch);
        
        if ($content) {
            file_put_contents($localFile, $content);
            return true;
        }
        return false;
    }
}
```

### 3. Google Drive 备份

```php
// lib/backup/GoogleDriveBackup.php
class GoogleDriveBackup {
    private string $accessToken;
    
    public function __construct(string $accessToken) {
        $this->accessToken = $accessToken;
    }
    
    public function upload(string $localFile, string $fileName, string $folderId = ''): bool {
        $fileMetadata = [
            'name' => $fileName,
        ];
        if ($folderId) {
            $fileMetadata['parents'] = [$folderId];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'metadata' => json_encode($fileMetadata),
            'file' => fopen($localFile, 'r'),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true) !== null;
    }
}
```

### 4. 百度网盘备份

```php
// lib/backup/BaiduPCSBackup.php
class BaiduPCSBackup {
    private string $accessToken;
    
    public function __construct(string $accessToken) {
        $this->accessToken = $accessToken;
    }
    
    public function upload(string $localFile, string $remotePath): bool {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://pan.baidu.com/rest/2.0/pcs/superfile2');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->accessToken,
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'method' => 'upload',
            'path' => $remotePath,
            'file' => new CURLFile($localFile),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true) !== null;
    }
}
```

## 三、恢复系统

```php
// lib/RestoreSystem.php
class RestoreSystem {
    /**
     * 从备份恢复
     */
    public static function restore(string $backupName): bool {
        $backupDir = BackupSystem::$backupDir . '/' . $backupName;
        $zipFile = BackupSystem::$backupDir . '/' . $backupName . '.zip';
        
        // 如果是 zip 文件，先解压
        if (file_exists($zipFile)) {
            $zip = new ZipArchive();
            $zip->open($zipFile);
            $zip->extractTo(BackupSystem::$backupDir . '/' . $backupName);
            $zip->close();
        }
        
        if (!is_dir($backupDir)) return false;
        
        // 1. 恢复数据文件
        self::copyDir($backupDir . '/data', DATA_DIR);
        
        // 2. 恢复上传文件
        if (is_dir($backupDir . '/uploads')) {
            self::copyDir($backupDir . '/uploads', UPLOAD_DIR);
        }
        
        return true;
    }
    
    /**
     * 从云备份恢复
     */
    public static function restoreFromCloud(string $provider, string $remotePath): bool {
        $downloader = self::getCloudDownloader($provider);
        $tempFile = sys_get_temp_dir() . '/openflow_restore.zip';
        
        if ($downloader->download($remotePath, $tempFile)) {
            $backupName = 'cloud_restore_' . date('Y-m-d_His');
            $backupDir = BackupSystem::$backupDir . '/' . $backupName;
            
            $zip = new ZipArchive();
            $zip->open($tempFile);
            $zip->extractTo($backupDir);
            $zip->close();
            
            unlink($tempFile);
            return self::restore($backupName);
        }
        
        return false;
    }
    
    private static function getCloudDownloader(string $provider) {
        switch ($provider) {
            case 'webdav':
                return new WebDAVBackup(
                    $_ENV['WEBDAV_SERVER'],
                    $_ENV['WEBDAV_USERNAME'],
                    $_ENV['WEBDAV_PASSWORD']
                );
            case 'dropbox':
                return new DropboxBackup($_ENV['DROPBOX_TOKEN']);
            case 'google':
                return new GoogleDriveBackup($_ENV['GOOGLE_TOKEN']);
            case 'baidu':
                return new BaiduPCSBackup($_ENV['BAIDU_TOKEN']);
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }
}
```

## 四、自动备份定时任务

```php
// bin/auto-backup.php
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/BackupSystem.php';

// 创建备份
$backupFile = BackupSystem::createFullBackup();

// 上传到云
$providers = ['webdav', 'dropbox']; // 配置的云服务
foreach ($providers as $provider) {
    $uploader = RestoreSystem::getCloudUploader($provider);
    $uploader->upload($backupFile, 'backups/' . basename($backupFile));
}

// 清理旧备份（保留 30 天）
$backups = BackupSystem::listBackups();
foreach ($backups as $backup) {
    if (strtotime($backup['created_at']) < strtotime('-30 days')) {
        BackupSystem::deleteBackup($backup['name']);
    }
}

echo "Backup completed: {$backupFile}\n";
```

### Crontab 配置

```bash
# 每天凌晨 2 点执行备份
0 2 * * * /usr/bin/php /path/to/bin/auto-backup.php

# 每周日凌晨 3 点执行完整备份
0 3 * * 0 /usr/bin/php /path/to/bin/backup-full.php
```

## 五、管理界面

```php
// admin/backup.php
// 提供以下功能：
// 1. 一键备份
// 2. 备份列表
// 3. 下载备份
// 4. 恢复备份
// 5. 云服务配置
// 6. 定时备份设置
```

## 六、最佳实践

1. **定期测试恢复** — 每月测试一次备份恢复
2. **多地备份** — 本地 + 云端 + 异地
3. **加密敏感数据** — 备份前加密
4. **监控备份状态** — 失败时发送通知
5. **保留历史版本** — 至少保留 30 天
