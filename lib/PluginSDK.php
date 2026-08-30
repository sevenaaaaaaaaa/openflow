<?php
/**
 * PluginSDK —— 插件作者的稳定门面（C2）
 *
 * PluginSystem 是引擎，能力都在，但每个插件都要自己重写同一批样板：
 * 拼 data/plugins/<id>/ 路径、mkdir、json_write、手搓 curl、手写侧栏
 * <a> 标签和 active 判断、后台配置页从 require config.php 开始抄一遍。
 * 示例插件里这四样全齐。SDK 把它们收成一个对象，插件正文只写业务。
 *
 * 用法（plugins/<id>/plugin.php）：
 *
 *     require_once __DIR__ . '/../../lib/PluginSDK.php';
 *     $p = plugin('my-plugin');
 *
 *     $p->on('crm_deal_won', function ($email, $lead) use ($p) {
 *         $p->log("成交：{$email} ¥" . ($lead['value'] ?? 0));
 *     });
 *
 *     $p->filter('article_save_before', fn($a) => $a);
 *
 *     $p->menu('我的插件', $p->pageUrl('view.php'), '🧩', 'settings');
 *
 * 设计约束：
 *   - 不引入任何新框架、新依赖，全部是现有能力的薄封装；
 *   - 所有回调都在 SDK 层再包一次 try/catch，异常写进「插件自己的」日志，
 *     PluginSystem 的全局日志只说哪个钩子炸了，说不出是哪个插件干的；
 *   - SDK 出问题绝不能反过来搞挂宿主，任何写盘失败都是静默降级。
 */

if (!class_exists('PluginSystem')) {
    require_once __DIR__ . '/PluginSystem.php';
}

class PluginContext
{
    private string $id;
    private array $meta;
    private ?array $configCache = null;

    public function __construct(string $id)
    {
        $this->id = $id;
        $all = class_exists('PluginSystem') ? PluginSystem::get_plugins() : [];
        $this->meta = $all[$id] ?? [];
    }

    // ── 身份 ──────────────────────────────────────────

    public function id(): string { return $this->id; }

    public function name(): string
    {
        return (string)($this->meta['name'] ?? $this->id);
    }

    public function version(): string
    {
        return (string)($this->meta['version'] ?? '0.0.0');
    }

    public function meta(string $key = '', $default = null)
    {
        if ($key === '') return $this->meta;
        return $this->meta[$key] ?? $default;
    }

    // ── 钩子 ──────────────────────────────────────────

    /**
     * 监听动作。回调异常会被记进本插件日志，不会冒泡。
     */
    public function on(string $hook, callable $cb, int $priority = 10): self
    {
        PluginSystem::add_action($hook, $this->wrapAction($hook, $cb), $priority);
        return $this;
    }

    /**
     * 注册过滤器。回调异常时原样返回上一个值——过滤器坏掉不该丢数据。
     */
    public function filter(string $hook, callable $cb, int $priority = 10): self
    {
        PluginSystem::add_filter($hook, $this->wrapFilter($hook, $cb), $priority);
        return $this;
    }

    private function wrapAction(string $hook, callable $cb): callable
    {
        return function (...$args) use ($hook, $cb) {
            try { return $cb(...$args); }
            catch (\Throwable $e) { $this->logError($hook, $e); return null; }
        };
    }

    private function wrapFilter(string $hook, callable $cb): callable
    {
        return function ($value, ...$args) use ($hook, $cb) {
            try { return $cb($value, ...$args); }
            catch (\Throwable $e) { $this->logError($hook, $e); return $value; }
        };
    }

    // ── 配置（每个插件一个独立命名空间，互不打架）──────

    public function dir(): string
    {
        $base = defined('DATA_DIR') ? DATA_DIR : (__DIR__ . '/../data');
        return $base . '/plugins/' . $this->id;
    }

    public function configFile(): string { return $this->dir() . '/config.json'; }

    public function config(): array
    {
        if ($this->configCache === null) {
            $f = $this->configFile();
            $this->configCache = is_file($f)
                ? (json_decode((string)@file_get_contents($f), true) ?: [])
                : [];
        }
        return $this->configCache;
    }

    public function get(string $key, $default = null)
    {
        $c = $this->config();
        return array_key_exists($key, $c) ? $c[$key] : $default;
    }

    /** 写单个配置项。返回是否落盘成功（失败只是降级，不抛）。 */
    public function set(string $key, $value): bool
    {
        $c = $this->config();
        $c[$key] = $value;
        return $this->setConfig($c);
    }

    public function setConfig(array $config): bool
    {
        $this->configCache = $config;
        if (!$this->ensureDir()) return false;
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return @file_put_contents($this->configFile(), $json, LOCK_EX) !== false;
    }

    private function ensureDir(): bool
    {
        $d = $this->dir();
        if (is_dir($d)) return true;
        return @mkdir($d, 0755, true) || is_dir($d);
    }

    // ── 日志（按插件分文件，自动截断，永不抛）──────────

    public function logFile(): string { return $this->dir() . '/plugin.log'; }

    public function log(string $message, string $level = 'info'): void
    {
        try {
            if (!$this->ensureDir()) return;
            $f = $this->logFile();
            // 超过 512KB 就砍掉前一半，插件日志不该把磁盘吃满
            if (is_file($f) && filesize($f) > 512 * 1024) {
                $keep = substr((string)@file_get_contents($f), -256 * 1024);
                @file_put_contents($f, "…（已截断）\n" . $keep, LOCK_EX);
            }
            $line = date('Y-m-d H:i:s') . " [{$level}] " . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL;
            @file_put_contents($f, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) { /* 日志失败绝不影响业务 */ }
    }

    public function logError(string $hook, \Throwable $e): void
    {
        $this->log("钩子 {$hook} 抛出 " . get_class($e) . '：' . $e->getMessage()
                   . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(), 'error');
    }

    /** 读最近 N 行日志，给配置页显示用 */
    public function tailLog(int $lines = 50): array
    {
        $f = $this->logFile();
        if (!is_file($f)) return [];
        $all = explode("\n", trim((string)@file_get_contents($f)));
        return array_slice($all, -$lines);
    }

    // ── 后台入口 ──────────────────────────────────────

    /** 插件自带页面的 URL（相对站点根） */
    public function pageUrl(string $file = 'view.php', array $query = []): string
    {
        $u = '/plugins/' . rawurlencode($this->id) . '/' . ltrim($file, '/');
        return $query ? $u . '?' . http_build_query($query) : $u;
    }

    /**
     * 往后台侧栏挂一个入口。
     * 自己判断权限与 active 态，插件不用再抄那段三元表达式。
     */
    public function menu(string $label, string $url = '', string $icon = '🧩', string $perm = 'settings'): self
    {
        $url = $url ?: $this->pageUrl();
        $id  = $this->id;
        $this->on('admin_sidebar_menu', function ($current = '') use ($label, $url, $icon, $perm, $id) {
            if ($perm !== '' && function_exists('has_perm') && !has_perm($perm)) return;
            $active = ((string)$current === $id) ? 'active' : '';
            echo '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" class="' . $active
               . '" style="padding-left:44px;font-size:13px">'
               . htmlspecialchars($icon . ' ' . $label) . '</a>';
        });
        return $this;
    }

    // ── 出站 HTTP（带超时与日志，不再手搓 curl）────────

    /**
     * POST JSON。永远返回数组，不抛异常。
     * @return array ['ok'=>bool, 'status'=>int, 'body'=>string, 'error'=>string]
     */
    public function httpPost(string $url, array $data, array $headers = [], int $timeout = 5): array
    {
        return $this->http('POST', $url, $data, $headers, $timeout);
    }

    public function httpGet(string $url, array $headers = [], int $timeout = 5): array
    {
        return $this->http('GET', $url, null, $headers, $timeout);
    }

    private function http(string $method, string $url, ?array $data, array $headers, int $timeout): array
    {
        $out = ['ok' => false, 'status' => 0, 'body' => '', 'error' => ''];
        if (!preg_match('#^https?://#i', $url)) {
            $out['error'] = 'URL 不合法';
            $this->log("HTTP {$method} 拒绝：URL 不合法 {$url}", 'error');
            return $out;
        }
        if (!function_exists('curl_init')) {
            $out['error'] = '环境无 curl';
            return $out;
        }
        try {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => max(1, $timeout),
                CURLOPT_CONNECTTIMEOUT => max(1, min(5, $timeout)),
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            ];
            if ($method === 'POST') {
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_POSTFIELDS] = json_encode($data ?? [], JSON_UNESCAPED_UNICODE);
            }
            curl_setopt_array($ch, $opts);
            $body = curl_exec($ch);
            $out['status'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($body === false) $out['error'] = curl_error($ch);
            else $out['body'] = (string)$body;
            curl_close($ch);
            $out['ok'] = $out['error'] === '' && $out['status'] >= 200 && $out['status'] < 300;
            if (!$out['ok']) {
                $this->log("HTTP {$method} {$url} 失败 status={$out['status']} {$out['error']}", 'error');
            }
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            $this->logError("http:{$method}", $e);
        }
        return $out;
    }

    // ── 配置页脚手架 ──────────────────────────────────

    /**
     * 渲染一个标准配置表单，并处理保存。
     *
     * $fields: [key => ['label'=>, 'type'=>'text|textarea|checkbox|select|number',
     *                   'hint'=>, 'options'=>[值=>文案], 'placeholder'=>]]
     *
     * 页面外壳（admin_header/sidebar/footer）由调用方负责，SDK 只吐表单，
     * 这样插件想在同一页里放别的东西也不会被挡住。
     * @return string 保存后的提示，无操作时为空串
     */
    public function renderSettings(array $fields): string
    {
        $notice = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['__plugin_save'])) {
            if (function_exists('csrf_verify')) csrf_verify();
            $c = $this->config();
            foreach ($fields as $key => $def) {
                $type = $def['type'] ?? 'text';
                if ($type === 'checkbox')      $c[$key] = !empty($_POST[$key]);
                elseif ($type === 'number')    $c[$key] = (float)($_POST[$key] ?? 0);
                else                           $c[$key] = trim((string)($_POST[$key] ?? ''));
            }
            $notice = $this->setConfig($c) ? '配置已保存' : '配置保存失败：无法写入 ' . $this->dir();
            $this->log($notice);
        }

        $c = $this->config();
        echo '<form method="post" class="card">';
        if (function_exists('csrf_field')) echo csrf_field();
        echo '<input type="hidden" name="__plugin_save" value="1">';
        foreach ($fields as $key => $def) {
            $label = htmlspecialchars($def['label'] ?? $key);
            $hint  = isset($def['hint']) ? '<span class="hint" style="color:var(--text-3);font-size:12px"> · '
                                           . htmlspecialchars($def['hint']) . '</span>' : '';
            $val   = $c[$key] ?? ($def['default'] ?? '');
            $ph    = htmlspecialchars((string)($def['placeholder'] ?? ''));
            $k     = htmlspecialchars($key, ENT_QUOTES);
            echo '<div class="field" style="margin-bottom:14px">';
            switch ($def['type'] ?? 'text') {
                case 'checkbox':
                    echo '<label style="display:flex;gap:8px;align-items:center">'
                       . '<input type="checkbox" name="' . $k . '" value="1"' . (!empty($val) ? ' checked' : '') . '> '
                       . $label . $hint . '</label>';
                    break;
                case 'textarea':
                    echo '<label>' . $label . $hint . '</label>'
                       . '<textarea name="' . $k . '" rows="5" placeholder="' . $ph . '" style="width:100%">'
                       . htmlspecialchars((string)$val) . '</textarea>';
                    break;
                case 'select':
                    echo '<label>' . $label . $hint . '</label><select name="' . $k . '" style="width:100%">';
                    foreach (($def['options'] ?? []) as $ov => $ol) {
                        echo '<option value="' . htmlspecialchars((string)$ov, ENT_QUOTES) . '"'
                           . ((string)$val === (string)$ov ? ' selected' : '') . '>'
                           . htmlspecialchars((string)$ol) . '</option>';
                    }
                    echo '</select>';
                    break;
                case 'number':
                    echo '<label>' . $label . $hint . '</label>'
                       . '<input type="number" step="any" name="' . $k . '" value="'
                       . htmlspecialchars((string)$val, ENT_QUOTES) . '" style="width:100%">';
                    break;
                default:
                    echo '<label>' . $label . $hint . '</label>'
                       . '<input type="text" name="' . $k . '" value="'
                       . htmlspecialchars((string)$val, ENT_QUOTES) . '" placeholder="' . $ph . '" style="width:100%">';
            }
            echo '</div>';
        }
        echo '<button class="btn btn-primary">保存配置</button></form>';
        return $notice;
    }
}

/**
 * 取一个插件上下文。同一个 id 复用同一个实例，配置只读一次。
 */
function plugin(string $id): PluginContext
{
    static $pool = [];
    if (!isset($pool[$id])) $pool[$id] = new PluginContext($id);
    return $pool[$id];
}
