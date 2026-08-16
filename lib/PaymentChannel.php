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
            'status' => 'skeleton',
        ],
        'alipay' => [
            'label' => '支付宝',
            'desc' => '支付宝当面付/电脑网站支付',
            'fields' => ['app_id' => 'AppID', 'private_key' => '应用私钥', 'public_key' => '支付宝公钥', 'gateway' => '网关地址'],
            'status' => 'skeleton',
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
    }
    return false;
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
        'return_url' => payment_return_url(),
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
    return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'nownexts.com');
}

function payment_notify_url(string $channel): string {
    return payment_site_base() . '/api/shop.php?action=notify&channel=' . $channel;
}

function payment_return_url(): string {
    return payment_site_base() . '/thank-you.php';
}
