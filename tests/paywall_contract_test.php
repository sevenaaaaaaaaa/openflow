<?php
/**
 * A4 会员付费墙契约测试
 * 验证：付费墙门禁 · 升级提示 · 订阅→会员联动 · AI 升级推荐
 */
$tmp = sys_get_temp_dir() . '/of-a4-' . getmypid();
@mkdir($tmp . '/membership', 0777, true);
@mkdir($tmp . '/subscription', 0777, true);
@mkdir($tmp . '/shop', 0777, true);
@mkdir($tmp . '/cdp', 0777, true);
@mkdir($tmp . '/members', 0777, true);
// 让 admin/config.php 用我们指定的 tmp 目录
putenv('OF_DATA_DIR=' . $tmp);
if (!function_exists('plugin_hook')) { function plugin_hook(string $e, array $d = []) {} }
require_once __DIR__ . '/../lib/Gamification.php';
require_once __DIR__ . '/../lib/SubscriptionSystem.php';
require_once __DIR__ . '/../lib/MembershipSystem.php';
require_once __DIR__ . '/../lib/PaidContent.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $n) { global $pass, $fail; if ($c) { $pass++; echo "  ✓ $n\n"; } else { $fail++; echo "  ✗ $n\n"; } }

echo "── A4 会员付费墙契约测试 ──\n\n";

// 1. 付费墙基础门禁
ok(paid_can_view(null, ''), '公开内容游客可看');
ok(!paid_can_view(null, 'member'), '会员内容游客不可看');
$freeMember = ['id' => 'u1', 'membership_plan' => 'free'];
ok(paid_can_view($freeMember, 'member'), '免费会员能看member内容（member=任意登录用户）');
$memberUser = ['id' => 'u2', 'membership_plan' => 'annual'];
ok(paid_can_view($memberUser, 'member'), '年度会员能看会员内容');
ok(paid_can_view($memberUser, 'annual'), '年度会员能看年度内容');
ok(!paid_can_view($memberUser, 'lifetime'), '年度会员不能看永久内容');
$lifetimeUser = ['id' => 'u3', 'membership_plan' => 'lifetime'];
ok(paid_can_view($lifetimeUser, 'annual'), '永久会员能看年度内容');
ok(paid_can_view($lifetimeUser, 'lifetime'), '永久会员能看永久内容');

// 2. 付费墙预览生成
$longContent = str_repeat('<p>这是一段很长的内容，用于测试付费墙截断功能。</p>', 20);
$preview = paid_preview($longContent, 200);
ok($preview['truncated'] === true, '长内容被截断');
ok(strlen($preview['preview']) < strlen($longContent), '预览比原文短');

$shortContent = '<p>短内容</p>';
$preview2 = paid_preview($shortContent, 400);
ok($preview2['truncated'] === false, '短内容不截断');

// 3. 升级提示
$hint1 = paid_upgrade_hint('member');
ok(strpos($hint1, '登录') !== false, '会员门槛提示含登录');
$hint2 = paid_upgrade_hint('annual');
ok(strpos($hint2, '升级') !== false, '年度门槛提示含升级');

// 4. 付费墙 HTML 渲染
$paywallHtml = mem_paywall_html('annual', $freeMember);
ok(strlen($paywallHtml) > 100, '付费墙 HTML 非空');
ok(strpos($paywallHtml, '升级') !== false || strpos($paywallHtml, '会员') !== false, '付费墙 HTML 含升级引导');
ok(strpos($paywallHtml, 'btn') !== false, '付费墙 HTML 含按钮');

// 已有权限的会员不显示付费墙
$noPaywall = mem_paywall_html('annual', $memberUser);
ok($noPaywall === '', '已有权限会员不显示付费墙');

// 5. 订阅→会员联动
// 创建一个会员
file_put_contents($tmp . '/members/index.json', json_encode([['id' => 'u5', 'email' => 'test@test.com', 'membership_plan' => 'free']]));
// 创建订阅计划
sub_save_plans([['id' => 'monthly', 'name' => '月度', 'price' => 99, 'period' => 'month', 'enabled' => true]]);
// 创建订阅
$r = sub_create('u5', 'monthly');
ok($r['ok'], 'u5 创建订阅成功');
$u5 = member_get('u5');
ok($u5 !== null, 'u5 会员存在');
// 会员等级应同步为 monthly
ok(($u5['membership_plan'] ?? '') === 'monthly', '订阅后会员等级同步为 monthly');

// 6. 取消订阅→会员降级
sub_cancel('u5', '测试降级', true);
$u5 = member_get('u5');
ok(($u5['membership_plan'] ?? '') === 'free', '取消后会员等级降为 free');

// 7. 非立即取消→pending_cancel + 恢复
sub_create('u5', 'monthly', ['auto_renew' => true]);
$subU5 = sub_get_member('u5');
$subU5['expires_at'] = date('Y-m-d', strtotime('+30 days'));
sub_set_member('u5', $subU5);
sub_cancel('u5', '测试取消', false);
$subU5 = sub_get_member('u5');
ok($subU5['status'] === 'pending_cancel', '非立即取消→pending_cancel');
sub_resume('u5');
$subU5 = sub_get_member('u5');
ok($subU5['status'] === 'active', '恢复后状态 active');
$u5 = member_get('u5');
ok(($u5['membership_plan'] ?? '') === 'monthly', '恢复后会员等级同步为 monthly');

// 8. 过期检查→会员降级
$subU5 = sub_get_member('u5');
$subU5['expires_at'] = date('Y-m-d', strtotime('-1 day'));
sub_set_member('u5', $subU5);
sub_expire_check();
$u5 = member_get('u5');
ok(($u5['membership_plan'] ?? '') === 'free', '过期后会员等级降为 free');

// 9. AI 升级推荐（低活跃→不推荐）
$recommend = mem_ai_upgrade_recommend(['id' => 'u6', 'membership_plan' => 'free']);
ok($recommend === null, '低活跃不推荐');

// 10. AI 升级推荐（高活跃→推荐）
$events = [];
for ($i = 0; $i < 60; $i++) {
    $events[] = ['event' => 'page_view', 'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} hours"))];
}
file_put_contents($tmp . '/cdp/events.json', json_encode($events));
$recommend2 = mem_ai_upgrade_recommend(['id' => 'u6', 'membership_plan' => 'free']);
ok($recommend2 !== null, '高活跃推荐升级');

// 11. 永久会员不推荐
$recommend3 = mem_ai_upgrade_recommend(['id' => 'u7', 'membership_plan' => 'lifetime']);
ok($recommend3 === null, '永久会员不推荐升级');

// 12. 计划选项
$opts = paid_tier_options();
ok(isset($opts['member']), '计划选项含会员');
ok(isset($opts['annual']), '计划选项含年度');

echo "\n通过 {$pass} · 失败 {$fail}\n";
exit($fail === 0 ? 0 : 1);
