<?php
/**
 * UserLoop 触达桥接插件 —— 让 UserLoop 复用 OpenFlow 的触达实现（零改内核）。
 *
 * 端点（服务间调用，Header: X-UserLoop-Bridge: <token>）：
 *   POST /api/plugin/userloop-bridge/mail          邮件（多通道 + 抑制名单 + HTML）
 *   POST /api/plugin/userloop-bridge/wechat        公众号消息
 *   POST /api/plugin/userloop-bridge/wecom         企微应用消息
 *   POST /api/plugin/userloop-bridge/sms           短信（未实现则明确报错）
 *   GET  /api/plugin/userloop-bridge/capabilities  能力自述（可用渠道探测）
 *
 * 纪律：失败必须显式 message（让 UserLoop 能换渠道降级），不静默失败。
 * 响应统一为 {ok, data:{...}} / {ok:false, error:'...'}（PluginApiResponse 契约）。
 */
require_once __DIR__ . '/../../lib/PluginSDK.php';

$p = plugin('userloop-bridge');

/** token 鉴权：插件配置 bridge_token + 请求头 X-UserLoop-Bridge */
$guard = function () use ($p): ?array {
    $expect = (string)$p->get('bridge_token', '');
    if ($expect === '') return ['ok' => false, 'message' => 'bridge_token 未配置（插件设置里填写）'];
    if (!hash_equals($expect, (string)($_SERVER['HTTP_X_USERLOOP_BRIDGE'] ?? ''))) {
        return ['ok' => false, 'message' => '鉴权失败'];
    }
    return null;
};

/** 统一返回：业务失败也走 200 + ok=false，便于 UserLoop 解析并降级 */
$fail = function (string $msg, array $extra = []) {
    if ($extra) {   // 需附带结构化信息（如 suppressed）时用 data 形态
        return PluginApiResponse::json(array_merge(['ok' => false, 'message' => $msg], $extra));
    }
    return PluginApiResponse::error($msg);
};
$done = function (array $data = []) {
    return PluginApiResponse::json($data);   // 外层由插件系统包 {ok:true,data:...}
};

// ── 邮件 ──
PluginSystem::register_api_route('userloop-bridge', 'POST', 'mail',
    function (array $req) use ($guard, $fail, $done, $p) {
        if ($err = $guard()) return $fail($err['message']);
        $d = $req['body'] ?? [];
        $to = trim((string)($d['to'] ?? ''));
        $subject = (string)($d['subject'] ?? '');
        $html = (string)($d['html'] ?? '');
        if ($to === '' || $subject === '') return $fail('缺少 to/subject');

        require_once __DIR__ . '/../../lib/MailChannel.php';
        require_once __DIR__ . '/../../lib/EmailDeliverability.php';

        if (function_exists('email_is_suppressed') && email_is_suppressed($to)) {
            return $fail('收件人在抑制名单（退订/退信/投诉）', ['suppressed' => true]);
        }
        $channel = (string)($d['channel'] ?? '');
        $ok = function_exists('mail_send') && mail_send($to, $subject, $html, $channel);
        $p->log(($ok ? 'mail ok: ' : 'mail fail: ') . $to . ' | ' . $subject);
        if (!$ok) return $fail('mail_send 失败（检查邮件渠道/发件域认证）');
        return $done(['ref' => 'mail:' . substr(md5($to . $subject . microtime()), 0, 16),
                      'channel' => $channel ?: 'default']);
    }, ['auth' => 'none']);

// ── 公众号 ──
PluginSystem::register_api_route('userloop-bridge', 'POST', 'wechat',
    function (array $req) use ($guard, $fail, $done, $p) {
        if ($err = $guard()) return $fail($err['message']);
        require_once __DIR__ . '/../../lib/WechatMp.php';
        if (!class_exists('WechatMp')) return $fail('WechatMp 不可用');
        $d = $req['body'] ?? [];
        $openid = (string)($d['openid'] ?? '');
        if ($openid === '') return $fail('缺少 openid（需公众号授权链路）');
        if (!method_exists('WechatMp', 'sendCustomMessage')) {
            return $fail('公众号客服消息未实现：当前仅支持群发/预览（需模板消息配置）');
        }
        // 运行时由上面的 method_exists 守卫；未来实现该方法即自动生效
        // @phpstan-ignore staticMethod.notFound
        $r = WechatMp::sendCustomMessage($openid, (string)($d['text'] ?? ''), (string)($d['url'] ?? ''));
        $p->log('wechat ' . $openid . ' -> ' . json_encode($r, JSON_UNESCAPED_UNICODE));
        return is_array($r) && ($r['ok'] ?? false) ? $done(['ref' => $r['msgid'] ?? null]) : $fail($r['message'] ?? '公众号发送失败');
    }, ['auth' => 'none']);

// ── 企微 ──
PluginSystem::register_api_route('userloop-bridge', 'POST', 'wecom',
    function (array $req) use ($guard, $fail, $done, $p) {
        if ($err = $guard()) return $fail($err['message']);
        require_once __DIR__ . '/../../lib/Wecom.php';
        if (!class_exists('Wecom')) return $fail('Wecom 不可用');
        $d = $req['body'] ?? [];
        $userid = (string)($d['userid'] ?? '');
        if ($userid === '') return $fail('缺少 userid（需企微客户联系链路）');
        if (!method_exists('Wecom', 'sendAppMessage')) {
            return $fail('企微应用消息未实现：当前仅支持标签/客户列表');
        }
        $r = Wecom::sendAppMessage(['touser' => $userid], 'text', ['content' => (string)($d['text'] ?? '')]);
        $p->log('wecom ' . $userid . ' -> ' . json_encode($r, JSON_UNESCAPED_UNICODE));
        return is_array($r) && ($r['ok'] ?? false) ? $done(['ref' => $r['msgid'] ?? null]) : $fail($r['message'] ?? '企微发送失败');
    }, ['auth' => 'none']);

// ── 短信（OpenFlow 只有供应商配置，无发送实现 → 明确报错，不假装成功）──
PluginSystem::register_api_route('userloop-bridge', 'POST', 'sms',
    function (array $req) use ($guard, $fail) {
        if ($err = $guard()) return $fail($err['message']);
        return $fail('OpenFlow 短信仅支持供应商配置（admin/sms.php），发送实现待补；请在 UserLoop 侧接阿里云/腾讯云直连');
    }, ['auth' => 'none']);

// ── 抑制名单：退订/退信/投诉 → 永久不再发（保护发件声誉）──
PluginSystem::register_api_route('userloop-bridge', 'POST', 'suppress',
    function (array $req) use ($guard, $fail, $done, $p) {
        if ($err = $guard()) return $fail($err['message']);
        require_once __DIR__ . '/../../lib/EmailDeliverability.php';
        if (!function_exists('email_suppress')) return $fail('EmailDeliverability 不可用');
        $d = $req['body'] ?? [];
        $email = trim((string)($d['email'] ?? ''));
        if ($email === '') return $fail('缺少 email');
        $ok = email_suppress($email, (string)($d['reason'] ?? 'userloop_unsubscribe'));
        $p->log('suppress ' . $email . ' -> ' . ($ok ? 'ok' : 'fail'));
        return $ok ? $done(['email' => $email]) : $fail('写入抑制名单失败');
    }, ['auth' => 'none']);

// ── 自动化触发：UserLoop 的旅程断点 → OpenFlow 自动化/画布（系统唯一入口 flow_handle）──
PluginSystem::register_api_route('userloop-bridge', 'POST', 'automation',
    function (array $req) use ($guard, $fail, $done, $p) {
        if ($err = $guard()) return $fail($err['message']);
        require_once __DIR__ . '/../../lib/FlowSystem.php';
        if (!function_exists('flow_handle')) return $fail('FlowSystem 不可用');
        $d = $req['body'] ?? [];
        $event = trim((string)($d['event'] ?? ''));
        if ($event === '') return $fail('缺少 event（OpenFlow 事件名，如 userloop_journey）');
        $ctx = [
            'uid' => (string)($d['visitor_id'] ?? ''),
            'email' => (string)($d['email'] ?? ''),
            'member_id' => (string)($d['member_id'] ?? ''),
            'source' => 'userloop',
            'loop_id' => (string)($d['loop_id'] ?? ''),
            'template_id' => (string)($d['template_id'] ?? ''),
            'message' => (string)($d['message'] ?? ''),
            'extra' => (array)($d['extra'] ?? []),
        ];
        $r = flow_handle($event, $ctx);
        $p->log('automation ' . $event . ' -> ' . json_encode(['triggers' => $r['triggers'] ?? []], JSON_UNESCAPED_UNICODE));
        return $done(['triggered' => true, 'event' => $event,
                      'triggers' => $r['triggers'] ?? [], 'tagged' => $r['tagged'] ?? []]);
    }, ['auth' => 'none']);

// ── 能力自述 ──
PluginSystem::register_api_route('userloop-bridge', 'GET', 'capabilities',
    function (array $req) use ($guard, $fail, $done) {
        if ($err = $guard()) return $fail($err['message']);
        require_once __DIR__ . '/../../lib/MailChannel.php';
        $channels = function_exists('mail_channels') ? mail_channels() : [];
        $enabled = [];
        foreach ($channels as $k => $c) {
            if ($k !== '_default' && !empty($c['enabled'])) $enabled[] = $k;
        }
        return $done(['channels' => [
            'email' => $enabled ?: [],
            'wechat_mp' => class_exists('WechatMp') ? ['mass', 'preview', 'tags'] : [],
            'wecom' => class_exists('Wecom') ? ['tags', 'customers'] : [],
            'sms' => [],
            'automation' => function_exists('flow_handle') ? ['flow_handle'] : [],
        ]]);
    }, ['auth' => 'none']);
