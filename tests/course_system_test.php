<?php
/**
 * 课程体系深项 契约 —— 类型单一真源 / 先修 / 分析 / 证书
 *   php tests/course_system_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-course-' . getmypid());
@mkdir(DATA_DIR . '/courses', 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
function site_config_get(string $k) { return ''; }
function member_get(string $id): ?array { return ['id' => $id, 'name' => '学员' . $id]; }

json_write(DATA_DIR . '/courses/index.json', [
    ['id' => 'c1', 'title' => '入门课', 'status' => 'published', 'type' => '课程', 'chapters' => [['lessons' => [['id' => 'l1'], ['id' => 'l2']]]]],
    ['id' => 'c2', 'title' => '进阶课', 'status' => 'published', 'type' => '认证课', 'certificate' => true, 'prerequisites' => ['c1'], 'chapters' => [['lessons' => [['id' => 'm1']]]]],
]);
json_write(DATA_DIR . '/courses/progress.json', [
    'u1' => ['c1' => ['l1' => ['done' => true], 'l2' => ['done' => true]], 'c2' => ['m1' => ['done' => true, 'last_at' => '2026-09-01 10:00:00']]],
    'u2' => ['c1' => ['l1' => ['done' => true]], 'c2' => []],
    'u3' => ['c2' => ['m1' => ['done' => true]]],
]);
$GLOBALS['ORDERS'] = [
    ['course_id' => 'c2', 'status' => 'paid', 'amount' => 199, 'member_id' => 'u1', 'paid_at' => '2026-09-01 09:00:00'],
    ['course_id' => 'c2', 'status' => 'paid', 'amount' => 199, 'member_id' => 'u2', 'paid_at' => '2026-09-02 09:00:00'],
    ['course_id' => 'c2', 'status' => 'pending', 'amount' => 199, 'member_id' => 'u9'],
];

// stubs 覆盖进度函数（避免拉起 ProgressSystem/config）
function progress_all(): array { return json_read(DATA_DIR . '/courses/progress.json'); }
function progress_get(string $m, string $c): array { return progress_all()[$m][$c] ?? []; }
function progress_summary(string $m, string $c, array $course): array {
    $pg = progress_get($m, $c);
    $total = 0; $done = 0;
    foreach ($course['chapters'] ?? [] as $ch) foreach ($ch['lessons'] ?? [] as $l) { $total++; if (!empty($pg[$l['id']]['done'])) $done++; }
    return ['total' => $total, 'done' => $done, 'percent' => $total ? round($done / $total * 100) : 0];
}
function shop_all_orders(): array { return $GLOBALS['ORDERS']; }

require_once __DIR__ . '/../lib/CourseSystem.php';
require_once __DIR__ . '/../lib/CertificateSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "课程体系深项\n";

// 类型单一真源
check('类型表含 5 类', count(course_types()) === 5 && course_type_label('认证课') === '认证课');

// 先修
$c2 = course_find('c2');
check('读取先修', course_prereq($c2) === ['c1']);
$miss = course_prereq_missing('u2', $c2);
check('未完成先修被识别', count($miss) === 1 && $miss[0]['id'] === 'c1');
check('已完成先修则通过', course_prereq_missing('u1', $c2) === []);

// 数据分析
$a = course_analytics('c2');
check('分析：营收/订单', $a['revenue'] === 398.0 && $a['orders'] === 2);
check('分析：购买人数', $a['buyers'] === 2);
check('分析：完课率', $a['completed'] === 2 && $a['completion_rate'] === 66.7, (string)$a['completion_rate']);
check("分析：课时流失列表", $a["lessons_total"] === 1 && $a["lessons"][0]["done"] === 2);

// 证书
$c = cert_issue('u1', 'c2', '进阶课', '甲');
check('颁发证书', $c && $c['cert_no'] !== '' && $c['verify'] !== '');
check('证书幂等', (cert_issue('u1', 'c2', '进阶课', '甲')['cert_no'] ?? '') === $c['cert_no']);
check('证书可验证', cert_verify($c['cert_no']));
check('篡改/不存在不可验证', cert_verify('OF-CERT-00000000-XXXXXX') === false);
check('按会员查证书', count(cert_for_member('u1')) === 1);
check('认证课自动出证', !empty(cert_maybe_issue('u3', $c2, '丙')));
check('普通课默认不出证', cert_maybe_issue('u1', course_find('c1'), '甲') === null);

// 结构守卫
$ce = file_get_contents(__DIR__ . '/../admin/course-edit.php');
check('编辑页用类型真源', strpos($ce, 'course_types()') !== false);
check('编辑页含先修与证书', strpos($ce, 'prerequisites') !== false && strpos($ce, 'name="certificate"') !== false);
$ac = file_get_contents(__DIR__ . '/../admin/courses.php');
check('课程列表支持批量', strpos($ac, 'bulk_action') !== false);
$ps = file_get_contents(__DIR__ . '/../lib/ProgressSystem.php');
check('完课触发证书', strpos($ps, 'cert_maybe_issue(') !== false);
$ht = file_get_contents(__DIR__ . '/../.htaccess');
check('证书路由存在', strpos($ht, 'certificate') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
