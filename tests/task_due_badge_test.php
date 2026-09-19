<?php
declare(strict_types=1);
/**
 * 到期分桶（页内角标）与提醒收件人 契约测试
 *   php tests/task_due_badge_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-due-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ProjectSystem.php';
require_once __DIR__ . '/../lib/NotifyChannels.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "到期分桶与收件人\n";

/* 固定参考时刻，避免踩运行时刻（教训：提醒类断言不要依赖 now） */
$now = '2026-09-19 10:00:00';
$day = static fn(int $d): string => date('Y-m-d', strtotime($now) + $d * 86400);

/* 用户表：只有 Seven 有邮箱；marketing 用登录名匹配 */
json_write(DATA_DIR . '/users.json', [
    'Seven' => ['name' => '超级管理员', 'role' => 'admin', 'email' => 'seven@example.com'],
    'marketing' => ['name' => '市场总监', 'role' => 'marketing'],
    'sales' => ['name' => '销售总监', 'role' => 'sales', 'email' => 'bad-email'],
]);

$p1 = (string) (ps_project_save(['name' => '迭代'])['id'] ?? '');
$p2 = (string) (ps_project_save(['name' => '内容'])['id'] ?? '');
$mk = static function (string $pid, string $title, string $due, string $status = 'todo', string $assignee = ''): string {
    return (string) (ps_task_save($pid, ['title' => $title, 'due' => $due, 'status' => $status, 'assignee' => $assignee])['task']['id'] ?? '');
};
$mk($p1, '逾期两天', $day(-2));
$mk($p1, '逾期五天', $day(-5));
$mk($p1, '今天到期', $day(0));
$mk($p1, '三天后', $day(3));
$mk($p1, '第十天', $day(10));                       // 超出近 7 天窗口
$mk($p1, '已完成的逾期', $day(-1), 'done');          // 完成的不算
$mk($p1, '没有截止', '');                            // 没日期不算
$mk($p2, '另一项目今天', $day(0));                   // 跨项目

$b = ps_due_buckets('', $now);
check('逾期 2 条', $b['counts']['overdue'] === 2, json_encode($b['counts']));
check('今天 2 条（跨项目）', $b['counts']['today'] === 2, json_encode($b['counts']));
check('近 7 天 1 条（第 10 天不算）', $b['counts']['soon'] === 1, json_encode($b['counts']));
check('已完成不计入', !in_array('已完成的逾期', array_map(static fn(array $x): string => (string) $x['task']['title'], $b['overdue']), true));
check('无截止不计入', !in_array('没有截止', array_map(static fn(array $x): string => (string) $x['task']['title'], $b['today']), true));
check('逾期按到期日升序', (string) $b['overdue'][0]['task']['title'] === '逾期五天', json_encode(array_column(array_column($b['overdue'], 'task'), 'title')));
check('条目带项目名', in_array('迭代', array_map(static fn(array $x): string => (string) $x['project_name'], $b['overdue']), true));

$bp = ps_due_buckets($p1, $now);
check('按项目过滤', $bp['counts']['today'] === 1 && $bp['counts']['overdue'] === 2, json_encode($bp['counts']));
check('项目过滤时不含另一项目', !in_array('另一项目今天', array_map(static fn(array $x): string => (string) $x['task']['title'], $bp['today']), true));
/* 已提醒过也要继续显示在角标里（提醒只发一次是另一件事） */
foreach (ps_due_reminders($now) as $r) ps_mark_reminded((string) $r['project'], (string) ($r['task']['id'] ?? ''), (string) $r['key']);
check('标记已提醒后角标不变', ps_due_buckets('', $now)['counts']['overdue'] === 2, json_encode(ps_due_buckets('', $now)['counts']));

/* 收件人解析 */
check('按显示名找到邮箱', ps_assignee_email('超级管理员') === 'seven@example.com');
check('按登录名找到邮箱', ps_assignee_email('marketing') === '' && ps_assignee_email('SeVeN') === 'seven@example.com');
check('大小写/空格容错', ps_assignee_email('  超级管理员 ') === 'seven@example.com');
check('没填邮箱返回空', ps_assignee_email('市场总监') === '');
check('查无此人返回空', ps_assignee_email('张三') === '');

check('负责人有邮箱 → 发给他', ps_reminder_recipients(['assignee' => '超级管理员']) === ['seven@example.com']);
check('负责人没邮箱 → 无收件人（不凭空乱发）', ps_reminder_recipients(['assignee' => '市场总监']) === []);
check('无负责人 → 无收件人', ps_reminder_recipients([]) === []);
/* 配了抄送：负责人没邮箱时也还有人收到 */
notify_channels_save(['task_email' => ['enabled' => true, 'to' => 'ops@example.com, lead@example.com']]);
$rs = ps_reminder_recipients(['assignee' => '超级管理员']);
check('负责人 + 抄送都在', $rs === ['seven@example.com', 'ops@example.com', 'lead@example.com'], json_encode($rs));
check('抄送去重', ps_reminder_recipients(['assignee' => '超级管理员', 'x' => 1]) === $rs);
notify_channels_save(['task_email' => ['enabled' => true, 'to' => 'seven@example.com, ops@example.com']]);
check('与负责人重复时去重', ps_reminder_recipients(['assignee' => '超级管理员']) === ['seven@example.com', 'ops@example.com'], json_encode(ps_reminder_recipients(['assignee' => '超级管理员'])));
notify_channels_save(['task_email' => ['enabled' => true, 'to' => '不是邮箱, ops@example.com']]);
check('非法邮箱被过滤', ps_reminder_recipients([]) === ['ops@example.com'], json_encode(ps_reminder_recipients([])));
/* 用户表里的非法邮箱不会被使用 */
check('用户表非法邮箱不采用', ps_reminder_recipients(['assignee' => '销售总监']) === ['ops@example.com']);

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
