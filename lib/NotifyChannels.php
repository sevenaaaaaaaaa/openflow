<?php
/**
 * 通知渠道扩展 — 企业微信 / 飞书 / WhatsApp
 * 通过 Webhook 发送站内通知到外部 IM
 */

function notify_channels_file(): string { return DATA_DIR . '/notify-channels.json'; }

function notify_channels(): array {
    return json_read(notify_channels_file());
}
function notify_channels_save(array $cfg): bool {
    return json_write(notify_channels_file(), $cfg);
}

// 发送到所有启用的渠道
function notify_channels_send(string $title, string $message, string $link = ''): void {
    $channels = notify_channels();
    foreach (['wecom','feishu','whatsapp'] as $type) {
        if (empty($channels[$type]['enabled']) || empty($channels[$type]['webhook'])) continue;
        $text = "🔔 {$title}\n{$message}" . ($link ? "\n🔗 {$link}" : '');
        switch ($type) {
            case 'wecom': notify_wecom($channels['wecom'], $text); break;
            case 'feishu': notify_feishu($channels['feishu'], $text); break;
            case 'whatsapp': notify_whatsapp($channels['whatsapp'], $text); break;
        }
    }
}

// 企业微信机器人 Webhook
function notify_wecom(array $cfg, string $text): void {
    $ch = curl_init($cfg['webhook']);
    curl_setopt_array($ch, [
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode(['msgtype'=>'text','text'=>['content'=>$text]]),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
    ]);
    curl_exec($ch);
}

// 飞书机器人 Webhook
function notify_feishu(array $cfg, string $text): void {
    $ch = curl_init($cfg['webhook']);
    curl_setopt_array($ch, [
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode(['msg_type'=>'text','content'=>['text'=>$text]]),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
    ]);
    curl_exec($ch);
}

// WhatsApp Business API（Meta）
function notify_whatsapp(array $cfg, string $text): void {
    // $cfg: api_url（Meta Graph API 或第三方）, token, to（接收号码）
    if (empty($cfg['to'])) return;
    $ch = curl_init($cfg['webhook'] ?: 'https://graph.facebook.com/v18.0/me/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode(['messaging_product'=>'whatsapp','to'=>$cfg['to'],'type'=>'text','text'=>['body'=>$text]]),
        CURLOPT_HTTPHEADER=>array_filter(['Content-Type: application/json', !empty($cfg['token']) ? 'Authorization: Bearer '.$cfg['token'] : null]),
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
    ]);
    curl_exec($ch);
}
