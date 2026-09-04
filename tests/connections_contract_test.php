<?php
/**
 * 开放能力契约 —— php tests/connections_contract_test.php
 *
 * 通用连接器让系统里的外部凭据成倍增加，也让「向任意地址发请求」变成一个配置项。
 * 所以这套测试盯的是边界：
 *   A. 秘钥：落盘必加密、界面只露末 4 位、日志必脱敏、模板导出必剥掉
 *   B. SSRF：私网/本机/自身域名/非 http 一律拒；导入的模板永远带不进 allow_private
 *   C. 代入：值的代入而不是文本拼接，上下文里的引号进不了请求体结构
 *   D. OAuth2：state 一次性、PKCE S256、redirect_uri 固定
 *   E. 接线：两套执行器都接、UI 都能选
 *   F. data/ 在根 .htaccess 里被拒绝（此前只靠 gitignored 的 data/.htaccess）
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
ob_start(); require_once "$root/admin/config.php"; ob_end_clean();
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  ✗ $msg\n"; } }

require_once "$root/lib/Secrets.php";
require_once "$root/lib/Connections.php";
require_once "$root/lib/ConnectionActions.php";
require_once "$root/lib/OAuth2Client.php";

// 干净的存储，跑完还原
$files = [conn_file(), action_file(), conn_log_file()];
$bak = []; foreach ($files as $f) { $bak[$f] = is_file($f) ? file_get_contents($f) : null; file_put_contents($f, '[]'); }
$GLOBALS['CONN_SENDER'] = null;
// 测试环境没有 DNS：把公网域名解析到一个公网地址，私网/本机仍走 IP 字面量判定。
// 顺带钉一个「解析到私网」的域名，验证 DNS 重绑定这一路也被挡。
$GLOBALS['CONN_RESOLVER'] = fn(string $h) => $h === 'rebind.evil.test' ? ['127.0.0.1'] : ['93.184.216.34'];

/* ══ A. 秘钥 ══ */
$c1 = secret_encrypt('sk-verysecret-12345678');
ok(secret_is_encrypted($c1), '加密结果没有可辨识前缀');
ok(secret_decrypt($c1) === 'sk-verysecret-12345678', '加密往返失败');
ok(secret_encrypt($c1) === $c1, '重复加密叠加了');
ok(secret_decrypt('legacy-plain') === 'legacy-plain', '老明文没有原样返回，历史数据会集体失效');
ok(secret_decrypt(substr($c1, 0, -6) . 'xxxxxx') === '', '篡改的密文没有被拒绝');
ok(secret_mask($c1) === '••••••••••••5678', 'mask 格式不对：' . secret_mask($c1));
$perm = substr(sprintf('%o', fileperms(secret_key_file())), -3);
ok(in_array($perm, ['600', '400'], true), '密钥文件权限过宽：' . $perm);
ok(str_starts_with(basename(secret_key_file()), '.'), '密钥文件不是隐藏文件');

$r = conn_save(['name' => '测试服务', 'base_url' => 'https://api.example.com', 'auth' => ['type' => 'bearer', 'token' => 'tok-PLAINTEXT-abcdef']]);
ok($r['ok'], '保存连接失败：' . $r['error']);
$cid = $r['conn']['id'];
$disk = file_get_contents(conn_file());
ok(!str_contains($disk, 'tok-PLAINTEXT-abcdef'), '明文 token 落盘了！');
ok(str_contains($disk, 'enc1:') || str_contains($disk, 'enc2:'), '落盘的不是密文');
$disp = conn_for_display(conn_get($cid));
ok(!isset($disp['auth']['token']) && ($disp['auth']['token_masked'] ?? '') === '••••••••••••cdef', '界面展示形态泄漏了 token 或 mask 不对');
// 留空 = 不改
$r2 = conn_save(['id' => $cid, 'name' => '测试服务', 'base_url' => 'https://api.example.com', 'auth' => ['type' => 'bearer', 'token' => '']]);
ok(secret_decrypt(conn_get($cid)['auth']['token']) === 'tok-PLAINTEXT-abcdef', '表单留空把原有 token 清掉了');

// 日志脱敏：让发送器失败并把 token 回显在错误里
$GLOBALS['CONN_SENDER'] = fn($m, $u, $o) => ['ok' => false, 'status' => 401, 'body' => '', 'json' => null, 'error' => 'bad auth: ' . ($o['headers']['Authorization'] ?? '')];
conn_request($cid, 'GET', '/me');
$log = file_get_contents(conn_log_file());
ok(!str_contains($log, 'tok-PLAINTEXT-abcdef'), '调用日志里出现了明文 token');
ok(str_contains($log, '[已脱敏]'), '错误信息没有脱敏标记');
ok(!str_contains($log, '?'), '日志记了查询参数（可能带 key）');

/* ══ B. SSRF ══ */
foreach (['http://127.0.0.1/', 'http://10.0.0.1/', 'http://172.16.0.1/', 'http://192.168.0.1/', 'http://169.254.169.254/',
          'http://100.64.0.1/', 'http://[::1]/', 'http://[fe80::1]/', 'http://localhost/', 'http://0.0.0.0/',
          'ftp://example.com/', 'file:///etc/passwd', 'http://user:pw@example.com/'] as $u) {
    ok(!conn_url_allowed($u)['ok'], "SSRF：放行了 {$u}");
}
ok(conn_url_allowed('http://127.0.0.1/', true)['ok'], 'allow_private 打开后本机应可用（自托管内网服务）');
ok(!conn_url_allowed('http://rebind.evil.test/')['ok'], 'SSRF：域名解析到私网地址竟然放行（DNS 重绑定）');
$_SERVER['HTTP_HOST'] = 'my.site.test';
ok(!conn_url_allowed('https://my.site.test/xmp/')['ok'], 'SSRF：放行了请求本站自身');
ok(!conn_save(['name' => 'x', 'base_url' => 'http://127.0.0.1:6379', 'auth' => ['type' => 'none']])['ok'], '基址是私网竟然能保存');
// 用一个「什么都放行」的发送器，确保拒绝真的来自同源检查而不是发送器碰巧失败
$GLOBALS['CONN_SENDER'] = fn($m, $u, $o) => ['ok' => true, 'status' => 200, 'body' => '', 'json' => null, 'error' => ''];
$xo = conn_request($cid, 'GET', 'https://evil.example.org/steal');
ok(!$xo['ok'] && str_contains($xo['error'], '同源'), '完整 URL 与基址不同源竟然放行——凭据会被带去别处');
$src = file_get_contents("$root/lib/Connections.php");
ok(str_contains($src, 'CURLOPT_FOLLOWLOCATION => false'), '跟随跳转没关，SSRF 检查可被绕过');
ok(str_contains($src, 'CURLOPT_RESOLVE'), '没有钉死 DNS，检查后可被重绑定');
ok(str_contains($src, 'CURLOPT_PROTOCOLS'), '没有限制 curl 协议');
ok(str_contains($src, 'CURLOPT_SSL_VERIFYPEER => true'), 'TLS 校验被关了');

/* ══ 模板：剥秘钥、导入停用、不带 allow_private ══ */
conn_save(['id' => $cid, 'name' => '测试服务', 'base_url' => 'https://api.example.com', 'auth' => ['type' => 'bearer', 'token' => ''], 'allow_private' => true]);
$a1 = action_save(['conn_id' => $cid, 'name' => '建联系人', 'method' => 'POST', 'path' => '/v1/contacts', 'body' => '{"email":"{{email}}"}']);
ok($a1['ok'], '保存动作失败：' . $a1['error']);
$tpl = conn_template_export($cid, action_for_conn($cid));
$tplJson = json_encode($tpl);
ok(!str_contains($tplJson, 'tok-PLAINTEXT-abcdef') && !str_contains($tplJson, 'enc1:') && !str_contains($tplJson, 'enc2:'), '导出模板里带了秘钥（明文或密文）');
ok(!isset($tpl['auth']['token']), '导出模板的 auth 里还有 token 字段');
ok(in_array('token', $tpl['secrets_needed'], true), '模板没有告诉导入方要填哪些秘钥');
ok(!isset($tpl['allow_private']), '导出模板带了 allow_private');
ok(count($tpl['actions']) === 1 && !isset($tpl['actions'][0]['id']), '模板里的动作没有剥掉内部 id');

$tpl['allow_private'] = true;                        // 恶意模板试图带进来
$tpl['auth']['token'] = 'evil-token';                // 恶意模板试图塞秘钥
$imp = conn_template_import($tpl);
ok($imp['ok'], '导入模板失败：' . $imp['error']);
ok(empty($imp['conn']['enabled']), '导入的连接不是停用状态——秘钥都没填就能被自动化触发');
ok(empty($imp['conn']['allow_private']), '导入的模板把 allow_private 带进来了——社区模板可以打内网');
ok(!isset($imp['conn']['auth']['token']), '导入的模板把秘钥带进来了');
ok(count($imp['actions']) === 1 && empty($imp['actions'][0]['enabled']), '导入的动作没有停用');
ok(!conn_template_import(['name' => 'x'])['ok'], '没有版本标记的 JSON 竟然当模板导入了');
ok(count(conn_bundled_templates()) >= 3, '随仓库带的示例模板少于 3 个');
foreach (conn_bundled_templates() as $t) {
    $js = json_encode($t);
    ok(!isset($t['allow_private']) && !preg_match('/"(token|value|pass|client_secret)"\s*:\s*"[^"]+"/', $js), "示例模板 {$t['_file']} 带了秘钥或私网开关");
}

/* ══ C. 代入 ══ */
$ctx = ['email' => 'a@b.c', 'amount' => 12.5, 'evil' => '", "admin": true, "x": "', 'tags' => ['a', 'b'], 'm' => ['id' => 'm1']];
$out = action_fill(['e' => '{{email}}', 'n' => '{{amount}}', 'msg' => 'hi {{evil}}', 't' => '{{tags}}', 'id' => '{{m.id}}', 'gone' => '{{nope}}'], $ctx);
ok($out['n'] === 12.5, '整段占位符没有保留原始类型');
ok($out['t'] === ['a', 'b'], '数组值没有保留');
ok($out['id'] === 'm1', '点路径不工作');
ok($out['gone'] === '', '缺失的字段没有变成空串');
ok(count($out) === 6 && !array_key_exists('admin', $out), '注入试探改写了请求体结构');
ok(action_fill_path('/u/{{x}}', ['x' => 'a/../b']) === '/u/a%2F..%2Fb', '路径代入没有编码，能改路径');
// 请求真的按结构发出去
$captured = null;
$GLOBALS['CONN_SENDER'] = function ($m, $u, $o) use (&$captured) { $captured = [$m, $u, $o]; return ['ok' => true, 'status' => 200, 'body' => '{}', 'json' => [], 'error' => '']; };
action_run($a1['action']['id'], ['email' => 'x@y.z']);
ok($captured && $captured[0] === 'POST' && str_ends_with($captured[1], '/v1/contacts'), '动作没有按模板发出');
ok(($captured[2]['json']['email'] ?? '') === 'x@y.z', '动作请求体没有代入');
ok(str_starts_with($captured[2]['headers']['Authorization'] ?? '', 'Bearer '), '动作没有注入鉴权');
ok(action_get($a1['action']['id'])['last_ok'] === true, '动作没有记录上次结果');
// 停用的动作不跑；force 才跑
action_save(['id' => $a1['action']['id'], 'conn_id' => $cid, 'name' => '建联系人', 'method' => 'POST', 'path' => '/v1/contacts', 'enabled' => false]);
$captured = null; action_run($a1['action']['id'], []);
ok($captured === null, '停用的动作仍然发出了请求');

/* ══ D. OAuth2 ══ */
$ro = conn_save(['name' => 'OA', 'base_url' => 'https://api.oauth.test', 'auth' => ['type' => 'oauth2',
    'auth_url' => 'https://auth.oauth.test/authorize', 'token_url' => 'https://auth.oauth.test/token', 'client_id' => 'cid', 'client_secret' => 'csec', 'scopes' => 'read']]);
ok($ro['ok'], 'OAuth2 连接保存失败：' . $ro['error']);
$_SESSION = [];
$b = oauth2_begin($ro['conn']);
ok($b['ok'], 'oauth2_begin 失败：' . $b['error']);
parse_str((string)parse_url($b['url'], PHP_URL_QUERY), $q);
ok(($q['code_challenge_method'] ?? '') === 'S256' && strlen($q['code_challenge'] ?? '') >= 43, '没有 PKCE S256');
ok(($q['redirect_uri'] ?? '') === oauth2_callback_url(), 'redirect_uri 不是固定的本站回调');
ok(!empty($q['state']) && isset($_SESSION['oauth2_pending'][$q['state']]), 'state 没有绑定到会话');
// 回调：错的 state 拒绝；对的 state 一次性
ok(!oauth2_finish('code', 'wrong-state')['ok'], '错误的 state 竟然通过');
$GLOBALS['CONN_SENDER'] = function ($m, $u, $o) { return ['ok' => true, 'status' => 200, 'body' => '', 'error' => '',
    'json' => ['access_token' => 'AT-1', 'refresh_token' => 'RT-1', 'expires_in' => 3600, 'token_type' => 'Bearer'], '_form' => $o['form'] ?? []]; };
$fin = oauth2_finish('the-code', $q['state']);
ok($fin['ok'], 'oauth2_finish 失败：' . $fin['error']);
ok(!oauth2_finish('the-code', $q['state'])['ok'], 'state 可以重放');
$oc = conn_get($ro['conn']['id']);
ok(secret_is_encrypted($oc['auth']['access_token']) && secret_is_encrypted($oc['auth']['refresh_token']), 'OAuth token 没有加密落盘');
ok(oauth2_access_token($oc) === 'AT-1', '取不到 access_token');
// 过期自动刷新
conn_patch($oc['id'], ['auth' => ['expires_at' => time() - 10]]);
$GLOBALS['CONN_SENDER'] = function ($m, $u, $o) { return ['ok' => true, 'status' => 200, 'body' => '', 'error' => '',
    'json' => ['access_token' => 'AT-2', 'expires_in' => 3600], '_grant' => $o['form']['grant_type'] ?? '']; };
ok(oauth2_access_token(conn_get($oc['id'])) === 'AT-2', '过期后没有自动刷新');
ok(secret_decrypt(conn_get($oc['id'])['auth']['refresh_token']) === 'RT-1', '刷新后丢了 refresh_token（对方没返回新的就该沿用）');
$cb = file_get_contents("$root/api/oauth-callback.php");
ok(str_contains($cb, 'is_logged_in()'), 'OAuth 回调没有要求后台登录——构造的回调能把别人的账号绑进来');

/* ══ E. 接线 ══ */
$au = file_get_contents("$root/lib/AutomationSystem.php"); $cv = file_get_contents("$root/lib/CanvasSystem.php");
ok(str_contains($au, "case 'connection_action'") && str_contains($au, 'action_run_safe('), '规则自动化没有接连接动作');
ok(str_contains($cv, "case 'connection'") && str_contains($cv, 'action_run_safe('), '画布没有接连接动作');
ok(str_contains(file_get_contents("$root/admin/automation.php"), 'connection_action'), '自动化编辑界面选不到连接动作');
ok(str_contains(file_get_contents("$root/admin/canvas.php"), "addNode('connection')"), '画布界面加不了连接节点');
ok(preg_match("/\\['tag','score','stage','webhook','connection'\\]/", file_get_contents("$root/admin/canvas.php")) === 1, '画布 JS 隐藏占位漏了 connection，POST 下标会错位');
ok(is_file("$root/admin/connections.php"), '缺少连接后台页');
ok(str_contains(file_get_contents("$root/includes/admin-nav.php"), "'id' => 'connections'"), '侧栏没有连接入口');

/* ══ F. data/ 在根 .htaccess 里被拒绝 ══ */
$ht = file_get_contents("$root/.htaccess");
ok(preg_match('/^RewriteRule \^data\(\/\|\$\) - \[F,L\]/m', $ht) === 1, '根 .htaccess 没有拒绝 data/——此前只靠 gitignored 的 data/.htaccess，全新部署是裸的');
ok(strpos($ht, 'RewriteRule ^data') < strpos($ht, 'RewriteRule ^$ index.php'), 'data/ 拒绝规则没有放在其他规则之前');

// 收尾
$GLOBALS['CONN_SENDER'] = null; $GLOBALS['CONN_RESOLVER'] = null;
foreach ($bak as $f => $content) { if ($content === null) @unlink($f); else file_put_contents($f, $content); }

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
