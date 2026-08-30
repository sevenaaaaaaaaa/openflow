<?php
/**
 * 结构性 CSRF 收口验收
 *
 *   php tests/csrf_guard_test.php
 *
 * 证明 require_login() 里的自动校验是真的生效的结构闸，而不是摆设：
 *   - 无 token 的 POST → 被 403 挡下
 *   - 带正确 token 的 POST → 放行进入页面逻辑
 *   - 无 token 的破坏性 GET(?delete=) → 被挡
 *   - 普通 GET → 正常渲染（闸对读操作零影响）
 *
 * 每种情形开子进程跑，csrf_verify() 会 die()，只能靠退出后的输出判断。
 */

$root = dirname(__DIR__);

// 子进程模式：以指定方法 / 参数请求一个后台页
if (($argv[1] ?? '') === '--req') {
    $page = $argv[2]; $method = $argv[3]; $withToken = ($argv[4] ?? '0') === '1';
    $getExtra = $argv[5] ?? '';

    $sessDir = sys_get_temp_dir() . '/of-csrf-sess-' . getmypid();
    @mkdir($sessDir, 0777, true);
    $sid = 'csrf' . getmypid();
    $tok = 'TESTTOKEN1234567890';
    file_put_contents("{$sessDir}/sess_{$sid}",
        'admin_login|b:1;admin_user|s:4:"root";admin_role|s:5:"admin";csrf_token|s:19:"' . $tok . '";');
    ini_set('session.save_path', $sessDir);
    ini_set('session.use_strict_mode', '0');
    $_COOKIE['PHPSESSID'] = $sid;

    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['SCRIPT_NAME'] = "/admin/{$page}.php";
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    parse_str($getExtra, $_GET);
    if ($withToken) {
        $_POST['_csrf_token'] = $tok;
        if ($method === 'GET') $_GET['csrf_token'] = $tok;
    }
    $_SERVER['REQUEST_URI'] = "/xmp/{$page}" . ($getExtra ? "?{$getExtra}" : '');

    ob_start();
    register_shutdown_function(function () use ($sessDir) {
        $out = ob_get_contents();
        // csrf_verify() 失败时 die('CSRF 验证失败...')
        if (strpos((string)$out, 'CSRF 验证失败') !== false) { /* marker in output */ }
        foreach (glob("{$sessDir}/*") ?: [] as $f) @unlink($f);
        @rmdir($sessDir);
    });
    try { include "{$root}/admin/{$page}.php"; }
    catch (\Throwable $e) { echo "\n__THROW__" . $e->getMessage(); }
    exit;
}

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}
function req(string $page, string $method, bool $token, string $get = ''): string {
    $cmd = escapeshellarg(PHP_BINARY) . ' '
         . '-d session.use_strict_mode=0 '
         . escapeshellarg(__FILE__) . ' --req '
         . escapeshellarg($page) . ' ' . escapeshellarg($method) . ' '
         . ($token ? '1' : '0') . ' ' . escapeshellarg($get) . ' 2>&1';
    return (string)shell_exec($cmd);
}

// 用 settings 页做被试：它有 POST 处理，且 require_login + require_perm 齐全
$page = 'settings';

echo "\n── POST 无 token ──\n";
$out = req($page, 'POST', false);
check('被 CSRF 闸挡下', strpos($out, 'CSRF 验证失败') !== false, mb_substr(trim($out), 0, 80));

echo "\n── POST 带正确 token ──\n";
$out = req($page, 'POST', true);
check('放行（未出现 CSRF 失败）', strpos($out, 'CSRF 验证失败') === false);
check('确实进入了页面渲染', strpos($out, '<') !== false);

echo "\n── 破坏性 GET(?delete=x) 无 token ──\n";
$out = req('downloads', 'GET', false, 'delete=someid');
check('破坏性 GET 被挡', strpos($out, 'CSRF 验证失败') !== false, mb_substr(trim($out), 0, 80));

echo "\n── 破坏性 GET 带 token ──\n";
$out = req('downloads', 'GET', true, 'delete=someid');
check('带 token 放行', strpos($out, 'CSRF 验证失败') === false);

echo "\n── 普通 GET（读操作）不受影响 ──\n";
$out = req($page, 'GET', false);
check('正常渲染，无 CSRF 拦截', strpos($out, 'CSRF 验证失败') === false && strpos($out, '<') !== false);

echo "\n── 前端兜底脚本已注入 ──\n";
$cfg = file_get_contents(dirname(__DIR__) . '/admin/config.php');
check('admin_footer 注入了 CSRF 兜底', strpos($cfg, 'CSRF 前端兜底') !== false);
check('fetch 被包装加头', strpos($cfg, "h.set('X-CSRF-Token'") !== false);
check('require_login 调用了自动校验', strpos($cfg, 'csrf_guard_auto()') !== false);

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
