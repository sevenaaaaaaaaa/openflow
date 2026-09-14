<?php
/**
 * AI 岗位(AgentPost) 契约 —— 注册表播种 / 交班防重 / KPI 考核 / 自动降级 / 主线 provider
 *   php tests/agent_post_test.php
 */

$tmp = sys_get_temp_dir() . '/of-agentpost-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AgentPost.php';
require_once __DIR__ . '/../lib/Mainline.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "AI 岗位\n";

// ① 注册表:空库播种「内容编辑」
@unlink($tmp . '/agent-posts.json');
$posts = agent_posts();
check('空库播种默认岗位', count($posts) >= 1);
$ce = agent_post_get('content-editor');
check('内容编辑岗位存在', $ce !== null);
check('岗位含 KPI 定义', isset($ce['kpi']['target'], $ce['kpi']['period_days']));
check('工具白名单只含 safe 能力', !in_array('publish_content', $ce['tools'] ?? [], true));
check('风险策略 safe_only', ($ce['risk_policy'] ?? '') === 'safe_only');

// ② 交班:无热点时优雅降级(不假成功),且有防重
$r = agent_post_run('content-editor');
check('交班返回 ok', !empty($r['ok']));
check('交班落盘', count(agent_post_shifts('content-editor')) === 1);
check('无热点日不出稿', (int)($r['shift']['drafts'] ?? 1) === 0);
$r2 = agent_post_run('content-editor');
check('同日二次交班被防重', !empty($r2['skipped']));
$r3 = agent_post_run('content-editor', ['force' => true]);
check('force 可重跑', empty($r3['skipped']));
// 无热点日:scan_trends 诚实报告「无新热点」,后续写稿步骤被跳过,只剩交班 1 步 —— 这是设计行为
check('交班含步骤留痕(无热点日至少交班步骤)', count($r3['shift']['steps'] ?? []) >= 1, 'steps=' . count($r3['shift']['steps'] ?? []));

// ③ 停用岗位不交班
$posts = agent_posts();
foreach ($posts as $i => $p) if ($p['id'] === 'content-editor') $posts[$i]['status'] = 'suspended';
agent_posts_save($posts);
$r4 = agent_post_run('content-editor');
check('停用岗位拒绝交班', empty($r4['ok']));
foreach ($posts as $i => $p) if ($p['id'] === 'content-editor') $posts[$i]['status'] = 'active';
agent_posts_save($posts);

// ④ KPI 考核:周期计数(造 3 条带出稿的历史 shift,分布在近 7 天)
$made = [];
for ($i = 1; $i <= 3; $i++) $made[] = ['date' => date('Y-m-d', strtotime("-{$i} days")), 'at' => date('Y-m-d H:i:s', strtotime("-{$i} days")), 'drafts' => 1, 'summary' => '模拟出稿', 'steps' => []];
agent_post_shifts_save('content-editor', $made);
$ev = agent_post_evaluate('content-editor');
check('KPI 统计周期出稿', ($ev['count'] ?? 0) >= 3, 'count=' . ($ev['count'] ?? '?'));
check('KPI 达标判定', !empty($ev['met']));

// ⑤ 自动降级:连续两周期 <50%(造历史 shift)
$old = [];
for ($i = 5; $i <= 12; $i++) $old[] = ['date' => date('Y-m-d', strtotime("-{$i} days")), 'at' => date('Y-m-d H:i:s', strtotime("-{$i} days")), 'drafts' => 0, 'summary' => '模拟空班', 'steps' => []];
agent_post_shifts_save('content-editor', array_merge($old, [['date' => date('Y-m-d'), 'at' => date('Y-m-d H:i:s'), 'drafts' => 0, 'summary' => '今日空班', 'steps' => []]]));
$ev2 = agent_post_evaluate('content-editor');
check('连续不达标触发降级', !empty($ev2['downgraded']));
$p2 = agent_post_get('content-editor');
check('岗位状态变为仅建议', ($p2['status'] ?? '') === 'suggest_only');
check('降级附人工恢复提示', strpos((string)($p2['note'] ?? ''), '人工确认') !== false);

// ⑥ 主线 provider:active 岗位才出现
$ml = mainline_src_agent_posts();
check('主线 provider 可调用', is_array($ml));

// ⑦ cron 入口只跑 active
$cron = agent_posts_cron();
check('cron 跳过非 active 岗位', !isset($cron['content-editor']));

echo "\n{$pass} passed · {$fail} failed\n";
exit($fail ? 1 : 0);
