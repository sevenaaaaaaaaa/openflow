<?php
/**
 * P1-5 验收：共享增长目标 + 大脑目标加权
 *
 *   php tests/growth_goal_test.php
 *
 * 验：设目标/唯一 active、进度按"基线以上增量"算、时间领先/落后、
 *     指标→模块加权表、以及大脑 propose 在有目标时把对的模块顶上来。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-goal-test-' . getmypid());
@mkdir(DATA_DIR . '/growth', 0777, true);
@mkdir(DATA_DIR . '/members', 0777, true);
function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    @mkdir(dirname($f), 0777, true);
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}
require_once __DIR__ . '/../lib/GrowthGoal.php';
require_once __DIR__ . '/../lib/GrowthBrain.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

echo "\n── 1. 设目标：唯一 active ──\n";
growth_goal_set(['title' => '先冲收入', 'metric' => 'revenue', 'target' => 10000, 'baseline' => 2000]);
$g2 = growth_goal_set(['title' => '本季 50 会员', 'metric' => 'members', 'target' => 50, 'baseline' => 10]);
$cur = growth_goal_current();
check('当前目标是最后设的', ($cur['title'] ?? '') === '本季 50 会员', $cur['title'] ?? '');
$actives = array_filter(growth_goal_all(), fn($x) => ($x['status'] ?? '') === 'active');
check('active 唯一', count($actives) === 1, '数量=' . count($actives));
check('非法指标回落 revenue', (growth_goal_set(['metric' => 'bogus'])['metric']) === 'revenue');

echo "\n── 2. 进度：基线以上增量 ──\n";
$goal = ['metric' => 'revenue', 'target' => 8000, 'baseline' => 2000, 'title' => 'x'];
$p = growth_goal_progress($goal, 6000); // 当前6000, 基线2000 → 增量4000 / 8000 = 50%
check('增量=4000', $p['gain'] === 4000.0, (string)$p['gain']);
check('pct=50', $p['pct'] === 50, (string)$p['pct']);
check('remaining=4000', $p['remaining'] === 4000.0);
check('display 正确', $p['display'] === '¥4,000 / ¥8,000', $p['display']);

echo "\n── 3. 时间领先/落后 ──\n";
$old = ['metric' => 'won', 'target' => 100, 'baseline' => 0, 'window_days' => 10, 'created_at' => date('Y-m-d H:i:s', time() - 5 * 86400)];
$behind = growth_goal_progress($old, 10);  // 5/10天=50%期望, 实际10%→落后
check('落后进度', $behind['pace_note'] === '落后进度', $behind['pace_note']);
$ahead = growth_goal_progress($old, 80);   // 实际80% > 期望50% → 领先
check('领先进度', $ahead['pace_note'] === '领先进度', $ahead['pace_note']);

echo "\n── 4. 指标→模块加权表 ──\n";
check('revenue 加权 Sales', (growth_goal_boost_modules('revenue')['Sales'] ?? 0) > 0);
check('members 加权 Content', (growth_goal_boost_modules('members')['Content'] ?? 0) > 0);
check('leads 加权 Content', (growth_goal_boost_modules('leads')['Content'] ?? 0) > 0);

echo "\n── 5. 大脑目标加权：把对的模块顶上来 ──\n";
// 一个既能出"临门一脚(Sales)"又能出"内容培育(Content)"边界的人不好造，
// 用两个人对比：低信号新人只出 Content(培育)，高分未成交只出 Sales(推成交)。
$hot  = ['id'=>'h','score'=>80,'props'=>'{}','tags'=>'[]','last_seen'=>date('Y-m-d H:i:s')];
$cold = ['id'=>'c','score'=>10,'props'=>'{}','tags'=>'[]','last_seen'=>date('Y-m-d H:i:s')];
$revenueGoal = ['metric'=>'revenue'];
$membersGoal = ['metric'=>'members'];

$hotNo   = growth_brain_propose($hot)['best']['priority'];
$hotRev  = growth_brain_propose($hot, null, $revenueGoal)['best']['priority'];
check('收入目标抬高 Sales 提议优先级', $hotRev > $hotNo, "$hotNo → $hotRev");
check('被加权标记', growth_brain_propose($hot, null, $revenueGoal)['best']['goal_boosted'] ?? false);

$coldNo  = growth_brain_propose($cold)['best']['priority'];
$coldMem = growth_brain_propose($cold, null, $membersGoal)['best']['priority'];
check('会员目标抬高 Content 提议优先级', $coldMem > $coldNo, "$coldNo → $coldMem");
check('收入目标不抬 Content（模块不匹配）',
    (growth_brain_propose($cold, null, $revenueGoal)['best']['priority']) === $coldNo);

echo "\n── 6. digest 传目标（backward compatible）──\n";
$dig = growth_brain_digest([$hot, $cold], null, 20, $revenueGoal);
check('digest 接受目标参数且不崩', is_array($dig) && count($dig) >= 1);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR . '/growth/*')); @array_map('unlink', glob(DATA_DIR . '/members/*'));
@rmdir(DATA_DIR . '/growth'); @rmdir(DATA_DIR . '/members'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
