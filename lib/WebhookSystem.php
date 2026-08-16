<?php
/**
 * Webhook 系统
 * 支持创建、管理、触发 Webhook
 */
require_once __DIR__ . '/../admin/config.php';

class WebhookSystem {
    private static string $webhooksFile = DATA_DIR . '/webhooks.json';
    private static string $logFile = DATA_DIR . '/webhook_log.json';

    /**
     * 获取所有 Webhook
     */
    public static function all(): array {
        return json_read(self::$webhooksFile);
    }

    /**
     * 创建 Webhook
     */
    public static function create(array $data): array {
        $webhooks = self::all();

        $webhook = [
            'id' => 'wh_' . bin2hex(random_bytes(8)),
            'name' => $data['name'] ?? 'Unnamed Webhook',
            'url' => $data['url'] ?? '',
            'events' => $data['events'] ?? ['*'],
            'secret' => $data['secret'] ?? bin2hex(random_bytes(16)),
            'headers' => $data['headers'] ?? [],
            'enabled' => true,
            'retry_count' => (int)($data['retry_count'] ?? 3),
            'created_at' => date('Y-m-d H:i:s'),
            'last_triggered' => '',
            'last_status' => '',
            'failure_count' => 0,
        ];

        $webhooks[$webhook['id']] = $webhook;
        self::save($webhooks);
        return $webhook;
    }

    /**
     * 更新 Webhook
     */
    public static function update(string $id, array $data): ?array {
        $webhooks = self::all();
        if (!isset($webhooks[$id])) return null;

        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'url', 'events', 'headers', 'retry_count', 'enabled'])) {
                $webhooks[$id][$key] = $value;
            }
        }

        self::save($webhooks);
        return $webhooks[$id];
    }

    /**
     * 删除 Webhook
     */
    public static function delete(string $id): bool {
        $webhooks = self::all();
        if (!isset($webhooks[$id])) return false;
        unset($webhooks[$id]);
        self::save($webhooks);
        return true;
    }

    /**
     * 触发 Webhook
     */
    public static function trigger(string $event, array $payload): array {
        $webhooks = self::all();
        $results = [];

        foreach ($webhooks as $wh) {
            if (!$wh['enabled']) continue;
            if (!in_array('*', $wh['events']) && !in_array($event, $wh['events'])) continue;

            $result = self::send($wh, $event, $payload);
            $results[] = $result;

            // 更新统计
            $webhooks[$wh['id']]['last_triggered'] = date('Y-m-d H:i:s');
            $webhooks[$wh['id']]['last_status'] = $result['success'] ? 'ok' : 'failed';
            if (!$result['success']) {
                $webhooks[$wh['id']]['failure_count']++;
            } else {
                $webhooks[$wh['id']]['failure_count'] = 0;
            }
        }

        self::save($webhooks);

        // 记录日志
        self::logTrigger($event, $payload, $results);

        return $results;
    }

    /**
     * 发送单个 Webhook
     */
    private static function send(array $webhook, string $event, array $payload): array {
        $body = json_encode([
            'event' => $event,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $payload,
        ], JSON_UNESCAPED_UNICODE);

        // 生成签名
        $signature = hash_hmac('sha256', $body, $webhook['secret']);

        $headers = [
            'Content-Type: application/json',
            'X-Webhook-Event: ' . $event,
            'X-Webhook-Signature: sha256=' . $signature,
            'X-Webhook-Timestamp: ' . time(),
        ];

        // 合并自定义 headers
        foreach ($webhook['headers'] as $k => $v) {
            $headers[] = "{$k}: {$v}";
        }

        $start = microtime(true);
        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $duration = round((microtime(true) - $start) * 1000);

        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'webhook_id' => $webhook['id'],
            'url' => $webhook['url'],
            'success' => $success,
            'http_code' => $httpCode,
            'duration_ms' => $duration,
            'error' => $error,
            'response' => substr($response ?? '', 0, 500),
        ];
    }

    /**
     * 验证 Webhook 签名
     */
    public static function verifySignature(string $body, string $signature, string $secret): bool {
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * 获取触发日志
     */
    public static function logs(int $limit = 50): array {
        return array_slice(json_read(self::$logFile), -$limit, $limit, true);
    }

    private static function logTrigger(string $event, array $payload, array $results): void {
        $logs = json_read(self::$logFile);
        $logs[] = [
            'event' => $event,
            'webhooks_triggered' => count($results),
            'success' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // 保留最近 1000 条
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }

        json_write(self::$logFile, $logs);
    }

    /**
     * 可用事件列表
     */
    public static function availableEvents(): array {
        return [
            'article.published' => '文章发布',
            'article.updated' => '文章更新',
            'article.deleted' => '文章删除',
            'lead.created' => '新线索',
            'lead.updated' => '线索更新',
            'lead.converted' => '线索转化',
            'form.submitted' => '表单提交',
            'order.created' => '新订单',
            'order.paid' => '订单支付',
            'order.completed' => '订单完成',
            'member.registered' => '会员注册',
            'member.updated' => '会员更新',
            'course.enrolled' => '课程报名',
            'course.completed' => '课程完成',
            'cdp.event' => 'CDP事件',
            'webhook.test' => '测试触发',
        ];
    }

    private static function save(array $webhooks): void {
        json_write(self::$webhooksFile, $webhooks);
    }
}
