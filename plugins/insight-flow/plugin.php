<?php
/**
 * Insight Flow 增长情报（官方插件 v1）
 *
 * 双向通道（集成方案 §3.1）：
 * 出站——业务钩子旁路推送 IF /api/v1/ingest（HMAC 签名，5 秒超时，绝不阻塞主请求）：
 *   cdp_event_received（filter，唯一能丢数据的钩子）/ cdp_segment_enter / cdp_segment_exit
 *   payment_success / crm_deal_won / form_submitted / user_registered
 * 入站回读——register_api_route：后台卡片与 GrowthBrain 消费 IF 洞察。
 * 定时——register_schedule：每日拉取 IF 周报摘要写进通知流。
 *
 * 设置项：insight_flow_url（IF 服务地址）、ingest_secret（HMAC 共享密钥）、
 *        订阅开关（订阅哪些洞察类型进卡片）、push 开关。
 */

require_once __DIR__ . '/../../lib/PluginSDK.php';

$p = plugin('insight-flow');

/** 统一出站：POST {insight_flow_url}/api/v1/ingest，HMAC-SHA256(rawBody, secret) */
$pushToIF = function (array $payload) use ($p): void {
    $base = rtrim((string)$p->get('insight_flow_url', ''), '/');
    $secret = (string)$p->get('ingest_secret', '');
    if ($base === '' || $secret === '' || !$p->get('push_enabled', true)) {
        return; // 没配置就静默跳过（钩子是旁路，不报错不打扰）
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $sig  = hash_hmac('sha256', $body, $secret);

    // IF 的 /api/v1/ingest 与 OpenFlow InboundReceiver 同算法：HMAC-SHA256(rawBody, secret)
    $r = $p->httpPost(
        $base . '/api/v1/ingest',
        $payload,
        ['X-Inbound-Signature: ' . $sig],
        5 // 5 秒超时，失败只记日志
    );
    $p->log(($r['ok'] ? '出站成功:' : '出站失败:') . ($payload['event'] ?? '?')
            . ($r['error'] ? ' ' . $r['error'] : ''));
};

// ── 出站钩子：行为事件流入 IF（旅程重建 / 转化漏斗 / LTV:CAC 第一方底座）──

// CDP 行为事件（filter：必须返回 $events，旁路推送绝不能改数据）
$p->filter('cdp_event_received', function (array $events) use ($p, $pushToIF) {
    if (!$p->get('push_cdp_events', true)) return $events;
    foreach (array_slice($events, 0, 20) as $ev) { // 上限保护，防大流量
        $pushToIF([
            'event' => 'cdp_event',
            'visitor_id' => $ev['visitor_id'] ?? '',
            'name' => $ev['event'] ?? '',
            'props' => $ev['props'] ?? [],
            'at' => $ev['at'] ?? date('c'),
        ]);
    }
    return $events; // filter 钩子：原样返回，绝不丢数据
});

$p->on('cdp_segment_enter', function ($segId, $profile, $seg) use ($p, $pushToIF) {
    $pushToIF(['event' => 'cdp_event', 'visitor_id' => $profile['email'] ?? ($profile['visitor_id'] ?? ''),
               'name' => 'segment_enter', 'props' => ['segment' => $seg['name'] ?? (string)$segId]]);
});

$p->on('cdp_segment_exit', function ($segId, $profile, $seg) use ($p, $pushToIF) {
    $pushToIF(['event' => 'cdp_event', 'visitor_id' => $profile['email'] ?? ($profile['visitor_id'] ?? ''),
               'name' => 'segment_exit', 'props' => ['segment' => $seg['name'] ?? (string)$segId]]);
});

// 成交真相 → LTV:CAC / RFM 分层
$p->on('payment_success', function (string $orderId, array $order, string $method) use ($p, $pushToIF) {
    if (!$p->get('push_orders', true)) return;
    $pushToIF(['event' => 'cdp_event', 'visitor_id' => $order['member_email'] ?? '', 'name' => 'payment_success',
               'props' => ['order_id' => $orderId, 'amount' => (float)($order['amount'] ?? 0), 'method' => $method]]);
});

$p->on('crm_deal_won', function (string $email, array $lead) use ($p, $pushToIF) {
    $pushToIF(['event' => 'cdp_event', 'visitor_id' => $email, 'name' => 'crm_deal_won',
               'props' => ['value' => (float)($lead['value'] ?? 0)]]);
});

// 获客漏斗 S1 诊断数据（签名：$formId, $type, $formData, $submission）
$p->on('form_submitted', function ($formId, $type, $formData, $submission) use ($p, $pushToIF) {
    $pushToIF(['event' => 'cdp_event', 'visitor_id' => $formData['email'] ?? '', 'name' => 'form_submitted',
               'props' => ['form_id' => (string)$formId, 'form_type' => (string)$type]]);
});

// 签名：$memberId, $email, $member
$p->on('user_registered', function ($memberId, string $email, array $member) use ($p, $pushToIF) {
    $pushToIF(['event' => 'cdp_event', 'visitor_id' => $email, 'name' => 'user_registered',
               'props' => ['member_id' => (string)$memberId]]);
});

// ── 入站回读 API（洞察供后台卡片 / GrowthBrain 消费）──
PluginSystem::register_api_route('insight-flow', 'GET', 'insights', function (array $req) use ($p) {
    $base = rtrim((string)$p->get('insight_flow_url', ''), '/');
    $key  = (string)$p->get('api_key', '');
    if ($base === '') return ['error' => '未配置 insight_flow_url'];

    $severity = $req['query']['severity'] ?? '';
    $limit    = min(50, max(1, (int)($req['query']['limit'] ?? 10)));
    $url = $base . '/api/v1/insights?workspace_id=' . urlencode((string)$p->get('workspace_id', 'default'))
         . '&limit=' . $limit . ($severity ? '&severity=' . urlencode($severity) : '');

    $r = $p->httpGet($url, $key ? ['X-API-Key: ' . $key] : [], 5);
    if (!$r['ok']) return ['error' => 'IF 拉取失败 status=' . $r['status'] . ' ' . $r['error']];
    $data = json_decode($r['body'], true);
    return $data['insights'] ?? [];
});

PluginSystem::register_api_route('insight-flow', 'GET', 'insights/{id}', function (array $req) use ($p) {
    $base = rtrim((string)$p->get('insight_flow_url', ''), '/');
    if ($base === '') return ['error' => '未配置 insight_flow_url'];
    $r = $p->httpGet($base . '/api/v1/insights/' . rawurlencode($req['params']['id']), [], 5);
    if (!$r['ok']) return ['error' => 'IF 拉取失败 status=' . $r['status']];
    return json_decode($r['body'], true) ?? [];
});

// 动作执行结果回传 IF（GrowthBrain 执行了派生动作后回写，进入 IF 验证状态机）
PluginSystem::register_api_route('insight-flow', 'POST', 'feedback', function (array $req) use ($p) {
    $base = rtrim((string)$p->get('insight_flow_url', ''), '/');
    $secret = (string)$p->get('ingest_secret', '');
    if ($base === '' || $secret === '') return ['error' => '未配置 IF 通道'];

    $payload = [
        'event' => 'openflow.action_feedback',
        'insight_id' => $req['body']['insight_id'] ?? '',
        'action' => $req['body']['action'] ?? '',
        'result' => $req['body']['result'] ?? '',
        'at' => date('c'),
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $r = $p->httpPost($base . '/api/v1/ingest', $payload, ['X-Inbound-Signature: ' . hash_hmac('sha256', $body, $secret)], 5);
    return ['ok' => $r['ok'], 'status' => $r['status']];
});

// ── 定时：每日拉取 IF 报告摘要写进通知流 ──
PluginSystem::register_schedule('insight-flow.daily-digest', 'daily', function () use ($p) {
    $base = rtrim((string)$p->get('insight_flow_url', ''), '/');
    $key  = (string)$p->get('api_key', '');
    if ($base === '') return;

    $r = $p->httpGet($base . '/api/v1/insights?workspace_id=' . urlencode((string)$p->get('workspace_id', 'default')) . '&limit=5',
                     $key ? ['X-API-Key: ' . $key] : [], 5);
    if (!$r['ok']) { $p->log('每日摘要拉取失败: ' . $r['error'], 'error'); return; }

    $data = json_decode($r['body'], true);
    $count = is_array($data['insights'] ?? null) ? count($data['insights']) : 0;
    $p->log("每日摘要：IF 洞察 {$count} 条");
});
