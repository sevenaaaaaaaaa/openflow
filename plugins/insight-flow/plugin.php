<?php
/**
 * Insight Flow 增长情报（官方插件 v1.1 · 三方契约 v1.1）
 *
 * 双向通道：
 * 出站——业务钩子旁路推送 IF {if_url}/api/v1/ingest，统一信封 + 稳定 event_id：
 *   cdp_event_received（filter，单条事件，原样返回）/ cdp_segment_enter / cdp_segment_exit → cdp.event
 *   payment_success / crm_deal_won → cdp.order；form_submitted → cdp.lead；user_registered → cdp.contact
 *   growth_action_status_changed → openflow.action_feedback（动作带 insight_id 时）
 *   签名头：X-IF-Signature = X-Inbound-Signature = hex(HMAC-SHA256(raw, if_secret))，X-IF-Timestamp。
 * 入站——POST inbox：IF 推送 insight.upsert，验签 + 幂等后落 insights 表（lib/InsightStore.php）。
 * 回读——GET insights[/{id}]：要求 API Key（或后台登录），本地库优先；?live=1 才回源 IF 并顺手缓存。
 * 定时——每日：可选回源同步一次，再把近 24h 洞察摘要写进后台通知流。
 *
 * 未配置 if_url / if_secret 时：出站全部静默跳过、inbox 拒收(401)、读接口只读本地库——整体 no-op。
 *
 * 设置项：if_url / if_secret / if_api_key（旧键 insight_flow_url / ingest_secret / api_key 仍兼容）、
 *        workspace_id、push_enabled / push_cdp_events / push_orders。
 */

require_once __DIR__ . '/../../lib/PluginSDK.php';
require_once __DIR__ . '/functions.php';

$p = plugin('insight-flow');

// ── 出站钩子：行为事件流入 IF ──

// CDP 行为事件（filter：CdpSystem 传入的是「单条」事件 entry，必须原样返回）
$p->filter('cdp_event_received', function ($entry) use ($p) {
    if (!is_array($entry) || !$p->get('push_cdp_events', true) || !insight_flow_outbound_ready($p)) return $entry;
    // 兼容：若上游传来的是事件列表，最多推 20 条（上限保护）
    $list = isset($entry['event']) ? [$entry] : array_slice(array_values(array_filter($entry, 'is_array')), 0, 20);
    foreach ($list as $ev) insight_flow_emit($p, insight_flow_cdp_event($ev));
    return $entry;
});

$p->on('cdp_segment_enter', function ($segId, $profile, $seg) use ($p) {
    insight_flow_emit($p, insight_flow_segment_event('enter', $segId, (array)$profile, (array)$seg));
});

$p->on('cdp_segment_exit', function ($segId, $profile, $seg) use ($p) {
    insight_flow_emit($p, insight_flow_segment_event('exit', $segId, (array)$profile, (array)$seg));
});

// 成交真相 → LTV:CAC / RFM 分层
$p->on('payment_success', function ($orderId, $order, $method = '') use ($p) {
    if (!$p->get('push_orders', true)) return;
    insight_flow_emit($p, insight_flow_order_event((string)$orderId, (array)$order, (string)$method));
});

$p->on('crm_deal_won', function ($email, $lead) use ($p) {
    if (!$p->get('push_orders', true)) return;
    insight_flow_emit($p, insight_flow_deal_event((string)$email, (array)$lead));
});

// 获客漏斗（签名：$formId, $type, $formData, $submission）
$p->on('form_submitted', function ($formId, $type, $formData, $submission) use ($p) {
    insight_flow_emit($p, insight_flow_form_event($formId, $type, (array)$formData, (array)$submission));
});

// 签名：$memberId, $email, $member
$p->on('user_registered', function ($memberId, $email, $member) use ($p) {
    insight_flow_emit($p, insight_flow_register_event($memberId, (string)$email, (array)$member));
});

// 动作状态变化 → openflow.action_feedback（GrowthAction::growth_action_set_status 触发；失败只记日志）
$p->on('growth_action_status_changed', function ($action, $status = '') use ($p) {
    insight_flow_on_action_status($p, (array)$action, (string)$status);
});

// ── 入站 inbox：IF → OpenFlow（insight.upsert）──
// auth=none：鉴权靠 HMAC 验签（共享密钥 if_secret），而不是后台登录
PluginSystem::register_api_route('insight-flow', 'POST', 'inbox', function (array $req) use ($p) {
    $raw = (string)($req['raw'] ?? file_get_contents('php://input'));
    $r = insight_flow_inbox_handle($raw, insight_flow_request_headers($_SERVER), insight_flow_cfg($p, 'if_secret'));
    if (($r['status'] ?? 500) >= 400) {
        $p->log('inbox 拒收 ' . $r['status'] . ' ' . ($r['body']['error'] ?? ''), 'warn');
    }
    // 契约 §1：响应体顶层就是 {"ok":..., ...}，不套 data
    return PluginApiResponse::raw($r['body'], (int)$r['status']);
}, ['auth' => 'none']);

// ── 回读 API（后台卡片 / GrowthBrain 消费）──
// 两条都要求 API Key（X-API-Key / Bearer / ?api_key，命中 if_api_key 或站点 api_key）或后台登录。
// 本地 InsightStore 优先；?live=1 时回源 IF（用 if_api_key 作为出站 X-API-Key），结果顺手缓存进本地。
PluginSystem::register_api_route('insight-flow', 'GET', 'insights', function (array $req) use ($p) {
    if (!insight_flow_read_authorized($p, $_SERVER, $req['query'] ?? [])) return PluginApiResponse::error('Invalid API key', 401);
    $q = $req['query'] ?? [];
    $severity = (string)($q['severity'] ?? '');
    $limit    = min(50, max(1, (int)($q['limit'] ?? 10)));
    $ws       = (string)($q['workspace_id'] ?? $p->get('workspace_id', 'default'));

    if (!empty($q['live'])) {
        $base = insight_flow_cfg($p, 'if_url');
        if ($base === '') return PluginApiResponse::error('未配置 if_url，无法 live 回源', 400);
        $key = insight_flow_cfg($p, 'if_api_key');
        $url = $base . '/api/v1/insights?workspace_id=' . urlencode($ws) . '&limit=' . $limit
             . ($severity !== '' ? '&severity=' . urlencode($severity) : '');
        $r = $p->httpGet($url, $key !== '' ? ['X-API-Key: ' . $key] : [], 5);
        if (!$r['ok']) return PluginApiResponse::error('IF 拉取失败 status=' . $r['status'] . ' ' . $r['error'], 502);
        $data = json_decode($r['body'], true);
        $items = is_array($data['insights'] ?? null) ? $data['insights'] : [];
        insight_flow_cache_remote($items, $ws);
        return $items;
    }
    return insight_store_list(['severity' => $severity, 'workspace_id' => (string)($q['workspace_id'] ?? '')], $limit);
}, ['auth' => 'none']);

PluginSystem::register_api_route('insight-flow', 'GET', 'insights/{id}', function (array $req) use ($p) {
    if (!insight_flow_read_authorized($p, $_SERVER, $req['query'] ?? [])) return PluginApiResponse::error('Invalid API key', 401);
    $id = (string)($req['params']['id'] ?? '');
    if (empty($req['query']['live'])) {
        $row = insight_store_get($id);
        if ($row !== null) return $row;
        return PluginApiResponse::error('insight not found', 404);
    }
    $base = insight_flow_cfg($p, 'if_url');
    if ($base === '') return PluginApiResponse::error('未配置 if_url，无法 live 回源', 400);
    $key = insight_flow_cfg($p, 'if_api_key');
    $r = $p->httpGet($base . '/api/v1/insights/' . rawurlencode($id), $key !== '' ? ['X-API-Key: ' . $key] : [], 5);
    if (!$r['ok']) return PluginApiResponse::error('IF 拉取失败 status=' . $r['status'], $r['status'] === 404 ? 404 : 502);
    $data = json_decode($r['body'], true) ?? [];
    $ins = is_array($data['insight'] ?? null) ? $data['insight'] : $data;
    if (is_array($ins)) insight_flow_cache_remote([$ins]);
    return $data;
}, ['auth' => 'none']);

// 动作执行结果手动回传 IF（后台登录）。与钩子发同一个 openflow.action_feedback 信封。
// body: insight_id（必填）、action_id、module、action、status（done/dismissed/effective/ineffective；兼容旧字段 result）
PluginSystem::register_api_route('insight-flow', 'POST', 'feedback', function (array $req) use ($p) {
    if (!insight_flow_outbound_ready($p)) return PluginApiResponse::error('未配置 IF 通道（if_url / if_secret）', 400);
    $env = insight_flow_feedback_envelope((array)($req['body'] ?? []), (string)$p->get('workspace_id', 'default'));
    if ($env === null) return PluginApiResponse::error('缺少 insight_id 或 status 不在 done/dismissed/effective/ineffective 内', 400);
    $r = insight_flow_send($p, $env);
    if (!$r['ok']) return PluginApiResponse::error('回传失败 status=' . $r['status'] . ' ' . $r['error'], 502);
    return ['status' => $r['status'], 'event_id' => $env['event_id']];
});

// ── 定时：每日同步 + 摘要写进通知流 ──
PluginSystem::register_schedule('insight-flow.daily-digest', 'daily', function () use ($p) {
    $ws = (string)$p->get('workspace_id', 'default');
    $base = insight_flow_cfg($p, 'if_url');
    if ($base !== '') {
        $key = insight_flow_cfg($p, 'if_api_key');
        $r = $p->httpGet($base . '/api/v1/insights?workspace_id=' . urlencode($ws) . '&limit=20',
                         $key !== '' ? ['X-API-Key: ' . $key] : [], 5);
        if ($r['ok']) {
            $data = json_decode($r['body'], true);
            insight_flow_cache_remote(is_array($data['insights'] ?? null) ? $data['insights'] : [], $ws);
        } else {
            $p->log('每日摘要回源失败: ' . $r['error'], 'error');
        }
    }

    $since = date('Y-m-d H:i:s', time() - 86400);
    $recent = array_values(array_filter(insight_store_list([], 200), fn($i) => ($i['updated_at'] ?? '') >= $since));
    $p->log('每日摘要：近 24h 洞察 ' . count($recent) . ' 条');
    if (!$recent || !function_exists('notify')) return;
    $high = count(array_filter($recent, fn($i) => in_array(strtolower((string)$i['severity']), ['high', 'critical'], true)));
    $titles = array_slice(array_map(fn($i) => (string)($i['title'] ?: $i['insight_id']), $recent), 0, 3);
    notify('system', 'Insight Flow 每日洞察：' . count($recent) . ' 条' . ($high ? "（高优 {$high}）" : ''),
           implode('；', $titles), $p->pageUrl('view.php'));
});
