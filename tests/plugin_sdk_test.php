<?php
/**
 * C2 / C3 验收：PluginSDK 与三个官方示例插件
 *
 *   php tests/plugin_sdk_test.php
 *
 * 示例插件是插件开发的活文档，所以这里不只测「能不能跑」，还要测它们
 * 是不是真的示范了正确的写法：过滤器一定 return、动作抛错不影响宿主、
 * 防火墙自己坏掉时 fail-open 而不是把全站埋点丢光。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-sdk-test-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

require_once __DIR__ . '/../lib/PluginSystem.php';
require_once __DIR__ . '/../lib/PluginSDK.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

// ─────────────────────────────────────────────
echo "\n── 1. 配置读写 ──\n";
$p = plugin('t-cfg');
check('未设置时返回默认值', $p->get('nope', 'fallback') === 'fallback');
check('写入成功', $p->set('url', 'https://example.com') === true);
check('读回来一致', $p->get('url') === 'https://example.com');
check('配置落在插件自己的目录', is_file(DATA_DIR . '/plugins/t-cfg/config.json'));
$p->setConfig(['a' => 1, 'b' => 2]);
check('整份覆盖生效', $p->config() === ['a' => 1, 'b' => 2]);
check('不同插件互不干扰', plugin('t-other')->get('a', 'none') === 'none');

echo "\n── 2. 日志 ──\n";
$p->log('第一条');
$p->log('第二条', 'warn');
$lines = $p->tailLog(10);
check('写进了日志', count($lines) === 2, (string)count($lines));
check('带级别标记', strpos($lines[1], '[warn]') !== false, $lines[1]);
check('换行被压平（一条日志一行）', substr_count($p->tailLog(10)[0], "\n") === 0);
$p->log("多行\n消息\r带回车");
check('多行消息不会撑破日志格式', count($p->tailLog(10)) === 3);

echo "\n── 3. 钩子包装：动作抛错不冒泡，且记进本插件日志 ──\n";
$p2 = plugin('t-hook');
$p2->on('t_boom', function () { throw new RuntimeException('插件炸了'); });
$broke = false;
try { PluginSystem::do_action('t_boom'); } catch (\Throwable $e) { $broke = true; }
check('异常没有冒泡到宿主', !$broke);
$log = implode("\n", $p2->tailLog(5));
check('错误记进了插件自己的日志', strpos($log, '插件炸了') !== false, $log);
check('日志里点名了是哪个钩子', strpos($log, 't_boom') !== false);

echo "\n── 4. 钩子包装：过滤器坏掉时保留上一个值，绝不丢数据 ──\n";
$p2->filter('t_filter', function ($v) { throw new RuntimeException('过滤器炸了'); });
$out = PluginSystem::apply_filters('t_filter', ['keep' => 'me']);
check('原值原样返回', $out === ['keep' => 'me'], json_encode($out));

$p2->filter('t_filter2', fn($v) => $v . '-加工过');
check('正常过滤器照常生效', PluginSystem::apply_filters('t_filter2', 'x') === 'x-加工过');

echo "\n── 5. 侧栏菜单 ──\n";
function has_perm(string $p): bool { return $GLOBALS['PERM'] ?? true; }
$p3 = plugin('t-menu');
$p3->menu('我的插件', '', '🧩', 'settings');
$GLOBALS['PERM'] = true;
ob_start(); PluginSystem::do_action('admin_sidebar_menu', 't-menu'); $html = ob_get_clean();
check('渲染出链接', strpos($html, '<a href=') === 0, $html);
check('URL 指向插件自己的页面', strpos($html, '/plugins/t-menu/view.php') !== false, $html);
check('当前页高亮', strpos($html, 'class="active"') !== false, $html);
ob_start(); PluginSystem::do_action('admin_sidebar_menu', '别的页'); $html2 = ob_get_clean();
check('非当前页不高亮', strpos($html2, 'class="active"') === false);
$GLOBALS['PERM'] = false;
ob_start(); PluginSystem::do_action('admin_sidebar_menu', 't-menu'); $html3 = ob_get_clean();
check('没权限就不渲染', trim($html3) === '', $html3);
$GLOBALS['PERM'] = true;

echo "\n── 6. 出站 HTTP 拒绝非法 URL（不会真的发请求）──\n";
$r = plugin('t-http')->httpPost('javascript:alert(1)', ['a' => 1]);
check('非 http(s) 直接拒绝', $r['ok'] === false && $r['error'] === 'URL 不合法');
check('返回结构完整', array_keys($r) === ['ok', 'status', 'body', 'error']);
check('拒绝也写了日志', strpos(implode("\n", plugin('t-http')->tailLog(3)), 'URL 不合法') !== false);

// ─────────────────────────────────────────────
// 示例插件：真加载，真触发
// ─────────────────────────────────────────────
echo "\n── 7. seo-enhancer：filter 补全但不覆盖 ──\n";
require __DIR__ . '/../plugins/seo-enhancer/plugin.php';
$se = plugin('seo-enhancer');
$se->setConfig(['desc_length' => 20, 'title_warn_length' => 10]);

$a = PluginSystem::apply_filters('article_save_before', [
    'title' => '短标题', 'content' => '<p>这是正文内容，用来测试自动摘要是否按长度截取。</p>',
    'tags' => ['增长', 'SEO'],
]);
check('补全了 SEO 描述', !empty($a['seo_description']), json_encode($a['seo_description'] ?? null, 320));
check('描述按配置长度截取', mb_strlen($a['seo_description']) <= 40);
check('描述里没有 HTML 标签', strpos($a['seo_description'], '<') === false);
check('关键词取自标签', $a['seo_keywords'] === '增长,SEO', $a['seo_keywords'] ?? '');

$a2 = PluginSystem::apply_filters('article_save_before', [
    'title' => 'x', 'content' => '正文', 'tags' => ['A'],
    'seo_description' => '编辑亲手写的描述', 'seo_keywords' => '编辑的关键词',
]);
check('已填的描述不被覆盖', $a2['seo_description'] === '编辑亲手写的描述');
check('已填的关键词不被覆盖', $a2['seo_keywords'] === '编辑的关键词');

$long = str_repeat('长', 30);
$a3 = PluginSystem::apply_filters('article_save_before', ['title' => $long, 'content' => '正文']);
check('标题超长只提醒不截断', $a3['title'] === $long);
check('提醒写进了日志', strpos(implode("\n", $se->tailLog(5)), '标题超过') !== false);

$a4 = PluginSystem::apply_filters('article_save_before', ['title' => 'x', 'content' => '']);
check('空正文时不造出空描述', !isset($a4['seo_description']) || $a4['seo_description'] === '');
check('过滤器任何分支都有返回值', is_array($a4));

echo "\n── 8. seo-enhancer：没配 key 时不推送收录 ──\n";
$se->setConfig([]);
$broke = false;
try { PluginSystem::do_action('content_published', 'article', 'a1', ['slug' => 's']); }
catch (\Throwable $e) { $broke = true; }
check('不抛异常', !$broke);
$se->setConfig(['indexnow_key' => 'k']);
try { PluginSystem::do_action('content_published', 'article', 'a1', ['slug' => 's']); }
catch (\Throwable $e) { $broke = true; }
check('配了 key 但没配域名也不炸', !$broke);
check('缺域名时写了告警', strpos(implode("\n", $se->tailLog(5)), '未配置站点域名') !== false);

echo "\n── 9. deal-notifier：没配 webhook 时全程静默 ──\n";
require __DIR__ . '/../plugins/deal-notifier/plugin.php';
$dn = plugin('deal-notifier');
$dn->setConfig([]);
$broke = false;
try {
    PluginSystem::do_action('crm_deal_won', 'a@t.com', ['name' => '甲', 'value' => 100]);
    PluginSystem::do_action('payment_success', 'o1', ['amount' => 10], 'wechat');
    PluginSystem::do_action('payment_refund', 'o1', ['amount' => 10], 10.0, '测试');
    PluginSystem::do_action('crm_leads_bulk_imported', ['created' => 3, 'segment' => 'S'], []);
} catch (\Throwable $e) { $broke = true; }
check('四个动作都不抛', !$broke);
check('没配 webhook 就一条日志都不写', count($dn->tailLog(5)) === 0, implode('|', $dn->tailLog(5)));

echo "\n── 10. deal-notifier：金额门槛 ──\n";
$dn->setConfig(['webhook_url' => 'javascript:bad', 'min_value' => 1000]);
PluginSystem::do_action('crm_deal_won', 'a@t.com', ['name' => '甲', 'value' => 100]);
check('低于门槛不播报', count($dn->tailLog(5)) === 0);
PluginSystem::do_action('crm_deal_won', 'b@t.com', ['name' => '乙', 'value' => 5000]);
check('超过门槛才走发送', count($dn->tailLog(5)) > 0);

echo "\n── 11. event-firewall：默认全放行 ──\n";
require __DIR__ . '/../plugins/event-firewall/plugin.php';
$fw = plugin('event-firewall');
$fw->setConfig([]);
$ev = ['event' => 'page_view', 'user_agent' => 'Googlebot/2.1', 'ip' => '192.168.1.1'];
check('未开启任何规则时原样放行', PluginSystem::apply_filters('cdp_event_received', $ev) === $ev);

echo "\n── 12. event-firewall：拦截规则 ──\n";
$fw->setConfig(['block_bots' => true]);
check('开启后拦爬虫', PluginSystem::apply_filters('cdp_event_received', $ev) === null);
check('真人照常放行',
    PluginSystem::apply_filters('cdp_event_received',
        ['event' => 'page_view', 'user_agent' => 'Mozilla/5.0 (Macintosh)']) !== null);

$fw->setConfig(['internal_ips' => '192.168.,10.0.']);
check('拦内部 IP',
    PluginSystem::apply_filters('cdp_event_received', ['event' => 'x', 'ip' => '192.168.5.9']) === null);
check('外部 IP 放行',
    PluginSystem::apply_filters('cdp_event_received', ['event' => 'x', 'ip' => '8.8.8.8']) !== null);

$fw->setConfig(['blocked_events' => 'heartbeat,ping']);
check('拦噪音事件名',
    PluginSystem::apply_filters('cdp_event_received', ['event' => 'heartbeat']) === null);
check('相近但不相同的事件名不误伤',
    PluginSystem::apply_filters('cdp_event_received', ['event' => 'heartbeat_v2']) !== null);

echo "\n── 13. event-firewall：清洗而非丢弃 ──\n";
$fw->setConfig(['strip_query' => true, 'strip_params' => 'token,code']);
$cleaned = PluginSystem::apply_filters('cdp_event_received',
    ['event' => 'page_view', 'url' => '/pay?order=1&token=SECRET&code=abc']);
check('事件没被丢', is_array($cleaned));
check('敏感参数被剥离', strpos($cleaned['url'], 'token') === false, $cleaned['url']);
check('正常参数保留', strpos($cleaned['url'], 'order=1') !== false, $cleaned['url']);

echo "\n── 14. event-firewall：fail-open（自己坏掉也不丢全站数据）──\n";
$fw->setConfig(['block_bots' => true]);
$weird = PluginSystem::apply_filters('cdp_event_received', 'not-an-array');
check('非数组原样放行', $weird === 'not-an-array');
$noUa = PluginSystem::apply_filters('cdp_event_received', ['event' => 'x']);
check('缺字段的事件不误杀', is_array($noUa));

// 清理
$rm = function (string $d) use (&$rm) {
    foreach (glob($d . '/*') ?: [] as $f) { is_dir($f) ? $rm($f) : @unlink($f); }
    @rmdir($d);
};
$rm(DATA_DIR);

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
