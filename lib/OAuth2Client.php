<?php
/**
 * OAuth 2.0 客户端 —— 授权码 + PKCE（主线 A）
 *
 * 【为什么要有通用的】此前唯一的 OAuth 在 SeoConsole 里，而且是 JWT 服务账号那一种，
 * 只能给 Google 用，也只能后台机器对机器。要让用户把自己的第三方账号
 * 「连」进来（点一下、跳过去授权、跳回来），需要的是授权码流程——这一份对所有
 * 遵守 RFC 6749 / 7636 的服务通用，接新服务只是填 auth_url / token_url / scopes。
 *
 * 【安全要点】
 *   - state：随机、绑定在会话里、一次性；回调时不匹配直接拒绝（防 CSRF 换账号）
 *   - PKCE（S256）：就算 client_secret 泄漏或不存在（公共客户端），code 也换不出 token
 *   - redirect_uri 固定为本站回调地址，从不取自请求参数
 *   - token 端点的请求也走 conn_http()：模板可以把 token_url 写成内网地址
 *   - access_token / refresh_token 加密落盘；刷新提前 60 秒
 */

require_once __DIR__ . '/Connections.php';

if (!function_exists('oauth2_callback_url')) {

function oauth2_callback_url(): string { return of_abs_url('/api/oauth-callback.php'); }

/**
 * 第一步：生成授权跳转地址。state 与 code_verifier 存在会话里，10 分钟有效。
 */
function oauth2_begin(array $conn): array {
    $a = (array)($conn['auth'] ?? []);
    if (($a['type'] ?? '') !== 'oauth2') return ['ok' => false, 'error' => '这个连接不是 OAuth2 鉴权'];
    $authUrl = (string)($a['auth_url'] ?? ''); $clientId = (string)($a['client_id'] ?? '');
    if ($authUrl === '' || $clientId === '') return ['ok' => false, 'error' => '缺少 auth_url 或 client_id'];
    $chk = conn_url_allowed($authUrl, !empty($conn['allow_private']));
    if (!$chk['ok']) return ['ok' => false, 'error' => '授权地址不可用：' . $chk['error']];

    $state    = bin2hex(random_bytes(16));
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');   // 43–128 字符
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $pending = (array)($_SESSION['oauth2_pending'] ?? []);
    // 清掉过期的，别让会话越用越大
    foreach ($pending as $k => $v) if ((int)($v['at'] ?? 0) < time() - 600) unset($pending[$k]);
    $pending[$state] = ['conn_id' => (string)$conn['id'], 'verifier' => $verifier, 'at' => time()];
    $_SESSION['oauth2_pending'] = $pending;

    $q = [
        'response_type'         => 'code',
        'client_id'             => $clientId,
        'redirect_uri'          => oauth2_callback_url(),
        'scope'                 => (string)($a['scopes'] ?? ''),
        'state'                 => $state,
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256',
    ];
    foreach ((array)($a['extra_params'] ?? []) as $k => $v) if (is_string($k) && is_scalar($v)) $q[$k] = (string)$v;
    $url = $authUrl . (str_contains($authUrl, '?') ? '&' : '?') . http_build_query($q);
    return ['ok' => true, 'error' => '', 'url' => $url];
}

/**
 * 第二步：回调。校验 state，用 code + verifier 换 token，加密存起来。
 */
function oauth2_finish(string $code, string $state): array {
    if ($code === '' || $state === '') return ['ok' => false, 'error' => '缺少 code 或 state'];
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $pending = (array)($_SESSION['oauth2_pending'] ?? []);
    $p = $pending[$state] ?? null;
    unset($pending[$state]); $_SESSION['oauth2_pending'] = $pending;          // 一次性
    if (!$p || (int)($p['at'] ?? 0) < time() - 600) return ['ok' => false, 'error' => 'state 无效或已过期，请重新发起连接'];

    $conn = conn_get((string)$p['conn_id']);
    if (!$conn) return ['ok' => false, 'error' => '连接不存在'];

    $r = oauth2_token_request($conn, [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => oauth2_callback_url(),
        'code_verifier' => (string)$p['verifier'],
    ]);
    if (!$r['ok']) return $r;
    conn_audit('OAuth2 授权完成', $conn);
    return ['ok' => true, 'error' => '', 'conn_id' => $conn['id']];
}

/** 向 token 端点发请求并把结果存回连接。授权码换取与刷新共用。 */
function oauth2_token_request(array $conn, array $params): array {
    $a = (array)($conn['auth'] ?? []);
    $tokenUrl = (string)($a['token_url'] ?? '');
    if ($tokenUrl === '') return ['ok' => false, 'error' => '缺少 token_url'];
    $clientId = (string)($a['client_id'] ?? '');
    $secret = secret_decrypt($a['client_secret'] ?? '');
    $params['client_id'] = $clientId;
    $headers = ['Accept' => 'application/json'];
    // 有 secret 的用 Basic（RFC 推荐），公共客户端没有 secret 就只带 client_id
    if ($secret !== '') $headers['Authorization'] = 'Basic ' . base64_encode($clientId . ':' . $secret);

    $r = conn_http('POST', $tokenUrl, ['form' => $params, 'headers' => $headers, 'timeout' => 20], !empty($conn['allow_private']));
    $r['error'] = secret_redact((string)$r['error'], [$secret]);
    if (!$r['ok'] || !is_array($r['json'])) {
        $msg = is_array($r['json']) ? (string)($r['json']['error_description'] ?? $r['json']['error'] ?? '') : '';
        return ['ok' => false, 'error' => 'token 端点返回 ' . $r['status'] . ($msg !== '' ? '：' . $msg : ($r['error'] !== '' ? '：' . $r['error'] : ''))];
    }
    $j = $r['json'];
    $access = (string)($j['access_token'] ?? '');
    if ($access === '') return ['ok' => false, 'error' => 'token 端点没有返回 access_token'];
    $patch = [
        'access_token' => secret_encrypt($access),
        'token_type'   => (string)($j['token_type'] ?? 'Bearer'),
        'expires_at'   => isset($j['expires_in']) ? time() + (int)$j['expires_in'] : 0,   // 0 = 不知道，当作不过期
    ];
    if (!empty($j['refresh_token'])) $patch['refresh_token'] = secret_encrypt((string)$j['refresh_token']);
    conn_patch((string)$conn['id'], ['auth' => $patch]);
    return ['ok' => true, 'error' => ''];
}

/** 取可用的 access_token；快过期且有 refresh_token 就先刷新。拿不到返回空串。 */
function oauth2_access_token(array $conn): string {
    $a = (array)($conn['auth'] ?? []);
    $exp = (int)($a['expires_at'] ?? 0);
    $hasRefresh = secret_decrypt($a['refresh_token'] ?? '') !== '';
    if ($exp > 0 && $exp <= time() + 60) {
        if (!$hasRefresh) return '';
        $r = oauth2_token_request($conn, ['grant_type' => 'refresh_token', 'refresh_token' => secret_decrypt($a['refresh_token'])]);
        if (!$r['ok']) { conn_audit('OAuth2 刷新失败', $conn, ['error' => $r['error']]); return ''; }
        $conn = conn_get((string)$conn['id']) ?? $conn;
        $a = (array)($conn['auth'] ?? []);
    }
    return secret_decrypt($a['access_token'] ?? '');
}

/** 界面用：授权状态 */
function oauth2_status(array $conn): array {
    $a = (array)($conn['auth'] ?? []);
    if (($a['type'] ?? '') !== 'oauth2') return ['state' => 'n/a', 'label' => ''];
    if (secret_decrypt($a['access_token'] ?? '') === '') return ['state' => 'none', 'label' => '尚未授权'];
    $exp = (int)($a['expires_at'] ?? 0);
    if ($exp > 0 && $exp <= time()) return ['state' => secret_decrypt($a['refresh_token'] ?? '') !== '' ? 'stale' : 'expired',
                                             'label' => secret_decrypt($a['refresh_token'] ?? '') !== '' ? '已过期（下次使用时自动刷新）' : '已过期，需重新授权'];
    return ['state' => 'ok', 'label' => $exp > 0 ? '已授权，' . date('m-d H:i', $exp) . ' 到期' : '已授权'];
}

}
