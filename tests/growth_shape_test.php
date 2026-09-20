<?php
declare(strict_types=1);
/**
 * GrowthEngine::shapeCompare 回归测试
 *
 * 【背景】2026-09-20 打开 /xmp/evolution 时线上 500：
 *   GrowthEngine.php:347 `str_contains()` 第二个参数收到 int。
 *   根因：`foreach ([...] as $k => $label)` 里 $k 是下标(0..4)，被当作类别名当 needle；
 *   既会在 PHP 8 抛 TypeError，逻辑上也永远匹配不到（分布恒为空）。
 *
 *   php tests/growth_shape_test.php
 */

$tmp = sys_get_temp_dir() . '/of-gshape-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

define('OF_NO_AUTO_CSRF', true);
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/GrowthEngine.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : "") . "\n"; }
}

echo "形态对比（shapeCompare）\n";

check('空信号也能调用（不抛 TypeError）', is_array(GrowthEngine::shapeCompare()));
check('空信号时分布为空', (GrowthEngine::shapeCompare()['distribution'] ?? []) === []);

/* 种入信号：类别名出现在 key 里 */
$st = GrowthEngine::state();
$st['signals'] = ['bug_php' => 2, 'content_missing' => 3, 'perf_slow' => 1, 'routing_404' => 4, 'weird_other' => 9];
json_write(DATA_DIR . '/growth.json', $st);

$r = GrowthEngine::shapeCompare();
$map = [];
foreach ((array) $r['distribution'] as $d) $map[(string) $d['cat']] = (int) $d['n'];

check('bug 归类正确', ($map['bug'] ?? 0) === 2, json_encode($map));
check('content 归类正确', ($map['content'] ?? 0) === 3, json_encode($map));
check('routing 归类正确', ($map['routing'] ?? 0) === 4, json_encode($map));
check('未匹配类别不计入', !isset($map['weird']), json_encode($map));
check('按数量倒序', array_values($map) === [4, 3, 2, 1], json_encode(array_values($map)));
check('形状字段存在（新生/茁壮）', in_array((string) ($r['shape'] ?? ''), ['新生', '茁壮'], true), (string) ($r['shape'] ?? ''));

/* 键为非字符串（旧数据里可能是 int 键）也不能炸 */
$st2 = GrowthEngine::state();
$st2['signals'] = [0 => 1, 'bug_x' => 1];
json_write(DATA_DIR . '/growth.json', $st2);
check('非字符串键不抛异常（键先转字符串）', is_array(GrowthEngine::shapeCompare()));

@exec('rm -rf ' . escapeshellarg($tmp));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
