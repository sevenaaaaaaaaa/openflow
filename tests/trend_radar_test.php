<?php
/**
 * 热点雷达 契约 —— 去重键 / 聚类兜底 / 转选题 / 结构
 *   php tests/trend_radar_test.php
 */

$tmp = sys_get_temp_dir() . '/of-trend-' . getmypid();
@mkdir($tmp . '/trends', 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/TrendRadar.php';
require_once __DIR__ . '/../lib/GeoSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "热点雷达\n";

// 去重键：忽略 query 与大小写
check('去重键忽略 query', trend_seen_key('https://a.com/x?utm=1') === trend_seen_key('https://a.com/x'));
check('去重键区分路径', trend_seen_key('https://a.com/x') !== trend_seen_key('https://a.com/y'));

// 设置归一
trend_save_settings(['keywords' => "AI, Agent\n growth ", 'rss' => ["https://f.com/rss", ''], 'min_points' => 'abc', 'max_age_days' => 99, 'enabled' => 1]);
$s = trend_settings();
check('关键词清理', $s['keywords'] === ['AI', 'Agent', 'growth']);
check('RSS 清理空值', $s['rss'] === ['https://f.com/rss']);
check('数值钳位', $s['min_points'] === 0 && $s['max_age_days'] === 30);

// 聚类兜底（AiCenter 未加载 → 按来源取 Top）
$items = [
    ['title' => 'HN 热门一', 'url' => 'https://hn/1', 'source' => 'HackerNews', 'points' => 300, 'comments' => 10, 'published' => '2026-09-10'],
    ['title' => 'HN 热门二', 'url' => 'https://hn/2', 'source' => 'HackerNews', 'points' => 120, 'comments' => 3, 'published' => '2026-09-11'],
    ['title' => '新仓库', 'url' => 'https://gh/1', 'source' => 'GitHub', 'points' => 800, 'comments' => 0, 'published' => '2026-09-12'],
];
$clusters = trend_cluster($items);
check('聚类产出话题', count($clusters) >= 2);
check('话题带热度与来源', isset($clusters[0]['heat'], $clusters[0]['item_urls']));
check('热度在 0-100', $clusters[0]['heat'] >= 0 && $clusters[0]['heat'] <= 100);

// 转选题 → 进 GEO 选题库
$before = count(geo_get_topics());
$r = trend_promote('AI Agent 新趋势', '上手实测', 'HN 热度 300');
check('转选题成功', !empty($r['ok']));
$topics = geo_get_topics();
check('GEO 选题库新增一条', count($topics) === $before + 1);
check('选题内容正确', end($topics)['topic'] === 'AI Agent 新趋势' && (end($topics)['source'] ?? '') === 'radar');

// 结构守卫
$api = file_get_contents(__DIR__ . '/../api/trend-radar.php');
check('雷达API要登录', strpos($api, 'require_login(') !== false);
check('雷达API要权限', strpos($api, "require_perm('settings')") !== false);
$cron = file_get_contents(__DIR__ . '/../api/cron.php');
check('cron 每日跑雷达', strpos($cron, 'trend_run(') !== false);
$nav = file_get_contents(__DIR__ . '/../includes/admin-nav.php');
check('导航含热点雷达', strpos($nav, 'trend-radar') !== false);
check('雷达页存在', is_file(__DIR__ . '/../admin/trend-radar.php'));

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
