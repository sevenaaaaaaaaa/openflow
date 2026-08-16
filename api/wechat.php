<?php
/**
 * WeChat API — 公众号服务器验证 + access_token + 消息接收
 * GET ?echostr=xxx → 服务器验证
 * POST → 接收用户消息 → 自动回复
 * GET ?action=push_menu → 推送自定义菜单
 */
require_once __DIR__ . '/../admin/config.php';

$cfg = json_read(DATA_DIR . '/wechat.json');
$mp = $cfg['mp'] ?? [];

$appid = $mp['appid'] ?? '';
$secret = $mp['secret'] ?? '';
$token = $mp['token'] ?? '';

// ─── 服务器验证（微信 GET 请求）───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['echostr']) && isset($_GET['signature']) && isset($_GET['timestamp']) && isset($_GET['nonce'])) {
    $signature = $_GET['signature'];
    $timestamp = $_GET['timestamp'];
    $nonce = $_GET['nonce'];
    $echostr = $_GET['echostr'];

    // 验证签名: sha1(sort([token, timestamp, nonce]))
    $arr = [$token, $timestamp, $nonce];
    sort($arr, SORT_STRING);
    $hash = sha1(implode('', $arr));

    if ($hash === $signature) {
        echo $echostr;
    } else {
        http_response_code(403);
        echo 'signature mismatch';
    }
    exit;
}

// ─── 推送菜单 ───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'push_menu') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($appid) || empty($secret)) {
        echo json_encode(['ok' => false, 'error' => '公众号 AppID 或 AppSecret 未配置']); exit;
    }
    $at = get_access_token($appid, $secret);
    if (!$at) {
        echo json_encode(['ok' => false, 'error' => '获取 access_token 失败']); exit;
    }
    $menuJson = $mp['menu_json'] ?? '';
    if (empty($menuJson)) {
        echo json_encode(['ok' => false, 'error' => '菜单 JSON 未配置']); exit;
    }

    $ch = curl_init("https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$at}");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $menuJson,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = json_decode(curl_exec($ch), true);

    if (($resp['errcode'] ?? 1) === 0) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => ($resp['errmsg'] ?? '未知错误'), 'code' => $resp['errcode'] ?? -1]);
    }
    exit;
}

// ─── 接收消息 / 事件推送（微信 POST 请求）───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if (empty($raw)) { echo 'success'; exit; }

    libxml_disable_entity_loader(true);
    $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) { echo 'success'; exit; }

    $msgType = (string)$xml->MsgType;
    $fromUser = (string)$xml->FromUserName;
    $toUser = (string)$xml->ToUserName;
    $content = (string)$xml->Content;
    $event = (string)$xml->Event;
    $eventKey = (string)$xml->EventKey;

    $reply = '';

    if ($msgType === 'event' && $event === 'subscribe') {
        $reply = $mp['auto_reply_welcome'] ?? '欢迎关注 OpenFlow！探索网站增长与 AI 运营。\n\n回复"文章"获取最新内容。';
    } elseif ($msgType === 'text') {
        $reply = handle_text_message($content, $mp);
    }

    if (!empty($reply)) {
        echo reply_xml($fromUser, $toUser, $reply);
    }
    exit;
}

// Default fallback
echo 'success';

// ─── Helper Functions ───

function get_access_token(string $appid, string $secret): ?string {
    $cacheFile = DATA_DIR . '/wechat_token.json';
    $cached = json_read($cacheFile);
    if ($cached && ($cached['expires'] ?? 0) > time()) return $cached['token'] ?? null;

    $ch = curl_init("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $resp = json_decode(curl_exec($ch), true);

    $token = $resp['access_token'] ?? null;
    if ($token) {
        json_write($cacheFile, ['token' => $token, 'expires' => time() + ($resp['expires_in'] ?? 7200) - 300]);
    }
    return $token;
}

function handle_text_message(string $content, array $mp): string {
    $content = trim($content);
    if (in_array($content, ['文章', '最新', '最新文章'])) {
        $articles = get_articles();
        $articles = array_values(array_filter($articles, fn($a) => ($a['status'] ?? 'draft') === 'published'));
        $articles = array_slice($articles, 0, 5);
        $reply = "📰 最新文章：\n";
        foreach ($articles as $i => $a) {
            $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/article/' . ($a['slug'] ?? '');
            $reply .= "\n" . ($i + 1) . '. ' . $a['title'] . "\n<a href=\"{$url}\">查看详情</a>\n";
        }
        return $reply;
    }
    return $mp['auto_reply_default'] ?? "已收到您的消息。如有业务咨询请留言，我们将尽快回复。\n\n回复\"文章\"获取最新内容。";
}

function reply_xml(string $to, string $from, string $text): string {
    $time = time();
    $text = htmlspecialchars($text);
    return "<xml>
<ToUserName><![CDATA[{$from}]]></ToUserName>
<FromUserName><![CDATA[{$to}]]></FromUserName>
<CreateTime>{$time}</CreateTime>
<MsgType><![CDATA[text]]></MsgType>
<Content><![CDATA[{$text}]]></Content>
</xml>";
}
