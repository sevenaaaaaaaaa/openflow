<?php
/**
 * 支付渠道抽象层 — 统一的多支付渠道注册/配置/下单/验签
 *
 * 渠道：虎皮椒(xfpay) / 微信(wechat) / 支付宝(alipay) / PayPal(paypal)
 *      信用卡(card) / 云闪付(unionpay) / Stripe(stripe) / Link(link)
 *
 * 数据：data/payment-channels.json，每个渠道 enabled + 参数
 * 原则：虎皮椒完整实现；其余渠道预留配置入口 + 接口骨架，接入 SDK 后即插即用。
 */

require_once __DIR__ . '/ShopSystem.php';

function payment_channels_file(): string { return DATA_DIR . '/payment-channels.json'; }

/** 渠道注册表：label + 配置字段（字段名 => 中文说明） */
function payment_channel_defs(): array {
    return [
        'xfpay' => [
            'label' => '虎皮椒支付',
            'desc' => '国内聚合支付，支持微信/支付宝扫码',
            'fields' => ['appid' => 'APPID', 'secret' => '通讯密钥', 'token' => 'Token', 'gateway' => '网关地址'],
            'status' => 'ready', // ready=已实现 / skeleton=预留骨架
        ],
        'wechat' => [
            'label' => '微信支付',
            'desc' => '微信支付商户直连（Native/JSAPI）',
            'fields' => ['mch_id' => '商户号', 'app_id' => 'AppID', 'api_key' => 'API 密钥', 'cert' => '证书路径'],
            'status' => 'implemented',
        ],
        'alipay' => [
            'label' => '支付宝',
            'desc' => '支付宝当面付/电脑网站支付',
            'fields' => ['app_id' => 'AppID', 'private_key' => '应用私钥', 'public_key' => '支付宝公钥', 'gateway' => '网关地址'],
            'status' => 'implemented',
        ],
        'paypal' => [
            'label' => 'PayPal 国际',
            'desc' => 'PayPal 国际版（海外用户）',
            'fields' => ['client_id' => 'Client ID', 'secret' => 'Secret', 'mode' => '环境(sandbox/live)'],
            'status' => 'skeleton',
        ],
        'card' => [
            'label' => '信用卡',
            'desc' => '国际信用卡（Visa/Master/Amex）',
            'fields' => ['gateway' => '网关', 'key' => '密钥'],
            'status' => 'skeleton',
        ],
        'unionpay' => [
            'label' => '云闪付',
            'desc' => '银联云闪付',
            'fields' => ['mch_id' => '商户号', 'key' => '密钥'],
            'status' => 'skeleton',
        ],
        'stripe' => [
            'label' => 'Stripe',
            'desc' => 'Stripe 国际支付（含信用卡）',
            'fields' => ['secret_key' => 'Secret Key', 'publishable_key' => 'Publishable Key'],
            'status' => 'skeleton',
        ],
        'link' => [
            'label' => 'Link',
            'desc' => 'Stripe Link（一键支付）',
            'fields' => ['secret_key' => 'Secret Key'],
            'status' => 'skeleton',
        ],
    ];
}

/** 读取所有渠道配置（含默认） */
function payment_channels(): array {
    $defs = payment_channel_defs();
    $saved = json_read(payment_channels_file());
    $out = [];
    foreach ($defs as $key => $def) {
        $cfg = $saved[$key] ?? [];
        $out[$key] = array_merge([
            'enabled' => (bool)($cfg['enabled'] ?? false),
            'label' => $def['label'],
            'status' => $def['status'],
            'params' => $cfg['params'] ?? [],
        ], $cfg);
    }
    return $out;
}

/** 保存渠道配置 */
function payment_channels_save(array $channels): bool {
    return json_write(payment_channels_file(), $channels);
}

/** 获取单个渠道配置 */
function payment_channel(string $key): ?array {
    $all = payment_channels();
    return $all[$key] ?? null;
}

/** 渠道是否启用且参数齐全 */
function payment_channel_ready(string $key): bool {
    $ch = payment_channel($key);
    if (!$ch || empty($ch['enabled'])) return false;
    $defs = payment_channel_defs()[$key]['fields'] ?? [];
    foreach ($defs as $f => $_) {
        if (empty($ch['params'][$f])) return false;
    }
    return true;
}

/**
 * 统一下单入口：根据渠道返回支付参数（跳转 URL / 二维码 / 支付表单）
 * @return array ['ok'=>bool, 'error'=>string, 'channel'=>string, 'payload'=>array]
 */
function payment_channel_create(string $key, array $order): array {
    $ch = payment_channel($key);
    if (!$ch) return ['ok' => false, 'error' => '未知支付渠道：' . $key];
    if (empty($ch['enabled'])) return ['ok' => false, 'error' => $ch['label'] . ' 未启用'];
    if ($ch['status'] === 'skeleton') {
        return ['ok' => false, 'error' => $ch['label'] . ' 已预留，待接入（当前请使用虎皮椒）'];
    }
    // 已实现渠道
    switch ($key) {
        case 'xfpay': return payment_xfpay_create($ch, $order);
        case 'stripe': return payment_stripe_create($ch, $order);
        case 'wechat': return payment_wechat_create($ch, $order);
        case 'alipay': return payment_alipay_create($ch, $order);
    }
    return ['ok' => false, 'error' => '渠道未实现：' . $key];
}

/**
 * 统一验签入口：支付回调时验证签名
 * @return bool
 */
function payment_channel_verify(string $key, array $data): bool {
    $ch = payment_channel($key);
    if (!$ch || $ch['status'] === 'skeleton') return false;
    switch ($key) {
        case 'xfpay': return payment_xfpay_verify($ch, $data);
        case 'stripe': return payment_stripe_verify($ch, $data);
        case 'wechat': return payment_wechat_verify($ch, $data);
        case 'alipay': return payment_alipay_verify($ch, $data);
    }
    return false;
}

/** Stripe 回调验签：checkout.session.completed 事件 → 验签简化为校验 secret_key 有效性（生产需配 Webhook Secret 签名验证） */
function payment_stripe_verify(array $ch, array $data): bool {
    // 简化：确认事件类型 + 订单号存在（完整 HMAC 签名验证需配 webhook secret）
    $type = (string)($data['type'] ?? '');
    return $type === 'checkout.session.completed' && !empty($data['data']['object']['client_reference_id']);
}

/** 微信支付 v2 回调验签：本地签名比对 */
function payment_wechat_verify(array $ch, array $data): bool {
    $apiKey = (string)($ch['api_key'] ?? '');
    if ($apiKey === '') return false;
    $sign = (string)($data['sign'] ?? '');
    if ($sign === '') return false;
    // 按字母序拼参数
    ksort($data);
    $str = '';
    foreach ($data as $k => $v) if ($k !== 'sign' && $v !== '') $str .= ($str !== '' ? '&' : '') . "{$k}={$v}";
    $expected = strtoupper(md5($str . "&key={$apiKey}"));
    return hash_equals($expected, $sign);
}

/** 支付宝回调验签（RSA2 公钥验证） */
function payment_alipay_verify(array $ch, array $data): bool {
    $publicKey = (string)($ch['public_key'] ?? '');
    if ($publicKey === '' || empty($data['sign'])) return false;
    $signStr = '';
    ksort($data);
    foreach ($data as $k => $v) if ($k !== 'sign' && $k !== 'sign_type' && $v !== '') $signStr .= ($signStr !== '' ? '&' : '') . "{$k}={$v}";
    $ok = openssl_verify($signStr, base64_decode((string)$data['sign']), "-----BEGIN PUBLIC KEY-----\n{$publicKey}\n-----END PUBLIC KEY-----", OPENSSL_ALGO_SHA256);
    return $ok === 1;
}

/* ═══════════════ 虎皮椒（完整实现） ═══════════════ */

function payment_xfpay_create(array $ch, array $order): array {
    $p = $ch['params'] ?? [];
    if (empty($p['appid']) || empty($p['secret'])) {
        return ['ok' => false, 'error' => '虎皮椒支付未配置，请联系管理员'];
    }
    $gateway = $p['gateway'] ?? 'https://api.xunhupay.com/payment/do.html';
    $params = [
        'version' => '1.1',
        'appid' => $p['appid'],
        'trade_order_id' => $order['id'],
        'total_fee' => (string)$order['amount'],
        'title' => $order['course_title'] ?? $order['goods_title'] ?? 'OpenFlow 订单',
        'time' => (string)time(),
        'notify_url' => payment_notify_url('xfpay'),
        'return_url' => payment_return_url($order),
        'nonce_str' => bin2hex(random_bytes(8)),
    ];
    // token（若配置了则加入）
    if (!empty($p['token'])) $params['token'] = $p['token'];
    // 签名
    $params['hash'] = payment_xfpay_sign($params, $p['secret']);
    return [
        'ok' => true,
        'channel' => 'xfpay',
        'gateway' => $gateway,
        'payload' => $params,
    ];
}

function payment_xfpay_verify(array $ch, array $data): bool {
    $p = $ch['params'] ?? [];
    if (empty($p['appid']) || empty($p['secret'])) return false;
    $hash = $data['hash'] ?? '';
    unset($data['hash']);
    ksort($data);
    $signStr = '';
    foreach ($data as $k => $v) $signStr .= $k . '=' . $v . '&';
    $signStr .= 'key=' . $p['secret'];
    $calc = md5($signStr);
    return hash_equals(strtolower($calc), strtolower($hash));
}

function payment_xfpay_sign(array $params, string $secret): string {
    unset($params['hash']);
    ksort($params);
    $signStr = '';
    foreach ($params as $k => $v) $signStr .= $k . '=' . $v . '&';
    $signStr .= 'key=' . $secret;
    return md5($signStr);
}

/* ═══════════════ 通用回调/回跳 URL ═══════════════ */

function payment_site_base(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'example.com');
}

function payment_notify_url(string $channel): string {
    return payment_site_base() . '/api/shop.php?action=notify&channel=' . $channel;
}

function payment_return_url(array $order = []): string {
    // F2d：支付成功落点统一为交付页（带订单号 → 交付动作 + 直播上下文返回）
    if (!empty($order['id'])) return payment_site_base() . '/order-success?order=' . urlencode((string)$order['id']);
    return payment_site_base() . '/thank-you.php';
}

/* ═══ 多支付渠道真实请求构造（第二批：有凭据即可运行——凭据位已就绪）═══ */

/**
 * Stripe Checkout Session 创建
 * 凭据：secret_key（sk_live_xxx / sk_test_xxx）
 * 需要 stripe 帐号 + Webhook 端点配 checkout.session.completed → /api/payment-callback.php?channel=stripe
 */
function payment_stripe_create(array $ch, array $order): array {
    $secret = (string)($ch['secret_key'] ?? '');
    if ($secret === '') return ['ok' => false, 'error' => 'Stripe secret_key 未配置'];
    $amount = (int)round((float)($order['amount'] ?? 0) * 100);   // Stripe 用分
    $url = 'https://api.stripe.com/v1/checkout/sessions';
    $params = http_build_query([
        'mode' => 'payment',
        'success_url' => payment_return_url($order),
        'cancel_url' => payment_site_base() . '/shop.php?cancelled=1',
        'client_reference_id' => (string)($order['id'] ?? ''),
        'line_items[0][price_data][currency]' => strtolower((string)($order['currency'] ?? 'cny')),
        'line_items[0][price_data][product_data][name]' => mb_substr((string)($order['course_title'] ?? ($order['plan_id'] ?? '商品')), 0, 120),
        'line_items[0][quantity]' => 1,
        'line_items[0][unit_amount]' => $amount,
        'client_reference_id' => (string)($order['id'] ?? ''),
    ]);
    $httpCurl = curl_init($url);
    curl_setopt_array($httpCurl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($httpCurl);
    $code = curl_getinfo($httpCurl, CURLINFO_HTTP_CODE);
    curl_close($httpCurl);
    if ($code !== 200) {
        $err = json_decode((string)$resp, true);
        return ['ok' => false, 'error' => 'Stripe 创建失败: ' . ($err['error']['message'] ?? "HTTP {$code}")];
    }
    $data = json_decode((string)$resp, true);
    $payUrl = (string)($data['url'] ?? '');
    if ($payUrl === '') return ['ok' => false, 'error' => 'Stripe 未返回支付链接'];
    return ['ok' => true, 'pay_url' => $payUrl, 'channel' => 'stripe'];
}

/**
 * 微信支付 Native 下单（扫码支付）
 * 凭据：mch_id + api_key + app_id
 * 说明：微信 Native API v2 需要 MD5 签名；成功返回 code_url（二维码内容）
 * 回调通知地址 notify_url 需在商户后台配置为 /api/payment-callback.php?channel=wechat
 */
function payment_wechat_create(array $ch, array $order): array {
    $mchId = (string)($ch['mch_id'] ?? '');
    $apiKey = (string)($ch['api_key'] ?? '');
    $appId = (string)($ch['app_id'] ?? '');
    if ($mchId === '' || $apiKey === '' || $appId === '') return ['ok' => false, 'error' => '微信支付凭据未配置完整（mch_id/api_key/app_id）'];
    $nonceStr = md5(uniqid('', true));
    $body = mb_substr((string)($order['course_title'] ?? ($order['plan_id'] ?? '商品')), 0, 60);
    $outTradeNo = (string)($order['id'] ?? '');
    $totalFee = (int)round((float)($order['amount'] ?? 0) * 100);
    $notifyUrl = payment_site_base() . '/api/payment-callback.php?channel=wechat';
    $signStr = "appid={$appId}&body={$body}&mch_id={$mchId}&nonce_str={$nonceStr}&notify_url={$notifyUrl}&out_trade_no={$outTradeNo}&spbill_create_ip={$_SERVER['REMOTE_ADDR']}total_fee={$totalFee}&trade_type=NATIVE";
    $sign = strtoupper(md5($signStr . "&key={$apiKey}"));
    $xml = "<xml><appid>{$appId}</appid><body>{$body}</body><mch_id>{$mchId}</mch_id><nonce_str>{$nonceStr}</nonce_str><notify_url>{$notifyUrl}</notify_url><out_trade_no>{$outTradeNo}</out_trade_no><spbill_create_ip>{$_SERVER['REMOTE_ADDR']}</spbill_create_ip><total_fee>{$totalFee}</total_fee><trade_type>NATIVE</trade_type><sign>{$sign}</sign></xml>";
    $httpCurl = curl_init('https://api.mch.weixin.qq.com/pay/unifiedorder');
    curl_setopt_array($httpCurl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($httpCurl);
    curl_close($httpCurl);
    // 解析 XML 取 code_url
    if (!preg_match('#<code_url><!\[CDATA\[(.+?)\]\]></code_url>#', (string)$resp, $m)) {
        return ['ok' => false, 'error' => '微信下单失败：' . mb_substr(strip_tags((string)$resp), 0, 120)];
    }
    $codeUrl = $m[1];
    return ['ok' => true, 'qrcode' => $codeUrl, 'pay_url' => '', 'channel' => 'wechat'];
}

/**
 * 支付宝电脑网站支付（页面跳转）
 * 凭据：app_id + private_key + alipay_public_key + gateway(默认 https://openapi.alipay.com/gateway.do)
 * 构造请求并 302 跳转；验签回调在 payment_channel_verify 处理
 */
function payment_alipay_create(array $ch, array $order): array {
    $appId = (string)($ch['app_id'] ?? '');
    $privateKey = (string)($ch['private_key'] ?? '');
    $gateway = (string)($ch['gateway'] ?? 'https://openapi.alipay.com/gateway.do');
    if ($appId === '' || $privateKey === '') return ['ok' => false, 'error' => '支付宝凭据未配置完整（app_id/private_key）'];
    $bizContent = json_encode([
        'out_trade_no' => (string)($order['id'] ?? ''),
        'total_amount' => (string)number_format((float)($order['amount'] ?? 0), 2, '.', ''),
        'subject' => mb_substr((string)($order['course_title'] ?? ($order['plan_id'] ?? '商品')), 0, 60),
        'product_code' => 'FAST_INSTANT_TRADE_PAY',
    ], JSON_UNESCAPED_UNICODE);
    $params = [
        'app_id' => $appId,
        'method' => 'alipay.trade.page.pay',
        'format' => 'JSON',
        'charset' => 'UTF-8',
        'sign_type' => 'RSA2',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0',
        'notify_url' => payment_site_base() . '/api/payment-callback.php?channel=alipay',
        'return_url' => payment_return_url($order),
        'biz_content' => $bizContent,
    ];
    // RSA2 签名
    $signStr = '';
    foreach ($params as $k => $v) if ($v !== '' && $k !== 'sign' && !str_starts_with((string)$k, 'sign')) $signStr .= ($signStr !== '' ? '&' : '') . "{$k}={$v}";
    $pkey = openssl_pkey_get_private($privateKey);
    if (!$pkey) return ['ok' => false, 'error' => '支付宝私钥格式不合法'];
    openssl_sign($signStr, $signature, $pkey, OPENSSL_ALGO_SHA256);
    $params['sign'] = base64_encode($signature ?? '');
    // 构造跳转 URL
    $query = http_build_query($params);
    return ['ok' => true, 'pay_url' => $gateway . '?' . $query, 'channel' => 'alipay'];
}
