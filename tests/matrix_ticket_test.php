<?php
/**
 * 矩阵账号互通(MatrixTicket) 契约 —— clients 播种 / secret 签发 / 短票签发与兑换 / 防重放 / 防越权
 *   php tests/matrix_ticket_test.php
 */

$tmp = sys_get_temp_dir() . '/of-mtx-' . getmypid();
@mkdir($tmp . '/members', 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MatrixTicket.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "矩阵账号互通\n";

// ① clients 播种
$clients = matrix_clients();
check('默认播种 7 个矩阵应用', count($clients) >= 7);
check('含 mflow', (bool)matrix_client('mflow'));

// ② 未启用前 enter 拒绝
$r = matrix_enter('mflow', '/');
check('未启用时 enter 拒绝', empty($r['ok']), $r['error'] ?? '');

// ③ 签发 secret + 配回跳 → 启用
$issue = matrix_client_issue_secret('mflow', 'https://mflow.example.com');
check('secret 签发成功', !empty($issue['ok']) && str_starts_with($issue['secret'], 'mtx_'));
$secret = $issue['secret'];
$c = matrix_client('mflow');
check('落库存的是 hash(非明文)', !empty($c['secret_hash']) && !str_contains($c['secret_hash'], 'mtx_'));
check('回跳地址已登记', $c['redirect_base'] === 'https://mflow.example.com');

// ④ 模拟会员登录后签票
$_SESSION['member_id'] = 'm_test001';   // member_current 的最小化注入(会话级)
$members = json_read(DATA_DIR . '/members/index.json') ?: [];
$members[] = ['id' => 'm_test001', 'email' => 'u@test.dev', 'name' => '互通测试员', 'level' => 'free', 'status' => 'active'];
json_write(DATA_DIR . '/members/index.json', $members);

// 直接构造票据并验证兑换(绕过 HTTP 层;enter 的会话依赖在 api 层测)
$ticket = 'mkt_' . bin2hex(random_bytes(20));
$tickets = [[
    'ticket' => hash('sha256', $ticket), 'client_id' => 'mflow', 'member_id' => 'm_test001',
    'email' => 'u@test.dev', 'name' => '互通测试员', 'level' => 'free', 'exp' => time() + 60, 'used' => false,
]];
json_write(matrix_tickets_file(), $tickets);

$red = matrix_redeem($ticket, 'mflow', $secret);
check('兑换成功', !empty($red['ok']));
check('身份字段完整', isset($red['user']['id'], $red['user']['email'], $red['user']['level']));
check('签发方标记 iss=openflow', ($red['user']['iss'] ?? '') === 'openflow');

// ⑤ 防重放:同一张票再兑 → 拒绝
$red2 = matrix_redeem($ticket, 'mflow', $secret);
check('重放被拒绝', empty($red2['ok']), $red2['error'] ?? '');

// ⑥ 防越权:错的 secret / 错的 client_id
$ticket2 = 'mkt_' . bin2hex(random_bytes(20));
json_write(matrix_tickets_file(), [[
    'ticket' => hash('sha256', $ticket2), 'client_id' => 'mflow', 'member_id' => 'm_test001',
    'email' => 'u@test.dev', 'name' => 'x', 'level' => 'free', 'exp' => time() + 60, 'used' => false,
]]);
check('secret 错误被拒', empty(matrix_redeem($ticket2, 'mflow', 'mtx_wrong')['ok']));
check('client_id 不匹配被拒', empty(matrix_redeem($ticket2, 'payflow', $secret)['ok']));

// ⑦ 过期票被拒
$ticket3 = 'mkt_' . bin2hex(random_bytes(20));
json_write(matrix_tickets_file(), [[
    'ticket' => hash('sha256', $ticket3), 'client_id' => 'mflow', 'member_id' => 'm_test001',
    'email' => 'u@test.dev', 'name' => 'x', 'level' => 'free', 'exp' => time() - 1, 'used' => false,
]]);
check('过期票被拒', empty(matrix_redeem($ticket3, 'mflow', $secret)['ok']));

// ⑧ 落库的是哈希:文件里搜不到明文票
$raw = (string)file_get_contents(matrix_tickets_file());
check('票据文件不含明文票', !str_contains($raw, 'mkt_'));

echo "\n{$pass} passed · {$fail} failed\n";
exit($fail ? 1 : 0);
