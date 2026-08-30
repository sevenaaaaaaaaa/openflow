<?php
/**
 * 全面质检 —— 主动找问题，不只是确认既有工作
 *
 *   php tests/qa_full.php
 *
 * 检查项覆盖本轮改动可能引入的所有失效面，以及若干与本轮无关
 * 但会影响上线的既有隐患。发现即报，不隐瞒。
 */

$root = dirname(__DIR__);
$issues = [];   // 必须修
$warns  = [];   // 需留意
$oks    = 0;

function ok(string $m)    { global $oks; $oks++; }
function bad(string $m)   { global $issues; $issues[] = $m; }
function warn(string $m)  { global $warns; $warns[] = $m; }
function t(string $name, bool $pass, string $detail = '', bool $fatal = true) {
    if ($pass) { ok($name); return; }
    $msg = $name . ($detail ? "：{$detail}" : '');
    $fatal ? bad($msg) : warn($msg);
}

echo "OpenFlow 全面质检\n" . str_repeat('=', 52) . "\n";

// ─────────────────────────────────────────────
echo "\n[1/8] PHP 语法\n";
$phpFiles = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) !== '.php') continue;
    if (strpos($p, '/vendor/') !== false || strpos($p, '/.git/') !== false) continue;
    $phpFiles[] = $p;
}
$syntaxBad = [];
foreach ($phpFiles as $p) {
    exec('php -l ' . escapeshellarg($p) . ' 2>&1', $o, $rc); $o = [];
    if ($rc !== 0) $syntaxBad[] = str_replace($root . '/', '', $p);
}
t('全部 ' . count($phpFiles) . ' 个 PHP 文件语法通过', empty($syntaxBad), implode(', ', $syntaxBad));

// ─────────────────────────────────────────────
echo "[2/8] 单元测试\n";
foreach (glob("{$root}/tests/*_test.php") as $tf) {
    exec('php ' . escapeshellarg($tf) . ' 2>&1', $o, $rc);
    $last = trim(end($o) ?: '');
    t('测试 ' . basename($tf), $rc === 0, $last);
    $o = [];
}

// ─────────────────────────────────────────────
echo "[3/8] 钩子完整性\n";
$hookCalls = [];
foreach (['lib', 'admin', 'api'] as $dir) {
    foreach (glob("{$root}/{$dir}/*.php") as $f) {
        if (preg_match_all("/(?:do_action|apply_filters)\(\s*'([a-z_]+)'/", file_get_contents($f), $m)) {
            foreach ($m[1] as $h) $hookCalls[$h] = true;
        }
    }
}
$hookNames = array_keys($hookCalls);
sort($hookNames);
t('钩子数量达标（≥30）', count($hookNames) >= 30, '实际 ' . count($hookNames));

// 文档与代码一致性
$doc = @file_get_contents("{$root}/docs/HOOKS.md") ?: '';
$undocumented = [];
foreach ($hookNames as $h) {
    if (strpos($doc, "`{$h}`") === false) $undocumented[] = $h;
}
t('每个钩子都有文档', empty($undocumented), '缺文档：' . implode(', ', $undocumented));

// 文档里写了但代码里没有的。
// 「尚无插入点」一节是刻意记录未实现的钩子，不参与对表，只在结尾提示。
$implDoc = explode('## 尚无插入点', $doc)[0];
preg_match_all('/^\| `([a-z_]+)` \|/m', $implDoc, $dm);
$ghost = array_diff($dm[1] ?? [], $hookNames);
t('文档无幽灵钩子', empty($ghost), '文档有代码无：' . implode(', ', $ghost));

// 反过来：「尚无插入点」里的钩子如果已经实现了，文档就该挪上去
preg_match_all('/^\| `([a-z_]+)` \|/m', substr($doc, strlen($implDoc)), $pm);
$staleUnimpl = array_intersect($pm[1] ?? [], $hookNames);
t('「尚无插入点」列表未过期', empty($staleUnimpl), '已实现却仍列为未实现：' . implode(', ', $staleUnimpl));

// ─────────────────────────────────────────────
echo "[4/8] 旁路契约（钩子不得打断业务）\n";
$ps = file_get_contents("{$root}/lib/PluginSystem.php");
t('do_action 包了 try/catch', (bool)preg_match('/do_action.*?\{.*?try \{.*?catch/s', $ps));
t('apply_filters 包了 try/catch', (bool)preg_match('/apply_filters.*?\{.*?try \{.*?catch/s', $ps));

// 调用 PluginSystem:: 的地方不得触发 fatal。两种方式都算安全：
//   a) 就近的 class_exists('PluginSystem') 守卫；
//   b) 文件（或它无条件 require 的 config.php）已经无条件 require 了 PluginSystem.php。
// 后者是老代码的既有写法，类必定已加载，不算隐患。
$requiresPluginDirect = function (string $file): bool {
    $s = @file_get_contents($file) ?: '';
    return strpos($s, "require_once __DIR__ . '/../lib/PluginSystem.php'") !== false
        || strpos($s, "require_once __DIR__ . '/PluginSystem.php'") !== false;
};
// admin/config.php 顶层无条件 require 了 PluginSystem，凡是 require 它的页面都安全
$cfgLoadsPlugin = $requiresPluginDirect("{$root}/admin/config.php");
$loadsPlugin = function (string $file) use ($requiresPluginDirect, $cfgLoadsPlugin): bool {
    if ($requiresPluginDirect($file)) return true;
    if (!$cfgLoadsPlugin) return false;
    $s = @file_get_contents($file) ?: '';
    return strpos($s, "require_once __DIR__ . '/config.php'") !== false
        || strpos($s, "require_once __DIR__ . '/../admin/config.php'") !== false;
};
$unguarded = [];
foreach (['lib', 'admin', 'api'] as $dir) {
    foreach (glob("{$root}/{$dir}/*.php") as $f) {
        if (basename($f) === 'PluginSystem.php') continue;
        $lines = explode("\n", file_get_contents($f));
        $safeByRequire = $loadsPlugin($f);
        foreach ($lines as $i => $line) {
            if (!preg_match('/PluginSystem::(do_action|apply_filters)\(/', $line)) continue;
            if ($safeByRequire) continue;
            $ctx = implode("\n", array_slice($lines, max(0, $i - 4), 5));
            if (strpos($ctx, "class_exists('PluginSystem')") === false) {
                $unguarded[] = str_replace($root . '/', '', $f) . ':' . ($i + 1);
            }
        }
    }
}
t('钩子调用不会 fatal（守卫或已 require）', empty($unguarded), implode(' ', array_slice($unguarded, 0, 6)));

// ─────────────────────────────────────────────
echo "[5/8] 后台合并：301 / 侧栏 / 守卫\n";
$ht  = file_get_contents("{$root}/.htaccess");
$cfg = file_get_contents("{$root}/admin/config.php");

$merged = ['seo','seo-tools','seo-batch','seo-console','structured-data','image-seo','redirects',
           'articles','pages-list','downloads','podcasts',
           'page-categories','tags','payment-settings','mail-settings','footer-links','storage','activity'];
$noRedirect = []; $stillInSidebar = [];
foreach ($merged as $slug) {
    if (!preg_match('#\^xmp/' . preg_quote($slug, '#') . '/\?\$#', $ht)) $noRedirect[] = $slug;
    if (strpos($cfg, 'href="/xmp/' . $slug . '"') !== false) $stillInSidebar[] = $slug;
}
t('18 个被合并页都有 301', empty($noRedirect), '缺：' . implode(', ', $noRedirect));
t('18 个被合并页都已撤出侧栏', empty($stillInSidebar), '残留：' . implode(', ', $stillInSidebar));

// 301 目标必须真实存在
$badTarget = [];
if (preg_match_all('#RewriteRule \^xmp/[a-z-]+/\?\$\s+/xmp/([a-z-]+)#', $ht, $rm)) {
    foreach (array_unique($rm[1]) as $target) {
        if (!is_file("{$root}/admin/{$target}.php")) $badTarget[] = $target;
    }
}
t('301 目标页面都存在', empty($badTarget), '缺失：' . implode(', ', $badTarget));

// 守卫配平：if 不能多于 endif
$guardBad = [];
foreach (glob("{$root}/admin/*.php") as $f) {
    $s = file_get_contents($f);
    if (strpos($s, 'OF_EMBED') === false) continue;
    $ifs = preg_match_all("/if \(!defined\('OF_EMBED'\)\): \?>/", $s);
    $end = preg_match_all("/<\?php endif; \?>|admin_footer\(\); endif; \?>/", $s);
    if ($ifs > $end) $guardBad[] = basename($f) . " (if={$ifs} endif={$end})";
}
t('OF_EMBED 守卫全部配平', empty($guardBad), implode(', ', $guardBad));

// 被 include 的子页里不能有 href="?..." 这种相对链接：
// 它会解析到 hub 自身并丢掉 tab/sub，一点就跳回默认标签页。必须走 of_hub_url()。
$relLinks = [];
foreach ($merged as $slug) {
    $f = "{$root}/admin/{$slug}.php";
    if (!is_file($f)) continue;
    foreach (explode("\n", file_get_contents($f)) as $i => $line) {
        if (preg_match('/href="\?/', $line)) $relLinks[] = "{$slug}.php:" . ($i + 1);
    }
}
t('子页无丢失 tab 的相对链接', empty($relLinks), implode(' ', array_slice($relLinks, 0, 8)));

// 子页跳转不能指向已被 301 的旧地址（会丢查询参数，如 ?trash=1）
$staleRedirect = [];
foreach ($merged as $slug) {
    foreach (glob("{$root}/admin/*.php") as $f) {
        if (preg_match_all("#Location: /xmp/" . preg_quote($slug, '#') . "[?'\"]#", file_get_contents($f), $mm)) {
            $staleRedirect[] = basename($f) . '→' . $slug;
        }
    }
}
t('无跳向已合并旧地址的重定向', empty($staleRedirect), implode(' ', array_unique($staleRedirect)));

t('of_hub_url() 已提供', strpos($cfg, 'function of_hub_url') !== false);

// ─────────────────────────────────────────────
echo "[6/8] 侧栏链接有效性（全量）\n";
preg_match_all('#href="/xmp/([a-z0-9-]+)"#', $cfg, $sm);
$dead = [];
foreach (array_unique($sm[1]) as $slug) {
    if (!is_file("{$root}/admin/{$slug}.php")) $dead[] = $slug;
}
t('侧栏每个入口都有对应文件', empty($dead), '死链：' . implode(', ', $dead));

// ─────────────────────────────────────────────
echo "[7/8] 数据安全\n";
// 本轮所有改动不得触碰 data/ 下的业务数据文件
exec('cd ' . escapeshellarg($root) . ' && git diff --name-only origin/main 2>/dev/null', $changed);
$dataTouched = array_filter($changed, fn($f) => strpos($f, 'data/') === 0);
t('未改动 data/ 下任何文件', empty($dataTouched), implode(', ', $dataTouched));

// 退款金额回收必须与支付发放一一对应
$shop = file_get_contents("{$root}/lib/ShopSystem.php");
$payGrants = preg_match_all('/balance = balance \+ \?/', $shop);
$refundBacks = preg_match_all('/balance = balance - \?/', $shop);
t('退款回收笔数 = 支付发放笔数', $payGrants === $refundBacks && $payGrants > 0,
  "发放 {$payGrants} 处 / 回收 {$refundBacks} 处");
t('退款有状态前置校验', strpos($shop, "!== 'paid'") !== false);
t('退款防重复', strpos($shop, "=== 'refunded'") !== false);

// ─────────────────────────────────────────────
echo "[8/8] 与任务清单对表\n";
$tasks = @file_get_contents("{$root}/docs/TASKS.md") ?: '';
t('TASKS.md 存在', $tasks !== '');
$done = [
    'A1 CRM→FlowSystem'   => strpos(file_get_contents("{$root}/lib/CrmSystem.php"), 'flow_crm_stage_change') !== false,
    'A2 画布读 CRM 字段'  => strpos(file_get_contents("{$root}/lib/CanvasSystem.php"), 'canvas_resolve_field') !== false,
    'B1 SEO 中心'         => is_file("{$root}/admin/seo-center.php"),
    'B2 内容中心'         => is_file("{$root}/admin/content-hub.php"),
    'B3 浅 CRUD 合并'     => is_file("{$root}/admin/_subtabs.php"),
    'C1 30+ hooks'        => count($hookNames) >= 30,
    '退款功能'            => strpos($shop, 'function shop_refund_order') !== false,
];
foreach ($done as $k => $v) t("已完成：{$k}", $v);

$crmSrc = @file_get_contents("{$root}/lib/CrmSystem.php") ?: '';
$dbSrc  = @file_get_contents("{$root}/lib/Database.php") ?: '';
$done['A3 分群→CRM 批量建线索'] = strpos($crmSrc, 'crm_bulk_create_leads') !== false;
$done['C2 PluginSDK']          = is_file("{$root}/lib/PluginSDK.php");
$done['C3 官方示例插件（3 个）'] = is_dir("{$root}/plugins/seo-enhancer")
                                && is_dir("{$root}/plugins/deal-notifier")
                                && is_dir("{$root}/plugins/event-firewall");
$done['D1 events 索引（替代 Redis 方案）'] = strpos($dbSrc, 'idx_events_event_created') !== false
                                          && is_file("{$root}/docs/PERFORMANCE.md");
foreach (['A3 分群→CRM 批量建线索', 'C2 PluginSDK', 'C3 官方示例插件（3 个）',
          'D1 events 索引（替代 Redis 方案）'] as $k) t("已完成：{$k}", $done[$k]);

$notDone = [];

// ── 批量建线索不能退化成逐条读写（O(n²)，5000 人的分群必然超时）──
// 这里只做「函数体内 crm_get/crm_save 各恰好一次」的静态断言；
// 真正的行为证明在 tests/bulk_leads_test.php（它数了实际读写次数）。
$bulkBody = '';
if (preg_match('/function crm_bulk_create_leads.*?\n\}/s', $crmSrc, $bm)) $bulkBody = $bm[0];
t('批量建线索函数体内只读一次 crm.json', substr_count($bulkBody, 'crm_get()') === 1,
  '实际 ' . substr_count($bulkBody, 'crm_get()') . ' 次');
t('批量建线索函数体内只写一次 crm.json', substr_count($bulkBody, 'crm_save(') === 1,
  '实际 ' . substr_count($bulkBody, 'crm_save(') . ' 次');

// ── 示例插件是活文档，必须是能被 PluginSystem 认出来的完整插件 ──
$badPlugin = [];
foreach (['seo-enhancer', 'deal-notifier', 'event-firewall'] as $pid) {
    $dir = "{$root}/plugins/{$pid}";
    if (!is_file("{$dir}/plugin.json")) { $badPlugin[] = "{$pid} 缺 plugin.json"; continue; }
    if (!is_file("{$dir}/plugin.php"))  { $badPlugin[] = "{$pid} 缺 plugin.php"; continue; }
    $meta = json_decode((string)file_get_contents("{$dir}/plugin.json"), true);
    if (($meta['id'] ?? '') !== $pid) $badPlugin[] = "{$pid} 的 plugin.json id 对不上";
    if (strpos((string)file_get_contents("{$dir}/plugin.php"), 'PluginSDK.php') === false) {
        $badPlugin[] = "{$pid} 没用 SDK（示例应当示范 SDK 用法）";
    }
}
t('三个示例插件结构完整', empty($badPlugin), implode('；', $badPlugin));

// ── 拿数据的钩子必须 fail-open：埋点防火墙自己坏了不能把全站事件丢光 ──
$fw = @file_get_contents("{$root}/plugins/event-firewall/plugin.php") ?: '';
t('埋点防火墙 fail-open', strpos($fw, 'catch') !== false && preg_match('/catch[^}]*return \$entry/s', $fw));

// ─────────────────────────────────────────────
echo "\n" . str_repeat('=', 52) . "\n";
echo "通过 {$oks} 项\n";

if ($warns) {
    echo "\n⚠ 需留意 " . count($warns) . " 项：\n";
    foreach ($warns as $w) echo "   · {$w}\n";
}
if ($issues) {
    echo "\n✘ 必须修复 " . count($issues) . " 项：\n";
    foreach ($issues as $i) echo "   · {$i}\n";
}

echo "\n── 任务清单进度 ──\n";
foreach ($done as $k => $v)    echo '   ' . ($v ? '✓' : '✗') . " {$k}\n";
foreach ($notDone as $k => $v) echo '   ' . ($v ? '✓' : '○') . " {$k}" . ($v ? '' : '（未开始）') . "\n";

echo "\n";
exit($issues ? 1 : 0);
