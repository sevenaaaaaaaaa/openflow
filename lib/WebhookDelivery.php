<?php
/**
 * Webhook 投递保障 —— 重试 / 退避 / 死信 / 幂等（P0-02，2026-09-03）
 *
 * 背景：后台的 Webhook 表单一直让人填「重试次数」，配置也确实存下来了，
 * 但 WebhookSystem::send() 里从来没有重试循环——填了不生效，属于「有按钮没功能」
 * 里藏得最深的一种：对方服务抖一下，这条事件就永久丢了，而界面显示一切正常。
 *
 * 这一层补四件事：
 *   1. 幂等键：每次投递有稳定的 delivery_id，重试复用同一个，接收方可去重
 *   2. 指数退避：首投在业务流程内同步发一次（不阻塞太久），失败入队，由 cron 按
 *      60s / 5min / 30min / 2h / 6h 重投，最多重投 retry_count 次
 *   3. 死信：重投耗尽后进死信队列，后台可见、可一键重放
 *   4. 投递明细：每次尝试都记一条（HTTP 码、耗时、错误、第几次），出问题能查
 *
 * 为什么不在请求内同步重试：trigger() 是在发布文章、创建线索这些用户操作路径里
 * 调用的，原地 sleep 重试会把用户卡住。异步入队 + cron 重投是这套架构该有的做法。
 */

if (!function_exists('wh_queue_file')) {

function wh_queue_file(): string { return DATA_DIR . '/webhook_queue.json'; }
function wh_dead_file(): string  { return DATA_DIR . '/webhook_dead.json'; }
function wh_deliv_file(): string { return DATA_DIR . '/webhook_deliveries.json'; }

/** 第 N 次重投的等待秒数（N 从 1 开始）。超出表长按最后一档。 */
function wh_backoff(int $attempt): int {
    $ladder = [60, 300, 1800, 7200, 21600];
    return $ladder[min(max($attempt, 1), count($ladder)) - 1];
}

/** 生成幂等键：同一条投递的所有重试共用它 */
function wh_new_delivery_id(): string {
    return 'whd_' . date('YmdHis') . '_' . bin2hex(random_bytes(6));
}

/** 记一次投递尝试（成功失败都记），保留最近 500 条 */
function wh_log_attempt(array $rec): void {
    $all = json_read(wh_deliv_file());
    $all[] = $rec + ['at' => date('Y-m-d H:i:s')];
    if (count($all) > 500) $all = array_slice($all, -500);
    json_write(wh_deliv_file(), $all);
}

function wh_deliveries(int $limit = 100): array {
    $all = json_read(wh_deliv_file());
    return array_slice(array_reverse($all), 0, $limit);
}

/** 首投失败 → 入重试队列 */
function wh_enqueue(string $webhookId, string $event, array $payload, string $deliveryId, int $attempt, int $maxRetry, string $lastError): void {
    if ($attempt > $maxRetry) { wh_to_dead($webhookId, $event, $payload, $deliveryId, $attempt, $lastError); return; }
    $q = json_read(wh_queue_file());
    $q[] = [
        'delivery_id' => $deliveryId,
        'webhook_id'  => $webhookId,
        'event'       => $event,
        'payload'     => $payload,
        'attempt'     => $attempt,        // 下一次是第几次重投
        'max_retry'   => $maxRetry,
        'next_at'     => time() + wh_backoff($attempt),
        'last_error'  => mb_substr($lastError, 0, 300),
        'created_at'  => date('Y-m-d H:i:s'),
    ];
    json_write(wh_queue_file(), $q);
}

/** 重投耗尽 → 死信，保留最近 200 条 */
function wh_to_dead(string $webhookId, string $event, array $payload, string $deliveryId, int $attempt, string $lastError): void {
    $d = json_read(wh_dead_file());
    $d[] = [
        'delivery_id' => $deliveryId,
        'webhook_id'  => $webhookId,
        'event'       => $event,
        'payload'     => $payload,
        'attempts'    => $attempt,
        'last_error'  => mb_substr($lastError, 0, 300),
        'died_at'     => date('Y-m-d H:i:s'),
    ];
    if (count($d) > 200) $d = array_slice($d, -200);
    json_write(wh_dead_file(), $d);
    try {
        require_once __DIR__ . '/AuditLog.php';
        AuditLog::log('Webhook 投递失败进入死信', 'webhook',
            ['event' => $event, 'webhook_id' => $webhookId, 'delivery_id' => $deliveryId, 'attempts' => $attempt, 'error' => mb_substr($lastError, 0, 200)]);
    } catch (Throwable $e) {}
}

function wh_dead_list(int $limit = 100): array {
    return array_slice(array_reverse(json_read(wh_dead_file())), 0, $limit);
}

function wh_queue_list(int $limit = 100): array {
    return array_slice(json_read(wh_queue_file()), 0, $limit);
}

function wh_queue_count(): int { return count(json_read(wh_queue_file())); }
function wh_dead_count(): int  { return count(json_read(wh_dead_file())); }

/**
 * 处理到期的重投（由 cron 调用）。
 * @return array{processed:int,ok:int,requeued:int,dead:int}
 */
function wh_process_queue(int $budget = 50): array {
    $q = json_read(wh_queue_file());
    if (!$q) return ['processed' => 0, 'ok' => 0, 'requeued' => 0, 'dead' => 0];

    $now = time();
    $keep = []; $stat = ['processed' => 0, 'ok' => 0, 'requeued' => 0, 'dead' => 0];

    foreach ($q as $job) {
        // 没到时间、或本轮预算用完 → 原样留队
        if ($stat['processed'] >= $budget || (int)($job['next_at'] ?? 0) > $now) { $keep[] = $job; continue; }

        $wh = WebhookSystem::get((string)($job['webhook_id'] ?? ''));
        if (!$wh || empty($wh['enabled'])) { continue; }   // Webhook 已删除或停用 → 丢弃这条重投

        $stat['processed']++;
        $r = WebhookSystem::deliver($wh, (string)$job['event'], (array)$job['payload'], (string)$job['delivery_id'], (int)$job['attempt']);

        if ($r['success']) {
            $stat['ok']++;
            continue;
        }
        $next = (int)$job['attempt'] + 1;
        if ($next > (int)$job['max_retry']) {
            wh_to_dead((string)$job['webhook_id'], (string)$job['event'], (array)$job['payload'], (string)$job['delivery_id'], (int)$job['attempt'], (string)($r['error'] ?: ('HTTP ' . $r['http_code'])));
            $stat['dead']++;
        } else {
            $job['attempt'] = $next;
            $job['next_at'] = $now + wh_backoff($next);
            $job['last_error'] = mb_substr((string)($r['error'] ?: ('HTTP ' . $r['http_code'])), 0, 300);
            $keep[] = $job;
            $stat['requeued']++;
        }
    }

    json_write(wh_queue_file(), array_values($keep));
    return $stat;
}

/** 从死信里重放一条：重新入队，立刻可投 */
function wh_replay_dead(string $deliveryId): bool {
    $d = json_read(wh_dead_file());
    $hit = null; $rest = [];
    foreach ($d as $row) {
        if ($hit === null && ($row['delivery_id'] ?? '') === $deliveryId) { $hit = $row; continue; }
        $rest[] = $row;
    }
    if (!$hit) return false;

    $wh = WebhookSystem::get((string)$hit['webhook_id']);
    if (!$wh) return false;

    $q = json_read(wh_queue_file());
    $q[] = [
        'delivery_id' => $hit['delivery_id'],      // 幂等键保持不变，接收方仍可去重
        'webhook_id'  => $hit['webhook_id'],
        'event'       => $hit['event'],
        'payload'     => $hit['payload'],
        'attempt'     => 1,
        'max_retry'   => (int)($wh['retry_count'] ?? 3),
        'next_at'     => time(),                    // 立刻可投，不等退避
        'last_error'  => '',
        'created_at'  => date('Y-m-d H:i:s'),
        'replayed'    => true,
    ];
    json_write(wh_queue_file(), $q);
    json_write(wh_dead_file(), $rest);
    try {
        require_once __DIR__ . '/AuditLog.php';
        AuditLog::log('Webhook 死信重放', 'webhook', ['delivery_id' => $deliveryId, 'event' => $hit['event'] ?? '']);
    } catch (Throwable $e) {}
    return true;
}

}
