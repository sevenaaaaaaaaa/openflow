<?php
/**
 * 后台页面渲染冒烟
 *
 *   php tests/render_smoke_test.php
 *
 * 静态检查看不出「合并之后页面还能不能打开」。这里真的把每个中心页、
 * 每个 tab 当成已登录请求跑一遍，抓 fatal / warning / notice。
 *
 * 每个 URL 必须开子进程：页面会 define('OF_EMBED') 并定义大量函数，
 * 同进程跑第二个页面必然互相污染。
 */

$root = dirname(__DIR__);

// ── 要巡检的 URL：所有中心页 + 所有 tab + 所有被合并页的 301 落点 ──
$urls = [
    ['seo-center',   ''],
    ['seo-center',   'tab=pages'],
    ['seo-center',   'tab=tools'],
    ['seo-center',   'tab=batch'],
    ['seo-center',   'tab=console'],
    ['seo-center',   'tab=structured'],
    ['seo-center',   'tab=images'],
    ['seo-center',   'tab=redirects'],
    ['content-hub',  ''],
    ['content-hub',  'tab=articles'],
    ['content-hub',  'tab=articles&trash=1'],
    ['content-hub',  'tab=pages'],
    ['content-hub',  'tab=pages&sub=cats'],
    ['content-hub',  'tab=pages&sub=tags'],
    ['content-hub',  'tab=downloads'],
    ['content-hub',  'tab=podcasts'],
    ['shop-settings', ''],
    ['shop-settings', 'sub=pay'],
    ['email',        'sub=smtp'],
    ['site-builder', 'sub=foot'],
    ['health-check', 'sub=stor'],
    ['audit-log',    'sub=act'],
    ['orders',       ''],
    ['orders',       'status=paid'],
    ['brain',        ''],
    ['cpt',          ''],
    ['content-i18n', ''],
    ['ask-data',     ''],
    ['click-tracking', ''],
    ['consent',      ''],
    ['inbox',        ''],
    ['platform-ops', ''],
    ['catalog',      ''],
    ['decision-trace', ''],
    ['dev-docs',     ''],
    ['commission',   ''],
    ['destinations', ''],
    ['ai-usage',     ''],
    ['api-permissions', ''],
];

// ─────────────────────────────────────────────
// 子进程模式：渲染单个页面
// ─────────────────────────────────────────────
if (($argv[1] ?? '') === '--render') {
    $page = $argv[2];
    $qs   = $argv[3] ?? '';

    // 预置一个「已登录管理员」的 session 文件，绕开 require_login()
    $sessDir = sys_get_temp_dir() . '/of-smoke-sess-' . getmypid();
    @mkdir($sessDir, 0777, true);
    $sid = 'ofsmoke' . getmypid();
    file_put_contents("{$sessDir}/sess_{$sid}",
        'admin_login|b:1;admin_user|s:4:"root";admin_role|s:5:"admin";');
    ini_set('session.save_path', $sessDir);
    ini_set('session.use_strict_mode', '0');
    $_COOKIE['PHPSESSID'] = $sid;

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SCRIPT_NAME']    = "/admin/{$page}.php";
    $_SERVER['REQUEST_URI']    = "/xmp/{$page}" . ($qs !== '' ? "?{$qs}" : '');
    $_SERVER['HTTP_HOST']      = 'localhost';   // localhost ⇒ OF_ENV=dev ⇒ 错误会显式输出
    $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    parse_str($qs, $_GET);

    error_reporting(E_ALL);
    ini_set('display_errors', '1');

    /* 汇报必须走 shutdown 钩子。
     * 后台不少页面在渲染完子标签后直接 exit（例如 shop-settings?sub=pay），
     * 这时 include 之后的代码根本不会执行，ob_get_clean() 拿不到内容，
     * 缓冲区被 PHP 直接冲到 stdout —— 父进程收到的是整页 HTML 而不是那一行结果，
     * 于是把正常页面判成失败。PHP 会先跑 shutdown 函数、再冲缓冲区，
     * 所以在钩子里收网，页面 exit 与否都能正确汇报。 */
    $report = function (string $prefix = '') use ($sessDir) {
        if (!empty($GLOBALS['__smoke_reported'])) return;
        $GLOBALS['__smoke_reported'] = true;

        $out = '';
        while (ob_get_level() > 0) { $out = (string)ob_get_clean() . $out; }

        foreach (glob("{$sessDir}/*") ?: [] as $f) @unlink($f);
        @rmdir($sessDir);

        if ($prefix !== '') { echo $prefix; return; }

        $bad = [];
        foreach (explode("\n", $out) as $l) {
            $l = trim(strip_tags($l));
            if ($l !== '' && preg_match('/(Fatal error|Parse error|Uncaught|Warning:|Notice:|Deprecated:)/', $l)) {
                $bad[] = mb_substr($l, 0, 160);
            }
        }
        echo (empty($bad) ? 'OK' : 'ERR') . "\t" . strlen($out) . "\t"
           . implode(' ｜ ', array_slice(array_unique($bad), 0, 3)) . "\n";
    };
    register_shutdown_function($report);

    ob_start();
    try {
        include dirname(__DIR__) . "/admin/{$page}.php";
    } catch (\Throwable $e) {
        $report("FATAL\t" . get_class($e) . ': ' . $e->getMessage()
              . ' @' . basename($e->getFile()) . ':' . $e->getLine() . "\n");
        exit(0);
    }
    $report();
    exit(0);
}

// ─────────────────────────────────────────────
// 驱动模式
// ─────────────────────────────────────────────
$pass = 0; $fail = 0;
foreach ($urls as [$page, $qs]) {
    $label = "/xmp/{$page}" . ($qs !== '' ? "?{$qs}" : '');
    if (!is_file("{$root}/admin/{$page}.php")) {
        $fail++; printf("  ✗ %-42s → 文件不存在\n", $label); continue;
    }
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
         . ' --render ' . escapeshellarg($page) . ' ' . escapeshellarg($qs) . ' 2>&1';
    $out = trim((string)shell_exec($cmd));
    $parts = explode("\t", $out);
    $status = $parts[0] ?? 'ERR';

    if ($status === 'OK') {
        $bytes = (int)($parts[1] ?? 0);
        if ($bytes < 500) { $fail++; printf("  ✗ %-42s → 只输出 %d 字节，疑似被 exit 截断\n", $label, $bytes); }
        else              { $pass++; printf("  ✓ %-42s %7d 字节\n", $label, $bytes); }
    } else {
        $fail++;
        printf("  ✗ %-42s → %s\n", $label, $parts[2] ?? ($parts[1] ?? $out));
    }
}

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 个页面\n" : "通过 {$pass} 个，失败 {$fail} 个\n";
exit($fail === 0 ? 0 : 1);
