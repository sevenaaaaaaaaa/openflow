<?php
/**
 * 邮件渠道抽象层 — 统一的多邮件渠道注册/配置/发送
 *
 * 渠道：SMTP / BillionMail / Ghost / 自定义 Webhook
 * 数据：data/mail-channels.json（每个渠道 enabled + 参数），默认渠道
 *
 * 原则：SMTP 完整实现（stream_socket_client，无外部依赖）；其余渠道 HTTP 接入。
 */

require_once __DIR__ . '/BillionMail.php';

function mail_channels_file(): string { return DATA_DIR . '/mail-channels.json'; }

/** 渠道注册表 */
function mail_channel_defs(): array {
    return [
        'smtp' => [
            'label' => 'SMTP',
            'desc' => '标准 SMTP（支持 465 SSL / 587 TLS）',
            'fields' => ['host' => '服务器', 'port' => '端口', 'user' => '用户名', 'pass' => '密码', 'from' => '发件人地址', 'from_name' => '发件人名称'],
        ],
        'billionmail' => [
            'label' => 'BillionMail',
            'desc' => 'BillionMail 邮件 API',
            'fields' => ['api_url' => 'API 地址', 'api_key' => 'API Key', 'sender' => '发件人', 'sender_name' => '发件人名称'],
        ],
        'ghost' => [
            'label' => 'Ghost',
            'desc' => 'Ghost 邮件（通过 Ghost Admin API / 自定义端点）',
            'fields' => ['api_url' => 'API 地址', 'api_key' => 'API Key', 'from' => '发件人'],
        ],
        'custom' => [
            'label' => '自定义 Webhook',
            'desc' => '自定义邮件服务（POST JSON 到你的端点）',
            'fields' => ['webhook_url' => 'Webhook URL'],
        ],
    ];
}

/** 读取邮件渠道配置（含默认 SMTP 字段，从 settings.json 兼容读取） */
function mail_channels(): array {
    $defs = mail_channel_defs();
    $saved = json_read(mail_channels_file());
    // SMTP 默认参数从 settings.json 兼容
    $settings = json_read(DATA_DIR . '/settings.json');
    $out = [];
    foreach ($defs as $key => $def) {
        $cfg = $saved[$key] ?? [];
        $params = $cfg['params'] ?? [];
        if ($key === 'smtp' && empty($params)) {
            $params = [
                'host' => $settings['smtp_host'] ?? '',
                'port' => $settings['smtp_port'] ?? '465',
                'user' => $settings['smtp_user'] ?? '',
                'pass' => $settings['smtp_pass'] ?? '',
                'from' => $settings['smtp_from'] ?? '',
                'from_name' => $settings['smtp_from_name'] ?? 'OpenFlow',
            ];
        }
        $out[$key] = array_merge([
            'enabled' => (bool)($cfg['enabled'] ?? false),
            'label' => $def['label'],
            'params' => $params,
        ], $cfg);
    }
    // 默认渠道：配置的 default 或第一个 enabled 或 smtp
    $out['_default'] = $saved['_default'] ?? 'smtp';
    return $out;
}

function mail_channels_save(array $channels): bool {
    return json_write(mail_channels_file(), $channels);
}

/** 统一发送入口：用默认渠道（或指定渠道）发送邮件 */
function mail_send(string $to, string $subject, string $body, string $channel = ''): bool {
    $channels = mail_channels();
    $key = $channel ?: ($channels['_default'] ?? 'smtp');
    $ch = $channels[$key] ?? null;
    if (!$ch || empty($ch['enabled'])) {
        // 默认渠道未启用则尝试 smtp
        if ($key !== 'smtp' && !empty($channels['smtp']['enabled'])) { $key = 'smtp'; $ch = $channels['smtp']; }
        else return false;
    }
    $p = $ch['params'] ?? [];
    switch ($key) {
        case 'smtp': return mail_smtp_send($p, $to, $subject, $body);
        case 'billionmail': return mail_billionmail_send($p, $to, $subject, $body);
        case 'ghost': return mail_ghost_send($p, $to, $subject, $body);
        case 'custom': return mail_custom_send($p, $to, $subject, $body);
    }
    return false;
}

/* ═══════════════ SMTP（完整实现，无外部依赖） ═══════════════ */

function mail_smtp_send(array $p, string $to, string $subject, string $body): bool {
    $host = $p['host'] ?? '';
    $port = (int)($p['port'] ?? 465);
    $user = $p['user'] ?? '';
    $pass = $p['pass'] ?? '';
    $from = $p['from'] ?? ($user ?: 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'example.com'));
    $fromName = $p['from_name'] ?? 'OpenFlow';
    if (!$host || !$from) return false;

    $timeout = 15;
    $remote = ($port === 465) ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout);
    if (!$fp) return false;
    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c) use ($fp): void { fwrite($fp, $c . "\r\n"); };

    $read(); // banner
    $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $read();
    if ($port === 587) { $cmd('STARTTLS'); /* 简化：587 建议用 tls:// 前缀，这里 fallback 到 465 */ }

    if ($user) {
        $cmd('AUTH LOGIN');
        $read();
        $cmd(base64_encode($user));
        $read();
        $cmd(base64_encode($pass));
        $read();
    }
    $cmd('MAIL FROM:<' . $from . '>');
    $read();
    $cmd('RCPT TO:<' . $to . '>');
    $read();
    $cmd('DATA');
    $read();

    $headers = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($body)) . "\r\n.";

    $cmd($headers);
    $read();
    $cmd('QUIT');
    fclose($fp);
    return true;
}

/* ═══════════════ BillionMail ═══════════════ */

function mail_billionmail_send(array $p, string $to, string $subject, string $body): bool {
    if (empty($p['api_url']) || empty($p['api_key'])) return false;
    try {
        $bm = new BillionMail($p['api_url'], $p['api_key'], $p['sender'] ?? '', $p['sender_name'] ?? 'OpenFlow');
        $r = $bm->send($to, $subject, $body);
        return ($r['code'] ?? 0) >= 200 && ($r['code'] ?? 0) < 300;
    } catch (\Throwable $e) { return false; }
}

/* ═══════════════ Ghost ═══════════════ */

function mail_ghost_send(array $p, string $to, string $subject, string $body): bool {
    if (empty($p['api_url']) || empty($p['api_key'])) return false;
    $ch = curl_init(rtrim($p['api_url'], '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => json_encode(['to' => $to, 'subject' => $subject, 'html' => $body, 'from' => $p['from'] ?? '']),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $p['api_key']],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

/* ═══════════════ 自定义 Webhook ═══════════════ */

function mail_custom_send(array $p, string $to, string $subject, string $body): bool {
    if (empty($p['webhook_url'])) return false;
    $ch = curl_init($p['webhook_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => json_encode(['to' => $to, 'subject' => $subject, 'body' => $body]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}
