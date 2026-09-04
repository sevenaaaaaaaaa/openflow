<?php
/**
 * 连接动作 —— 事件发生时，按模板向外部服务发一个请求（主线 A）
 *
 * 动作 = 绑定某个连接的请求模板：方法、路径、请求体（JSON 或表单）、额外查询参数。
 * 模板里用 {{email}} {{name}} {{amount}} 这类占位符，触发时从事件上下文代进去。
 *
 * 【代入是「值」的代入，不是「文本拼接」】
 * 请求体先按 JSON 解析成结构，再对叶子节点逐个替换：
 *   - 叶子整段就是一个 {{x}} → 换成上下文里的原始值（数字还是数字、数组还是数组）
 *   - 叶子里夹着 {{x}} → 转成字符串拼进去
 * 绝不在 JSON 文本层面做字符串替换——那样上下文里一个带引号的值就能改写整个请求体的结构。
 *
 * 【接到哪】AutomationSystem（规则流）和 CanvasSystem（可视化画布）两套执行器
 * 都接同一个 action_run()。此前它们各自只有一个「Webhook」动作：任意 URL、无鉴权、
 * 固定载荷。现在那个仍然保留（兼容），新的走连接。
 */

require_once __DIR__ . '/Connections.php';

if (!function_exists('action_file')) {

function action_file(): string { return conn_dir() . '/actions.json'; }

function action_all(): array {
    $d = json_read(action_file());
    return is_array($d) ? $d : [];
}
function action_get(string $id): ?array {
    foreach (action_all() as $a) if (($a['id'] ?? '') === $id) return $a;
    return null;
}
function action_for_conn(string $connId): array {
    return array_values(array_filter(action_all(), fn($a) => ($a['conn_id'] ?? '') === $connId));
}
function action_enabled(): array {
    return array_values(array_filter(action_all(), fn($a) => !empty($a['enabled'])));
}

function action_save(array $data): array {
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') return ['ok' => false, 'error' => '动作名称不能为空'];
    $connId = (string)($data['conn_id'] ?? '');
    if ($connId === '' || !conn_get($connId)) return ['ok' => false, 'error' => '请选择一个已有的连接'];
    $method = strtoupper(trim((string)($data['method'] ?? 'POST')));
    if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) return ['ok' => false, 'error' => '不支持的方法'];

    // 请求体模板：字符串就当 JSON 文本校验一遍；数组直接用
    $body = $data['body'] ?? '';
    if (is_string($body) && trim($body) !== '') {
        $j = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) return ['ok' => false, 'error' => '请求体不是合法 JSON：' . json_last_error_msg()];
        $body = $j;
    } elseif (!is_array($body)) $body = null;

    $list = action_all(); $id = (string)($data['id'] ?? ''); $idx = -1; $existing = null;
    if ($id !== '') foreach ($list as $i => $a) if (($a['id'] ?? '') === $id) { $idx = $i; $existing = $a; break; }

    $row = [
        'id'         => $id !== '' ? $id : ('ac_' . substr(bin2hex(random_bytes(6)), 0, 12)),
        'conn_id'    => $connId,
        'name'       => mb_substr($name, 0, 60),
        'method'     => $method,
        'path'       => trim((string)($data['path'] ?? '/')),
        'query'      => is_array($data['query'] ?? null) ? $data['query'] : [],
        'body'       => $body,
        'body_mode'  => in_array($data['body_mode'] ?? 'json', ['json', 'form'], true) ? ($data['body_mode'] ?? 'json') : 'json',
        'headers'    => is_array($data['headers'] ?? null) ? $data['headers'] : [],
        'enabled'    => array_key_exists('enabled', $data) ? !empty($data['enabled']) : ($existing['enabled'] ?? true),
        'note'       => mb_substr(trim((string)($data['note'] ?? '')), 0, 300),
        'created_at' => $existing['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'last_run_at'=> $existing['last_run_at'] ?? '',
        'last_ok'    => $existing['last_ok'] ?? null,
    ];
    if ($idx >= 0) $list[$idx] = $row; else $list[] = $row;
    json_write(action_file(), array_values($list));
    return ['ok' => true, 'error' => '', 'action' => $row];
}

function action_delete(string $id): bool {
    $list = action_all(); $n = count($list);
    $list = array_values(array_filter($list, fn($a) => ($a['id'] ?? '') !== $id));
    if (count($list) === $n) return false;
    return json_write(action_file(), $list);
}

/* ─────────── 占位符代入 ─────────── */

/** 从上下文里按点路径取值：{{member.email}} → $ctx['member']['email'] */
function action_ctx_get(array $ctx, string $path) {
    $cur = $ctx;
    foreach (explode('.', $path) as $seg) {
        if (is_array($cur) && array_key_exists($seg, $cur)) $cur = $cur[$seg];
        else return null;
    }
    return $cur;
}

/** 字符串里的 {{x}} 代入（拼接语义，全部转成字符串） */
function action_fill_string(string $s, array $ctx): string {
    return (string)preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.\-]+)\s*\}\}/', function ($m) use ($ctx) {
        $v = action_ctx_get($ctx, $m[1]);
        if ($v === null) return '';
        if (is_bool($v)) return $v ? 'true' : 'false';
        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
        return (string)$v;
    }, $s);
}

/**
 * 结构化代入：递归到叶子。叶子整段是一个占位符 → 原值；否则拼接。
 * 这就是「值的代入」——上下文里的引号、花括号进不了请求体的结构层。
 */
function action_fill(mixed $tpl, array $ctx): mixed {
    if (is_array($tpl)) { $o = []; foreach ($tpl as $k => $v) $o[action_fill_string((string)$k, $ctx)] = action_fill($v, $ctx); return $o; }
    if (!is_string($tpl)) return $tpl;
    if (preg_match('/^\{\{\s*([a-zA-Z0-9_.\-]+)\s*\}\}$/', $tpl, $m)) {
        $v = action_ctx_get($ctx, $m[1]);
        return $v === null ? '' : $v;
    }
    return action_fill_string($tpl, $ctx);
}

/** 路径里的占位符要 URL 编码，否则一个带 / 的值就能改路径 */
function action_fill_path(string $path, array $ctx): string {
    return (string)preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.\-]+)\s*\}\}/', function ($m) use ($ctx) {
        $v = action_ctx_get($ctx, $m[1]);
        return rawurlencode(is_scalar($v) ? (string)$v : '');
    }, $path);
}

/* ─────────── 执行 ─────────── */

/**
 * 跑一个动作。$ctx 是事件上下文（member_id / email / amount / order_id ...）。
 * 返回 conn_request 的结果，外加 'action'。
 */
function action_run(string $actionId, array $ctx, bool $force = false): array {
    $a = action_get($actionId);
    if (!$a) return ['ok' => false, 'error' => '动作不存在', 'status' => 0];
    if (!$force && empty($a['enabled'])) return ['ok' => false, 'error' => '动作已停用', 'status' => 0];

    // 上下文里放一点通用的，模板里能直接用
    $ctx += ['now' => date('Y-m-d H:i:s'), 'site' => of_abs_url(), 'event_at' => date('c')];

    $opts = ['headers' => action_fill((array)($a['headers'] ?? []), $ctx), 'query' => action_fill((array)($a['query'] ?? []), $ctx)];
    if ($a['body'] !== null && in_array($a['method'], ['POST', 'PUT', 'PATCH'], true)) {
        $filled = action_fill($a['body'], $ctx);
        if (($a['body_mode'] ?? 'json') === 'form') $opts['form'] = is_array($filled) ? $filled : [];
        else $opts['json'] = $filled;
    }
    $r = conn_request((string)$a['conn_id'], (string)$a['method'], action_fill_path((string)$a['path'], $ctx), $opts);

    // 记到动作上
    $list = action_all();
    foreach ($list as &$x) if (($x['id'] ?? '') === $actionId) { $x['last_run_at'] = date('Y-m-d H:i:s'); $x['last_ok'] = !empty($r['ok']); break; }
    unset($x);
    json_write(action_file(), $list);

    $r['action'] = $a['name'];
    return $r;
}

/** 给执行器用：把「事件上下文」跑给某个动作，失败不抛（自动化里一个动作挂了不该拖垮整条流程） */
function action_run_safe(string $actionId, array $ctx): bool {
    try { return !empty(action_run($actionId, $ctx)['ok']); }
    catch (Throwable $e) { return false; }
}

/** 界面用：所有启用的动作，带连接名 */
function action_options(): array {
    $conns = []; foreach (conn_all() as $c) $conns[$c['id']] = $c['name'];
    $out = [];
    foreach (action_enabled() as $a) $out[$a['id']] = ($conns[$a['conn_id']] ?? '?') . ' · ' . $a['name'];
    return $out;
}

}
