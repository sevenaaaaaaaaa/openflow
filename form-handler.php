<?php
/**
 * ============================================================
 *  form-handler.php — OpenFlow 官网线索接收端（方案 A · 自建 PHP）
 * ============================================================
 *  部署位置 : 服务器根目录 / form-handler.php（与 5 个页面同层）
 *
 *  职责：
 *    1. 接收 5 个正式页面（index / capability / courses /
 *       flow-community / about）表单的 POST 提交；
 *    2. 必填校验（姓名、电话）+ 字段清洗；
 *    3. honeypot 隐藏字段反垃圾提交；
 *    4. 邮件 -> admin@nownexts.com（尽力而为）；
 *    5. 兜底落盘 -> leads.csv（与脚本同目录，UTF-8 BOM，Excel 直接打开）。
 *
 *  返回：application/json，前端据此给出成功 / 失败反馈。
 *  联调：form-handler.php?debug=1（跳过发信、回显解析结果）
 *
 *  要求：PHP >= 7.1（推荐 7.4+ / 8.x）
 * ============================================================
 */

declare(strict_types=1);

/* ---------------- 配置区 ---------------- */

/* 时区：线索时间按北京时间记录（CSV 与邮件均受影响），可按需修改 */
date_default_timezone_set('Asia/Shanghai');

const RECIPIENT_EMAIL = 'admin@nownexts.com'; // 线索收件邮箱
const SENDER_EMAIL    = 'no-reply@nownexts.com';   // 发件人地址（建议换成真实域名邮箱，见部署说明）
const SENDER_NAME     = 'OpenFlow 官网';
const CSV_FILE        = __DIR__ . '/leads.csv';      // 兜底存储，与脚本同目录
const HONEYPOT_FIELD  = 'website';                   // 人类陷阱：隐藏字段，机器人会自动填写
const MAX_FIELD_LEN   = 2000;                        // 单字段最大长度

/**
 * 字段别名：兼容各页面现有表单的不同命名。
 * 键 = 页面里可能出现的 name，值 = 归一化后的标准字段。
 */
const FIELD_ALIASES = [
    'mobile'       => 'phone',
    'tel'          => 'phone',
    'contact'      => 'phone',
    'username'     => 'name',
    'real_name'    => 'name',
    'company_name' => 'company',
    'content'      => 'message',
    'notes'        => 'message',
    'note'         => 'message',
    'title'        => 'job',
    'source'       => 'page',
    'page_name'    => 'page',
];

/* 标准字段及其顺序（决定 CSV 列顺序与邮件展示顺序） */
const CANONICAL_FIELDS = ['name', 'phone', 'company', 'email', 'job', 'message', 'page'];

/* ---------------- 工具函数 ---------------- */

function u_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function u_sub(string $s, int $max): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}

/** 取标量值：防止 name[] 之类的数组注入 */
function scalar($v): string
{
    if (is_array($v)) {
        $v = reset($v);
    }
    return is_scalar($v) ? (string) $v : '';
}

/** 清洗：去首尾空白 + 去控制字符（含 \r \n，顺带防止邮件头注入） */
function clean($raw): string
{
    $s = trim(scalar($raw));
    $r = preg_replace('/[\x00-\x1F\x7F]/u', '', $s);
    if ($r !== null) {
        $s = $r;
    }
    return u_sub($s, MAX_FIELD_LEN);
}

/** 统一 JSON 出口 */
function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** 邮件头编码（subject / From 名称为中文时用） */
function mime_header(string $s): string
{
    return function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($s, 'UTF-8') : $s;
}

/* ---------------- 入口 ---------------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'message' => '仅支持 POST 请求。'], 405);
}

$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

/* ---- 1) honeypot 反垃圾：机器人会填这个隐藏字段。命中即假装成功，不落库、不发信 ---- */
if (clean($_POST[HONEYPOT_FIELD] ?? '') !== '') {
    $resp = ['ok' => true, 'message' => '提交成功，我们会在 1 个工作日内与您联系。'];
    if ($debug) {
        $resp['honeypot'] = true; // 联调时可见，线上不暴露
    }
    json_out($resp);
}

/* ---- 2) 字段收集 + 别名归一 ---- */
$canonical = array_fill_keys(CANONICAL_FIELDS, '');

foreach ($_POST as $key => $value) {
    $key = (string) $key;
    if ($key === '' || $key === HONEYPOT_FIELD) {
        continue;
    }
    $norm = isset(FIELD_ALIASES[$key]) ? FIELD_ALIASES[$key] : $key;
    if (array_key_exists($norm, $canonical)) {
        $canonical[$norm] = clean($value);
    }
}

/* 未识别的字段原样收进"其他字段"，避免丢数据 */
$extras = [];
foreach ($_POST as $key => $value) {
    $key = (string) $key;
    if ($key === '' || $key === HONEYPOT_FIELD) {
        continue;
    }
    $norm = isset(FIELD_ALIASES[$key]) ? FIELD_ALIASES[$key] : $key;
    if (array_key_exists($norm, $canonical)) {
        continue;
    }
    $extras[] = $key . '=' . clean($value);
}
$extrasStr = implode(' | ', $extras);

$name    = $canonical['name'];
$phone   = $canonical['phone'];
$company = $canonical['company'];
$email   = $canonical['email'];
$job     = $canonical['job'];
$message = $canonical['message'];
$page    = $canonical['page'];

/* ---- 3) 必填校验 ---- */
$errors = [];

if ($name === '') {
    $errors['name'] = '请填写您的称呼。';
} elseif (u_len($name) < 2 || u_len($name) > 60) {
    $errors['name'] = '称呼长度需在 2–60 字之间。';
}

if ($phone === '') {
    $errors['phone'] = '请填写联系电话。';
} elseif (!preg_match('/^[+]?[0-9][0-9\s\-()]{5,19}$/', $phone)) {
    $errors['phone'] = '请填写有效的电话号码（手机或座机）。';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = '请填写有效的邮箱地址。';
}

if (u_len($message) > MAX_FIELD_LEN) {
    $errors['message'] = '需求描述请控制在 ' . MAX_FIELD_LEN . ' 字以内。';
}

if ($errors) {
    json_out([
        'ok'      => false,
        'message' => '表单有项未通过校验，请检查后重新提交。',
        'errors'  => $errors,
    ], 422);
}

/* ---- 4) 兜底落盘：先写 leads.csv（权威通道，邮件失败也不丢线索） ---- */
$dir = dirname(CSV_FILE);
if (!is_dir($dir) || !is_writable($dir)) {
    error_log('[form-handler] leads.csv 所在目录不可写: ' . $dir);
    json_out(['ok' => false, 'message' => '服务器暂时无法保存您的信息，请稍后再试。'], 500);
}

$time  = date('Y-m-d H:i:s');
$isNew = !file_exists(CSV_FILE);

$fp = @fopen(CSV_FILE, 'ab');
if ($fp === false) {
    error_log('[form-handler] 无法打开 leads.csv');
    json_out(['ok' => false, 'message' => '服务器暂时无法保存您的信息，请稍后再试。'], 500);
}

$csvOk = false;
if (flock($fp, LOCK_EX)) {
    if ($isNew) {
        fwrite($fp, "\xEF\xBB\xBF"); // UTF-8 BOM：Excel 打开中文不乱码
        fputcsv($fp, ['时间', '来源页面', '姓名', '电话', '公司', '邮箱', '职位', '需求留言', '其他字段'], ',', '"', '\\');
    }
    $csvOk = fputcsv($fp, [$time, $page, $name, $phone, $company, $email, $job, $message, $extrasStr], ',', '"', '\\') !== false;
    flock($fp, LOCK_UN);
}
fclose($fp);

if (!$csvOk) {
    error_log('[form-handler] 写入 leads.csv 失败');
    json_out(['ok' => false, 'message' => '服务器暂时无法保存您的信息，请稍后再试。'], 500);
}

/* ---- 5) 邮件通道（尽力而为；失败不阻断，CSV 已兜底） ---- */
$emailSent = false;
if (!$debug) {
    $emailSent = send_lead_mail([
        'time'    => $time,
        'page'    => $page,
        'name'    => $name,
        'phone'   => $phone,
        'company' => $company,
        'email'   => $email,
        'job'     => $job,
        'message' => $message,
        'extras'  => $extrasStr,
    ]);
    if (!$emailSent) {
        error_log('[form-handler] mail() 发送失败，线索已落 CSV 兜底。收件人: ' . RECIPIENT_EMAIL);
    }
}

/* ---- 5.5) Webhook：与其他工具对接（飞书 / CRM 等） ---- */
$webhookFile = __DIR__ . '/data/webhook.json';
if (file_exists($webhookFile)) {
    $wh = json_decode(file_get_contents($webhookFile), true);
    if (!empty($wh['url'])) {
        $whPayload = json_encode([
            'event' => 'new_lead',
            'time'  => $time,
            'data'  => $canonical + ['extras' => $extrasStr],
        ]);
        $ch = curl_init($wh['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $whPayload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        curl_exec($ch);
    }
}

/* ---- 5.75) 同步到新表单提交系统 ---- */
$subsFile = __DIR__ . '/data/submissions/index.json';
$subs = file_exists($subsFile) ? (json_decode(file_get_contents($subsFile), true) ?: []) : [];
$subs[] = [
    'id' => 'sub_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
    'form_id' => 'form_lead_default',
    'slug' => 'appointment',
    'type' => 'lead',
    'data' => $canonical + ['extras' => $extrasStr],
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'created_at' => $time,
];
file_put_contents($subsFile, json_encode($subs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

/* ---- 6) 响应 ---- */
$resp = [
    'ok'         => true,
    'email_sent' => $emailSent,
    'message'    => '提交成功，我们会在 1 个工作日内与您联系。',
    'thank_you_url' => '/thank-you?from=' . urlencode($page ?: 'lead'),
];
if ($debug) {
    $resp['debug'] = [
        'received' => $canonical,
        'extras'   => $extras,
        'csv'      => CSV_FILE,
        'note'     => 'debug=1 已跳过邮件发送。',
    ];
}
json_out($resp);

/* ---------------- 邮件发送（以后换 SMTP 只需改这一个函数） ---------------- */

function send_lead_mail(array $d): bool
{
    $subject = '【官网线索】' . $d['name'] . ' / ' . ($d['company'] !== '' ? $d['company'] : '未填公司') . ' / ' . $d['time'];

    $rows = [
        ['提交时间', $d['time']],
        ['来源页面', $d['page'] !== '' ? $d['page'] : '（未记录）'],
        ['姓名', $d['name']],
        ['电话', $d['phone']],
        ['公司', $d['company'] !== '' ? $d['company'] : '（未填）'],
        ['邮箱', $d['email'] !== '' ? $d['email'] : '（未填）'],
        ['职位', $d['job'] !== '' ? $d['job'] : '（未填）'],
        ['需求留言', $d['message'] !== '' ? $d['message'] : '（未填）'],
    ];
    if ($d['extras'] !== '') {
        $rows[] = ['其他字段', $d['extras']];
    }

    $htmlRows = '';
    foreach ($rows as $r) {
        $k = htmlspecialchars($r[0], ENT_QUOTES, 'UTF-8');
        $v = htmlspecialchars($r[1], ENT_QUOTES, 'UTF-8');
        $htmlRows .= '<tr>'
            . '<td style="padding:8px 14px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:600;white-space:nowrap;">' . $k . '</td>'
            . '<td style="padding:8px 14px;border:1px solid #e5e7eb;">' . nl2br($v) . '</td>'
            . '</tr>';
    }

    $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111827;max-width:640px;">'
        . '<p style="margin:0 0 12px;">OpenFlow 官网收到一条新的线索，请及时跟进：</p>'
        . '<table style="border-collapse:collapse;width:100%;">' . $htmlRows . '</table>'
        . '<p style="margin:16px 0 0;color:#6b7280;font-size:12px;">此邮件由官网表单自动发送，请勿直接回复。</p>'
        . '</div>';

    $text = "OpenFlow 官网收到一条新的线索：\n\n";
    foreach ($rows as $r) {
        $text .= $r[0] . '：' . $r[1] . "\n";
    }
    $text .= "\n此邮件由官网表单自动发送，请勿直接回复。";

    $boundary = '----=_openflow_' . md5(uniqid('', true));

    $headers = 'From: ' . mime_header(SENDER_NAME) . ' <' . SENDER_EMAIL . ">\r\n";
    $headers .= 'Reply-To: ' . ($d['email'] !== '' ? $d['email'] : RECIPIENT_EMAIL) . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";
    $headers .= 'X-Mailer: OpenFlow-Form-Handler/1.0';

    $body = '--' . $boundary . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: base64' . "\r\n\r\n"
        . base64_encode($text) . "\r\n"
        . '--' . $boundary . "\r\n"
        . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: base64' . "\r\n\r\n"
        . base64_encode($html) . "\r\n"
        . '--' . $boundary . "--\r\n";

    return @mail(RECIPIENT_EMAIL, mime_header($subject), $body, $headers);
}
