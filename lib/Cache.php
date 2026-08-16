<?php
/**
 * Cache System — 支持 Redis / 文件缓存 / 内存缓存
 * 
 * 用法：
 *   Cache::remember('key', 3600, fn() => expensive_query());
 *   Cache::forget('key');
 *   Cache::flush();
 */
declare(strict_types=1);

class Cache {
    private static ?object $driver = null;
    private static array $stats = ['hits' => 0, 'misses' => 0, 'sets' => 0];

    /**
     * 获取缓存驱动
     */
    private static function driver(): object {
        if (self::$driver !== null) return self::$driver;

        // 1. 尝试 Redis（优先从 settings.json 读取配置）
        if (class_exists('Redis')) {
            try {
                $redis = new Redis();
                $config = self::redisConfig();
                $host = $config['host'] ?? '127.0.0.1';
                $port = (int)($config['port'] ?? 6379);
                $connect = @$redis->connect($host, $port, 1.0);
                if ($connect) {
                    // 密码认证
                    if (!empty($config['password'])) {
                        @$redis->auth($config['password']);
                    }
                    // 选择数据库
                    $db = (int)($config['database'] ?? 0);
                    if ($db > 0) {
                        @$redis->select($db);
                    }
                    self::$driver = new RedisCache($redis);
                    return self::$driver;
                }
            } catch (\Throwable $e) {}
        }

        // 2. 文件缓存
        self::$driver = new FileCache();
        return self::$driver;
    }

    /**
     * 读取 Redis 配置（优先 settings.json，其次环境变量）
     */
    private static function redisConfig(): array {
        // 从 settings.json 读取
        $settingsFile = (defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/../data') . '/settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
            if (!empty($settings['redis_host'])) {
                return [
                    'host' => $settings['redis_host'] ?? '127.0.0.1',
                    'port' => $settings['redis_port'] ?? 6379,
                    'password' => $settings['redis_password'] ?? '',
                    'database' => $settings['redis_database'] ?? 0,
                ];
            }
        }

        // 回退到环境变量
        return [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? '',
            'database' => $_ENV['REDIS_DATABASE'] ?? 0,
        ];
    }

    /**
     * 测试 Redis 连接
     */
    public static function testRedis(): array {
        if (!class_exists('Redis')) {
            return ['ok' => false, 'error' => 'Redis 扩展未安装'];
        }
        try {
            $redis = new Redis();
            $config = self::redisConfig();
            $connect = @$redis->connect($config['host'], (int)$config['port'], 1.0);
            if (!$connect) {
                return ['ok' => false, 'error' => '连接失败: ' . $config['host'] . ':' . $config['port']];
            }
            if (!empty($config['password'])) {
                @$redis->auth($config['password']);
            }
            $info = $redis->info('server');
            return [
                'ok' => true,
                'version' => $info['redis_version'] ?? 'unknown',
                'host' => $config['host'],
                'port' => $config['port'],
                'database' => $config['database'] ?? 0,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取缓存，不存在则执行回调并缓存
     */
    public static function remember(string $key, int $ttl, callable $fn) {
        $value = self::get($key);
        if ($value !== null) {
            self::$stats['hits']++;
            return $value;
        }

        self::$stats['misses']++;
        $value = $fn();
        self::set($key, $value, $ttl);
        return $value;
    }

    /**
     * 获取缓存
     */
    public static function get(string $key) {
        return self::driver()->get($key);
    }

    /**
     * 设置缓存
     */
    public static function set(string $key, mixed $value, int $ttl = 3600): bool {
        self::$stats['sets']++;
        return self::driver()->set($key, $value, $ttl);
    }

    /**
     * 删除缓存
     */
    public static function forget(string $key): bool {
        return self::driver()->delete($key);
    }

    /**
     * 清空所有缓存
     */
    public static function flush(): bool {
        return self::driver()->flush();
    }

    /**
     * 检查缓存是否存在
     */
    public static function has(string $key): bool {
        return self::driver()->has($key);
    }

    /**
     * 批量获取
     */
    public static function getMultiple(array $keys): array {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = self::get($key);
        }
        return $results;
    }

    /**
     * 批量设置
     */
    public static function setMultiple(array $items, int $ttl = 3600): bool {
        $ok = true;
        foreach ($items as $key => $value) {
            if (!self::set($key, $value, $ttl)) $ok = false;
        }
        return $ok;
    }

    /**
     * 获取缓存统计
     */
    public static function stats(): array {
        return self::$stats;
    }

    /**
     * 带 tag 的缓存（简化版）
     */
    public static function rememberWithTag(string $tag, string $key, int $ttl, callable $fn) {
        $cacheKey = "tag:{$tag}:{$key}";
        return self::remember($cacheKey, $ttl, $fn);
    }

    /**
     * 清除指定 tag 的缓存
     */
    public static function flushTag(string $tag): bool {
        return self::driver()->deleteByPrefix("tag:{$tag}:");
    }
}

/**
 * Redis 缓存驱动
 */
class RedisCache {
    public function __construct(private Redis $redis) {}

    public function get(string $key) {
        $data = $this->redis->get("openflow:{$key}");
        return $data ? unserialize($data) : null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool {
        return $this->redis->setex("openflow:{$key}", $ttl, serialize($value));
    }

    public function delete(string $key): bool {
        return $this->redis->del("openflow:{$key}") > 0;
    }

    public function flush(): bool {
        $keys = $this->redis->keys("openflow:*");
        if ($keys) {
            $this->redis->del($keys);
        }
        return true;
    }

    public function has(string $key): bool {
        return $this->redis->exists("openflow:{$key}") > 0;
    }

    public function deleteByPrefix(string $prefix): bool {
        $keys = $this->redis->keys("openflow:{$prefix}*");
        if ($keys) {
            $this->redis->del($keys);
        }
        return true;
    }
}

/**
 * 文件缓存驱动
 */
class FileCache {
    private string $dir;

    public function __construct() {
        $this->dir = DATA_DIR . '/cache';
        if (!is_dir($this->dir)) mkdir($this->dir, 0755, true);
    }

    public function get(string $key) {
        $file = $this->file($key);
        if (!file_exists($file)) return null;

        $data = json_decode(file_get_contents($file), true);
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool {
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time(),
        ];
        return file_put_contents($this->file($key), json_encode($data)) !== false;
    }

    public function delete(string $key): bool {
        $file = $this->file($key);
        return file_exists($file) ? unlink($file) : true;
    }

    public function flush(): bool {
        $files = glob($this->dir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }

    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    public function deleteByPrefix(string $prefix): bool {
        $files = glob($this->dir . '/' . md5($prefix) . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }

    private function file(string $key): string {
        return $this->dir . '/' . md5($key) . '.cache';
    }
}
