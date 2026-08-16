<?php
/**
 * 审计日志系统
 * 记录管理后台操作、登录日志、数据变更追踪
 */
require_once __DIR__ . '/../admin/config.php';

class AuditLog {
    private static string $logFile = DATA_DIR . '/audit_log.json';

    /**
     * 记录审计事件
     */
    public static function log(string $action, string $category = 'system', array $details = []): void {
        $logs = self::all();

        $entry = [
            'id' => 'log_' . bin2hex(random_bytes(8)),
            'action' => $action,
            'category' => $category,
            'user' => $_SESSION['admin_user'] ?? 'system',
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $logs[] = $entry;

        // 保留最近 5000 条
        if (count($logs) > 5000) {
            $logs = array_slice($logs, -5000);
        }

        json_write(self::$logFile, $logs);
    }

    /**
     * 获取所有日志
     */
    public static function all(): array {
        return json_read(self::$logFile);
    }

    /**
     * 按分类筛选
     */
    public static function byCategory(string $category): array {
        return array_filter(self::all(), fn($log) => $log['category'] === $category);
    }

    /**
     * 按用户筛选
     */
    public static function byUser(string $user): array {
        return array_filter(self::all(), fn($log) => $log['user'] === $user);
    }

    /**
     * 按时间范围筛选
     */
    public static function byDateRange(string $from, string $to): array {
        return array_filter(self::all(), function ($log) use ($from, $to) {
            $ts = $log['timestamp'];
            return $ts >= $from && $ts <= $to;
        });
    }

    /**
     * 搜索日志
     */
    public static function search(string $query): array {
        $query = strtolower($query);
        return array_filter(self::all(), function ($log) use ($query) {
            return str_contains(strtolower($log['action']), $query)
                || str_contains(strtolower($log['category']), $query)
                || str_contains(strtolower($log['user']), $query)
                || str_contains(strtolower($log['url']), $query);
        });
    }

    /**
     * 获取最近 N 条日志
     */
    public static function recent(int $count = 50): array {
        return array_slice(self::all(), -$count, $count, true);
    }

    /**
     * 统计操作类型
     */
    public static function stats(): array {
        $logs = self::all();
        $stats = [
            'total' => count($logs),
            'by_category' => [],
            'by_user' => [],
            'today' => 0,
            'this_week' => 0,
        ];

        $today = date('Y-m-d');
        $weekAgo = date('Y-m-d', strtotime('-7 days'));

        foreach ($logs as $log) {
            $cat = $log['category'];
            $user = $log['user'];
            $stats['by_category'][$cat] = ($stats['by_category'][$cat] ?? 0) + 1;
            $stats['by_user'][$user] = ($stats['by_user'][$user] ?? 0) + 1;
            if (substr($log['timestamp'], 0, 10) === $today) $stats['today']++;
            if ($log['timestamp'] >= $weekAgo) $stats['this_week']++;
        }

        return $stats;
    }

    /**
     * 清空日志
     */
    public static function clear(): void {
        json_write(self::$logFile, []);
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
}
