<?php
/**
 * InboundReceiver — 入站数据接收层
 * 外部系统（CRM / 投放平台 / 第三方 API / webhook）向本站推送数据：
 *   lead      → CRM 线索
 *   cdp_event → CDP 行为事件
 *   contact   → CDP 画像属性
 * 支持 HMAC-SHA256 签名校验、字段映射、处理日志。
 */

function inbound_file(): string { return DATA_DIR . '/inbound.json'; }
function inbound_log_file(): string { return DATA_DIR . '/inbound-log.json'; }

// 全部入站连接器
function inbound_connectors(): array {
    return json_read(inbound_file());
}
function inbound_save_connectors(array $list): void {
    json_write(inbound_file(), $list);
}

// 签名校验：header X-Inbound-Signature = hash_hmac('sha256', rawBody, secret)
function inbound_verify(string $rawBody, string $signature, string $secret): bool {
    if ($secret === '' || $signature === '') return false;
    return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
}

// 按 id 取连接器
function inbound_connector(string $id): ?array {
    foreach (inbound_connectors() as $c) {
        if (($c['id'] ?? '') === $id) return $c;
    }
    return null;
}

// 写处理日志
function inbound_log(string $connId, array $entry): void {
    $log = json_read(inbound_log_file());
    array_unshift($log, array_merge(['id' => 'il_' . bin2hex(random_bytes(4)), 'connector_id' => $connId, 'at' => date('Y-m-d H:i:s')], $entry));
    $log = array_slice($log, 0, 200);
    json_write(inbound_log_file(), $log);
}

/**
 * 处理入站数据（映射 + 落库）
 * $payload: 解码后的 JSON 数组
 */
function inbound_handle(string $connId, array $payload): array {
    $conn = inbound_connector($connId);
    if (!$conn) return ['ok' => false, 'error' => '连接器不存在'];
    if (empty($conn['enabled'])) return ['ok' => false, 'error' => '连接器已停用'];
    $type = $conn['type'] ?? 'lead';
    $map = (array)($conn['mapping'] ?? []);

    // 映射辅助：从 payload 取映射字段
    $get = function (string $target) use ($payload, $map) {
        $key = $map[$target] ?? $target;
        return trim((string)($payload[$key] ?? ''));
    };

    $result = ['type' => $type];
    try {
        if ($type === 'lead') {
            // 外部线索 → CRM
            $email = $get('email');
            $name = $get('name');
            $phone = $get('phone');
            if ($email === '' && $phone === '') return ['ok' => false, 'error' => '缺少 email/phone 用于创建线索'];
            require_once __DIR__ . '/CrmSystem.php';
            $lead = crm_ensure_lead($email, $name, $phone);
            $updates = [];
            $company = $get('company');
            $source = $get('source') ?: ($conn['source'] ?? 'inbound');
            if ($company !== '') $updates['company'] = $company;
            if ($source !== '') $updates['source'] = $source;
            if (!empty($conn['tags'])) $updates['tags'] = array_values(array_unique(array_merge($lead['tags'] ?? [], (array)$conn['tags'])));
            if (!empty($updates)) crm_update_lead($email ?: $phone, $updates);
            $result['lead'] = ['email' => $email, 'name' => $name];
        } elseif ($type === 'cdp_event') {
            // 外部行为 → CDP 事件
            require_once __DIR__ . '/CdpSystem.php';
            $event = $get('event');
            if ($event === '' ) $event = $conn['event'] ?? '';
            if ($event === '') $event = 'external_event';
            $visitorId = $get('visitor_id');
            // 属性：映射的 properties 子集，否则透传整个 payload
            $props = [];
            $propKeys = (array)($conn['properties'] ?? []);
            if (!empty($propKeys)) {
                foreach ($propKeys as $pk) $props[$pk] = $payload[$pk] ?? null;
            } else {
                $props = $payload;
                unset($props['event'], $props['visitor_id']);
            }
            $props['source'] = $conn['source'] ?? ($conn['name'] ?? 'inbound');
            CdpSystem::track($event, $props, $visitorId);
            $result['event'] = $event;
        } elseif ($type === 'contact') {
            // 外部用户/画像 → CDP 画像属性
            require_once __DIR__ . '/CdpSystem.php';
            $email = $get('email');
            $visitorId = $get('visitor_id');
            $props = $payload;
            unset($props['event'], $props['visitor_id']);
            // 用一个 contact_sync 事件落属性和身份
            CdpSystem::track('contact_sync', array_merge($props, ['email' => $email]), $visitorId);
            $result['contact'] = ['email' => $email];
        } else {
            return ['ok' => false, 'error' => "未知类型 {$type}"];
        }
        inbound_log($connId, ['status' => 'ok', 'type' => $type, 'detail' => $result]);
        return ['ok' => true] + $result;
    } catch (Throwable $e) {
        inbound_log($connId, ['status' => 'error', 'type' => $type, 'error' => $e->getMessage()]);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
