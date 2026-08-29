<?php
/**
 * OpenFlow Plugin System — hooks, actions, filters engine
 *
 * Usage in plugins:
 *   PluginSystem::add_action('admin_sidebar_menu', function() { echo '<a href="...">My Plugin</a>'; });
 *   PluginSystem::add_filter('article_save', function($data) { $data['extra'] = 'value'; return $data; });
 */

class PluginSystem {
    private static array $actions = [];
    private static array $filters = [];
    private static array $plugins = [];
    private static bool $initialized = false;

    // ─── Hook Registration ───
    public static function add_action(string $hook, callable $callback, int $priority = 10): void {
        self::$actions[$hook][$priority][] = $callback;
    }

    /**
     * 触发动作钩子。
     *
     * 旁路契约：任何插件回调抛出的异常都在此被捕获并记录，绝不冒泡到调用方——
     * 业务主流程不能因为某个插件写坏而中断。
     *
     * @param string $hook    钩子名
     * @param mixed  ...$args 透传给回调的参数
     */
    public static function do_action(string $hook, mixed ...$args): void {
        if (empty(self::$actions[$hook])) return;
        ksort(self::$actions[$hook]);
        foreach (self::$actions[$hook] as $callbacks) {
            foreach ($callbacks as $cb) {
                try { call_user_func_array($cb, $args); }
                catch (\Throwable $e) { self::hook_error($hook, $e); }
            }
        }
    }

    public static function add_filter(string $hook, callable $callback, int $priority = 10): void {
        self::$filters[$hook][$priority][] = $callback;
    }

    /**
     * 应用过滤器钩子。
     *
     * 旁路契约：某个回调抛错时跳过该回调、保留上一轮的值继续，不中断主流程。
     *
     * @param string $hook    钩子名
     * @param mixed  $value   待过滤的值
     * @param mixed  ...$args 附加上下文
     * @return mixed 过滤后的值（全部回调失败时返回原值）
     */
    public static function apply_filters(string $hook, mixed $value, mixed ...$args): mixed {
        if (empty(self::$filters[$hook])) return $value;
        ksort(self::$filters[$hook]);
        foreach (self::$filters[$hook] as $callbacks) {
            foreach ($callbacks as $cb) {
                try { $value = call_user_func_array($cb, array_merge([$value], $args)); }
                catch (\Throwable $e) { self::hook_error($hook, $e); }
            }
        }
        return $value;
    }

    /** 钩子回调异常落盘（自身绝不抛出） */
    private static function hook_error(string $hook, \Throwable $e): void {
        try {
            $dir = defined('DATA_DIR') ? DATA_DIR : (__DIR__ . '/../data');
            $line = date('Y-m-d H:i:s') . " [hook:{$hook}] " . $e->getMessage()
                  . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
            @file_put_contents($dir . '/plugin-errors.log', $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $ignore) {}
    }

    // ─── Plugin Loading ───
    public static function load_plugins(): void {
        if (self::$initialized) return;
        self::$initialized = true;

        $pluginsDir = __DIR__ . '/../plugins';
        $registry = json_read(__DIR__ . '/../data/plugins.json');

        if (!is_dir($pluginsDir)) return;
        $dirs = glob($pluginsDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $pluginId = basename($dir);
            $manifest = $dir . '/plugin.json';
            $entry = $dir . '/plugin.php';
            if (!file_exists($manifest) || !file_exists($entry)) continue;

            $meta = json_decode(file_get_contents($manifest), true);
            if (!$meta || empty($meta['id'])) continue;

            // Check if plugin is enabled in registry
            $enabled = $registry['enabled'][$pluginId] ?? ($meta['enabled_by_default'] ?? true);

            self::$plugins[$pluginId] = $meta;
            if ($enabled) {
                require_once $entry;
                self::do_action('plugin_loaded', $pluginId, $meta);
            }
        }
    }

    public static function get_plugins(): array { return self::$plugins; }

    // ─── Plugin Management ───
    public static function install_plugin(string $source): array {
        // Support GitHub shorthand: "user/repo" or full URL
        $pluginsDir = __DIR__ . '/../plugins';
        $pluginId = '';

        if (preg_match('/^[\w-]+\/[\w-]+$/', $source)) {
            // GitHub shorthand — build download URL
            $apiUrl = "https://api.github.com/repos/{$source}/releases/latest";
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => 'OpenFlow-CMS', CURLOPT_TIMEOUT => 15]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http !== 200) return ['ok' => false, 'error' => "无法获取 {$source} 的最新版本"];
            $release = json_decode($resp, true);
            $zipUrl = $release['zipball_url'] ?? '';
            if (empty($zipUrl)) return ['ok' => false, 'error' => '未找到可下载的发布包'];
            $pluginId = $source;
            return self::install_from_url($zipUrl, $pluginId);
        }

        if (filter_var($source, FILTER_VALIDATE_URL)) {
            return self::install_from_url($source, basename(parse_url($source, PHP_URL_PATH)));
        }

        return ['ok' => false, 'error' => '不支持的来源格式，请使用 GitHub user/repo 或直接 ZIP URL'];
    }

    private static function install_from_url(string $url, string $pluginId): array {
        $pluginsDir = __DIR__ . '/../plugins';
        $tmp = sys_get_temp_dir() . '/openflow-plugin-' . md5($url) . '.zip';

        $ch = curl_init($url);
        $fp = fopen($tmp, 'w');
        curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'OpenFlow-CMS']);
        curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fp);

        if ($http !== 200) { unlink($tmp); return ['ok' => false, 'error' => "下载失败 (HTTP {$http})"]; }

        // Extract ZIP
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) { unlink($tmp); return ['ok' => false, 'error' => '无法解压 ZIP']; }

        // Check if plugin.json exists in archive
        $hasManifest = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (basename($name) === 'plugin.json') { $hasManifest = true; break; }
        }
        if (!$hasManifest) { $zip->close(); unlink($tmp); return ['ok' => false, 'error' => 'ZIP 中未找到 plugin.json']; }

        // Extract to plugins directory
        $targetDir = $pluginsDir . '/' . $pluginId;
        if (is_dir($targetDir)) {
            // Remove existing
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) { $f->isFile() ? unlink($f->getRealPath()) : rmdir($f->getRealPath()); }
        }
        mkdir($targetDir, 0755, true);

        // The ZIP might contain a root directory; handle both cases
        $rootDir = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $parts = explode('/', $name);
            if (count($parts) > 1 && empty($rootDir)) $rootDir = $parts[0];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === $rootDir . '/' || $name === $rootDir . '/') continue;
            $relPath = $rootDir ? substr($name, strlen($rootDir) + 1) : $name;
            if (empty($relPath)) continue;
            $dest = $targetDir . '/' . $relPath;
            if (substr($name, -1) === '/') { mkdir($dest, 0755, true); }
            else { copy('zip://' . $tmp . '#' . $name, $dest); }
        }
        $zip->close();
        unlink($tmp);

        // Save to registry
        $manifest = json_decode(file_get_contents($targetDir . '/plugin.json'), true);
        $registry = json_read(__DIR__ . '/../data/plugins.json');
        $registry['installed'][$pluginId] = ['id' => $pluginId, 'name' => $manifest['name'] ?? $pluginId, 'version' => $manifest['version'] ?? '1.0.0', 'installed_at' => date('Y-m-d H:i:s')];
        $registry['enabled'][$pluginId] = true;
        json_write(__DIR__ . '/../data/plugins.json', $registry);

        return ['ok' => true, 'plugin_id' => $pluginId, 'name' => $manifest['name'] ?? $pluginId];
    }

    public static function uninstall_plugin(string $pluginId): bool {
        $pluginsDir = __DIR__ . '/../plugins/' . $pluginId;
        if (!is_dir($pluginsDir)) return false;

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginsDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isFile() ? unlink($f->getRealPath()) : rmdir($f->getRealPath()); }
        rmdir($pluginsDir);

        $registry = json_read(__DIR__ . '/../data/plugins.json');
        unset($registry['installed'][$pluginId], $registry['enabled'][$pluginId]);
        json_write(__DIR__ . '/../data/plugins.json', $registry);
        return true;
    }

    public static function toggle_plugin(string $pluginId, bool $enabled): bool {
        $registry = json_read(__DIR__ . '/../data/plugins.json');
        if (!isset($registry['installed'][$pluginId])) return false;
        $registry['enabled'][$pluginId] = $enabled;
        json_write(__DIR__ . '/../data/plugins.json', $registry);
        return true;
    }
}
