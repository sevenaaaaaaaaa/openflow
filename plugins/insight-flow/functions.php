<?php
declare(strict_types=1);
/**
 * Insight Flow 插件 · 纯函数层（三方契约 v1.1）
 *
 * plugin.php 只做注册；可测的逻辑都在这里：
 *   - 配置读取（新键 if_url / if_secret / if_api_key，回落旧键 insight_flow_url / ingest_secret / api_key）
 *   - 出站信封 + 签名头（X-IF-Signature / X-Inbound-Signature / X-IF-Timestamp）
 *   - 入站 inbox 处理（验签 → 时间窗 → 必填 → 幂等 → 分发）
 *   - 动作反馈信封 openflow.action_feedback
 *
 * 测试注入：$GLOBALS['INSIGHT_FLOW_HTTP_POST'] = function (string $url, array $payload, array $headers): array
 *           返回 ['ok'=>bool,'status'=>int,'body'=>string,'error'=>string]，替代真实 HTTP。
 */

require_once __DIR__ . '/../../lib/InsightStore.php';

if (!function_exists('insight_flow_cfg')) {

    define('INSIGHT_FLOW_SCHEMA_VERSION', '1.1');
    define('INSIGHT_FLOW_MAX_SKEW', 300);
    /** openflow.action_feedback 允许的 status（契约 §2） */
    define('INSIGHT_FLOW_FEEDBACK_STATUSES', ['done', 'dismissed', 'effective', 'ineffective']);

    /** 读配置：新键优先，空则回落旧键。 */
    function insight_flow_cfg(PluginContext $p, string $key): string {
        $legacy = ['if_url' => 'insight_flow_url', 'if_secret' => 'ingest_secret', 'if_api_key' => 'api_key'];
        $v = trim((string)$p->get($key, ''));
        if ($v === '' && isset($legacy[$key])) $v = trim((string)$p->get($legacy[$key], ''));
        return $key === 'if_url' ? rtrim($v, '/') : $v;
    }

    /** 出站通道是否可用（未配 URL 或密钥 → 整个插件出站是 no-op） */
    function insight_flow_outbound_ready(PluginContext $p): bool {
        return insight_flow_cfg($p, 'if_url') !== '' && insight_flow_cfg($p, 'if_secret') !== ''
            && (bool)$p->get('push_enabled', true);
    }

    /** 契约 §1 统一信封 */
    function insight_flow_envelope(string $event, string $eventId, array $data, string $workspaceId = 'default', ?string $occurredAt = null): array {
        return [
            'schema_version' => INSIGHT_FLOW_SCHEMA_VERSION,
            'event'          => $event,
            'event_id'       => $eventId,
            'occurred_at'    => $occurredAt ?: gmdate('Y-m-d\TH:i:s\Z'),
            'source'         => 'openflow',
            'workspace_id'   => $workspaceId !== '' ? $workspaceId : 'default',
            'data'           => $data,
        ];
    }

    /** 与 PluginContext::httpPost 完全相同的编码方式——签名必须对实际发出的字节计算 */
    function insight_flow_encode(array $payload): string {
        return (string)json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /** 签名头：X-IF-Signature + X-Inbound-Signature（同值，兼容期）+ X-IF-Timestamp */
    function insight_flow_sign_headers(string $rawBody, string $secret, ?int $ts = null): array {
        $sig = hash_hmac('sha256', $rawBody, $secret);
        return [
            'X-IF-Signature: ' . $sig,
            'X-Inbound-Signature: ' . $sig,
            'X-IF-Timestamp: ' . (string)($ts ?? time()),
        ];
    }

    /** 任意时间值 → ISO8601 UTC；解析失败用当前时间 */
    function insight_flow_iso($t): string {
        if (is_numeric($t)) {
            $n = (float)$t;
            if ($n > 1e12) $n /= 1000; // 毫秒
            return gmdate('Y-m-d\TH:i:s\Z', (int)$n);
        }
        $u = is_string($t) && $t !== '' ? strtotime($t) : false;
        return gmdate('Y-m-d\TH:i:s\Z', $u ?: time());
    }

    /**
     * 发送一个信封到 {if_url}/api/v1/ingest。永不抛异常；未配置返回 skipped。
     * @return array ['ok'=>bool,'status'=>int,'error'=>string,'skipped'=>bool]
     */
    function insight_flow_send(PluginContext $p, array $envelope): array {
        if (!insight_flow_outbound_ready($p)) return ['ok' => false, 'status' => 0, 'error' => 'not_configured', 'skipped' => true];
        try {
            $url = insight_flow_cfg($p, 'if_url') . '/api/v1/ingest';
            $raw = insight_flow_encode($envelope);
            $headers = insight_flow_sign_headers($raw, insight_flow_cfg($p, 'if_secret'));
            $fn = $GLOBALS['INSIGHT_FLOW_HTTP_POST'] ?? null;
            $r = is_callable($fn) ? (array)$fn($url, $envelope, $headers) : $p->httpPost($url, $envelope, $headers, 5);
            $ok = !empty($r['ok']);
            $p->log(($ok ? '出站成功:' : '出站失败:') . ($envelope['event'] ?? '?') . ' ' . ($envelope['event_id'] ?? '')
                    . (!empty($r['error']) ? ' ' . $r['error'] : ''), $ok ? 'info' : 'error');
            return ['ok' => $ok, 'status' => (int)($r['status'] ?? 0), 'error' => (string)($r['error'] ?? ''), 'skipped' => false];
        } catch (\Throwable $e) {
            $p->logError('insight_flow_send', $e);
            return ['ok' => false, 'status' => 0, 'error' => $e->getMessage(), 'skipped' => false];
        }
    }

    // ── 出站事件构造（cdp.*）──────────────────────────

    /**
     * CDP 行为事件 → cdp.event。event_id 取 CDP 自身的事件 id（EventIdentity，重试稳定）；
     * 没有 id 时才用内容哈希兜底。
     */
    function insight_flow_cdp_event(array $entry): array {
        $identity = (string)($entry['visitor_id'] ?? '');
        $name = (string)($entry['event'] ?? '');
        $props = $entry['properties'] ?? ($entry['props'] ?? []);
        $at = $entry['timestamp'] ?? ($entry['at'] ?? null);
        $rid = (string)($entry['id'] ?? ($entry['message_id'] ?? ''));
        if ($rid === '') $rid = substr(sha1($name . '|' . $identity . '|' . json_encode($props) . '|' . (string)$at), 0, 24);
        $iso = insight_flow_iso($at);
        return [
            'event' => 'cdp.event',
            'event_id' => 'of-cdp-' . $rid,
            'occurred_at' => $iso,
            'data' => [
                'identity' => $identity, 'visitor_id' => $identity,
                'member_id' => (string)($entry['member_id'] ?? ''),
                'name' => $name, 'props' => is_array($props) ? $props : [],
                'occurred_at' => $iso,
            ],
        ];
    }

    /** 分群进出 → cdp.event（同一人同一分群同一分钟内的同向变化视为同一事件） */
    function insight_flow_segment_event(string $kind, $segId, array $profile, array $seg, ?int $now = null): array {
        $identity = (string)($profile['email'] ?? ($profile['visitor_id'] ?? ''));
        $minute = (int)floor(($now ?? time()) / 60);
        return [
            'event' => 'cdp.event',
            'event_id' => 'of-seg-' . $kind . '-' . substr(sha1((string)$segId . '|' . $identity . '|' . $minute), 0, 20),
            'data' => ['identity' => $identity, 'visitor_id' => $identity, 'name' => 'segment_' . $kind,
                       'props' => ['segment' => (string)($seg['name'] ?? (string)$segId), 'segment_id' => (string)$segId]],
        ];
    }

    /** 支付成功 → cdp.order（event_id 取订单号） */
    function insight_flow_order_event(string $orderId, array $order, string $method): array {
        $identity = (string)($order['member_email'] ?? ($order['email'] ?? ''));
        return [
            'event' => 'cdp.order',
            'event_id' => 'of-order-' . $orderId,
            'data' => ['identity' => $identity, 'email' => $identity, 'order_id' => $orderId,
                       'amount' => (float)($order['amount'] ?? 0), 'method' => $method, 'name' => 'payment_success'],
        ];
    }

    /** CRM 赢单 → cdp.order（kind=crm_deal；同一线索赢单只算一次） */
    function insight_flow_deal_event(string $email, array $lead): array {
        $ref = (string)($lead['id'] ?? '') ?: substr(sha1(mb_strtolower($email)), 0, 16);
        return [
            'event' => 'cdp.order',
            'event_id' => 'of-deal-won-' . $ref,
            'data' => ['identity' => $email, 'email' => $email, 'amount' => (float)($lead['value'] ?? 0),
                       'kind' => 'crm_deal', 'name' => 'crm_deal_won',
                       'channel' => (string)($lead['source'] ?? '')],
        ];
    }

    /** 表单提交 → cdp.lead（event_id 取提交记录 id） */
    function insight_flow_form_event($formId, $type, array $formData, array $submission): array {
        $email = (string)($formData['email'] ?? '');
        $sid = (string)($submission['id'] ?? '') ?: substr(sha1((string)$formId . '|' . $email . '|' . json_encode($formData)), 0, 20);
        return [
            'event' => 'cdp.lead',
            'event_id' => 'of-form-' . $sid,
            'data' => ['email' => $email, 'identity' => $email, 'form_id' => (string)$formId, 'form_type' => (string)$type,
                       'channel' => (string)($formData['utm_source'] ?? ''), 'campaign' => (string)($formData['utm_campaign'] ?? ''),
                       'name' => 'form_submitted'],
        ];
    }

    /** 会员注册 → cdp.contact（event_id 取会员 id） */
    function insight_flow_register_event($memberId, string $email, array $member): array {
        $ref = (string)$memberId !== '' ? (string)$memberId : substr(sha1(mb_strtolower($email)), 0, 16);
        return [
            'event' => 'cdp.contact',
            'event_id' => 'of-member-' . $ref,
            'data' => ['email' => $email, 'identity' => $email, 'member_id' => (string)$memberId,
                       'channel' => (string)($member['source'] ?? ''), 'name' => 'user_registered'],
        ];
    }

    /** 构造好的 {event,event_id,data,occurred_at?} → 完整信封并发送 */
    function insight_flow_emit(PluginContext $p, array $ev): array {
        if (!insight_flow_outbound_ready($p)) return ['ok' => false, 'skipped' => true, 'error' => 'not_configured', 'status' => 0];
        $env = insight_flow_envelope((string)$ev['event'], (string)$ev['event_id'], (array)$ev['data'],
                                     (string)$p->get('workspace_id', 'default'), $ev['occurred_at'] ?? null);
        return insight_flow_send($p, $env);
    }

    // ── 动作反馈 openflow.action_feedback ────────────

    /**
     * 构造反馈信封。必填 insight_id；status 必须在契约枚举内（兼容旧字段 result）。
     * event_id = of-fb-<action_id>-<status>：同一动作同一状态重试稳定。
     * @return array|null 不合法返回 null
     */
    function insight_flow_feedback_envelope(array $in, string $workspaceId = 'default'): ?array {
        $insightId = trim((string)($in['insight_id'] ?? ''));
        $status = trim((string)($in['status'] ?? ($in['result'] ?? '')));
        if ($insightId === '' || !in_array($status, INSIGHT_FLOW_FEEDBACK_STATUSES, true)) return null;
        $actionId = trim((string)($in['action_id'] ?? ''));
        $at = (string)($in['at'] ?? '') ?: insight_flow_iso(null);
        $ref = $actionId !== '' ? $actionId : substr(sha1($insightId . '|' . (string)($in['action'] ?? '')), 0, 16);
        return insight_flow_envelope('openflow.action_feedback', 'of-fb-' . $ref . '-' . $status, [
            'insight_id' => $insightId,
            'action_id'  => $actionId,
            'module'     => (string)($in['module'] ?? ''),
            'action'     => (string)($in['action'] ?? ''),
            'status'     => $status,
            'at'         => $at,
        ], $workspaceId);
    }

    /** growth_action_status_changed 钩子的处理体：有 insight_id 且已配置才发 */
    function insight_flow_on_action_status(PluginContext $p, array $action, string $status): array {
        if (trim((string)($action['insight_id'] ?? '')) === '') return ['ok' => false, 'skipped' => true, 'error' => 'no_insight_id'];
        if (!insight_flow_outbound_ready($p)) return ['ok' => false, 'skipped' => true, 'error' => 'not_configured'];
        $at = (string)($action['done_at'] ?? '') ?: (string)($action['updated_at'] ?? '');
        $env = insight_flow_feedback_envelope([
            'insight_id' => $action['insight_id'], 'action_id' => $action['id'] ?? '',
            'module' => $action['module'] ?? '', 'action' => $action['action'] ?? '',
            'status' => $status, 'at' => $at !== '' ? insight_flow_iso($at) : '',
        ], (string)$p->get('workspace_id', 'default'));
        if ($env === null) return ['ok' => false, 'skipped' => true, 'error' => 'status_not_reported'];
        return insight_flow_send($p, $env);
    }

    // ── 入站 inbox ───────────────────────────────────

    /** 从 $_SERVER 收集需要的请求头（小写键） */
    function insight_flow_request_headers(array $server): array {
        $map = [
            'x-if-signature' => 'HTTP_X_IF_SIGNATURE',
            'x-inbound-signature' => 'HTTP_X_INBOUND_SIGNATURE',
            'x-insight-flow-signature' => 'HTTP_X_INSIGHT_FLOW_SIGNATURE',
            'x-if-timestamp' => 'HTTP_X_IF_TIMESTAMP',
        ];
        $h = [];
        foreach ($map as $k => $sk) if (isset($server[$sk]) && $server[$sk] !== '') $h[$k] = (string)$server[$sk];
        return $h;
    }

    /**
     * 处理 POST /api/plugin/insight-flow/inbox。
     * $headers 键为小写头名。返回 ['status'=>HTTP 码, 'body'=>数组]。
     * 顺序：验签(401) → 时间窗(401) → JSON/必填(400) → 未知事件(400) → 幂等(200 duplicate) → 落库。
     */
    function insight_flow_inbox_handle(string $raw, array $headers, string $secret, ?int $now = null): array {
        $err = fn(int $code, string $msg) => ['status' => $code, 'body' => ['ok' => false, 'error' => $msg]];
        if ($secret === '') return $err(401, '未配置 if_secret（共享密钥），拒绝入站');

        $sig = (string)($headers['x-if-signature'] ?? ($headers['x-inbound-signature'] ?? ($headers['x-insight-flow-signature'] ?? '')));
        $sig = strtolower(trim((string)preg_replace('/^sha256=/i', '', $sig)));
        if ($sig === '' || !hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) return $err(401, 'signature verification failed');

        if (isset($headers['x-if-timestamp']) && $headers['x-if-timestamp'] !== '') {
            $ts = (string)$headers['x-if-timestamp'];
            if (!ctype_digit($ts) || abs(($now ?? time()) - (int)$ts) > INSIGHT_FLOW_MAX_SKEW) {
                return $err(401, 'timestamp outside allowed window');
            }
        }

        $env = json_decode($raw, true);
        if (!is_array($env)) return $err(400, 'invalid JSON body');
        $event = trim((string)($env['event'] ?? ($env['type'] ?? '')));
        $eventId = trim((string)($env['event_id'] ?? ''));
        if ($event === '') return $err(400, 'missing event');
        if ($eventId === '') return $err(400, 'missing event_id');
        $source = trim((string)($env['source'] ?? '')) ?: 'insight-flow';

        if ($event !== 'insight.upsert') return $err(400, "unknown event: {$event}");

        if (insight_ingest_seen($source, $eventId)) {
            return ['status' => 200, 'body' => ['ok' => true, 'duplicate' => true, 'event_id' => $eventId]];
        }

        $flat = insight_store_normalize($env);
        if (trim((string)($flat['insight_id'] ?? '')) === '') return $err(400, 'missing insight_id');
        $r = insight_store_upsert($flat, $source);
        if (empty($r['ok'])) return $err(400, (string)($r['error'] ?? 'upsert failed'));
        insight_ingest_mark($source, $eventId, $event, (string)$flat['insight_id']);
        return ['status' => 200, 'body' => ['ok' => true, 'duplicate' => false, 'event_id' => $eventId,
                                             'insight_id' => (string)$flat['insight_id'], 'created' => (bool)$r['created']]];
    }

    // ── 读接口鉴权 ───────────────────────────────────

    /**
     * GET insights 鉴权：后台已登录，或 X-API-Key / Bearer / ?api_key 命中
     *   ① 插件设置 if_api_key（与 InFlow 共享的 API Key），或 ② 站点 data/api_key.json 的 key
     *     （与 api/segments.php、api/leads.php 同一把）。
     */
    function insight_flow_read_authorized(PluginContext $p, array $server, array $query): bool {
        if (function_exists('is_logged_in') && is_logged_in()) return true;
        $given = (string)($server['HTTP_X_API_KEY'] ?? '');
        $auth = (string)($server['HTTP_AUTHORIZATION'] ?? '');
        if ($given === '' && stripos($auth, 'Bearer ') === 0) $given = trim(substr($auth, 7));
        if ($given === '') $given = (string)($query['api_key'] ?? '');
        if ($given === '') return false;
        $keys = [insight_flow_cfg($p, 'if_api_key')];
        if (defined('DATA_DIR') && is_file(DATA_DIR . '/api_key.json')) {
            $d = json_decode((string)@file_get_contents(DATA_DIR . '/api_key.json'), true);
            $keys[] = (string)($d['key'] ?? '');
        }
        foreach ($keys as $k) if ($k !== '' && hash_equals($k, $given)) return true;
        return false;
    }

    /** 入站 IF 洞察（live 拉取结果）批量缓存进本地库；返回写入条数 */
    function insight_flow_cache_remote(array $insights, string $workspaceId = ''): int {
        $n = 0;
        foreach ($insights as $ins) {
            if (!is_array($ins)) continue;
            if ($workspaceId !== '' && empty($ins['workspace_id'])) $ins['workspace_id'] = $workspaceId;
            try { if (!empty(insight_store_upsert($ins, 'insight-flow')['ok'])) $n++; } catch (\Throwable $e) {}
        }
        return $n;
    }
}
