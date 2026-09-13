<?php
/**
 * 全平台发布适配器 契约 —— 适配器注册 / 就绪判定 / 手动降级 / 通用 webhook / 队列重试
 *   php tests/publish_adapters_test.php
 */

$tmp = sys_get_temp_dir() . '/of-pub-' . getmypid();
@mkdir($tmp . '/publish', 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/SocialPublisher.php';
require_once __DIR__ . '/../lib/PublishAdapters.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "全平台发布适配器\n";

$ads = pub_adapters();
check('含 API 适配器', isset($ads['webhook']) && ($ads['webhook']['mode'] ?? '') === 'api');
check('含 Telegram/Discord/Mastodon/WordPress', isset($ads['telegram'], $ads['discord'], $ads['mastodon'], $ads['wordpress']));
check('含手动平台', isset($ads['zhihu']) && ($ads['zhihu']['mode'] ?? '') === 'manual');

// 就绪判定
check('webhook 未配置 → 未就绪', pub_adapter_ready('webhook') === false);
pub_adapter_save('webhook', ['url' => 'https://hooks.example/x']);
check('webhook 配置后 → 就绪', pub_adapter_ready('webhook') === true);
check('手动平台永远可用', pub_adapter_ready('zhihu') === true);

// 未配置的 API 平台 → 明确失败（不假成功）
$r = pub_adapter_publish('telegram', ['title' => 'T']);
check('未配置 API 平台明确报错', empty($r['ok']) && str_contains($r['message'], '未配置'));

// 手动平台 → 生成文案 + 发布入口
$m = pub_adapter_publish('zhihu', ['title' => '标题', 'excerpt' => '摘要', 'slug' => 's1', 'id' => 'a1']);
check('手动平台返回 manual 标记', !empty($m['manual']) && empty($m['ok']));
check('手动平台带变体与发布入口', !empty($m['variant']) && !empty($m['publish_url']));

// SocialPublisher 委托到适配器
$d = SocialPublisher::publish(['title' => '标题', 'excerpt' => '摘要', 'slug' => 's1', 'id' => 'a1'], 'telegram');
check('SocialPublisher 委托适配器', str_contains($d['message'] ?? '', '未配置') || !empty($d['ok']));

// 队列重试字段（结构性）
$sp = file_get_contents(__DIR__ . '/../lib/SocialPublisher.php');
check('队列处理含重试退避', strpos($sp, 'next_retry_at') !== false && strpos($sp, "attempts") !== false);
check('SocialPublisher 委托适配器', strpos($sp, 'pub_adapter_publish(') !== false);

// 结构守卫
$api = file_get_contents(__DIR__ . '/../api/publish-adapters.php');
check('适配器API要登录', strpos($api, 'require_login(') !== false);
check('适配器API要权限', strpos($api, "require_perm('channels')") !== false);
$page = file_get_contents(__DIR__ . '/../admin/publish.php');
check('分发页含适配器面板', strpos($page, '平台适配器与凭据') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
