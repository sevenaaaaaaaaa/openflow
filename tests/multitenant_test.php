<?php
/**
 * 多租户地基验收（docs/ROADMAP.md 阶段二）
 *   php tests/multitenant_test.php
 *
 * 这一条改的是 admin/config.php 里解析数据目录的那几行。它值钱是因为：
 * 全仓 861 处在用 DATA_DIR，但都读同一个常量，常量只定义一次——
 * 所以"一个客户一个独立目录"这件通常要翻遍全仓的事，只需要改这一处。
 *
 * 这份测试守两件事：
 *   ① **默认什么都不变**。不开开关时，单站部署的路径与之前逐字节一致。
 *      这是最重要的一条——地基不能以改变现有行为为代价。
 *   ② 开启后按域名隔离，且**拼路径这一步是安全的**（域名来自外部输入，
 *      是整个方案唯一的要害，必须挡住目录穿越）。
 *
 * 每个用例都在独立子进程里跑，因为 DATA_DIR 是常量，一个进程只能定义一次。
 */

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

$root = realpath(__DIR__ . '/..');
$base = sys_get_temp_dir() . '/of-mt-' . getmypid();
@mkdir($base, 0777, true);

/**
 * 在子进程里加载 config.php，回报它算出来的 DATA_DIR / UPLOAD_DIR / OF_TENANT。
 * @param array $env 额外环境变量
 * @param string $host 模拟的 HTTP_HOST
 */
function probe(array $env, string $host): array {
    global $root;
    // host 直接写进探针文件（var_export 安全转义），避免经过 shell——
    // 攻击用例里含空字节，走 escapeshellarg 会直接抛异常。
    $php = "<?php\n"
         . '$_SERVER["HTTP_HOST"] = ' . var_export($host, true) . ";\n"
         . '$_SERVER["REQUEST_URI"] = "/";' . "\n"
         . 'require_once ' . var_export($root . '/admin/config.php', true) . ";\n"
         . 'echo json_encode(["data"=>DATA_DIR,"upload"=>UPLOAD_DIR,'
         . '"tenant"=>defined("OF_TENANT")?OF_TENANT:null], JSON_UNESCAPED_SLASHES);' . "\n";
    $f = sys_get_temp_dir() . '/of-probe-' . getmypid() . '-' . mt_rand() . '.php';
    file_put_contents($f, $php);

    // 环境变量走 proc_open 的 env 数组，不拼 shell 字符串
    $descr = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open([PHP_BINARY, $f], $descr, $pipes, null, array_merge($_ENV, $env));
    $out = ''; $err = '';
    if (is_resource($proc)) {
        $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
        proc_close($proc);
    }
    @unlink($f);
    $j = json_decode(trim((string)$out), true);
    return is_array($j) ? $j : ['data' => '?', 'upload' => '?', 'tenant' => '?', 'err' => trim((string)$err)];
}

echo "\n── 1. 默认行为完全不变（最重要的一条）──\n";
$plain = probe(['OF_DATA_DIR' => $base . '/d', 'OF_UPLOAD_DIR' => $base . '/u'], 'a.example.com');
check('不开开关：DATA_DIR 就是配的那个', $plain['data'] === $base . '/d', json_encode($plain));
check('不开开关：UPLOAD_DIR 就是配的那个', $plain['upload'] === $base . '/u', json_encode($plain));
check('不开开关：租户标识为空', $plain['tenant'] === '', var_export($plain['tenant'], true));

$plain2 = probe(['OF_DATA_DIR' => $base . '/d', 'OF_UPLOAD_DIR' => $base . '/u'], 'b.other.com');
check('不开开关：换域名也不影响路径', $plain2['data'] === $plain['data']);

echo "\n── 2. 开启后按域名分目录 ──\n";
$envOn = ['OF_DATA_DIR' => $base . '/d', 'OF_UPLOAD_DIR' => $base . '/u',
          'OF_MULTI_TENANT' => '1', 'OF_TENANT_ROOT' => $base . '/tenants'];
$a = probe($envOn, 'a.example.com');
$b = probe($envOn, 'b.example.com');
check('a 站有自己的目录', $a['data'] === $base . '/tenants/a.example.com', json_encode($a));
check('b 站有自己的目录', $b['data'] === $base . '/tenants/b.example.com', json_encode($b));
check('两站目录不同（隔离成立）', $a['data'] !== $b['data']);
check('租户标识 = 域名', $a['tenant'] === 'a.example.com', (string)$a['tenant']);
check('目录真的被建出来了', is_dir($base . '/tenants/a.example.com'));
check('上传目录默认落在租户目录内', $a['upload'] === $base . '/tenants/a.example.com/uploads', (string)$a['upload']);

echo "\n── 3. 端口和大小写归一（同一个站不能因为写法不同分成两个）──\n";
check('带端口 → 同一目录', probe($envOn, 'a.example.com:8080')['data'] === $a['data']);
check('大写 → 同一目录', probe($envOn, 'A.Example.COM')['data'] === $a['data']);

echo "\n── 4. 安全要害：域名是外部输入，不许拼出目录穿越 ──\n";
$attacks = [
    '目录穿越'      => '../../etc',
    '编码穿越'      => '..%2f..%2fetc',
    '斜杠注入'      => 'a.example.com/../../root',
    '空字节'        => "a.example.com\0/etc",
    '纯点'          => '...',
    '空 host'       => '',
];
foreach ($attacks as $label => $host) {
    $r = probe($envOn, $host);
    $d = (string)$r['data'];
    $inside = strpos($d, $base . '/tenants/') === 0 || $d === $base . '/d';
    $noDots = strpos($d, '..') === false;
    check("{$label} → 路径没跑出租户根目录", $inside && $noDots, $d);
}

echo "\n── 5. 未开启时不建任何租户目录 ──\n";
check('tenants 目录不会凭空出现在默认部署里',
      !is_dir($base . '/d/tenants'), $base . '/d/tenants');

echo "\n── 6. 常量在全仓的用法没被改动（861 处自动跟随的前提）──\n";
$cfg = file_get_contents($root . '/admin/config.php');
check('DATA_DIR 仍然只定义一次', substr_count($cfg, "define('DATA_DIR'") === 1,
      (string)substr_count($cfg, "define('DATA_DIR'"));
check('UPLOAD_DIR 仍然只定义一次', substr_count($cfg, "define('UPLOAD_DIR'") === 1);
check('派生路径仍基于 DATA_DIR', strpos($cfg, "define('PAGES_DIR', DATA_DIR") !== false);
check('域名做了字符白名单过滤', strpos($cfg, "preg_replace('/[^a-z0-9.\\-]/'") !== false);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exec('rm -rf ' . escapeshellarg($base));
exit($fail === 0 ? 0 : 1);
