<?php
/**
 * Image Metadata System — 图片 SEO 元数据管理
 * 支持 alt 标签、title、描述等
 */
require_once __DIR__ . '/../admin/config.php';

class ImageMeta {
    private static string $file = DATA_DIR . '/image-meta.json';

    public static function all(): array {
        return json_read(self::$file);
    }

    public static function save(array $meta): bool {
        return json_write(self::$file, $meta);
    }

    /**
     * 获取图片的 alt 文本
     */
    public static function getAlt(string $path): string {
        $meta = self::all();
        return $meta[$path]['alt'] ?? '';
    }

    /**
     * 获取图片的完整元数据
     */
    public static function get(string $path): array {
        $meta = self::all();
        return $meta[$path] ?? [];
    }

    /**
     * 设置图片的 alt 文本
     */
    public static function setAlt(string $path, string $alt): bool {
        $meta = self::all();
        if (!isset($meta[$path])) {
            $meta[$path] = ['path' => $path];
        }
        $meta[$path]['alt'] = trim($alt);
        $meta[$path]['updated_at'] = date('Y-m-d H:i:s');
        return self::save($meta);
    }

    /**
     * 设置图片的完整元数据
     */
    public static function set(string $path, array $data): bool {
        $meta = self::all();
        $meta[$path] = array_merge($meta[$path] ?? ['path' => $path], $data);
        $meta[$path]['updated_at'] = date('Y-m-d H:i:s');
        return self::save($meta);
    }

    /**
     * 删除图片元数据
     */
    public static function delete(string $path): bool {
        $meta = self::all();
        unset($meta[$path]);
        return self::save($meta);
    }

    /**
     * 批量设置 alt 文本
     */
    public static function batchSetAlt(array $items): bool {
        $meta = self::all();
        foreach ($items as $path => $alt) {
            if (!isset($meta[$path])) {
                $meta[$path] = ['path' => $path];
            }
            $meta[$path]['alt'] = trim($alt);
            $meta[$path]['updated_at'] = date('Y-m-d H:i:s');
        }
        return self::save($meta);
    }

    /**
     * 扫描目录，自动为没有 alt 的图片生成默认 alt
     */
    public static function autoGenerateAlts(string $dir = ''): int {
        $meta = self::all();
        $count = 0;
        $uploadDir = UPLOAD_DIR . ($dir ? '/' . $dir : '');

        if (!is_dir($uploadDir)) return 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadDir)
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;

            $relativePath = 'uploads/' . ($dir ? $dir . '/' : '') . $file->getFilename();
            
            if (!isset($meta[$relativePath]) || empty($meta[$relativePath]['alt'])) {
                // 从文件名生成默认 alt
                $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $name = preg_replace('/[-_]/', ' ', $name);
                $name = preg_replace('/\d{8}_\d{6}_[a-f0-9]+/', '', $name); // 移除时间戳
                $name = trim($name);
                if (empty($name)) $name = 'Image';

                $meta[$relativePath] = [
                    'path' => $relativePath,
                    'alt' => $name,
                    'auto_generated' => true,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $count++;
            }
        }

        self::save($meta);
        return $count;
    }

    /**
     * 获取没有 alt 文本的图片列表
     */
    public static function getMissingAlts(): array {
        $meta = self::all();
        $missing = [];

        foreach ($meta as $path => $data) {
            if (empty($data['alt'])) {
                $missing[] = $data;
            }
        }

        return $missing;
    }

    /**
     * 输出 img 标签（带 alt）
     */
    public static function img(string $path, string $class = '', string $style = ''): string {
        $alt = self::getAlt($path);
        $classAttr = $class ? ' class="' . htmlspecialchars($class) . '"' : '';
        $styleAttr = $style ? ' style="' . htmlspecialchars($style) . '"' : '';
        return '<img src="' . htmlspecialchars($path) . '" alt="' . htmlspecialchars($alt) . '"' . $classAttr . $styleAttr . ' loading="lazy">';
    }
}
