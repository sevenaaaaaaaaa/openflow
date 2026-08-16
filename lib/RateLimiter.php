<?php
/**
 * API 限流器
 * 基于 Redis 或文件实现的请求限流
 */
require_once __DIR__ . '/Cache.php';

class RateLimiter {
    /**
     * 检查是否超出限流
     * @param string $key 限流标识（如 API Key ID 或 IP）
     * @param int $limit 每分钟最大请求数
     * @param int $window 时间窗口（秒）
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public static function check(string $key, int $limit = 60, int $window = 60): array {
        $cacheKey = "rate:{$key}";
        $now = time();
        $windowStart = $now - $window;

        // 获取当前窗口内的请求记录
        $requests = Cache::get($cacheKey) ?: [];

        // 清理过期记录
        $requests = array_filter($requests, fn($ts) => $ts > $windowStart);
        $current = count($requests);

        $allowed = $current < $limit;
        $remaining = max(0, $limit - $current - 1);
        $reset = $now + $window;

        if ($allowed) {
            $requests[] = $now;
            Cache::set($cacheKey, $requests, $window + 10);
        }

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'limit' => $limit,
            'reset' => $reset,
        ];
    }

    /**
     * 发送限流响应头
     */
    public static function sendHeaders(array $result): void {
        header("X-RateLimit-Limit: {$result['limit']}");
        header("X-RateLimit-Remaining: {$result['remaining']}");
        header("X-RateLimit-Reset: {$result['reset']}");
    }

    /**
     * 限流失败时的响应
     */
    public static function reject(array $result): void {
        self::sendHeaders($result);
        http_response_code(429);
        header('Retry-After: ' . ($result['reset'] - time()));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'rate_limit_exceeded',
            'message' => '请求过于频繁，请稍后重试',
            'retry_after' => $result['reset'] - time(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 中间件：自动限流检查
     */
    public static function throttle(string $key, int $limit = 60, int $window = 60): void {
        $result = self::check($key, $limit, $window);
        self::sendHeaders($result);
        if (!$result['allowed']) {
            self::reject($result);
        }
    }
}
