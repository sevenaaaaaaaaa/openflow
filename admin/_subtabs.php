<?php
/**
 * 父页子 tab 助手（B3）
 *
 * 把浅 CRUD 页归并到父页时用的极简 tab 机制。与 seo-center/content-hub
 * 的中心页机制同源，区别是父页自己的正文也占一个 tab（'self'）。
 *
 * 用法（父页内）：
 *   $SUBTABS = ['self' => ['页面列表', '', 'pages'],
 *               'cats' => ['分类',     'page-categories.php', 'pages']];
 *   $t = of_subtab_begin($SUBTABS);
 *   if ($t === 'self'):
 *       ... 父页原有正文 ...
 *   else: of_subtab_include($SUBTABS, $t); endif;
 *
 * tab 定义：key => [标题, 子页文件（self 为空）, 所需权限]
 */

if (!function_exists('of_subtab_begin')) {

/**
 * 过滤权限、确定当前 tab、渲染 tab 条。
 * @return string 当前 tab 的 key
 */
function of_subtab_begin(array $tabs, string $param = 'sub'): string {
    $allowed = [];
    foreach ($tabs as $k => $t) {
        $perm = $t[2] ?? '';
        if ($perm === '' || !function_exists('has_perm') || has_perm($perm)) $allowed[$k] = $t;
    }
    if (!$allowed) return 'self';

    $cur = $_GET[$param] ?? 'self';
    if (!isset($allowed[$cur])) $cur = array_key_first($allowed);

    // 保留当前查询串里除 tab 参数外的其它参数（父页可能带 ?page=xxx 之类）
    $qs = $_GET;
    unset($qs[$param]);

    echo '<div class="of-subtabs">';
    foreach ($allowed as $k => $t) {
        $q = $qs; $q[$param] = $k;
        $href = '?' . http_build_query($q);
        echo '<a class="of-subtab' . ($k === $cur ? ' on' : '') . '" href="' . htmlspecialchars($href) . '">'
           . htmlspecialchars($t[0]) . '</a>';
    }
    echo '</div>';
    of_subtab_style();
    return $cur;
}

/** 引入子页正文（子页已带 OF_EMBED 守卫，不会输出自己的外壳） */
function of_subtab_include(array $tabs, string $key): void {
    $file = $tabs[$key][1] ?? '';
    if ($file === '') return;
    $path = __DIR__ . '/' . $file;
    if (is_file($path)) {
        if (!defined('OF_EMBED')) define('OF_EMBED', 1);
        include $path;
    } else {
        echo function_exists('msg')
            ? msg('error', '子页面缺失：' . htmlspecialchars($file))
            : '<p>子页面缺失</p>';
    }
}

/** tab 条样式，只输出一次 */
function of_subtab_style(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    echo '<style>
.of-subtabs{display:flex;flex-wrap:wrap;gap:4px;margin:14px 0 20px;border-bottom:1px solid var(--border)}
.of-subtab{padding:8px 14px;border-radius:8px 8px 0 0;font-size:13px;font-weight:600;color:var(--text-2);
  text-decoration:none;border:1px solid transparent;border-bottom:none;position:relative;top:1px;transition:.15s}
.of-subtab:hover{background:var(--bg-2);color:var(--text-1)}
.of-subtab.on{background:var(--bg-1);color:var(--primary);border-color:var(--border);border-bottom:1px solid var(--bg-1)}
.of-subtabs + * > h1:first-child{display:none}
</style>';
}

}
