<?php
/**
 * 本地开发路由器 — php 内置服务器专用
 *
 *   php -S 127.0.0.1:8080 router.php
 *
 * 作用：php -S 不读 .htaccess，这个脚本按**相同顺序**镜像 .htaccess 的重写规则，
 * 让美化 URL（/articles、/xmp/today、/article/{slug}…）在本地与线上行为一致。
 * 生产环境不用它（Apache + .htaccess / Nginx + deploy/nginx.site.conf）。
 * 与 .htaccess 的差异：不镜像历史 301 归并规则（/xmp/tags 等旧地址直接 404）。
 */

$root = __DIR__;
$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);

// ── 前置拒绝：data/ 一律 403（与 .htaccess 第一条一致）──
if (preg_match('~^/data(/|$)~', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

// ── 语言前缀 /growth 前缀剥离 ──
// 剥离后若直接命中真实文件（如 /en/feed.php → /feed.php），必须在这里就地 require：
// 走到末尾的 return false 会让 php -S 按原始 URI（带前缀）找文件而回落 404/首页。
if (preg_match('~^/(en|ja|zh-CN|zh-TW|ru|fr)/(.*)$~', $path, $m)) {   // 白名单与 .htaccess / settings.json multilang 保持一致
    $stripped = '/' . $m[2];
    if (is_file($root . urldecode($stripped))) {
        chdir($root);
        require $root . urldecode($stripped);
        return true;
    }
    $path = $stripped;
}
if (preg_match('~^/growth/(.*)$~', $path, $m)) $path = '/' . $m[1];

// ── 规则表：顺序即优先级，与 .htaccess 保持同序 ──
// target 为 false 表示纯放行（交给后面的文件直查）
$rules = [
    '~^/([a-f0-9]{32})\.txt$~'          => 'api/indexnow-key.php?key=$1',

    '~^/login/?$~'                       => 'admin/login.php',
    '~^/xmp/?$~'                         => 'admin/index.php',
    '~^/xmp/plugin/([a-z0-9-]+)/?$~'     => 'admin/plugin-page.php?plugin=$1',
    '~^/xmp/([a-z0-9-]+)/?$~'            => 'admin/$1.php',
    '~^/xmp/(.+)$~'                      => 'admin/$1',

    // 主站美化 URL（必须在「已存在文件放行」之前——/docs 与真实 docs/ 目录同名）
    '~^/docs/?$~'                        => 'docs.php',
    '~^/academy/?$~'                     => 'academy.php',
    '~^/community/?$~'                   => 'community.php',
    '~^/pricing/?$~'                     => 'pricing.php',
    '~^/help/([a-z0-9-]+)/?$~'           => 'help-article.php?slug=$1',
    '~^/help/?$~'                        => 'help.php',
    '~^/tools/?$~'                       => 'tools.php',
    '~^/marketplace/?$~'                 => 'marketplace.php',
    '~^/articles/?$~'                    => 'articles.php',
    '~^/courses/?$~'                     => 'courses.php',
    '~^/about/?$~'                       => 'about.php',
    '~^/developers/?$~'                  => 'developers.php',
    '~^/product/openflow/?$~'            => 'product.php',
    '~^/product/(mflow|webs-flow|userloop|inflow|payflow|learnflow)/?$~' => 'front-builder.php?slug=$1',
    '~^/product/?$~'                     => 'hub-products.php',
    '~^/capability/openflow/?$~'         => 'capability.php',
    '~^/capability/([a-z0-9_-]+)/?$~'    => 'hub-capabilities.php?cap=$1',
    '~^/capability/?$~'                  => 'hub-capabilities.php',
    '~^/changelog/?$~'                   => 'changelog.php',
    '~^/s/([a-f0-9]{16,64})/?$~'         => 'share.php?t=$1',
    '~^/downloads/([^/]+)/?$~'           => 'download.php?slug=$1',
    '~^/downloads/?$~'                   => 'downloads.php',
    '~^/download/([^/]+)/?$~'            => 'download.php?slug=$1',
    '~^/podcasts/?$~'                    => 'podcasts.php',
    '~^/survey/?$~'                      => 'survey.php',
    '~^/reviews/?$~'                     => 'reviews.php',
    '~^/topics/?$~'                      => 'topics.php',
    '~^/newsletter/?$~'                  => 'newsletter.php',
    '~^/cart/?$~'                        => 'cart.php',
    '~^/consultation/?$~'                => 'consultation.php',
    '~^/enterprise/?$~'                  => 'enterprise.php',
    '~^/live/?$~'                        => 'live.php',
    '~^/live-overlay/?$~'                => 'live-overlay.php',
    '~^/messages/?$~'                    => 'messages.php',
    '~^/nps/?$~'                         => 'nps.php',
    '~^/account/?$~'                     => 'member.php',
    '~^/member/?$~'                      => 'member.php',
    '~^/register/?$~'                    => 'member.php?view=register',
    '~^/courses/([^/]+)$~'               => 'course-player.php?slug=$1',
    '~^/course/([^/]+)$~'                => 'course-player.php?slug=$1',
    '~^/category/([a-z]+)/([a-z0-9_-]+)/?$~' => 'category.php?section=$1&subkey=$2',
    '~^/community-post/(.+)$~'           => 'community-post.php?id=$1',
    '~^(skill|plugin|theme)/([^/]+)/?$~' => 'asset.php?type=$1&id=$2',
    '~^/deck/([a-z0-9_]+)/?$~'           => 'deck.php?id=$1',
    '~^/order-success/?$~'               => 'order-success.php',
    '~^/receipt/?$~'                     => 'receipt.php',
    '~^/certificate/([A-Za-z0-9-]+)/?$~' => 'certificate.php?no=$1',
    '~^/certificate/?$~'                 => 'certificate.php',

    // API（无后缀形式）
    '~^/api/plugin/([a-z0-9-]+)(?:/(.*))?$~' => 'api/plugin.php?plugin=$1&path=$2',
    '~^/api/v1/docs\.json$~'             => 'api/v1/docs.json.php',
    '~^/api/v1/docs$~'                   => 'api/v1/docs.php',
    '~^/api/([a-z0-9_-]+)/?$~'           => 'api/$1.php',
];

$target = null;
foreach ($rules as $pat => $tpl) {
    if (preg_match($pat, $path, $m)) {
        $target = preg_replace_callback('/\$(\d)/', fn($g) => $m[(int)$g[1]] ?? '', $tpl);
        break;
    }
}

if ($target === null) {
    // ── 已存在文件/目录直接放行（php -S 自己服务，含 .php 与静态资源）──
    // robots.txt / llms.txt 是真实文件则同样直出（与 .htaccess 的 -f 优先一致）
    $file = $root . urldecode($path);
    if ($path !== '/' && (is_file($file) || is_dir($file))) return false;

    // ── 动态 SEO / 内容路由 ──
    $late = [
        '~^/robots\.txt$~'      => 'robots.php',
        '~^/sitemap\.xml$~'     => 'sitemap.php',
        '~^/llms\.txt$~'        => 'llms.php',
        '~^(feed|rss)(\.xml)?$~' => 'feed.php',
        '~^/articles/(.+)$~'    => 'article.php?slug=$1',
        '~^/article/(.+)$~'     => 'article.php?slug=$1',
        '~^/authors/(.+)$~'     => 'author.php?slug=$1',
        '~^/author/(.+)$~'      => 'author.php?name=$1',
        '~^/topic/(.+)$~'       => 'topics.php?slug=$1',
        '~^/topics/(.+)$~'      => 'topics.php?slug=$1',
        '~^/navigation/submit/?$~' => 'nav-submit.php',
        '~^/navigation/compare/?$~' => 'navigation-compare.php',
        '~^/navigation/([^/]+)/?$~' => 'navigation-site.php?site=$1',
        '~^/navigation/?$~'     => 'navigation.php',
        '~^/navigation-site/?$~' => 'navigation-site.php',
        '~^/event/(.+)$~'       => 'event.php?slug=$1',
        '~^/events/(.+)$~'      => 'event.php?slug=$1',
        '~^/events/?$~'         => 'events.php',
        '~^/lp/(.+)$~'          => 'landing.php?slug=$1',
        '~^/b/(.+)$~'           => 'front-builder.php?slug=$1',
        '~^/c/(.+)$~'           => 'collab.php?t=$1',
        '~^/c/?$~'              => 'collab.php',
        '~^/search$~'           => 'search.php',
        '~^/thank-you/?$~'      => 'thank-you.php',
        '~^/activate/?$~'       => 'activate.php',
        '~^/shop/?$~'           => 'shop.php',
        '~^/pay/?$~'            => 'pay.php',
        // 单段 slug 兜底：Landing 聚合页（.htaccess 最后一条）
        '~^/([a-z0-9][a-z0-9-]*)/?$~' => 'landing.php?slug=$1',
    ];
    foreach ($late as $pat => $tpl) {
        if (preg_match($pat, $path, $m)) {
            $target = preg_replace_callback('/\$(\d)/', fn($g) => $m[(int)$g[1]] ?? '', $tpl);
            break;
        }
    }
}

// 首页
if ($target === null && ($path === '/' || $path === '')) $target = 'index.php';

// 兜底 404（404.php 自带 redirects.json 拦截与友好页）
if ($target === null) {
    http_response_code(404);
    require $root . '/404.php';
    return true;
}

// 拆出 query 并合并进 $_GET（QSA 语义：保留原 query 再叠加）
$qpos  = strpos($target, '?');
$query = $qpos === false ? '' : substr($target, $qpos + 1);
$file  = $qpos === false ? $target : substr($target, 0, $qpos);
if ($query !== '') {
    parse_str($query, $add);
    $_GET = array_merge($_GET, $add);
}
$_SERVER['QUERY_STRING'] = http_build_query($_GET);

if (!is_file($root . '/' . $file)) {
    http_response_code(404);
    require $root . '/404.php';
    return true;
}

chdir($root);
require $root . '/' . $file;
