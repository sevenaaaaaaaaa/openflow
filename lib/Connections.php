<?php
/**
 * 通用连接器 —— 开放能力的核心（主线 A，2026-09-04）
 *
 * 【为什么不是「200+ 集成」】一人公司的多样性太大：这个人接飞书，那个人接 Notion，
 * 第三个人接一个只有他自己在用的小众 SaaS。按目录一个个做，永远追不上，
 * 而且每一个都是一坨自己的鉴权代码和自己的 curl——现在仓库里 Mautic / Notion /
 * Cloudflare / BillionMail / 百度站长 正是这么各写各的，凭据还都是明文。
 *
 * 开放能力是**机制**，不是清单：
 *   连接（Connection）= 对某一个外部服务的一份命名凭据 + 基址
 *   动作（Action）    = 绑定某个连接的一段请求模板，事件上下文代进去就能发
 *   模板（Template）  = 连接 + 动作的定义，**剥掉秘钥**后可以导出分享
 * 于是「接一个新服务」变成填一张表，「把接法分享给别人」变成传一个 JSON。
 *
 * 【conn_request() 是唯一的出口】所有走连接的 HTTP 都经过它：
 *   - 注入鉴权（API Key / Bearer / Basic / OAuth2，OAuth2 过期自动刷新）
 *   - SSRF 防护：拒绝私网、本机、链路本地、自身域名；DNS 解析结果钉死到 curl，
 *     防止解析后再变；不跟随跳转（跳转是绕过上面检查的经典路子）
 *   - 超时、脱敏留痕、最近调用日志
 * 此前 canvas_action_webhook() 就是这些东西全都没有的雏形：任意 URL、无鉴权、
 * 固定载荷、不防 SSRF、不留痕。
 *
 * 【秘钥】一律经 Secrets 加密后落盘；界面上只显示末 4 位；日志里脱敏。
 */

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/Secrets.php';

if (!function_exists('conn_file')) {

function conn_dir(): string {
    $d = DATA_DIR . '/connections';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}
function conn_file(): string { return conn_dir() . '/connections.json'; }
function conn_log_file(): string { return conn_dir() . '/calls.json'; }

define('CONN_LOG_KEEP', 500);          // 调用日志保留条数
define('CONN_TIMEOUT', 15);            // 秒
define('CONN_MAX_BODY', 2 * 1024 * 1024); // 响应体最多留 2MB

/** 鉴权方式 */
function conn_auth_types(): array {
    return ['none' => '无鉴权', 'api_key' => 'API Key（头或查询参数）', 'bearer' => 'Bearer Token',
            'basic' => 'HTTP Basic', 'oauth2' => 'OAuth 2.0（授权码 + PKCE）'];
}

/** auth 里哪些字段是秘钥——落盘必加密、导出必剥掉、日志必脱敏 */
function conn_secret_fields(): array {
    return ['value', 'token', 'pass', 'client_secret', 'access_token', 'refresh_token'];
}

function conn_all(): array {
    $d = json_read(conn_file());
    return is_array($d) ? $d : [];
}

function conn_get(string $id): ?array {
    foreach (conn_all() as $c) if (($c['id'] ?? '') === $id) return $c;
    return null;
}

function conn_save_all(array $list): bool { return json_write(conn_file(), array_values($list)); }

/**
 * 新建 / 更新连接。秘钥字段在这里加密；传空串表示「不改」（界面上秘钥不回显，
 * 提交空的就是没动）。
 */
function conn_save(array $data): array {
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') return ['ok' => false, 'error' => '名称不能为空'];

    $base = trim((string)($data['base_url'] ?? ''));
    if ($base !== '') {
        $chk = conn_url_allowed($base, !empty($data['allow_private']));
        if (!$chk['ok']) return ['ok' => false, 'error' => '基址不可用：' . $chk['error']];
    }

    $authType = (string)($data['auth']['type'] ?? 'none');
    if (!isset(conn_auth_types()[$authType])) return ['ok' => false, 'error' => '不支持的鉴权方式'];

    $list = conn_all();
    $id = (string)($data['id'] ?? '');
    $existing = null; $idx = -1;
    if ($id !== '') foreach ($list as $i => $c) if (($c['id'] ?? '') === $id) { $existing = $c; $idx = $i; break; }

    // 合并 auth：秘钥字段空串 = 保留原值；非空 = 加密后覆盖
    $auth = ['type' => $authType];
    $in = (array)($data['auth'] ?? []);
    foreach ($in as $k => $v) {
        if ($k === 'type') continue;
        if (in_array($k, conn_secret_fields(), true)) {
            $v = is_string($v) ? trim($v) : '';
            if ($v === '' && $existing && isset($existing['auth'][$k])) { $auth[$k] = $existing['auth'][$k]; continue; }
            if ($v !== '') $auth[$k] = secret_encrypt($v);
        } else {
            $auth[$k] = is_scalar($v) ? trim((string)$v) : $v;
        }
    }
    // OAuth2 已换到的 token 不在表单里，改配置时要保留
    if ($existing && $authType === 'oauth2') {
        foreach (['access_token', 'refresh_token', 'expires_at', 'token_type'] as $k) {
            if (!isset($auth[$k]) && isset($existing['auth'][$k])) $auth[$k] = $existing['auth'][$k];
        }
    }

    $headers = [];
    foreach ((array)($data['headers'] ?? []) as $k => $v) {
        $k = trim((string)$k); if ($k === '' || !preg_match('/^[A-Za-z0-9-]+$/', $k)) continue;
        if (in_array(strtolower($k), ['host', 'authorization', 'content-length'], true)) continue;  // 这些由出口控制
        $headers[$k] = trim((string)$v);
    }

    $row = [
        'id'            => $id !== '' ? $id : ('cn_' . substr(bin2hex(random_bytes(6)), 0, 12)),
        'name'          => mb_substr($name, 0, 60),
        'kind'          => 'http',
        'base_url'      => rtrim($base, '/'),
        'auth'          => $auth,
        'headers'       => $headers,
        // 私网只能在后台由人手动勾，模板导入永远不能带（见 conn_template_import）
        'allow_private' => !empty($data['allow_private']),
        'enabled'       => array_key_exists('enabled', $data) ? !empty($data['enabled']) : ($existing['enabled'] ?? true),
        'note'          => mb_substr(trim((string)($data['note'] ?? '')), 0, 300),
        'template_from' => (string)($data['template_from'] ?? ($existing['template_from'] ?? '')),
        'created_at'    => $existing['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at'    => date('Y-m-d H:i:s'),
        'last_used_at'  => $existing['last_used_at'] ?? '',
        'last_status'   => $existing['last_status'] ?? null,
    ];
    if ($idx >= 0) $list[$idx] = $row; else $list[] = $row;
    conn_save_all($list);
    conn_audit($idx >= 0 ? '更新连接' : '新建连接', $row);
    return ['ok' => true, 'error' => '', 'conn' => $row];
}

function conn_delete(string $id): bool {
    $list = conn_all(); $n = count($list);
    $hit = null;
    $list = array_values(array_filter($list, function ($c) use ($id, &$hit) {
        if (($c['id'] ?? '') === $id) { $hit = $c; return false; } return true;
    }));
    if (count($list) === $n) return false;
    conn_save_all($list);
    if ($hit) conn_audit('删除连接', $hit);
    return true;
}

/** 更新连接的一部分字段（token 刷新、最后使用时间等内部用） */
function conn_patch(string $id, array $patch): void {
    $list = conn_all();
    foreach ($list as &$c) {
        if (($c['id'] ?? '') !== $id) continue;
        foreach ($patch as $k => $v) {
            if ($k === 'auth' && is_array($v)) { $c['auth'] = array_merge((array)($c['auth'] ?? []), $v); }
            else $c[$k] = $v;
        }
        break;
    }
    unset($c);
    conn_save_all($list);
}

/* ─────────────────────────────────────────────────────────
 * SSRF 防护
 * 后台管理员是可信的，但**模板是从外面导进来的**——一个社区分享的模板
 * 可以把 base_url 写成 http://127.0.0.1:6379 或 http://169.254.169.254。
 * 所以这里默认拒绝一切私网/本机地址；自托管内网服务确有需要的，
 * 由管理员在后台亲手勾 allow_private，导入的模板永远带不进这个开关。
 * ───────────────────────────────────────────────────────── */

function conn_ip_is_private(string $ip): bool {
    if ($ip === '' || $ip === '0.0.0.0' || $ip === '::') return true;
    // PHP 自带的判定覆盖 10/8、172.16/12、192.168/16、127/8、::1、fc00::/7 等
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return true;
    // 它不覆盖的几段再补：链路本地 169.254/16、CGNAT 100.64/10、IPv6 链路本地 fe80::/10
    if (str_starts_with($ip, '169.254.')) return true;
    if (preg_match('/^100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\./', $ip)) return true;
    if (str_starts_with(strtolower($ip), 'fe80:')) return true;
    return false;
}

/** 域名 → IP 列表。可注入（测试环境没有 DNS；生产永远是真解析） */
function conn_resolve(string $host): array {
    if (isset($GLOBALS['CONN_RESOLVER']) && is_callable($GLOBALS['CONN_RESOLVER'])) {
        return array_values(array_filter((array)($GLOBALS['CONN_RESOLVER'])($host), 'is_string'));
    }
    $ips = [];
    $recs = @dns_get_record($host, DNS_A + DNS_AAAA);
    if (is_array($recs)) foreach ($recs as $r) { if (!empty($r['ip'])) $ips[] = $r['ip']; if (!empty($r['ipv6'])) $ips[] = $r['ipv6']; }
    if (!$ips) { $a = @gethostbyname($host); if ($a !== $host) $ips[] = $a; }
    return $ips;
}

/**
 * 这个 URL 能不能请求。返回 ['ok'=>bool,'error'=>string,'ips'=>[]]。
 * 解析出的 ip 交给调用方钉死到 curl，避免「检查时解析到 A、请求时解析到 B」。
 */
function conn_url_allowed(string $url, bool $allowPrivate = false): array {
    $p = parse_url($url);
    if (!$p || empty($p['host']) || empty($p['scheme'])) return ['ok' => false, 'error' => 'URL 格式无效', 'ips' => []];
    $scheme = strtolower($p['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) return ['ok' => false, 'error' => '只允许 http/https', 'ips' => []];
    if (!empty($p['user']) || !empty($p['pass'])) return ['ok' => false, 'error' => 'URL 里不要带账号密码，请用鉴权设置', 'ips' => []];

    $host = strtolower(trim($p['host'], '[]'));
    // 自己的域名：绕回来打自己的后台，不行
    $self = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($self !== '' && $host === $self && !$allowPrivate) return ['ok' => false, 'error' => '不允许请求本站自身', 'ips' => []];
    if (in_array($host, ['localhost', 'localhost.localdomain'], true) && !$allowPrivate) return ['ok' => false, 'error' => '不允许请求本机', 'ips' => []];

    // 直接是 IP
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (conn_ip_is_private($host) && !$allowPrivate) return ['ok' => false, 'error' => '不允许请求私网/本机地址', 'ips' => []];
        return ['ok' => true, 'error' => '', 'ips' => [$host]];
    }
    // 域名：解析后逐个检查
    $ips = conn_resolve($host);
    if (!$ips) return ['ok' => false, 'error' => '域名无法解析', 'ips' => []];
    foreach ($ips as $ip) {
        if (conn_ip_is_private($ip) && !$allowPrivate) return ['ok' => false, 'error' => "域名解析到私网地址（{$ip}）", 'ips' => []];
    }
    return ['ok' => true, 'error' => '', 'ips' => $ips];
}

/* ─────────────────────────────────────────────────────────
 * HTTP 出口
 * ───────────────────────────────────────────────────────── */

/**
 * 底层受保护的 HTTP。不认识「连接」，只管安全地发一个请求。
 * OAuth2 的 token 端点也走这里——模板可以把 token_url 写成内网地址。
 */
function conn_http(string $method, string $url, array $opts = [], bool $allowPrivate = false): array {
    $t0 = microtime(true);
    $method = strtoupper($method);
    if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'], true)) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => '不支持的方法', 'ms' => 0];
    }
    if (!empty($opts['query']) && is_array($opts['query'])) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($opts['query']);
    }
    $chk = conn_url_allowed($url, $allowPrivate);
    if (!$chk['ok']) return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => $chk['error'], 'ms' => 0];

    // 可注入的发送器（测试用；生产永远是真 curl）
    if (isset($GLOBALS['CONN_SENDER']) && is_callable($GLOBALS['CONN_SENDER'])) {
        $r = ($GLOBALS['CONN_SENDER'])($method, $url, $opts);
        $r['ms'] = (int)((microtime(true) - $t0) * 1000);
        return $r + ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => ''];
    }

    $headers = [];
    foreach ((array)($opts['headers'] ?? []) as $k => $v) $headers[] = $k . ': ' . $v;
    $body = null;
    if (array_key_exists('json', $opts)) {
        $body = json_encode($opts['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
    } elseif (array_key_exists('form', $opts) && is_array($opts['form'])) {
        $body = http_build_query($opts['form']);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    } elseif (isset($opts['body'])) {
        $body = (string)$opts['body'];
    }

    $ch = curl_init($url);
    $p = parse_url($url);
    $port = $p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80);
    $curlOpts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_FOLLOWLOCATION => false,                     // 跳转是绕过 SSRF 检查的经典路子
        CURLOPT_TIMEOUT        => (int)($opts['timeout'] ?? CONN_TIMEOUT),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'OpenFlow-Connector/1.0',
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        // 把检查时解析到的 IP 钉死，防止 DNS 重绑定
        CURLOPT_RESOLVE        => [$p['host'] . ':' . $port . ':' . implode(',', $chk['ips'])],
    ];
    if ($method === 'HEAD') $curlOpts[CURLOPT_NOBODY] = true;
    if ($body !== null) $curlOpts[CURLOPT_POSTFIELDS] = $body;
    curl_setopt_array($ch, $curlOpts);

    $resp = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $ms = (int)((microtime(true) - $t0) * 1000);
    if ($resp === false) return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => $err ?: '请求失败', 'ms' => $ms];
    if (strlen($resp) > CONN_MAX_BODY) $resp = substr($resp, 0, CONN_MAX_BODY);
    $json = null;
    $trim = ltrim($resp);
    if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) { $j = json_decode($resp, true); if (json_last_error() === JSON_ERROR_NONE) $json = $j; }
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $resp, 'json' => $json, 'error' => '', 'ms' => $ms];
}

/**
 * 走某个连接发请求 —— 业务代码只该用这个。
 * $path 可以是相对基址的路径，也可以是完整 URL（但必须与基址同源，防止「借连接的凭据打别处」）。
 */
function conn_request(string $connId, string $method, string $path, array $opts = []): array {
    $c = conn_get($connId);
    if (!$c) return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => '连接不存在', 'ms' => 0];
    if (empty($c['enabled'])) return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => '连接已停用', 'ms' => 0];

    $base = (string)($c['base_url'] ?? '');
    if (preg_match('#^https?://#i', $path)) {
        // 完整 URL：必须与基址同源——凭据是给这个服务的，不能带着它去打别的地方
        if ($base === '' || strtolower((string)parse_url($path, PHP_URL_HOST)) !== strtolower((string)parse_url($base, PHP_URL_HOST))) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => '请求地址与连接基址不同源', 'ms' => 0];
        }
        $url = $path;
    } else {
        if ($base === '') return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => '连接没有配置基址', 'ms' => 0];
        $url = $base . '/' . ltrim($path, '/');
    }

    // 鉴权注入
    $headers = array_merge((array)($c['headers'] ?? []), (array)($opts['headers'] ?? []));
    $query = (array)($opts['query'] ?? []);
    $auth = (array)($c['auth'] ?? []);
    $secretsUsed = [];
    switch ($auth['type'] ?? 'none') {
        case 'api_key':
            $v = secret_decrypt($auth['value'] ?? '');
            if ($v === '') return conn_fail($c, $method, $url, 'API Key 未设置');
            $secretsUsed[] = $v;
            if (($auth['in'] ?? 'header') === 'query') $query[(string)($auth['name'] ?: 'api_key')] = $v;
            else $headers[(string)($auth['name'] ?: 'X-API-Key')] = $v;
            break;
        case 'bearer':
            $v = secret_decrypt($auth['token'] ?? '');
            if ($v === '') return conn_fail($c, $method, $url, 'Token 未设置');
            $secretsUsed[] = $v; $headers['Authorization'] = 'Bearer ' . $v;
            break;
        case 'basic':
            $pw = secret_decrypt($auth['pass'] ?? '');
            $secretsUsed[] = $pw;
            $headers['Authorization'] = 'Basic ' . base64_encode((string)($auth['user'] ?? '') . ':' . $pw);
            break;
        case 'oauth2':
            require_once __DIR__ . '/OAuth2Client.php';
            $tok = oauth2_access_token($c);          // 过期会自动刷新
            if ($tok === '') return conn_fail($c, $method, $url, 'OAuth2 尚未授权或刷新失败，请在后台重新连接');
            $secretsUsed[] = $tok; $headers['Authorization'] = 'Bearer ' . $tok;
            break;
    }
    $opts['headers'] = $headers; $opts['query'] = $query;

    $r = conn_http($method, $url, $opts, !empty($c['allow_private']));
    $r['error'] = secret_redact((string)$r['error'], $secretsUsed);
    conn_log_call($c, $method, $url, $r, $secretsUsed);
    conn_patch($c['id'], ['last_used_at' => date('Y-m-d H:i:s'), 'last_status' => $r['status']]);
    return $r;
}

function conn_fail(array $c, string $method, string $url, string $err): array {
    $r = ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => $err, 'ms' => 0];
    conn_log_call($c, $method, $url, $r, []);
    return $r;
}

/** 最近调用日志（环形，脱敏，不存响应体正文——那里面可能有对方的敏感数据） */
function conn_log_call(array $c, string $method, string $url, array $r, array $secrets): void {
    $log = json_read(conn_log_file()); if (!is_array($log)) $log = [];
    $u = parse_url($url);
    $log[] = [
        'at'      => date('Y-m-d H:i:s'),
        'conn'    => (string)($c['id'] ?? ''),
        'name'    => (string)($c['name'] ?? ''),
        'method'  => $method,
        'url'     => ($u['scheme'] ?? '') . '://' . ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '') . ($u['path'] ?? ''),   // 不记 query，里面可能带 key
        'status'  => (int)($r['status'] ?? 0),
        'ok'      => !empty($r['ok']),
        'ms'      => (int)($r['ms'] ?? 0),
        'error'   => secret_redact(mb_substr((string)($r['error'] ?? ''), 0, 200), $secrets),
        'actor'   => (string)($GLOBALS['of_actor']['name'] ?? $_SESSION['admin_user'] ?? 'system'),
    ];
    if (count($log) > CONN_LOG_KEEP) $log = array_slice($log, -CONN_LOG_KEEP);
    json_write(conn_log_file(), $log);
}

function conn_recent_calls(int $n = 50, string $connId = ''): array {
    $log = json_read(conn_log_file()); if (!is_array($log)) return [];
    if ($connId !== '') $log = array_values(array_filter($log, fn($l) => ($l['conn'] ?? '') === $connId));
    return array_reverse(array_slice($log, -$n));
}

function conn_audit(string $action, array $c, array $extra = []): void {
    try {
        require_once __DIR__ . '/AuditLog.php';
        AuditLog::log($action, 'connections', array_merge([
            'id' => (string)($c['id'] ?? ''), 'name' => (string)($c['name'] ?? ''),
            'auth' => (string)($c['auth']['type'] ?? ''), 'base_url' => (string)($c['base_url'] ?? ''),
        ], $extra));
    } catch (Throwable $e) {}
}

/** 给界面用：秘钥只露末 4 位 */
function conn_for_display(array $c): array {
    foreach (conn_secret_fields() as $f) {
        if (isset($c['auth'][$f])) $c['auth'][$f . '_masked'] = secret_mask($c['auth'][$f]);
        unset($c['auth'][$f]);
    }
    return $c;
}

/* ─────────────────────────────────────────────────────────
 * 可分享模板
 * 「把接法分享给别人」= 传一个 JSON。剥掉一切秘钥；导入后默认停用、
 * 且不得带 allow_private——这两条是导入方的安全边界，不是导出方的礼貌。
 * ───────────────────────────────────────────────────────── */

define('CONN_TEMPLATE_VERSION', 1);

function conn_template_export(string $connId, array $actions = []): ?array {
    $c = conn_get($connId);
    if (!$c) return null;
    $auth = (array)($c['auth'] ?? []);
    $needed = [];
    foreach (conn_secret_fields() as $f) {
        if (array_key_exists($f, $auth)) { unset($auth[$f]); if (!in_array($f, ['access_token', 'refresh_token'], true)) $needed[] = $f; }
    }
    unset($auth['expires_at'], $auth['token_type']);
    return [
        'openflow_connection_template' => CONN_TEMPLATE_VERSION,
        'name'        => (string)$c['name'],
        'note'        => (string)($c['note'] ?? ''),
        'base_url'    => (string)$c['base_url'],
        'auth'        => $auth,               // 含类型、header 名、oauth 的 auth_url/token_url/client_id/scopes
        'headers'     => (array)($c['headers'] ?? []),
        'secrets_needed' => $needed,          // 导入方要自己填的
        'actions'     => array_values(array_map(fn($a) => array_diff_key($a, ['id' => 1, 'conn_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'enabled' => 1]), $actions)),
        'exported_at' => date('Y-m-d H:i:s'),
    ];
}

/**
 * 导入模板 → 一个**停用**的连接（秘钥待填）+ 它的动作（也停用）。
 * 返回 ['ok','error','conn','actions'].
 */
function conn_template_import(array $tpl): array {
    if ((int)($tpl['openflow_connection_template'] ?? 0) < 1) return ['ok' => false, 'error' => '不是有效的连接模板'];
    $name = trim((string)($tpl['name'] ?? ''));
    if ($name === '') return ['ok' => false, 'error' => '模板没有名称'];

    $auth = (array)($tpl['auth'] ?? []);
    foreach (conn_secret_fields() as $f) unset($auth[$f]);        // 就算模板里带了，也不要
    if (!isset(conn_auth_types()[$auth['type'] ?? ''])) $auth['type'] = 'none';

    $r = conn_save([
        'name'          => $name . '（导入）',
        'note'          => (string)($tpl['note'] ?? ''),
        'base_url'      => (string)($tpl['base_url'] ?? ''),
        'auth'          => $auth,
        'headers'       => (array)($tpl['headers'] ?? []),
        'allow_private' => false,                                   // 永远不从模板继承
        'enabled'       => false,                                   // 填完秘钥、人看过之后再启用
        'template_from' => mb_substr($name, 0, 60),
    ]);
    if (!$r['ok']) return $r;

    $acts = [];
    if (function_exists('action_save')) {
        foreach ((array)($tpl['actions'] ?? []) as $a) {
            if (!is_array($a)) continue;
            $a['conn_id'] = $r['conn']['id']; $a['enabled'] = false;
            $ar = action_save($a);
            if ($ar['ok']) $acts[] = $ar['action'];
        }
    }
    conn_audit('导入连接模板', $r['conn'], ['actions' => count($acts)]);
    return ['ok' => true, 'error' => '', 'conn' => $r['conn'], 'actions' => $acts];
}

/** 仓库自带的示例模板（说明机制用的几个，不是目录） */
function conn_bundled_templates(): array {
    $out = [];
    foreach (glob(__DIR__ . '/connection-templates/*.json') ?: [] as $f) {
        $t = json_decode((string)file_get_contents($f), true);
        if (is_array($t) && !empty($t['name'])) { $t['_file'] = basename($f); $out[] = $t; }
    }
    return $out;
}

}
