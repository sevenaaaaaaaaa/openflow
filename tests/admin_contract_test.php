<?php
/**
 * 后台框架层契约（docs/ADMIN-UX.md）—— 纯静态检查，php tests/admin_contract_test.php
 *
 *   1. 没有原生 confirm()：一律 data-confirm 或 ofConfirm()
 *   2. 没有原生 alert()：一律 ofAlert()（config.php 里 fcToast 的兜底除外）
 *   3. 每个带侧栏的后台页都能在导航树里定位到（按脚本名，含别名）
 *   4. 导航树里每个条目都指向存在的页面
 *   5. admin_header / admin_footer 挂了 admin-ui.css / admin-ui.js，且版本号一致
 *   6. <form>/<select> 开闭成对（防止再出现被截断的表单）
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  ✗ $msg\n"; } }

$files = glob("$root/admin/*.php");
$noSidebar = ['config.php', 'login.php', 'logout.php', 'reset-password.php', '_subtabs.php'];

// 1 + 2
foreach ($files as $f) {
    $b = basename($f); $s = file_get_contents($f);
    if ($b === 'config.php') continue;
    ok(!preg_match('/\bon(click|submit)\s*=\s*"[^"]*\bconfirm\(/', $s) && !preg_match('/(?<![\w.$])confirm\(/', $s), "$b 还在用原生 confirm()");
    ok(!preg_match('/(?<![\w.$])alert\(/', $s), "$b 还在用原生 alert()");
}

// 2.5 表单 / 表格标签成对（历史上有 20 处 <form> 在 csrf_field() 后被截断，按钮全没了）
foreach ($files as $f) {
    $b = basename($f); $s = file_get_contents($f);
    ok(preg_match_all('/<form\b/', $s) === preg_match_all('/<\/form>/', $s), "$b <form> 与 </form> 数量不一致（表单被截断？）");
    ok(preg_match_all('/<select\b/', $s) === preg_match_all('/<\/select>/', $s), "$b <select> 与 </select> 数量不一致");
}

// 3 + 4
$_SESSION = ['admin_role' => 'admin'];
if (!function_exists('has_perm')) { function has_perm(string $p): bool { return true; } }
require_once "$root/includes/admin-nav.php";
$ids = [];
foreach (admin_nav_tree() as $area) foreach ($area['groups'] as $g) foreach ($g['items'] as $it) {
    $ids[$it['id']] = true;
    $href = $it['href'] ?? ('/xmp/' . $it['id']);
    $page = preg_replace('/\?.*$/', '', substr($href, strlen('/xmp/')));
    ok(is_file("$root/admin/$page.php"), "导航条目「{$it['label']}」指向不存在的页面 admin/$page.php");
}
foreach ($files as $f) {
    $b = basename($f); $s = file_get_contents($f);
    if (in_array($b, $noSidebar, true) || !str_contains($s, 'admin_sidebar(')) continue;
    $loc = admin_nav_locate('', basename($b, '.php'));
    ok($loc['item'] !== null, "$b 在导航树里定位不到（补 ADMIN_NAV_ALIAS 或加条目）");
}

// 5
$cfg = file_get_contents("$root/admin/config.php");
ok(preg_match('/admin-ui\.css\?v=<\?= OF_ADMIN_UI_VER \?>/', $cfg) === 1, 'admin_header 未挂 admin-ui.css');
ok(preg_match('/admin-ui\.js\?v=<\?= OF_ADMIN_UI_VER \?>/', $cfg) === 1, 'admin_footer 未挂 admin-ui.js');
ok(!str_contains($cfg, 'return confirm('), 'config.php 里仍有原生 confirm');
ok(is_file("$root/assets/admin-ui.css") && is_file("$root/assets/admin-ui.js"), 'admin-ui 资源缺失');

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
