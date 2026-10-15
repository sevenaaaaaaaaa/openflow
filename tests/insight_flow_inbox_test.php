<?php
/**
 * 三方契约 v1.1 · insight-flow 插件：inbox 入站 + 出站信封 + 读接口鉴权
 *
 *   php tests/insight_flow_inbox_test.php
 *
 * 验：
 *   inbox —— 验签通过/失败、X-Inbound-Signature 兼容、时间窗、缺 event / event_id、未知事件、
 *            (source,event_id) 幂等、嵌套与扁平写法、未配密钥 fail-closed、路由响应顶层 ok
 *   出站 —— cdp_event_received（单条 entry）/ payment_success / form_submitted / user_registered
 *            → cdp.event / cdp.order / cdp.lead / cdp.contact 信封，event_id 稳定，签名头可验
 *   读接口 —— GET insights / insights/{id} 无 Key 401，有 Key 读本地库
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-if-inbox-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

require_once __DIR__ . '/../lib/PluginSystem.php';
require_once __DIR__ . '/../lib/PluginSDK.php';
require_once __DIR__ . '/../plugins/insight-flow/plugin.php';   // 注册钩子与路由（未配置时全部 no-op）

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}
function hdr(array $headers, string $name): string {
    foreach ($headers as $h) { [$k, $v] = array_map('trim', explode(':', $h, 2)); if (strcasecmp($k, $name) === 0) return $v; }
    return '';
}

$SECRET = 'test-shared-secret';
$sign = fn(string $raw) => hash_hmac('sha256', $raw, $SECRET);
$env = function (array $over = []) {
    return array_merge([
        'schema_version' => '1.1', 'event' => 'insight.upsert', 'event_id' => 'if-ins-1-v1',
        'occurred_at' => '2026-10-12T08:00:00Z', 'source' => 'insight-flow', 'workspace_id' => 'default',
        'data' => ['insight_id' => 'ins_1', 'title' => '竞品降价', 'severity' => 'high', 'confidence' => 0.8,
                   'evidence' => [['url' => 'https://e.example']], 'actions' => [], 'stage_tags' => ['S1']],
    ], $over);
};

echo "\n── 1. 验签 ──\n";
$raw = json_encode($env(), JSON_UNESCAPED_UNICODE);
$r = insight_flow_inbox_handle($raw, ['x-if-signature' => 'deadbeef'], $SECRET);
check('错签 → 401', $r['status'] === 401, json_encode($r));
$r = insight_flow_inbox_handle($raw, [], $SECRET);
check('无签名 → 401', $r['status'] === 401);
$r = insight_flow_inbox_handle($raw, ['x-if-signature' => $sign($raw)], '');
check('未配置密钥 → 401（fail-closed）', $r['status'] === 401);
$r = insight_flow_inbox_handle($raw, ['x-if-signature' => $sign($raw)], $SECRET);
check('X-IF-Signature 正确 → 200', $r['status'] === 200, json_encode($r, JSON_UNESCAPED_UNICODE));
check('body.ok + 新建', ($r['body']['ok'] ?? false) === true && ($r['body']['duplicate'] ?? true) === false && ($r['body']['created'] ?? false) === true);
check('嵌套 data 落库', (insight_store_get('ins_1')['title'] ?? '') === '竞品降价');
check('evidence 落库', (insight_store_get('ins_1')['evidence'][0]['url'] ?? '') === 'https://e.example');
check('签名对字节敏感（改一个字节即失败）', insight_flow_inbox_handle($raw . ' ', ['x-if-signature' => $sign($raw)], $SECRET)['status'] === 401);

echo "\n── 2. 幂等 (source, event_id) ──\n";
$r = insight_flow_inbox_handle($raw, ['x-if-signature' => $sign($raw)], $SECRET);
check('重放 → 200 duplicate:true', $r['status'] === 200 && ($r['body']['duplicate'] ?? false) === true, json_encode($r));
$raw2 = json_encode($env(['event_id' => 'if-ins-1-v2', 'data' => ['insight_id' => 'ins_1', 'title' => '竞品降价 v2']]), JSON_UNESCAPED_UNICODE);
$r = insight_flow_inbox_handle($raw2, ['x-inbound-signature' => $sign($raw2)], $SECRET);
check('新 event_id 同 insight → 更新（X-Inbound-Signature 兼容）', $r['status'] === 200 && ($r['body']['created'] ?? true) === false, json_encode($r));
check('标题已更新', (insight_store_get('ins_1')['title'] ?? '') === '竞品降价 v2');

echo "\n── 3. 扁平写法 ──\n";
$flatRaw = json_encode(['event' => 'insight.upsert', 'event_id' => 'flat-1', 'source' => 'insight-flow',
                        'insight_id' => 'ins_flat', 'workspace_id' => 'ws9', 'title' => '扁平', 'severity' => 'low', 'confidence' => 0.3], JSON_UNESCAPED_UNICODE);
$r = insight_flow_inbox_handle($flatRaw, ['x-if-signature' => $sign($flatRaw)], $SECRET);
check('扁平 → 200', $r['status'] === 200, json_encode($r));
check('扁平字段落库', (insight_store_get('ins_flat')['workspace_id'] ?? '') === 'ws9');

echo "\n── 4. 必填 / 未知事件 → 400 ──\n";
$noId = json_encode($env(['event_id' => '']));
check('缺 event_id → 400', insight_flow_inbox_handle($noId, ['x-if-signature' => $sign($noId)], $SECRET)['status'] === 400);
$noEv = json_encode(['event_id' => 'x-1', 'data' => ['insight_id' => 'a']]);
check('缺 event → 400', insight_flow_inbox_handle($noEv, ['x-if-signature' => $sign($noEv)], $SECRET)['status'] === 400);
$unk = json_encode($env(['event' => 'content.published', 'event_id' => 'u-1']));
$r = insight_flow_inbox_handle($unk, ['x-if-signature' => $sign($unk)], $SECRET);
check('未知事件 → 400（不假成功）', $r['status'] === 400 && ($r['body']['ok'] ?? true) === false, json_encode($r));
$noIns = json_encode($env(['event_id' => 'n-1', 'data' => ['title' => '无 id']]));
check('缺 insight_id → 400', insight_flow_inbox_handle($noIns, ['x-if-signature' => $sign($noIns)], $SECRET)['status'] === 400);
check('缺 insight_id 不登记幂等（修正后可重发）', insight_ingest_seen('insight-flow', 'n-1') === false);
check('非 JSON → 400', insight_flow_inbox_handle('not json', ['x-if-signature' => $sign('not json')], $SECRET)['status'] === 400);

echo "\n── 5. 时间窗 X-IF-Timestamp ──\n";
$tsRaw = json_encode($env(['event_id' => 'ts-1']));
$now = 1_800_000_000;
check('偏差 301s → 401', insight_flow_inbox_handle($tsRaw, ['x-if-signature' => $sign($tsRaw), 'x-if-timestamp' => (string)($now - 301)], $SECRET, $now)['status'] === 401);
check('非数字 → 401', insight_flow_inbox_handle($tsRaw, ['x-if-signature' => $sign($tsRaw), 'x-if-timestamp' => 'abc'], $SECRET, $now)['status'] === 401);
check('偏差 120s → 200', insight_flow_inbox_handle($tsRaw, ['x-if-signature' => $sign($tsRaw), 'x-if-timestamp' => (string)($now + 120)], $SECRET, $now)['status'] === 200);

echo "\n── 6. 请求头收集 ──\n";
$h = insight_flow_request_headers(['HTTP_X_IF_SIGNATURE' => 'a', 'HTTP_X_IF_TIMESTAMP' => '1', 'HTTP_X_OTHER' => 'z']);
check('只收契约相关头（小写键）', $h === ['x-if-signature' => 'a', 'x-if-timestamp' => '1'], json_encode($h));

echo "\n── 7. 路由：inbox / insights 通过 PluginSystem 分发 ──\n";
$dispatch = function (string $method, string $path, array $server = [], array $get = []) {
    // CLI 下无法设置 HTTP 状态码（http_response_code 会告警），这里只断言响应体
    $bak = $_SERVER; $_SERVER = array_merge($_SERVER, $server); $_GET = $get;
    $lvl = error_reporting(E_ALL & ~E_WARNING);
    ob_start(); $handled = PluginSystem::dispatch_api_route('insight-flow', $method, $path); $out = ob_get_clean();
    error_reporting($lvl); $_SERVER = $bak; $_GET = [];
    return ['handled' => $handled, 'json' => json_decode((string)$out, true)];
};
$o = $dispatch('POST', 'inbox');
check('inbox 已注册且公开（auth=none，靠验签）', $o['handled'] === true && ($o['json']['error'] ?? '') !== '需要管理员登录', json_encode($o, JSON_UNESCAPED_UNICODE));
check('未配密钥 → 顶层 {"ok":false}（不套 data）', ($o['json']['ok'] ?? null) === false && !isset($o['json']['data']));
$o = $dispatch('GET', 'insights');
check('GET insights 无 Key → 401', ($o['json']['ok'] ?? null) === false && ($o['json']['error'] ?? '') === 'Invalid API key', json_encode($o, JSON_UNESCAPED_UNICODE));
$o = $dispatch('GET', 'insights/ins_1');
check('GET insights/{id} 无 Key → 401', ($o['json']['error'] ?? '') === 'Invalid API key');

plugin('insight-flow')->setConfig(['if_api_key' => 'k-123']);
$o = $dispatch('GET', 'insights', ['HTTP_X_API_KEY' => 'wrong']);
check('错 Key → 401', ($o['json']['error'] ?? '') === 'Invalid API key');
$o = $dispatch('GET', 'insights', ['HTTP_X_API_KEY' => 'k-123'], ['severity' => 'high']);
check('X-API-Key → 读本地库', ($o['json']['ok'] ?? false) === true && ($o['json']['data'][0]['insight_id'] ?? '') === 'ins_1', json_encode($o, JSON_UNESCAPED_UNICODE));
$o = $dispatch('GET', 'insights/ins_flat', ['HTTP_AUTHORIZATION' => 'Bearer k-123']);
check('Bearer → 单条本地读', ($o['json']['data']['title'] ?? '') === '扁平', json_encode($o, JSON_UNESCAPED_UNICODE));
$o = $dispatch('GET', 'insights/nope', ['HTTP_X_API_KEY' => 'k-123']);
check('不存在 → 错误 insight not found', ($o['json']['ok'] ?? null) === false && ($o['json']['error'] ?? '') === 'insight not found', json_encode($o));
@file_put_contents(DATA_DIR . '/api_key.json', json_encode(['key' => 'site-key']));
$o = $dispatch('GET', 'insights', [], ['api_key' => 'site-key']);
check('站点 api_key.json 的 key 也可用', ($o['json']['ok'] ?? false) === true);

echo "\n── 8. 出站：未配置时完全 no-op ──\n";
$SENT = [];
$GLOBALS['INSIGHT_FLOW_HTTP_POST'] = function (string $url, array $payload, array $headers) use (&$SENT) {
    $SENT[] = ['url' => $url, 'payload' => $payload, 'headers' => $headers];
    return ['ok' => true, 'status' => 200, 'body' => '{"ok":true}', 'error' => ''];
};
$entry = ['id' => 'evt_abc', 'event' => 'page_view', 'visitor_id' => 'v1', 'member_id' => '', 'properties' => ['p' => 1], 'timestamp' => '2026-10-12 10:00:00'];
$ret = PluginSystem::apply_filters('cdp_event_received', $entry);
check('filter 原样返回', $ret === $entry);
check('未配置不发', count($SENT) === 0);

echo "\n── 9. 出站信封 ──\n";
plugin('insight-flow')->setConfig(['if_url' => 'https://if.example/', 'if_secret' => $SECRET, 'workspace_id' => 'ws_main']);
$ret = PluginSystem::apply_filters('cdp_event_received', $entry);
check('filter 仍原样返回', $ret === $entry);
check('发出 1 条', count($SENT) === 1, (string)count($SENT));
$s = $SENT[0] ?? ['payload' => [], 'headers' => [], 'url' => ''];
check('URL = {if_url}/api/v1/ingest', $s['url'] === 'https://if.example/api/v1/ingest', $s['url']);
check('schema_version 1.1', ($s['payload']['schema_version'] ?? '') === '1.1');
check('event = cdp.event', ($s['payload']['event'] ?? '') === 'cdp.event');
check('event_id 来自 CDP 事件 id（稳定）', ($s['payload']['event_id'] ?? '') === 'of-cdp-evt_abc');
check('source = openflow', ($s['payload']['source'] ?? '') === 'openflow');
check('workspace_id 取设置', ($s['payload']['workspace_id'] ?? '') === 'ws_main');
check('occurred_at ISO8601', (bool)preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', (string)($s['payload']['occurred_at'] ?? '')));
check('data.identity / name / props', ($s['payload']['data']['identity'] ?? '') === 'v1' && ($s['payload']['data']['name'] ?? '') === 'page_view' && ($s['payload']['data']['props'] ?? []) === ['p' => 1]);
$body = insight_flow_encode($s['payload']);
check('X-IF-Signature 可验', hash_equals(hash_hmac('sha256', $body, $SECRET), hdr($s['headers'], 'X-IF-Signature')));
check('X-Inbound-Signature 同值', hdr($s['headers'], 'X-Inbound-Signature') === hdr($s['headers'], 'X-IF-Signature'));
check('X-IF-Timestamp 在当前时间附近', abs(time() - (int)hdr($s['headers'], 'X-IF-Timestamp')) < 5);
check('签名字节 = httpPost 实际发送字节', $body === json_encode($s['payload'], JSON_UNESCAPED_UNICODE));

$SENT = [];
PluginSystem::do_action('payment_success', 'ORD-9', ['member_email' => 'b@x.com', 'amount' => '199'], 'alipay');
PluginSystem::do_action('form_submitted', 'f1', 'contact', ['email' => 'l@x.com', 'utm_source' => 'ads'], ['id' => 'sub_1']);
PluginSystem::do_action('user_registered', 'm7', 'c@x.com', ['id' => 'm7']);
PluginSystem::do_action('crm_deal_won', 'd@x.com', ['id' => 'lead_5', 'value' => 5000]);
PluginSystem::do_action('cdp_segment_enter', 'seg1', ['email' => 'e@x.com'], ['name' => '高意向']);
$byEvent = [];
foreach ($SENT as $x) $byEvent[] = ($x['payload']['event'] ?? '') . '|' . ($x['payload']['event_id'] ?? '');
check('payment_success → cdp.order / of-order-ORD-9', in_array('cdp.order|of-order-ORD-9', $byEvent, true), json_encode($byEvent));
check('form_submitted → cdp.lead / of-form-sub_1', in_array('cdp.lead|of-form-sub_1', $byEvent, true));
check('user_registered → cdp.contact / of-member-m7', in_array('cdp.contact|of-member-m7', $byEvent, true));
check('crm_deal_won → cdp.order / of-deal-won-lead_5', in_array('cdp.order|of-deal-won-lead_5', $byEvent, true));
check('segment_enter → cdp.event', count(array_filter($byEvent, fn($e) => strpos($e, 'cdp.event|of-seg-enter-') === 0)) === 1);
$order = array_values(array_filter($SENT, fn($x) => ($x['payload']['event'] ?? '') === 'cdp.order'))[0]['payload']['data'] ?? [];
check('cdp.order 金额为数字', ($order['amount'] ?? null) === 199.0);
check('同输入 event_id 稳定', insight_flow_order_event('ORD-9', [], '')['event_id'] === insight_flow_order_event('ORD-9', ['amount' => 1], 'x')['event_id']);

echo "\n── 10. 旧配置键仍兼容 ──\n";
plugin('insight-flow')->setConfig(['insight_flow_url' => 'https://old.example', 'ingest_secret' => 'old']);
check('if_url 回落 insight_flow_url', insight_flow_cfg(plugin('insight-flow'), 'if_url') === 'https://old.example');
check('if_secret 回落 ingest_secret', insight_flow_cfg(plugin('insight-flow'), 'if_secret') === 'old');

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exit($fail === 0 ? 0 : 1);
