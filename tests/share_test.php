<?php
declare(strict_types=1);
/**
 * 视图级公开只读分享 契约测试
 *   php tests/share_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-share-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ProjectSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : "") . "\n"; }
}

echo "只读分享\n";
$_SESSION = ['admin_user' => 'Seven', 'admin_role' => 'admin'];
json_write(DATA_DIR . '/users.json', [
    'Seven' => ['name' => '超级管理员', 'email' => 'seven@example.com'],
    'outsider' => ['name' => '外部人', 'email' => 'out@example.com'],
]);
$pid = (string) (ps_project_save(['name' => '官网改版'])['id'] ?? '');
ps_member_set($pid, 'outsider', 'viewer');
$parent = (string) (ps_task_save($pid, ['title' => '首页改版', 'status' => 'doing', 'assignee' => '市场总监', 'due' => '2026-10-01', 'priority' => 'urgent', 'note' => '内部备注：预算 8 万', 'ref' => ['type' => 'lead', 'id' => 'lead_1', 'label' => '张三线索']])['task']['id'] ?? '');
ps_task_save($pid, ['title' => '首屏文案', 'parent' => $parent, 'status' => 'done', 'due' => '2026-09-20']);
ps_task_save($pid, ['title' => '视觉规范', 'parent' => $parent, 'status' => 'todo']);
ps_task_comment_add($pid, $parent, '@outsider 这是内部讨论，不该外泄', 'Seven');

/* 权限：只有 manage（项目负责人/站点管理员）能建与撤 */
check('viewer 不能创建分享', (ps_share_create($pid, 'board', '', 'outsider', false)['ok'] ?? true) === false);
check('manage 可创建', (ps_share_create($pid, 'board', '', 'Seven', true)['ok'] ?? false) === true);

/* 创建与 token 形态 */
$r = ps_share_create($pid, 'grid', '', 'Seven', true);
$tok = (string) ($r['token'] ?? '');
check('token 是 32 位十六进制', preg_match('/^[a-f0-9]{32}$/', $tok) === 1, $tok);
check('视图不合法被拒', (ps_share_create($pid, 'nope', '', 'Seven', true)['ok'] ?? true) === false);
check('非法有效期被忽略（长期有效）', (ps_share_create($pid, 'board', '2026/10/01', 'Seven', true)['meta']['expires'] ?? 'x') === '');
check('分享可列出', count(ps_shares($pid)) === 3, (string) count(ps_shares($pid)));
check('列表含视图与打开次数', (ps_shares($pid)[$tok]['view'] ?? '') === 'grid' && (ps_shares($pid)[$tok]['opens'] ?? -1) === 0);

/* 反查与失效 */
check('有效 token 可反查', (ps_share_get($tok)['project'] ?? '') === $pid);
check('未知 token → null', ps_share_get(str_repeat('a', 32)) === null);
check('过短 token → null', ps_share_get('abc') === null);
$exp = ps_share_create($pid, 'board', '2020-01-01', 'Seven', true);
check('过期 token → null', ps_share_get((string) $exp['token']) === null);
$rev = ps_share_create($pid, 'board', '', 'Seven', true);
check('撤销成功', ps_share_revoke($pid, (string) $rev['token'], true) === true);
check('撤销后 → null', ps_share_get((string) $rev['token']) === null);
check('viewer 撤销被拒', ps_share_revoke($pid, $tok, false) === false);
check('撤销不存在的 → false', ps_share_revoke($pid, str_repeat('b', 32), true) === false);
check('撤销后其它分享仍在', ps_share_get($tok) !== null);

/* 打开计数 */
ps_share_payload($tok);
check('打开次数累加', (ps_shares($pid)[$tok]['opens'] ?? 0) === 1, (string) (ps_shares($pid)[$tok]['opens'] ?? -1));

/* 公开模型：白名单 */
$data = ps_share_payload($tok);
check('模型含项目名/视图/统计', ($data['project'] ?? '') === '官网改版' && ($data['view'] ?? '') === 'grid' && (int) ($data['stats']['total'] ?? 0) === 3);
$row = $data['rows'][0] ?? [];
$keys = array_keys($row);
$leak = array_intersect($keys, ['id', 'note', 'ref', 'parent', 'comments', 'remind', 'history', 'created_by', 'repeat', 'repeat_src', 'dispatch']);
check('任务行不含内部字段', $leak === [], json_encode($leak));
check('不含内部备注文本', !str_contains(json_encode($data, JSON_UNESCAPED_UNICODE), '预算 8 万'));
check('不含关联对象（线索/订单）', !str_contains(json_encode($data, JSON_UNESCAPED_UNICODE), '张三线索') && !str_contains(json_encode($data, JSON_UNESCAPED_UNICODE), 'lead_1'));
check('不含评论内容', !str_contains(json_encode($data, JSON_UNESCAPED_UNICODE), '内部讨论'));
check('不含负责人邮箱', !str_contains(json_encode($data, JSON_UNESCAPED_UNICODE), 'example.com'));
check('白名单字段齐全', in_array('title', $keys, true) && in_array('status_label', $keys, true) && in_array('assignee', $keys, true) && in_array('due', $keys, true) && in_array('priority_label', $keys, true));
$parentRow = null;
foreach ($data['rows'] as $r2) if (($r2['title'] ?? '') === '首页改版') $parentRow = $r2;
check('子任务进度进入公开模型', (int) ($parentRow['sub_total'] ?? 0) === 2 && (int) ($parentRow['sub_done'] ?? 0) === 1 && (int) ($parentRow['sub_pct'] ?? 0) === 50, json_encode($parentRow));
check('逾期标记正确（done 不算逾期）', ($parentRow['overdue'] ?? true) === false);

/* 各视图都能出结构 */
$boardTok = (string) (ps_share_create($pid, 'board', '', 'Seven', true)['token'] ?? '');
$board = ps_share_payload($boardTok)['columns'] ?? [];
$colMap = [];
foreach ($board as $c) $colMap[(string) $c['key']] = count((array) $c['tasks']);
check('看板四列齐全', count($board) === 4 && ($colMap['doing'] ?? 0) === 1 && ($colMap['done'] ?? 0) === 1 && ($colMap['todo'] ?? 0) === 1, json_encode($colMap));
$calTok = (string) (ps_share_create($pid, 'calendar', '', 'Seven', true)['token'] ?? '');
$cal = ps_share_payload($calTok)['calendar'] ?? [];
check('日历有 30 天格子与未排期计数', count((array) ($cal['cells'] ?? [])) === 30 && (int) ($cal['undated'] ?? -1) === 1, json_encode([count((array) ($cal['cells'] ?? [])), $cal['undated'] ?? null]));
$gTok = (string) (ps_share_create($pid, 'gantt', '', 'Seven', true)['token'] ?? '');
$g = ps_share_payload($gTok)['gantt'] ?? [];
check('甘特有区间与条', ($g['start'] ?? '') !== '' && count((array) ($g['bars'] ?? [])) >= 1, json_encode($g));
$tTok = (string) (ps_share_create($pid, 'tree', '', 'Seven', true)['token'] ?? '');
$tree = ps_share_payload($tTok)['tree'] ?? [];
$hasDepth = false;
foreach ($tree as $n) if ((int) ($n['depth'] ?? 0) >= 1) $hasDepth = true;
check('树视图带层级', count($tree) === 3 && $hasDepth, json_encode(array_column($tree, 'depth')));

/* 回归：改名/改成员不能把分享弄丢 */
ps_project_save(['id' => $pid, 'name' => '官网改版（改名）']);
check('改名不丢分享', isset(ps_shares($pid)[$tok]));
ps_member_set($pid, 'outsider', 'editor');
check('改成员不丢分享', isset(ps_shares($pid)[$tok]));
check('公开模型读到新项目名', (ps_share_payload($tok)['project'] ?? '') === '官网改版（改名）');

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
