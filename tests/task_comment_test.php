<?php
declare(strict_types=1);
/**
 * 任务评论 + @提及 + 定向通知 契约测试
 *   php tests/task_comment_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-cmt-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

/* 通知替身：ProjectSystem 只在 function_exists('notify') 时调用 */
$GLOBALS['NOTIF'] = [];
function notify(string $type, string $title, string $message, string $link = '', $audience = 'all'): void {
    $GLOBALS['NOTIF'][] = ['type' => $type, 'title' => $title, 'message' => $message, 'link' => $link, 'audience' => is_array($audience) ? $audience : [$audience]];
}

require_once __DIR__ . '/../lib/ProjectSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : "") . "\n"; }
}

echo "任务评论与 @提及\n";
json_write(DATA_DIR . '/users.json', [
    'Seven' => ['name' => '超级管理员', 'role' => 'admin', 'email' => 'seven@example.com'],
    'marketing' => ['name' => '市场总监', 'role' => 'marketing', 'email' => 'mkt@example.com'],
    'sales' => ['name' => '销售总监', 'role' => 'sales'],
]);

$_SESSION = ['admin_user' => 'Seven', 'admin_role' => 'admin'];
$pid = (string) (ps_project_save(['name' => '迭代'])['id'] ?? '');
$tid = (string) (ps_task_save($pid, ['title' => '改版首页'])['task']['id'] ?? '');
check('项目与任务就绪', $pid !== '' && $tid !== '');

/* 基本增删查 */
$r = ps_task_comment_add($pid, $tid, '先看信息架构，明天对齐', 'Seven');
check('新增评论成功', ($r['ok'] ?? false) === true, (string) ($r['error'] ?? ''));
check('评论可读回', count(ps_task_comments($pid, $tid)) === 1);
check('空评论被拒', (ps_task_comment_add($pid, $tid, '   ', 'Seven')['ok'] ?? true) === false);
check('超长评论被拒', (ps_task_comment_add($pid, $tid, str_repeat('字', 2001), 'Seven')['ok'] ?? true) === false);
check('不存在的任务被拒', (ps_task_comment_add($pid, 't_ghost', 'x', 'Seven')['ok'] ?? true) === false);
$c1 = (string) ($r['comment']['id'] ?? '');
$r2 = ps_task_comment_add($pid, $tid, '第二条', 'marketing');
$c2 = (string) ($r2['comment']['id'] ?? '');
$list = ps_task_comments($pid, $tid);
check('按时间正序', (string) ($list[0]['id'] ?? '') === $c1 && (string) ($list[1]['id'] ?? '') === $c2);
check('记录作者与时间', (string) ($list[1]['by'] ?? '') === 'marketing' && strlen((string) ($list[1]['at'] ?? '')) >= 16);

/* 保存/移动任务不能把评论冲掉（字段白名单回归） */
ps_task_save($pid, ['id' => $tid, 'title' => '改版首页（改名）', 'status' => 'doing']);
check('改名不丢评论', count(ps_task_comments($pid, $tid)) === 2);
ps_task_move($pid, $tid, 'review');
check('改状态不丢评论', count(ps_task_comments($pid, $tid)) === 2);

/* 删除权限：作者或管理员 */
check('他人不能删（非管理员）', ps_task_comment_delete($pid, $tid, $c2, 'sales', false) === false);
check('作者可删自己的', ps_task_comment_delete($pid, $tid, $c2, 'marketing', false) === true);
check('管理员可删他人评论', ps_task_comment_delete($pid, $tid, $c1, 'Seven', true) === true);
check('删完为空', ps_task_comments($pid, $tid) === []);
check('删不存在的返回 false', ps_task_comment_delete($pid, $tid, 'c_ghost', 'Seven', true) === false);

/* @提及解析 */
check('按显示名提及', ps_comment_mentions('@市场总监 帮忙看下') === ['marketing']);
check('按登录名提及', ps_comment_mentions('@marketing 帮忙看下') === ['marketing']);
check('登录名大小写不敏感', ps_comment_mentions('@MARKETING 看下') === ['marketing']);
check('多人提及', ps_comment_mentions('@市场总监 @sales 一起') === ['marketing', 'sales']);
check('部分匹配不算（@市场总监X）', ps_comment_mentions('@市场总监X 不是') === []);
check('未知的人忽略', ps_comment_mentions('@nobody 你好') === []);
check('没有 @ 就是空', ps_comment_mentions('普通一句话') === []);

/* 渲染：转义 + 高亮 */
$html = ps_comment_html('<script>alert(1)</script> @市场总监 看这里');
check('HTML 被转义（防 XSS）', !str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'));
check('@提及被高亮', str_contains($html, '<span class="cmt-at">@市场总监</span>'));

/* 通知：定向到被提及的人，且不通知自己 */
$GLOBALS['NOTIF'] = [];
$add = ps_task_comment_add($pid, $tid, '@市场总监 @sales @超级管理员 麻烦看下', 'Seven');
check('提及解析排除自己', ($add['mentioned'] ?? []) === ['marketing', 'sales'], json_encode($add['mentioned'] ?? null));
$task = ps_task_save($pid, ['id' => $tid, 'title' => '改版首页（改名）', 'status' => 'review'])['task'] ?? [];
$notified = ps_comment_notify($pid, $task, (array) $add['comment']);
check('通知到 2 人', $notified === ['marketing', 'sales'], json_encode($notified));
check('通知 audience 是 user:<登录名>', count($GLOBALS['NOTIF']) === 2
    && ($GLOBALS['NOTIF'][0]['audience'] ?? []) === ['user:marketing']
    && ($GLOBALS['NOTIF'][1]['audience'] ?? []) === ['user:sales'], json_encode($GLOBALS['NOTIF']));
check('通知带项目名与任务名', str_contains((string) ($GLOBALS['NOTIF'][0]['message'] ?? ''), '迭代')
    && str_contains((string) ($GLOBALS['NOTIF'][0]['message'] ?? ''), '改版首页'), (string) ($GLOBALS['NOTIF'][0]['message'] ?? ''));
check('通知链接指向团队视角', str_contains((string) ($GLOBALS['NOTIF'][0]['link'] ?? ''), '/xmp/today?view=team'), (string) ($GLOBALS['NOTIF'][0]['link'] ?? ''));
check('没有 @ 时不通知', ps_comment_notify($pid, $task, ['mentions' => []]) === []);

/* 单任务评论上限 200 条 */
for ($i = 0; $i < 210; $i++) ps_task_comment_add($pid, $tid, '刷屏 ' . $i, 'Seven');
$capped = ps_task_comments($pid, $tid);
check('评论上限 200 条', count($capped) === 200, (string) count($capped));
check('保留最新（尾条是最后一条）', str_contains((string) ($capped[199]['text'] ?? ''), '刷屏 209'), (string) ($capped[199]['text'] ?? ''));

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
