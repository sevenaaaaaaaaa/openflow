<?php
/**
 * 今日主线契约 —— 聚合 / 分泳道 / 排序 / 回流过滤
 *   php tests/mainline_test.php
 *
 * 主线的价值在「收口」：把散落信号归到 立即/今日/本周，并能被处理掉。
 * 本测试用最小 fixture 验证：订单/任务/评论三类信号被正确归类、计分排序、完成后消失。
 */

$tmp = sys_get_temp_dir() . '/of-mainline-' . getmypid();
@mkdir($tmp . '/commerce', 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/xmp/today';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/Mainline.php';

// 模拟管理员会话：任务按 assignee 过滤需要 has_perm('users')
$_SESSION['admin_user'] = 'admin';
$_SESSION['admin_role'] = 'admin';

// ── fixtures ──
json_write($tmp . '/commerce/orders.json', [
    ['id' => 'o1', 'status' => 'paid', 'fulfillment' => 'pending'],
    ['id' => 'o2', 'status' => 'paid', 'fulfillment' => 'done'],
]);
json_write($tmp . '/tasks.json', [
    ['id' => 't1', 'title' => '逾期任务', 'status' => 'pending', 'assignee' => 'admin', 'due_date' => date('Y-m-d', time() - 86400)],
    ['id' => 't2', 'title' => '今日任务', 'status' => 'pending', 'assignee' => 'admin', 'due_date' => date('Y-m-d')],
]);
json_write($tmp . '/comments.json', [
    ['id' => 'c1', 'status' => 'pending', 'text' => '待审'],
    ['id' => 'c2', 'status' => 'approved', 'text' => '已通过'],
]);

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else { $fail++; echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}
function find_item(array $items, string $id): ?array {
    foreach ($items as $it) if ($it['id'] === $id) return $it;
    return null;
}

echo "今日主线\n";

$items = mainline_items(['all' => true]);
check('聚合出订单/任务/评论信号', find_item($items, 'order:ship') && find_item($items, 'task:overdue') && find_item($items, 'moderation:pending'));
$ship = find_item($items, 'order:ship');
check('待发货归入「立即」', ($ship['lane'] ?? '') === 'now');
check('待发货计入数量', (int)($ship['meta']['count'] ?? 0) === 1);
$todo = find_item($items, 'task:overdue');
check('逾期任务归入「立即」', ($todo['lane'] ?? '') === 'now');
$mod = find_item($items, 'moderation:pending');
check('待审评论归入「今日」', ($mod['lane'] ?? '') === 'today');

check('按分数降序排列', ($items[0]['score'] ?? 0) >= ($items[count($items) - 1]['score'] ?? 0));
$lanes = mainline_lanes($items);
check('三泳道都存在', isset($lanes['now'], $lanes['today'], $lanes['week']));
$summary = mainline_summary($items);
check('概览 top 是最高分项', ($summary['top']['id'] ?? '') === ($items[0]['id'] ?? ''));
check('预计耗时为正', ($summary['est_min'] ?? 0) > 0);

// ── 回流：完成后从主线消失 ──
mainline_log('order:ship', 'done', ['title' => '待发货']);
$items2 = mainline_items(['all' => true]);
check('完成后该项消失', find_item($items2, 'order:ship') === null);
check('其他项仍在', find_item($items2, 'task:overdue') !== null);

// ── 分数模型 ──
check('紧急 > 重要 > 待办', mainline_score(['severity' => 'critical', 'lane' => 'now']) > mainline_score(['severity' => 'warn', 'lane' => 'today']));
check('立即 > 今日 > 本周（同严重度）', mainline_score(['severity' => 'warn', 'lane' => 'now']) > mainline_score(['severity' => 'warn', 'lane' => 'week']));

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
