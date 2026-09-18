<?php
/**
 * UserLoop 旅程联动插件 —— OpenFlow × UserLoop 双向通道（官方扩展点，零改内核）
 *
 * 通道①（本插件 · 事件流入）：
 *   cdp_event_received（filter，旁路转发不拦截）→ POST UserLoop /api/v1/ingest
 *   UserLoop 侧自动 CDP 建档 → 旅程重建 → 断点 Loop
 * 通道②（track.js · 页面注入）：
 *   body_end 前台插槽注入 UserLoop tracker，网页端行为直报 UserLoop
 * 通道③（回读，可选）：
 *   UserLoop 提供 MCP server（stdio）供 OpenFlow AgentRuntime mcp:* 消费
 *
 * 旁路纪律：回调极短、httpPost 2 秒超时不重试、失败静默——绝不拖慢 CDP 主链路。
 */
require_once __DIR__ . '/../../lib/PluginSDK.php';

$p = plugin('userloop-tracker');

$UL_URL   = rtrim((string)$p->get('userloop_url', 'https://nownexts.com/userloop'), '/');
$UL_TOKEN = (string)$p->get('api_token', '');
$FORWARD  = (bool)$p->get('forward_events', true);

$headers = ['Content-Type: application/json'];
if ($UL_TOKEN !== '') $headers[] = 'X-UserLoop-Token: ' . $UL_TOKEN;

/** 旁路转发：失败静默，永不阻塞主链路 */
$forward = function (array $event) use ($p, $UL_URL, $headers, $FORWARD) {
    if (!$FORWARD || $UL_URL === '') return;
    $props = (array)($event['properties'] ?? []);
    $payload = [
        'distinct_id' => $event['visitor_id'] ?? ('of_' . substr(md5((string)($event['member_id'] ?? '')), 0, 12)),
        'event'       => $event['event'] ?? 'unknown',
        'props'       => array_merge($props, [
            'url' => $event['url'] ?? '', 'referrer' => $event['referrer'] ?? '',
            // 跨系统身份映射：会员 id + 访客 id（命名空间化，避免与 WebsFlow 的 visitor_id 语义冲突）
            'openflow_member_id'  => (string)($event['member_id'] ?? ''),
            'openflow_visitor_id' => (string)($event['visitor_id'] ?? ''),
        ]),
        'source'      => 'openflow',
        'event_id'    => $event['message_id'] ?? ('of_' . md5(json_encode($event))),
        'ts'          => isset($event['timestamp']) ? date('c', strtotime($event['timestamp'])) : null,
    ];
    // 事件带邮箱/手机号 → 顶层透传，UserLoop 侧自动实名并归并
    if (!empty($props['email'])) $payload['email'] = (string)$props['email'];
    if (!empty($props['phone'])) $payload['props']['phone'] = (string)$props['phone'];
    $r = $p->httpPost($UL_URL . '/api/v1/ingest', $payload, $headers, 2);
    if (!$r['ok']) $p->log('UserLoop 转发失败：' . ($r['error'] ?: ('HTTP ' . $r['status'])));
};

// ── 通道①：CDP 行为事件旁路转发（filter：原样返回，绝不改写/丢弃）──
$p->filter('cdp_event_received', function ($entry) use ($forward) {
    if (is_array($entry)) $forward($entry);
    return $entry;  // 无条件透传 —— UserLoop 是旁路观察者
});

// ── 通道②：前台埋点脚本注入 ──
PluginSystem::register_front_slot('body_end', function () use ($UL_URL) {
    ?>
    <script src="<?= htmlspecialchars($UL_URL) ?>/track.js" defer></script>
    <?php
});
