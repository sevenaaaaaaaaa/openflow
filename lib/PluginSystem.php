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

    /* ════════════════════════════════════════════════════════════════
     * 插件开放 API（批C）：路由 / 后台菜单 / 前台插槽 / 定时任务
     * 让第三方开发者不改核心代码就能扩展四套能力。
     * ════════════════════════════════════════════════════════════════ */

    // ─── C1. API 路由：/api/plugin/{插件ID}/{路径} ───
    private static array $apiRoutes = [];   // pluginId => [ "METHOD path" => ['cb'=>, 'auth'=>] ]

    /**
     * 注册插件 API 端点。
     *   PluginSystem::register_api_route('my-plugin', 'GET', 'stats', fn($req) => ['n' => 42]);
     *   → GET /api/plugin/my-plugin/stats 返回 {"ok":true,"data":{"n":42}}
     *
     * @param string $pluginId 插件 ID（必须与 plugin.json 一致）
     * @param string $method   GET / POST / PUT / DELETE / ANY
     * @param string $path     端点路径（不含插件 ID），支持 {param} 占位：'report/{id}'
     * @param callable $cb     function (array $req): mixed
     *                         $req = ['params'=>路径参数, 'query'=>GET, 'body'=>POST/JSON, 'member'=>当前会员|null]
     *                         返回数组 → JSON data；返回 ApiResponse 可自定义状态码
     * @param array  $opts     ['auth' => 'admin'|'member'|'none'(默认)]  'admin' 需后台登录，'member' 需前台会员登录
     */
    public static function register_api_route(string $pluginId, string $method, string $path, callable $cb, array $opts = []): void {
        $path = trim($path, '/');
        self::$apiRoutes[$pluginId][strtoupper($method) . ' ' . $path] = [
            'cb' => $cb,
            'auth' => $opts['auth'] ?? 'none',
        ];
    }

    /** 分发插件 API 请求（由 api/plugin.php 调用）。返回 false 表示无此路由。 */
    public static function dispatch_api_route(string $pluginId, string $method, string $path): bool {
        $routes = self::$apiRoutes[$pluginId] ?? [];
        $path = trim($path, '/');
        $method = strtoupper($method);
        foreach ($routes as $key => $route) {
            [$rm, $rp] = explode(' ', $key, 2);
            if ($rm !== 'ANY' && $rm !== $method) continue;
            // 路径匹配：支持 {param} 段
            $params = [];
            $rSegs = $rp === '' ? [] : explode('/', $rp);
            $aSegs = $path === '' ? [] : explode('/', $path);
            if (count($rSegs) !== count($aSegs)) continue;
            $match = true;
            foreach ($rSegs as $i => $seg) {
                if (preg_match('/^\{([a-z_]+)\}$/i', $seg, $m)) $params[$m[1]] = urldecode($aSegs[$i]);
                elseif ($seg !== $aSegs[$i]) { $match = false; break; }
            }
            if (!$match) continue;

            // 鉴权
            if ($route['auth'] === 'admin' && !(function_exists('is_logged_in') && is_logged_in())) {
                http_response_code(401); echo json_encode(['ok' => false, 'error' => '需要管理员登录']); return true;
            }
            $member = null;
            if ($route['auth'] === 'member') {
                if (function_exists('member_current')) $member = member_current();
                if (!$member) { http_response_code(401); echo json_encode(['ok' => false, 'error' => '需要会员登录']); return true; }
            } elseif (function_exists('member_current')) {
                try { $member = member_current(); } catch (\Throwable $e) {}
            }

            $req = [
                'params' => $params,
                'query'  => $_GET,
                'body'   => $_POST ?: (json_decode(file_get_contents('php://input'), true) ?: []),
                'member' => $member,
                'method' => $method,
            ];
            try {
                $result = call_user_func($route['cb'], $req);
                if ($result instanceof PluginApiResponse) { $result->send(); return true; }
                echo json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                self::hook_error("api:{$pluginId}/{$path}", $e);
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => '插件内部错误（已记录）']);
            }
            return true;
        }
        return false;
    }

    // ─── C2. 后台菜单：主导航注册 ───
    private static array $adminMenus = [];  // id => item

    /**
     * 注册后台主导航项（渲染在侧栏「插件」区）。
     *   PluginSystem::register_admin_menu([
     *     'id'    => 'my-plugin',            // 必填
     *     'label' => '我的插件',              // 必填
     *     'icon'  => 'bolt',                 // 选填，图标名（见 includes/nav-icons.php），默认 puzzle
     *     'href'  => '/xmp/plugin/my-plugin',// 选填；不填则自动指向插件设置页
     *     'sort'  => 50,                     // 选填，数字小的排前
     *   ]);
     */
    public static function register_admin_menu(array $item): void {
        if (empty($item['id']) || empty($item['label'])) return;
        $item['icon'] = $item['icon'] ?? 'puzzle';
        $item['sort'] = (int)($item['sort'] ?? 50);
        $item['href'] = $item['href'] ?? ('/xmp/plugin/' . $item['id']);
        self::$adminMenus[$item['id']] = $item;
    }

    /** 排序后的后台插件菜单（admin-nav.php 渲染用） */
    public static function get_admin_menus(): array {
        $items = array_values(self::$adminMenus);
        usort($items, fn($a, $b) => $a['sort'] <=> $b['sort']);
        return $items;
    }

    // ─── C2b. 插件设置页：/xmp/plugin/{id} ───
    private static array $adminPages = [];  // pluginId => callable

    /**
     * 注册插件后台设置页（在 /xmp/plugin/{id} 渲染，已带后台外壳与登录保护）。
     *   PluginSystem::register_admin_page('my-plugin', function () {
     *       echo '<div class="card">…设置表单…</div>';
     *   });
     */
    public static function register_admin_page(string $pluginId, callable $cb): void {
        self::$adminPages[$pluginId] = $cb;
    }

    /** 渲染插件设置页（admin/plugin-page.php 调用）。无注册返回 false。 */
    public static function render_admin_page(string $pluginId): bool {
        if (empty(self::$adminPages[$pluginId])) return false;
        try { call_user_func(self::$adminPages[$pluginId]); }
        catch (\Throwable $e) {
            self::hook_error("admin-page:{$pluginId}", $e);
            echo '<div class="card" style="color:var(--danger)">插件页面渲染出错（已记录到 plugin-errors.log）</div>';
        }
        return true;
    }

    // ─── C3. 前台插槽与资产 ───
    private static array $frontSlots = [];   // slot => [priority => cb[]]
    private static array $frontAssets = [];  // ['css'=>[url...], 'js'=>[url...]]

    /**
     * 注册前台插槽内容。可用插槽：
     *   'head'          <head> 内（meta/样式/统计代码）
     *   'body_end'      </body> 前（脚本/浮层）
     *   'footer_before' 页脚上方（横幅/订阅框）
     *   'article_after' 文章正文后（相关推荐/作者卡）
     *   PluginSystem::register_front_slot('footer_before', fn() => print '<div>…</div>');
     */
    public static function register_front_slot(string $slot, callable $cb, int $priority = 10): void {
        self::$frontSlots[$slot][$priority][] = $cb;
    }

    /** 渲染插槽（前台外壳在对应位置调用）。$ctx 为页面上下文（如 ['article'=>…]）。 */
    public static function render_front_slot(string $slot, array $ctx = []): void {
        if (empty(self::$frontSlots[$slot])) return;
        ksort(self::$frontSlots[$slot]);
        foreach (self::$frontSlots[$slot] as $cbs) {
            foreach ($cbs as $cb) {
                try { call_user_func($cb, $ctx); }
                catch (\Throwable $e) { self::hook_error("slot:{$slot}", $e); }
            }
        }
    }

    /**
     * 注册前台资产（全站页面自动加载）。
     *   PluginSystem::register_front_asset('css', '/plugins/my-plugin/assets/style.css');
     *   PluginSystem::register_front_asset('js',  '/plugins/my-plugin/assets/app.js', ['defer' => true]);
     */
    public static function register_front_asset(string $type, string $url, array $opts = []): void {
        if (!in_array($type, ['css', 'js'], true) || $url === '') return;
        self::$frontAssets[$type][] = ['url' => $url, 'opts' => $opts];
    }

    /** 输出某类资产标签（of_head_assets/of_footer 调用） */
    public static function render_front_assets(string $type): void {
        foreach (self::$frontAssets[$type] ?? [] as $a) {
            $url = htmlspecialchars($a['url'], ENT_QUOTES);
            if ($type === 'css') echo '<link rel="stylesheet" href="' . $url . '">' . "\n";
            elseif ($type === 'js') echo '<script src="' . $url . '"' . (!empty($a['opts']['defer']) ? ' defer' : '') . '></script>' . "\n";
        }
    }

    // ─── C4. 插件定时任务 ───
    private static array $schedules = [];   // name => ['interval'=>秒, 'cb'=>, 'plugin'=>]

    /**
     * 注册定时任务（由 api/cron.php 每分钟驱动，按间隔到点执行）。
     *   PluginSystem::register_schedule('my-plugin.daily-report', 'daily', function () { … });
     *
     * @param string $name     任务名（建议带插件前缀，全局唯一）
     * @param string $interval 'every'|'5min'|'15min'|'hourly'|'6hours'|'daily'|'weekly'
     */
    public static function register_schedule(string $name, string $interval, callable $cb): void {
        $secs = ['every' => 0, '5min' => 300, '15min' => 900, 'hourly' => 3600, '6hours' => 21600, 'daily' => 86400, 'weekly' => 604800][$interval] ?? null;
        if ($secs === null || $name === '') return;
        self::$schedules[$name] = ['interval' => $secs, 'cb' => $cb];
    }

    /** 执行到点的插件任务（api/cron.php 末尾调用）。返回执行摘要。 */
    public static function run_schedules(): array {
        if (empty(self::$schedules)) return ['ran' => 0];
        $stateFile = (defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/../data') . '/plugin-schedule.json';
        $state = function_exists('json_read') ? json_read($stateFile) : [];
        $now = time();
        $ran = 0; $errors = 0;
        foreach (self::$schedules as $name => $task) {
            $last = (int)($state[$name] ?? 0);
            if ($task['interval'] > 0 && $now - $last < $task['interval']) continue;
            try {
                call_user_func($task['cb']);
                $ran++;
            } catch (\Throwable $e) {
                $errors++;
                self::hook_error("schedule:{$name}", $e);
            }
            $state[$name] = $now;
        }
        if (function_exists('json_write')) json_write($stateFile, $state);
        return ['ran' => $ran, 'errors' => $errors, 'registered' => count(self::$schedules)];
    }
}

/**
 * 插件 API 自定义响应（需要非 200 状态码或原始输出时 return 它）。
 *   return new PluginApiResponse(['x' => 1], 201);
 *   return PluginApiResponse::error('参数缺失', 422);
 */
class PluginApiResponse {
    public function __construct(
        public mixed $data = null,
        public int $status = 200,
        public bool $isError = false,
    ) {}
    /** 成功响应：PluginApiResponse::json(['count' => 42]) */
    public static function json(mixed $data, int $status = 200): self {
        return new self($data, $status, false);
    }
    public static function error(string $msg, int $status = 400): self {
        return new self(['error' => $msg], $status, true);
    }
    public function send(): void {
        http_response_code($this->status);
        if ($this->isError) echo json_encode(['ok' => false, 'error' => $this->data['error'] ?? 'error'], JSON_UNESCAPED_UNICODE);
        else echo json_encode(['ok' => true, 'data' => $this->data], JSON_UNESCAPED_UNICODE);
    }
}
