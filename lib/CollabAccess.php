<?php
/**
 * 外部临时协作 —— 授权层（主线 B，2026-09-04）
 *
 * 【为什么不是「实时协同编辑」】一人公司要的不是两个人同时敲一份文档，
 * 而是：把某一篇东西，限时、限范围地交给一个外面的人——外包写手、顾问、客户——
 * 让他改或者提意见，改完能看清是谁改的、能退回去，到点自动收回。
 * CRDT 那一套解决的是另一个问题，这里用不上，也不该背它的复杂度。
 *
 * 【底座已经就位】
 *   - 修订层（RevisionSystem）管「谁改了什么、能不能退回去」，
 *     它的来源标记里本来就留了 external 这一档。落地页原本漏在外面（直接 json_write，
 *     没有版本记录），所以上一版只给批注不给编辑；现在 lib/BuilderPages.php
 *     把落地页也收进了同一条咽喉，两种内容都能放心交出去。
 *   - 块契约的 _key 管「批注钉在哪个块上」——没有稳定的块身份，
 *     外部协作者留的批注下次保存就会变成孤儿。
 *
 * 【安全上的几个决定】
 *  1. **token 只存哈希**。grants.json 万一随备份泄漏（自托管很常见），
 *     里面的东西也换不出访问权。token 熵足够高，sha256 即可，不需要 bcrypt。
 *  2. **分成 id + secret 两段**。用 id 定位、用 hash_equals 比 secret，
 *     既不用遍历全表做常数时间比较，也不给时序攻击留缝。
 *  3. **能力集是白名单，且根本没有删除这一档**。外部协作者最多能改内容，
 *     发钱、群发、改价、删除这些一概不在可授予的范围里——不是靠界面不给按钮，
 *     是这个集合里就没有。
 *  4. **按对象授权**。一个 grant 绑死它能碰的那几个对象，越权直接拒。
 *  5. **限流**。token 是可猜的东西，必须给爆破上闸。
 */

require_once __DIR__ . '/../admin/config.php';

if (!function_exists('collab_grants_file')) {

/** 可授予的能力 —— 白名单。注意这里没有、也不会有 delete/publish 之类。 */
function collab_caps(): array {
    return [
        'view'    => '查看内容',
        'comment' => '留批注（不改内容）',
        'edit'    => '编辑内容（改动进修订历史，可还原）',
    ];
}

/** 可授权的对象类型 —— 只有内容，不含订单、会员、设置这些 */
function collab_types(): array {
    return ['article' => '文章', 'page' => '落地页'];
}

function collab_grants_file(): string { return DATA_DIR . '/collab/grants.json'; }

function collab_all(): array {
    $d = json_read(collab_grants_file());
    return is_array($d) ? $d : [];
}

function collab_save(array $list): bool {
    $dir = dirname(collab_grants_file());
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return json_write(collab_grants_file(), array_values($list));
}

/**
 * 发一个协作链接。
 *
 * **明文 token 只在这里返回这一次**，之后系统里只剩哈希，找不回来。
 * 这是刻意的：找得回来的东西，泄漏一次就等于一直泄漏。
 *
 * @return array{ok:bool,error:string,token:string,grant:array}
 */
function collab_create(array $opts): array {
    $label = trim((string)($opts['label'] ?? ''));
    if ($label === '') return ['ok' => false, 'error' => '请填写这个链接给谁用', 'token' => '', 'grant' => []];

    $type = (string)($opts['type'] ?? '');
    $id   = (string)($opts['target_id'] ?? '');
    if (!isset(collab_types()[$type])) return ['ok' => false, 'error' => '不支持的内容类型', 'token' => '', 'grant' => []];
    if ($id === '') return ['ok' => false, 'error' => '请选择要共享的内容', 'token' => '', 'grant' => []];

    // 能力：只认白名单里的，且 view 是底线（没有 view 给了也没意义）
    $caps = array_values(array_intersect((array)($opts['caps'] ?? ['view', 'comment']), array_keys(collab_caps())));
    if (!in_array('view', $caps, true)) $caps[] = 'view';


    // 有效期：必须有，且不允许无限期。默认 7 天，最长 90 天。
    $days = (int)($opts['days'] ?? 7);
    if ($days < 1) $days = 1;
    if ($days > 90) $days = 90;

    $gid    = 'clb_' . substr(bin2hex(random_bytes(6)), 0, 12);
    $secret = bin2hex(random_bytes(32));           // 256 位，够了

    $grant = [
        'id'          => $gid,
        'secret_hash' => hash('sha256', $secret),
        'label'       => mb_substr($label, 0, 60),
        'note'        => mb_substr(trim((string)($opts['note'] ?? '')), 0, 200),
        'type'        => $type,
        'target_id'   => $id,
        'caps'        => $caps,
        'expires_at'  => date('Y-m-d H:i:s', time() + $days * 86400),
        'created_at'  => date('Y-m-d H:i:s'),
        'created_by'  => (string)($_SESSION['admin_user'] ?? 'system'),
        'revoked'     => false,
        'last_seen_at'=> '',
        'seen_count'  => 0,
    ];
    $list = collab_all();
    $list[] = $grant;
    collab_save($list);

    collab_audit('发出外部协作链接', $grant, ['caps' => $caps, 'expires_at' => $grant['expires_at']]);
    return ['ok' => true, 'error' => '', 'token' => $gid . '.' . $secret, 'grant' => $grant];
}

/**
 * 校验 token。任何一步不过都返回 null，**不区分原因**——
 * 对外只说「链接无效或已过期」，不告诉对方是猜错了还是过期了。
 */
function collab_verify(string $token): ?array {
    $token = trim($token);
    if ($token === '' || !str_contains($token, '.')) return null;

    [$gid, $secret] = explode('.', $token, 2);
    if ($gid === '' || $secret === '') return null;

    // 爆破上闸：按 IP 限流，与具体 token 无关
    if (!collab_rate_ok()) return null;

    foreach (collab_all() as $g) {
        if (($g['id'] ?? '') !== $gid) continue;
        if (!hash_equals((string)($g['secret_hash'] ?? ''), hash('sha256', $secret))) return null;
        return collab_usable($g) ? $g : null;
    }
    return null;
}

/** 按 id 取（会话建立之后用，此时已经验过 secret） */
function collab_get(string $gid): ?array {
    foreach (collab_all() as $g) if (($g['id'] ?? '') === $gid) return collab_usable($g) ? $g : null;
    return null;
}

/** 还能不能用：没吊销、没过期 */
function collab_usable(array $g): bool {
    if (!empty($g['revoked'])) return false;
    $exp = (string)($g['expires_at'] ?? '');
    if ($exp === '' || strtotime($exp) < time()) return false;   // 没有有效期视为无效，不给无限期后门
    return true;
}

/** 有没有某项能力 */
function collab_can(array $g, string $cap): bool {
    if (!isset(collab_caps()[$cap])) return false;               // 不在白名单里的能力，问也是没有
    return in_array($cap, (array)($g['caps'] ?? []), true);
}

/** 这个 grant 能不能碰这个对象 —— 越权的第一道闸 */
function collab_scope_ok(array $g, string $type, string $id): bool {
    return ($g['type'] ?? '') === $type && ($g['target_id'] ?? '') === $id && $id !== '';
}

/** 限流：同一 IP 每分钟最多 20 次校验 */
function collab_rate_ok(): bool {
    try {
        require_once __DIR__ . '/RateLimiter.php';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $r = RateLimiter::check('collab:' . $ip, 20, 60);
        return !empty($r['allowed']);
    } catch (Throwable $e) {
        return true;    // 限流器坏了不该把正常协作者也挡在外面
    }
}

/** 记一次访问 */
function collab_touch(string $gid): void {
    $list = collab_all(); $hit = false;
    foreach ($list as &$g) {
        if (($g['id'] ?? '') === $gid) {
            $g['last_seen_at'] = date('Y-m-d H:i:s');
            $g['seen_count'] = (int)($g['seen_count'] ?? 0) + 1;
            $hit = true; break;
        }
    }
    unset($g);
    if ($hit) collab_save($list);
}

/** 吊销：立刻失效，但记录留着（谁在什么时候看过、改过什么，要能查） */
function collab_revoke(string $gid): bool {
    $list = collab_all(); $hit = null;
    foreach ($list as &$g) {
        if (($g['id'] ?? '') === $gid) { $g['revoked'] = true; $g['revoked_at'] = date('Y-m-d H:i:s'); $hit = $g; break; }
    }
    unset($g);
    if (!$hit) return false;
    collab_save($list);
    collab_audit('吊销外部协作链接', $hit);
    return true;
}

/** 外部协作者的身份，交给修订层做归属标记 */
function collab_actor(array $g): array {
    return ['name' => (string)($g['label'] ?? '外部协作者'), 'source' => 'external'];
}

/** 把当前请求标成外部协作者的操作 —— 改动会以 external 来源进修订历史 */
function collab_set_actor(array $g): void {
    $GLOBALS['of_actor'] = collab_actor($g);
}

function collab_audit(string $action, array $g, array $extra = []): void {
    try {
        require_once __DIR__ . '/AuditLog.php';
        AuditLog::log($action, 'collab', array_merge([
            'grant_id' => (string)($g['id'] ?? ''),
            'label'    => (string)($g['label'] ?? ''),
            'type'     => (string)($g['type'] ?? ''),
            'target'   => (string)($g['target_id'] ?? ''),
        ], $extra));
    } catch (Throwable $e) {}
}

/** 状态中文名，给后台列表用 */
function collab_status(array $g): array {
    if (!empty($g['revoked'])) return ['已吊销', 'gray'];
    $exp = strtotime((string)($g['expires_at'] ?? ''));
    if (!$exp || $exp < time()) return ['已过期', 'gray'];
    if ($exp - time() < 86400) return ['即将到期', 'orange'];
    return ['生效中', 'green'];
}

}
