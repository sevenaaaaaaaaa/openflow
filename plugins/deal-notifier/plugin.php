<?php
/**
 * 成交播报（官方示例 2/3）
 *
 * 演示重点：一个插件监听多个业务动作，把它们收敛成同一件事（发群消息），
 * 以及出站 HTTP 该怎么写才不会拖垮主流程。
 *
 * 监听：crm_deal_won / payment_success / payment_refund / crm_leads_bulk_imported
 *
 * 注意每个回调都很短，而且不做任何可能失败的强假设——钩子是旁路，
 * 它慢一秒，用户的下单请求就慢一秒，所以 httpPost 默认 5 秒超时且不重试。
 */

require_once __DIR__ . '/../../lib/PluginSDK.php';

$p = plugin('deal-notifier');

/** 统一出口：拼消息 + 发送 + 记日志 */
$send = function (string $title, string $body) use ($p) {
    $url = (string)$p->get('webhook_url', '');
    if ($url === '') return;                       // 没配就静默跳过

    $text = $title . "\n" . $body;
    // 企业微信与飞书的 body 结构不同，按配置切
    $payload = $p->get('provider', 'wecom') === 'feishu'
        ? ['msg_type' => 'text', 'content' => ['text' => $text]]
        : ['msgtype'  => 'text', 'text'    => ['content' => $text]];

    $r = $p->httpPost($url, $payload);
    $p->log(($r['ok'] ? '播报成功：' : '播报失败：') . $title
            . ($r['error'] ? ' ' . $r['error'] : ''));
};

// ── CRM 成交 ──────────────────────────────────────────
$p->on('crm_deal_won', function (string $email, array $lead) use ($p, $send) {
    $min = (float)$p->get('min_value', 0);
    $val = (float)($lead['value'] ?? 0);
    if ($val < $min) return;                       // 小单不打扰

    $send('🎉 成交', sprintf(
        "客户：%s\n金额：¥%s\n负责人：%s",
        ($lead['name'] ?? '') ?: $email,
        number_format($val, 2),
        ($lead['owner'] ?? '') ?: '未分配'
    ));
});

// ── 订单支付 ──────────────────────────────────────────
$p->on('payment_success', function (string $orderId, array $order, string $method) use ($p, $send) {
    if (!$p->get('notify_orders', true)) return;
    $send('💰 新订单', sprintf(
        "订单：%s\n商品：%s\n金额：¥%s\n渠道：%s",
        $orderId,
        $order['course_title'] ?? ($order['goods_type'] ?? '—'),
        number_format((float)($order['amount'] ?? 0), 2),
        $method ?: '—'
    ));
});

// ── 退款（负面事件同样要让人知道）──────────────────────
$p->on('payment_refund', function (string $orderId, array $order, float $amount, string $reason) use ($p, $send) {
    if (!$p->get('notify_refunds', true)) return;
    $send('↩️ 退款', sprintf(
        "订单：%s\n退款：¥%s\n原因：%s",
        $orderId, number_format($amount, 2), $reason ?: '未填写'
    ));
});

// ── 分群批量导入完成 ──────────────────────────────────
$p->on('crm_leads_bulk_imported', function (array $stat, array $opts) use ($send) {
    if (($stat['created'] ?? 0) <= 0) return;
    $send('📥 线索导入完成', sprintf(
        "分群：%s\n新建 %d 条，补齐 %d 条，跳过 %d 条",
        $stat['segment'] ?? '—',
        $stat['created'] ?? 0, $stat['updated'] ?? 0, $stat['skipped'] ?? 0
    ));
});

$p->menu('成交播报', $p->pageUrl(), '📣', 'crm');
