<?php
declare(strict_types=1);
/**
 * 后台页面清单（单一来源）
 *
 * 【为什么要单独抽出来】"后台有多少个页面"这个数字被至少三个地方用到：
 * 可用性审计、使用埋点（算"从没被打开过的页"）、对外文档。此前各算各的，
 * 于是同一周里出现过 226 / 222 / 192 三个版本——数字一旦能各说各话，
 * 基于它的结论就不可信了。这里给出唯一口径，其它地方一律引用。
 *
 * 口径（三类文件，只有第一类算"真实页面"）：
 *   1. 真实页面   admin/<name>.php 且调用了 admin_header()/admin_footer()
 *   2. 301 别名   .htaccess 里 ^xmp/<name>/?$ → [R=301] 的旧地址（同一屏的第二个地址）
 *   3. 片段/工具  _ 开头、config/login/logout、以及不渲染页面的被 include 文件
 */

/**
 * @return array{pages: array<string,string>, alias_301: array<string,string>,
 *               fragments: list<string>, root: string}
 */
function admin_page_inventory(?string $root = null): array
{
    static $cache = [];
    $root = $root !== null ? rtrim($root, '/') : dirname(__DIR__);
    if (isset($cache[$root])) return $cache[$root];

    // 301 别名：从 .htaccess 读，避免另维护一份名单造成漂移
    $alias = [];
    $ht = (string) @file_get_contents($root . '/.htaccess');
    if ($ht !== '' && preg_match_all('#^RewriteRule\s+\^xmp/([a-z0-9-]+)/\?\$\s+(\S+)\s+\[R=301#mi', $ht, $m)) {
        foreach ($m[1] as $i => $a) $alias[(string) $a] = (string) ($m[2][$i] ?? '');
    }

    $pages = [];
    $fragments = [];
    foreach (glob($root . '/admin/*.php') ?: [] as $f) {
        $base = basename($f, '.php');
        if (str_starts_with($base, '_')) { $fragments[] = $base; continue; }
        if (in_array($base, ['config', 'login', 'logout'], true)) { $fragments[] = $base; continue; }
        if (isset($alias[$base])) continue;                       // 别名单独归类
        $src = (string) @file_get_contents($f);
        if (!str_contains($src, 'admin_header(') && !str_contains($src, 'admin_footer(')) {
            $fragments[] = $base; continue;                        // 被 include 的片段，不是页面
        }
        $pages[$base] = '/xmp/' . ($base === 'index' ? 'dashboard' : $base);
    }
    ksort($pages);
    sort($fragments);

    return $cache[$root] = [
        'pages' => $pages,
        'alias_301' => $alias,
        'fragments' => $fragments,
        'root' => $root,
    ];
}

/** 只要页面清单（slug => /xmp/路径） @return array<string,string> */
function admin_pages(?string $root = null): array
{
    return admin_page_inventory($root)['pages'];
}

/** 把 /xmp/foo 这样的请求路径还原成页面 slug（找不到返回 ''） */
function admin_page_slug_from_path(string $path, ?string $root = null): string
{
    $p = (string) parse_url($path, PHP_URL_PATH);
    if ($p === '') return '';
    $base = basename(rtrim($p, '/'));
    if ($base === '') return '';                  // '/' 是前台首页，不是后台
    $inv = admin_page_inventory($root);
    // /xmp 与 /xmp/ 都是后台首页（admin/index.php）
    if ($base === 'xmp') return isset($inv['pages']['index']) ? 'index' : '';
    $base = preg_replace('/\.php$/', '', $base) ?? $base;
    if (!preg_match('/^[a-z0-9-]{1,64}$/i', $base)) return '';
    $base = strtolower($base);
    if (isset($inv['pages'][$base])) return $base;
    if (isset($inv['alias_301'][$base])) return '';   // 旧地址会 301，不重复计数
    return '';
}
