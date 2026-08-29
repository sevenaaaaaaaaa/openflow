<?php
/**
 * C1 第二刀验收：内容 / 支付 / 评论钩子
 *
 *   php tests/content_payment_hooks_test.php
 *
 * 只加载被测函数所需的最小依赖，不拉起整个 admin/config.php。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-content-test-' . getmypid());
define('ARTICLES_DIR', DATA_DIR . '/articles');
@mkdir(ARTICLES_DIR, 0777, true);

function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    @mkdir(dirname($f), 0777, true);
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}

$GLOBALS['FLOW_PUB'] = [];
function flow_content_published(array $a): void { $GLOBALS['FLOW_PUB'][] = $a['id'] ?? '?'; }

require_once __DIR__ . '/../lib/PluginSystem.php';

// 从 admin/config.php 抽出被测的两个函数（避免整文件的登录/会话副作用）
$src = file_get_contents(__DIR__ . '/../admin/config.php');
foreach (['save_article', 'delete_article'] as $fn) {
    if (!preg_match('/\nfunction ' . $fn . '\(.*?\n\}\n/s', $src, $m)) {
        fwrite(STDERR, "无法从 admin/config.php 抽取 {$fn}()\n"); exit(2);
    }
    eval($m[0]);
}

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else     { $fail++; echo "  ✗ {$name}" . ($detail ? "  → {$detail}" : '') . "\n"; }
}

$H = [];
foreach (['content_published','content_updated','content_deleted','comment_added','review_added','payment_success','course_enrolled'] as $h) {
    PluginSystem::add_action($h, function (...$a) use ($h, &$H) { $H[] = $h; });
}

echo "\n── 1. 新建草稿 ──\n";
$H = []; $GLOBALS['FLOW_PUB'] = [];
save_article('a1', ['id'=>'a1','title'=>'稿子','status'=>'draft']);
check('触发 content_updated', in_array('content_updated', $H, true));
check('草稿不触发 content_published', !in_array('content_published', $H, true));
check('草稿不触发内容流联动', count($GLOBALS['FLOW_PUB']) === 0);

echo "\n── 2. 草稿 → 发布 ──\n";
$H = []; $GLOBALS['FLOW_PUB'] = [];
save_article('a1', ['status'=>'published']);
check('触发 content_published', in_array('content_published', $H, true));
check('flow_content_published 被调用', $GLOBALS['FLOW_PUB'] === ['a1'],
      '实际 ' . json_encode($GLOBALS['FLOW_PUB']));

echo "\n── 3. 已发布的再次保存，不应重复触发发布 ──\n";
$H = []; $GLOBALS['FLOW_PUB'] = [];
save_article('a1', ['title'=>'改了标题']);
check('不重复触发 content_published', !in_array('content_published', $H, true));
check('不重复触发内容流联动', count($GLOBALS['FLOW_PUB']) === 0);
check('仍触发 content_updated', in_array('content_updated', $H, true));
check('标题确实写入', (json_read(ARTICLES_DIR.'/index.json')[0]['title'] ?? '') === '改了标题');

echo "\n── 4. 直接以 published 新建（批量导入路径）──\n";
$H = []; $GLOBALS['FLOW_PUB'] = [];
save_article('a2', ['id'=>'a2','title'=>'导入的','status'=>'published']);
check('新建即发布也触发 content_published', in_array('content_published', $H, true));
check('新建即发布也触发内容流联动', $GLOBALS['FLOW_PUB'] === ['a2']);

echo "\n── 5. 删除 ──\n";
$H = [];
$ok = delete_article('a2');
check('删除返回成功', $ok);
check('触发 content_deleted', in_array('content_deleted', $H, true));
check('记录确实被移除', count(json_read(ARTICLES_DIR.'/index.json')) === 1);

echo "\n── 6. 删除不存在的 id，不应误触发 ──\n";
$H = [];
delete_article('nope');
check('不触发 content_deleted', !in_array('content_deleted', $H, true));

echo "\n── 7. 插件抛异常不能影响文章保存 ──\n";
PluginSystem::add_action('content_updated', function () { throw new RuntimeException('炸'); });
$broke = false;
try { save_article('a1', ['title'=>'异常后仍需保存']); } catch (\Throwable $e) { $broke = true; }
check('save_article 未被打断', !$broke);
check('返回值仍为 true', save_article('a1', ['title'=>'再存一次']) === true);
check('数据正确落盘', (json_read(ARTICLES_DIR.'/index.json')[0]['title'] ?? '') === '再存一次');

foreach (glob(ARTICLES_DIR.'/*') ?: [] as $f) if (is_file($f)) @unlink($f);
@rmdir(ARTICLES_DIR);
foreach (glob(DATA_DIR.'/*') ?: [] as $f) if (is_file($f)) @unlink($f);
@rmdir(DATA_DIR);

echo "\n" . str_repeat('─', 44) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
