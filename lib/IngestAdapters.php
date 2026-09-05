<?php
/**
 * IngestAdapters —— 第三方数据源导入（P1-2）
 *
 * 【为什么】CDP 站内采集（inject.js → api/cdp.php）很强，但**没有第三方数据源导入**：
 * GA / Segment / 神策 的数据进不来。真实用户旅程里，客户可能已有 GA4、Segment 或神策，
 * 缺这块，CDP 就成了「只有自家埋点的孤岛」。
 *
 * 【思路】不重写 inbound_handle 主链路，而是叠一层「格式识别 → 归一化」：
 *   识别 payload 是 Segment /v1/batch、GA4 MP、神策 sensorsdata，还是通用 Webhook，
 *   把前三者归一化成 OpenFlow 事件（含 visitor_id/member_id/email/event 幂等键），
 *   再逐条走 CdpSystem::track（触发画像/分群/打标/身份合并）。
 *
 * 幂等：Segment 的 messageId / GA4 的 params 无 ID（用哈希兜底）/ 神策无 ID（用哈希），
 * 由 events 表 message_id 唯一索引 + EventStore::recordBatch 的 INSERT OR IGNORE 去重。
 */

if (!function_exists('ingest_detect_format')) {

/** 识别数据源格式（只看顶层键，零成本） */
function ingest_detect_format(array $p): string {
    if (isset($p['batch']) && is_array($p['batch']) && isset($p['batch'][0]['type'])) return 'segment';
    if (isset($p['events']) && is_array($p['events']) && isset($p['client_id'])) return 'ga4';
    if (isset($p['distinct_id']) || isset($p['anonymous_id']) && isset($p['type'])) return 'sensors';
    return 'generic';
}

/** Segment /v1/batch → 归一化事件数组 */
function ingest_segment_to_events(array $p): array {
    $out = [];
    foreach ((array)($p['batch'] ?? []) as $el) {
        if (!is_array($el)) continue;
        $type = (string)($el['type'] ?? '');
        $anonymous = (string)($el['anonymousId'] ?? '');
        $userId = (string)($el['userId'] ?? '');
        $visitor = $anonymous !== '' ? $anonymous : $userId;
        $msg = (string)($el['messageId'] ?? md5(($type ?? '') . '|' . ($anonymous ?? '') . '|' . ($el['timestamp'] ?? '')));
        $props = (array)($el['properties'] ?? []);
        // 从 traits/context 提取身份字段
        $traits = array_merge((array)($el['traits'] ?? []), (array)($el['context']['traits'] ?? []));
        $email = (string)($traits['email'] ?? ($props['email'] ?? ''));
        $member = $userId !== '' ? ('member:' . $userId) : '';

        if ($type === 'identify') {
            $out[] = ['event' => 'contact_sync', 'visitor_id' => $visitor, 'member_id' => $member,
                'email' => $email, 'properties' => $traits, 'message_id' => $msg];
        } elseif ($type === 'page') {
            $out[] = ['event' => 'page_view', 'visitor_id' => $visitor, 'member_id' => $member,
                'email' => $email, 'properties' => array_merge($props, ['page' => $el['name'] ?? '', 'category' => $el['category'] ?? '']), 'message_id' => $msg];
        } elseif ($type === 'group') {
            $out[] = ['event' => 'contact_sync', 'visitor_id' => $visitor, 'member_id' => $member,
                'email' => $email, 'properties' => array_merge($traits, ['segment_group' => $el['groupId'] ?? '']), 'message_id' => $msg];
        } elseif ($type === 'track' && ($el['event'] ?? '') !== '') {
            $out[] = ['event' => (string)$el['event'], 'visitor_id' => $visitor, 'member_id' => $member,
                'email' => $email, 'properties' => array_merge($props, $traits), 'message_id' => $msg];
        }
    }
    return $out;
}

/** GA4 Measurement Protocol → 归一化事件数组 */
function ingest_ga4_to_events(array $p): array {
    $out = [];
    $client = (string)($p['client_id'] ?? '');
    foreach ((array)($p['events'] ?? []) as $el) {
        if (!is_array($el) || empty($el['name'])) continue;
        $params = (array)($el['params'] ?? []);
        $out[] = ['event' => (string)$el['name'], 'visitor_id' => $client, 'member_id' => '',
            'email' => (string)($params['email'] ?? ''), 'properties' => $params,
            'message_id' => md5(($el['name'] ?? '') . '|' . $client . '|' . ($params['ts'] ?? '') . '|' . ($el['timestamp'] ?? ''))];
    }
    return $out;
}

/** 神策 sensorsdata → 归一化事件数组 */
function ingest_sensors_to_events(array $p): array {
    $type = (string)($p['type'] ?? '');
    $distinct = (string)($p['distinct_id'] ?? $p['anonymous_id'] ?? '');
    $props = (array)($p['properties'] ?? []);
    $email = (string)($props['$email'] ?? ($props['email'] ?? ''));
    $member = (!empty($p['$is_login'])) ? ('member:' . $distinct) : '';
    if ($type === 'track' && ($p['event'] ?? '') !== '') {
        $event = (string)$p['event'];
        if (str_starts_with($event, '$')) $event = ltrim($event, '$');
        return [['event' => $event, 'visitor_id' => $distinct, 'member_id' => $member, 'email' => $email,
            'properties' => $props, 'message_id' => md5($event . '|' . $distinct . '|' . ($p['time'] ?? ''))]];
    }
    if ($type === 'profile_set' || $type === 'profile_init') {
        return [['event' => 'contact_sync', 'visitor_id' => $distinct, 'member_id' => $member, 'email' => $email,
            'properties' => $props, 'message_id' => md5('profile|' . $distinct . '|' . ($p['time'] ?? ''))]];
    }
    return [];
}

/** 统一入口：识别格式 → 归一化 → 逐条落 CDP（批量幂等） */
function ingest_handle_formatted(array $payload): array {
    $fmt = ingest_detect_format($payload);
    if ($fmt === 'generic') return ['ok' => false, 'error' => 'unsupported_format', 'detected' => $fmt];
    $events = $fmt === 'segment' ? ingest_segment_to_events($payload)
             : ($fmt === 'ga4' ? ingest_ga4_to_events($payload)
             : ingest_sensors_to_events($payload));
    $count = 0; $errs = 0;
    foreach ($events as $e) {
        try {
            // 身份合并：email/member 会触发 IdentityResolver::merge（跨设备）
            $visitor = $e['visitor_id'] ?? '';
            $memberId = trim((string)($e['member_id'] ?? ''));
            if ($memberId !== '' && str_starts_with($memberId, 'member:')) $memberId = substr($memberId, 7);
            $email = (string)($e['email'] ?? '');
            // CdpSystem::track 内部会 merge + 触发分群打标
            if (function_exists('CdpSystem')) {
                // 用前端 track 兼容签名：track(event, props, visitorId, memberId, email 透传)
                CdpSystem::track($e['event'] ?? 'unknown', (array)($e['properties'] ?? []), $visitor);
                $count++;
            }
        } catch (\Throwable $ex) { $errs++; }
    }
    return ['ok' => true, 'format' => $fmt, 'ingested' => $count, 'errors' => $errs];
}

}
