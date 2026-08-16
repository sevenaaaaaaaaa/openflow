<?php
/**
 * Cloudflare API 封装
 * 清缓存 / DNS 管理 / 站点性能与状态 / Zone 信息
 * 基于 Cloudflare API v4（Global API Key 或 API Token）
 */
require_once __DIR__ . '/../admin/config.php';

class CloudflareApi {
    private static string $configFile = DATA_DIR . '/cloudflare.json';

    public static function config(): array {
        $cfg = json_read(self::$configFile);
        return $cfg ?: ['email' => '', 'api_key' => '', 'token' => '', 'zone_id' => '', 'zone_name' => ''];
    }

    public static function saveConfig(array $data): bool {
        return json_write(self::$configFile, $data);
    }

    public static function configured(): bool {
        $cfg = self::config();
        return (!empty($cfg['token']) || (!empty($cfg['email']) && !empty($cfg['api_key']))) && !empty($cfg['zone_id']);
    }

    private static function request(string $method, string $path, ?array $body = null): array {
        $cfg = self::config();
        $headers = ['Content-Type: application/json'];
        if (!empty($cfg['token'])) {
            $headers[] = 'Authorization: Bearer ' . $cfg['token'];
        } elseif (!empty($cfg['email']) && !empty($cfg['api_key'])) {
            $headers[] = 'X-Auth-Email: ' . $cfg['email'];
            $headers[] = 'X-Auth-Key: ' . $cfg['api_key'];
        } else {
            return ['success' => false, 'errors' => [['message' => 'Cloudflare 未配置']]];
        }

        $ch = curl_init('https://api.cloudflare.com/client/v4' . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
        ];
        if ($method === 'POST' && $body !== null) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        } elseif ($method === 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }
        curl_setopt_array($ch, $opts);
        $resp = json_decode(curl_exec($ch), true);
        return $resp ?: ['success' => false, 'errors' => [['message' => 'Cloudflare 请求失败']]];
    }

    // ─── 缓存 ───

    /**
     * 清理全站缓存
     */
    public static function purgeCache(array $urls = []): array {
        $zone = self::config()['zone_id'] ?? '';
        if (!$zone) return ['success' => false, 'errors' => [['message' => 'Zone 未配置']]];
        $body = $urls ? ['files' => $urls] : ['purge_everything' => true];
        return self::request('POST', "/zones/{$zone}/purge_cache", $body);
    }

    /**
     * 清理单个 URL 缓存
     */
    public static function purgeUrl(string $url): array {
        return self::purgeCache([$url]);
    }

    // ─── DNS ───

    /**
     * 获取 DNS 记录
     */
    public static function dnsRecords(string $type = '', string $name = '', int $page = 1): array {
        $zone = self::config()['zone_id'] ?? '';
        if (!$zone) return ['success' => false, 'errors' => [['message' => 'Zone 未配置']]];
        $query = "?per_page=50&page={$page}";
        if ($type) $query .= '&type=' . urlencode($type);
        if ($name) $query .= '&name=' . urlencode($name);
        return self::request('GET', "/zones/{$zone}/dns_records{$query}");
    }

    /**
     * 添加 DNS 记录
     */
    public static function addDnsRecord(string $type, string $name, string $content, int $ttl = 1, bool $proxied = true): array {
        $zone = self::config()['zone_id'] ?? '';
        if (!$zone) return ['success' => false, 'errors' => [['message' => 'Zone 未配置']]];
        return self::request('POST', "/zones/{$zone}/dns_records", [
            'type' => $type, 'name' => $name, 'content' => $content,
            'ttl' => $ttl, 'proxied' => $proxied,
        ]);
    }

    /**
     * 更新 DNS 记录
     */
    public static function updateDnsRecord(string $recordId, array $data): array {
        $zone = self::config()['zone_id'] ?? '';
        if (!$zone) return ['success' => false, 'errors' => [['message' => 'Zone 未配置']]];
        return self::request('PUT', "/zones/{$zone}/dns_records/{$recordId}", $data);
    }

    /**
     * 删除 DNS 记录
     */
    public static function deleteDnsRecord(string $recordId): array {
        $zone = self::config()['zone_id'] ?? '';
        if (!$zone) return ['success' => false, 'errors' => [['message' => 'Zone 未配置']]];
        return self::request('DELETE', "/zones/{$zone}/dns_records/{$recordId}");
    }

    // ─── Zone / 站点信息 ───

    /**
     * 获取 Zone 概览
     */
    public static function zoneOverview(): array {
        $zone = self::config()['zone_id'] ?? '';
        if (!$zone) return ['success' => false, 'errors' => [['message' => 'Zone 未配置']]];
        return self::request('GET', "/zones/{$zone}");
    }

    /**
     * 站点性能分析（Analytics）
     */
    public static function analytics(string $since = '-1hour', string $until = 'now'): array {
        $zone = self::config()['zone_id'] ?? '';
        if (!$zone) return ['success' => false, 'errors' => [['message' => 'Zone 未配置']]];
        return self::request('GET', "/zones/{$zone}/analytics/dashboard?since={$since}&until={$until}");
    }

    // ─── 安全 ───

    /**
     * 获取防火墙规则（安全检查）
     */
    public static function securityLevel(): array {
        $zone = self::config()['zone_id'] ?? '';
        if (!$zone) return ['success' => false, 'errors' => [['message' => 'Zone 未配置']]];
        return self::request('GET', "/zones/{$zone}/settings/security_level");
    }

    /**
     * 设置安全等级
     */
    public static function setSecurityLevel(string $level = 'medium'): array {
        $zone = self::config()['zone_id'] ?? '';
        if (!$zone) return ['success' => false, 'errors' => [['message' => 'Zone 未配置']]];
        return self::request('PATCH', "/zones/{$zone}/settings/security_level", ['value' => $level]);
    }

    // ─── 验证 ───

    /**
     * 验证配置是否正确（获取账户信息）
     */
    public static function verify(): array {
        return self::request('GET', '/user');
    }
}
