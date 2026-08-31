<?php
/**
 * API 权限矩阵验收（docs/ROADMAP.md 阶段三）
 *   php tests/api_policy_test.php
 *
 * 这一层最大的风险不是"拦不住"，是**误伤自己的前台**——
 * 把一个前台在用的端点标成后台，站点就默默坏一块，而且往往不会立刻发现。
 * 所以这份测试的第一组、也是最重要的一组，是「默认不改变现有行为」：
 * 凡是标成非 public 的端点，**必须没有任何前台页面在调它**。这条是自动核的，
 * 不靠我当初 grep 的那一遍——以后谁往表里加一条标错了，测试就红。
 */

$tmp = sys_get_temp_dir() . '/of-apip-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/ApiPolicy.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

$root = dirname(__DIR__);
$defaults = api_policy_defaults();

echo "\n── 1. 最重要的一条：默认不许误伤前台 ──\n";
// 标成非 public 的端点，必须没有 admin/ 与 lib/ 之外的调用方
$mistakes = [];
foreach ($defaults as $slug => $cfg) {
    if (($cfg['tier'] ?? 'public') === 'public' || ($cfg['tier'] ?? '') === 'token') continue;
    $callers = [];
    foreach (['*.php', '*.js', '*.html'] as $pat) {
        foreach (glob($root . '/' . $pat) as $f) $callers[] = $f;              // 站点根目录（前台）
        foreach (glob($root . '/assets/**/' . $pat) as $f) $callers[] = $f;
        foreach (glob($root . '/themes/*/' . $pat) as $f) $callers[] = $f;
    }
    foreach ($callers as $f) {
        if (basename($f) === 'config.php') continue;   // 根 config.php 是死代码（无人 include）
        if (strpos((string)file_get_contents($f), "api/{$slug}.php") !== false) {
            $mistakes[] = "{$slug} ← " . basename($f);
        }
    }
}
check('没有把前台在用的端点标成需要登录', empty($mistakes), implode(' ', $mistakes));

$adminSlugs = array_keys(array_filter($defaults, fn($c) => ($c['tier'] ?? '') === 'admin'));
check('确实标了一批后台端点（不是空表）', count($adminSlugs) >= 15, (string)count($adminSlugs));
check('每个非 public 端点都写了理由',
      count(array_filter($defaults, fn($c) => trim((string)($c['note'] ?? '')) === '')) === 0);

echo "\n── 2. 端点必须真实存在（防表里留下已删除的条目）──\n";
$ghost = [];
foreach (array_keys($defaults) as $slug) {
    if (!is_file($root . '/api/' . $slug . '.php')) $ghost[] = $slug;
}
check('表里没有不存在的端点', empty($ghost), implode(' ', $ghost));

echo "\n── 3. 权限 slug 必须是系统认识的 ──\n";
$known = of_perm_registry();
$badPerm = [];
foreach ($defaults as $slug => $cfg) {
    $p = (string)($cfg['perm'] ?? '');
    if ($p !== '' && !in_array($p, $known, true)) $badPerm[] = "{$slug}:{$p}";
}
check('没有拼错的权限名', empty($badPerm), implode(' ', $badPerm));

echo "\n── 4. 判定逻辑 ──\n";
$pub   = ['tier' => 'public', 'perm' => ''];
$mem   = ['tier' => 'member', 'perm' => ''];
$adm   = ['tier' => 'admin',  'perm' => ''];
$admP  = ['tier' => 'admin',  'perm' => 'articles'];
$tok   = ['tier' => 'token',  'perm' => ''];
$none  = ['admin' => false, 'perm' => false, 'member' => false];
$asMem = ['admin' => false, 'perm' => false, 'member' => true];
$asAdm = ['admin' => true,  'perm' => false, 'member' => false];
$asAdmP= ['admin' => true,  'perm' => true,  'member' => false];

check('public：谁都放行', api_policy_check($pub, $none)['allowed'] === true);
check('token：本层不拦（端点自带校验）', api_policy_check($tok, $none)['allowed'] === true);
check('member：匿名被拦', api_policy_check($mem, $none)['allowed'] === false);
check('member：会员放行', api_policy_check($mem, $asMem)['allowed'] === true);
check('member：后台管理员不自动算会员', api_policy_check($mem, $asAdm)['allowed'] === false);
check('admin：匿名被拦', api_policy_check($adm, $none)['allowed'] === false);
check('admin：拦的原因是未登录', api_policy_check($adm, $none)['reason'] === 'need_login');
check('admin：会员≠管理员', api_policy_check($adm, $asMem)['allowed'] === false);
check('admin 无具体权限要求：登录即可', api_policy_check($adm, $asAdm)['allowed'] === true);
check('admin 有权限要求：登录但无权限 → 拦', api_policy_check($admP, $asAdm)['allowed'] === false);
check('拦的原因是缺权限', api_policy_check($admP, $asAdm)['reason'] === 'need_perm');
check('admin 有权限要求：权限满足 → 放行', api_policy_check($admP, $asAdmP)['allowed'] === true);

echo "\n── 5. 自定义覆盖 ──\n";
check('默认 consent 是公开', api_policy_for('consent')['tier'] === 'public');
api_policy_save('consent', 'admin', 'settings');
check('改成后台后生效', api_policy_for('consent')['tier'] === 'admin');
check('权限也一起保存', api_policy_for('consent')['perm'] === 'settings');
api_policy_save('consent', 'public');
check('改回公开', api_policy_for('consent')['tier'] === 'public');

api_policy_save('stock', 'public');
check('可以把默认的后台端点放宽回公开', api_policy_for('stock')['tier'] === 'public');
api_policy_save('stock', 'admin', 'media');
check('也能再收紧回去', api_policy_for('stock')['tier'] === 'admin');

check('非法档位被忽略', (function () {
    api_policy_save('theme', 'superuser');
    return api_policy_for('theme')['tier'] === 'public';
})());
check('权限名里的怪字符被清掉', (function () {
    api_policy_save('theme', 'admin', 'set../tings!!');
    return api_policy_for('theme')['perm'] === 'settings';
})());
api_policy_save('theme', 'public');

echo "\n── 6. 三种模式 ──\n";
check('默认是强制', api_policy_mode() === 'enforce');
api_policy_set_mode('observe');
check('可切观察', api_policy_mode() === 'observe');
api_policy_set_mode('off');
check('可关闭', api_policy_mode() === 'off');
api_policy_set_mode('乱写');
check('非法模式被忽略（保持上一次的值）', api_policy_mode() === 'off');
api_policy_set_mode('enforce');

echo "\n── 7. 记录 ──\n";
api_policy_log('stock', 'admin', 'need_login', true);
api_policy_log('cdp-insight', 'admin', 'need_perm', false);
$log = json_read(api_policy_log_file());
check('记了两条', count($log) === 2, (string)count($log));
check('区分「已拦」与「仅记录」',
      ($log[0]['blocked'] ?? null) === true && ($log[1]['blocked'] ?? null) === false);
check('记了端点与原因', ($log[1]['endpoint'] ?? '') === 'cdp-insight' && ($log[1]['reason'] ?? '') === 'need_perm');

echo "\n── 8. 执行点在统一入口，不是散在 92 个文件里 ──\n";
$cfg = file_get_contents($root . '/admin/config.php');
check('config.php 里挂了守卫', strpos($cfg, 'api_policy_guard(') !== false);
check('只对 api/ 目录生效', strpos($cfg, "=== 'api'") !== false);
check('CLI 不受影响（否则所有测试都要挂）', strpos(file_get_contents($root . '/lib/ApiPolicy.php'), "PHP_SAPI === 'cli'") !== false);
$scattered = 0;
foreach (glob($root . '/api/*.php') as $f) {
    if (strpos((string)file_get_contents($f), 'api_policy_guard(') !== false) $scattered++;
}
check('没有端点自己调守卫（避免退回逐个打补丁）', $scattered === 0, (string)$scattered);

echo "\n── 9. 本轮封过的三个花钱端点仍在矩阵里 ──\n";
foreach (['ai-business', 'ai-generate', 'survey-ai', 'assistant'] as $slug) {
    check("{$slug} 是后台档", api_policy_for($slug)['tier'] === 'admin', api_policy_for($slug)['tier']);
}
check('site-agent 保持公开（它本该公开，靠限流护）', api_policy_for('site-agent')['tier'] === 'public');

echo "\n── 10. 覆盖率 ──\n";
$total = count(glob($root . '/api/*.php'));
$gated = count(array_filter(api_policy_all(), fn($c) => in_array($c['tier'] ?? '', ['admin', 'member'], true)));
printf("    共 %d 个端点，其中 %d 个需要身份（改之前是 1 个）\n", $total, $gated);
check('需要身份的端点数明显多于改之前的 1 个', $gated >= 15, (string)$gated);

echo "\n── 11. 端到端：真的发 HTTP 请求（前面测的都是纯函数，这里测接线）──\n";
// 纯函数对不代表守卫真的挂上了：SCRIPT_FILENAME 的识别、session、exit 时机，
// 这些只有真发一次请求才知道。起一个 php -S，匿名打几个端点看返回码。
$port = 8800 + (getmypid() % 900);
$srvData = $tmp . '/http';
@mkdir($srvData . '/uploads', 0777, true);
$desc = [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
$srv = @proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root],
    $desc, $pipes, $root,
    array_merge($_ENV, ['OF_DATA_DIR' => $srvData, 'OF_UPLOAD_DIR' => $srvData . '/uploads'])
);
$httpOk = false;
if (is_resource($srv)) {
    for ($i = 0; $i < 40; $i++) {          // 等它起来
        $c = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2);
        if ($c) { fclose($c); $httpOk = true; break; }
        usleep(150000);
    }
}
if (!$httpOk) {
    echo "    ⚠ 起不了本地服务器，跳过端到端（纯函数部分已覆盖逻辑）\n";
} else {
    $get = function (string $path) use ($port) {
        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $body = @file_get_contents("http://127.0.0.1:{$port}{$path}", false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
        }
        return ['code' => $code, 'body' => (string)$body];
    };
    foreach (['stock', 'cdp-insight', 'templates', 'notifications', 'calendar'] as $e) {
        $r = $get("/api/{$e}.php");
        check("匿名打 {$e} → 401", $r['code'] === 401, $r['code'] . ' ' . mb_substr($r['body'], 0, 60));
    }
    $r = $get('/api/stock.php');
    check('拦下来的是 JSON 且带错误码', strpos($r['body'], '"code":"need_login"') !== false, mb_substr($r['body'], 0, 80));
    foreach (['theme', 'lang', 'consent', 'click-tracks'] as $e) {
        $r = $get("/api/{$e}.php");
        check("公开端点 {$e} 照常 200", $r['code'] === 200, (string)$r['code']);
    }
    proc_terminate($srv);
    proc_close($srv);
}

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exec('rm -rf ' . escapeshellarg($tmp));
exit($fail === 0 ? 0 : 1);
