<?php
/**
 * MatrixTicket — 芭乐派产品矩阵账号互通 v1
 *
 * 模型:OpenFlow 是矩阵的**账号真源(SSO Hub)**,兄弟产品(MFlow/inFlow/UserLoop/PayFlow/LearnFlow)
 * 各自在后台登记一个 client_secret;用户在 OpenFlow 登录后,点「进入 XX」时本站签发一张
 * **一次性短票(60 秒)**,兄弟产品后端拿票 + 自己的 secret 来兑换 → 拿到用户身份 → 建立自己的会话。
 *
 * 为什么是「短票兑换」而不是直接传 JWT:
 *   - 票只在 URL 出现一次、60 秒内有效、用后即焚 —— 泄露窗口最小;
 *   - 兑换必须带 client_secret,只有后端能做,浏览器/日志拿不到持久凭据;
 *   - 兄弟产品自持会话,OpenFlow 不需要为它们维持在线状态(拔掉任何一个,其他产品不受影响)。
 *
 * 端点:
 *   GET /api/matrix.php?action=enter&app=mflow&next=/dashboard  → 签票并 302 到产品入口(需本站登录)
 *   POST /api/matrix.php?action=redeem { ticket, client_id }    → 兑换身份(需 client_secret 头)
 *
 * 凭据存储:data/matrix/clients.json  [{client_id, name, secret_hash, redirect_base, created_at}]
 */
require_once __DIR__ . '/MemberSystem.php';

function matrix_clients_file(): string { return DATA_DIR . '/matrix/clients.json'; }
function matrix_tickets_file(): string { return DATA_DIR . '/matrix/tickets.json'; }

/** 已登记的矩阵应用(空则播种默认五个兄弟产品,secret 为空=未启用) */
function matrix_clients(): array {
    $d = json_read(matrix_clients_file());
    if (!is_array($d) || !$d) {
        $d = [];
        foreach ([['mflow', 'MFlow · 内容生产与分发'], ['inflow', 'inFlow · 情报增长'], ['userloop', 'UserLoop · 用户数据中枢'], ['payflow', 'PayFlow · 商业变现'], ['learnflow', 'LearnFlow · 知识交付'], ['websflow', 'WebsFlow · 落地页工场'], ['thirdc', 'ThirdC · 知识工作台']] as [$id, $name]) {
            $d[] = ['client_id' => $id, 'name' => $name, 'secret_hash' => '', 'redirect_base' => '', 'created_at' => date('Y-m-d H:i:s')];
        }
        matrix_clients_save($d);
    }
    return $d;
}

function matrix_clients_save(array $clients): bool {
    $dir = dirname(matrix_clients_file());
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return json_write(matrix_clients_file(), array_values($clients));
}

function matrix_client(string $clientId): ?array {
    foreach (matrix_clients() as $c) if (($c['client_id'] ?? '') === $clientId) return $c;
    return null;
}

/** 后台操作:为某应用生成/轮换 secret(只回显一次,落库存 hash) */
function matrix_client_issue_secret(string $clientId, string $redirectBase = ''): array {
    $clients = matrix_clients();
    $secret = 'mtx_' . bin2hex(random_bytes(24));
    $found = false;
    foreach ($clients as $i => $c) if (($c['client_id'] ?? '') === $clientId) {
        $clients[$i]['secret_hash'] = password_hash($secret, PASSWORD_DEFAULT);
        if ($redirectBase !== '') $clients[$i]['redirect_base'] = rtrim($redirectBase, '/');
        $found = true;
        break;
    }
    if (!$found) return ['ok' => false, 'error' => '未知应用: ' . $clientId];
    matrix_clients_save($clients);
    return ['ok' => true, 'secret' => $secret];   // 明文只出现这一次
}

/** 用户点「进入 XX」:签发一次性短票,返回带票跳转 URL */
function matrix_enter(string $clientId, string $next = ''): array {
    $member = member_current();
    if (!$member) return ['ok' => false, 'error' => '请先登录 OpenFlow'];
    $c = matrix_client($clientId);
    if (!$c) return ['ok' => false, 'error' => '未知应用'];
    if (empty($c['secret_hash']) || empty($c['redirect_base'])) return ['ok' => false, 'error' => '该应用尚未启用互通(缺 secret 或回跳地址)'];

    $ticket = 'mkt_' . bin2hex(random_bytes(20));
    $tickets = json_read(matrix_tickets_file());
    if (!is_array($tickets)) $tickets = [];
    // 顺手清理过期票(签发超过 120 秒的)
    $tickets = array_values(array_filter($tickets, fn($t) => (int)($t['exp'] ?? 0) > time()));
    $tickets[] = [
        'ticket'    => hash('sha256', $ticket),          // 落库存的是哈希,文件泄露也换不走
        'client_id' => $clientId,
        'member_id' => (string)($member['id'] ?? ''),
        'email'     => (string)($member['email'] ?? ''),
        'name'      => (string)($member['name'] ?? ''),
        'level'     => (string)($member['level'] ?? 'free'),
        'exp'       => time() + 60,
        'used'      => false,
        'created_at'=> date('Y-m-d H:i:s'),
    ];
    $dir = dirname(matrix_tickets_file());
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    json_write(matrix_tickets_file(), array_slice($tickets, -200));

    $nextPath = $next !== '' ? $next : '/';
    return ['ok' => true, 'redirect' => $c['redirect_base'] . '/auth/matrix?ticket=' . urlencode($ticket) . '&next=' . urlencode($nextPath)];
}

/** 兄弟产品后端兑换:POST ticket + client_id + Header X-Client-Secret → 用户身份(用后即焚) */
function matrix_redeem(string $ticket, string $clientId, string $clientSecret): array {
    if ($ticket === '' || $clientId === '' || $clientSecret === '') return ['ok' => false, 'error' => '参数不全'];
    $c = matrix_client($clientId);
    if (!$c || empty($c['secret_hash'])) return ['ok' => false, 'error' => '未知应用或未启用'];
    if (!password_verify($clientSecret, $c['secret_hash'])) return ['ok' => false, 'error' => 'client_secret 校验失败'];

    $tickets = json_read(matrix_tickets_file());
    if (!is_array($tickets)) return ['ok' => false, 'error' => '票据不存在'];
    $hash = hash('sha256', $ticket);
    foreach ($tickets as $i => $t) {
        if (($t['ticket'] ?? '') !== $hash || ($t['client_id'] ?? '') !== $clientId) continue;
        if (!empty($t['used'])) return ['ok' => false, 'error' => '票据已被使用'];
        if ((int)($t['exp'] ?? 0) < time()) return ['ok' => false, 'error' => '票据已过期'];
        $tickets[$i]['used'] = true;
        $tickets[$i]['used_at'] = date('Y-m-d H:i:s');
        json_write(matrix_tickets_file(), array_slice($tickets, -200));
        return ['ok' => true, 'user' => [
            'id' => (string)($t['member_id'] ?? ''), 'email' => (string)($t['email'] ?? ''),
            'name' => (string)($t['name'] ?? ''), 'level' => (string)($t['level'] ?? 'free'),
            'iss' => 'openflow', 'iat' => time(),
        ]];
    }
    return ['ok' => false, 'error' => '票据不存在'];
}
