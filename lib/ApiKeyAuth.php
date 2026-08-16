<?php
/**
 * API Key 认证系统
 * 支持 API Key 生成、验证、权限控制
 */
require_once __DIR__ . '/../admin/config.php';

class ApiKeyAuth {
    private static string $keysFile = DATA_DIR . '/api_keys.json';

    /**
     * 获取所有 API Key
     */
    public static function allKeys(): array {
        return json_read(self::$keysFile);
    }

    /**
     * 创建 API Key
     */
    public static function create(array $data): array {
        $keys = self::allKeys();

        $key = [
            'id' => 'key_' . bin2hex(random_bytes(8)),
            'name' => $data['name'] ?? 'Unnamed',
            'key' => bin2hex(random_bytes(32)),
            'secret' => bin2hex(random_bytes(32)),
            'permissions' => $data['permissions'] ?? ['read'],
            'rate_limit' => (int)($data['rate_limit'] ?? 60),
            'allowed_ips' => $data['allowed_ips'] ?? [],
            'expires_at' => $data['expires_at'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'last_used' => '',
            'request_count' => 0,
            'enabled' => true,
        ];

        $keys[$key['id']] = $key;
        self::save($keys);
        return $key;
    }

    /**
     * 验证 API Key
     */
    public static function verify(string $key, string $secret = ''): ?array {
        $keys = self::allKeys();

        foreach ($keys as $k) {
            if ($k['key'] === $key && $k['enabled']) {
                // 检查过期
                if (!empty($k['expires_at']) && strtotime($k['expires_at']) < time()) {
                    return null;
                }

                // 验证 secret
                if ($secret && $k['secret'] !== $secret) {
                    return null;
                }

                // 更新使用统计
                $keys[$k['id']]['last_used'] = date('Y-m-d H:i:s');
                $keys[$k['id']]['request_count']++;
                self::save($keys);

                return $k;
            }
        }

        return null;
    }

    /**
     * 检查权限
     */
    public static function hasPermission(array $keyData, string $permission): bool {
        return in_array($permission, $keyData['permissions'] ?? []) || in_array('admin', $keyData['permissions'] ?? []);
    }

    /**
     * 删除 API Key
     */
    public static function delete(string $id): bool {
        $keys = self::allKeys();
        if (!isset($keys[$id])) return false;
        unset($keys[$id]);
        self::save($keys);
        return true;
    }

    /**
     * 启用/禁用 API Key
     */
    public static function toggle(string $id, bool $enabled): bool {
        $keys = self::allKeys();
        if (!isset($keys[$id])) return false;
        $keys[$id]['enabled'] = $enabled;
        self::save($keys);
        return true;
    }

    /**
     * 从请求中提取 API Key（Header: X-API-Key 或 Query: api_key）
     */
    public static function extractKey(): ?string {
        // Header
        $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($key) return $key;

        // Query
        $key = $_GET['api_key'] ?? '';
        if ($key) return $key;

        // Bearer token (simplified - API key as bearer)
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * 认证请求并返回 key 数据
     */
    public static function authenticate(): ?array {
        $key = self::extractKey();
        if (!$key) return null;
        return self::verify($key);
    }

    /**
     * 检查 IP 白名单
     */
    public static function checkIP(array $keyData): bool {
        $allowed = $keyData['allowed_ips'] ?? [];
        if (empty($allowed)) return true;

        $ip = self::getClientIP();
        return in_array($ip, $allowed);
    }

    private static function getClientIP(): string {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                return trim($ip);
            }
        }
        return '127.0.0.1';
    }

    private static function save(array $keys): void {
        json_write(self::$keysFile, $keys);
    }
}
