<?php
/**
 * OpsDigest —— 自动化运行观测 + 每日简报
 * 覆盖：正在跑的任务聚合 / 当日流程执行聚合 / token 每日每小时 / 简报数据与 HTML / 幂等发送标记
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-ops-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $file): array { if (!is_file($file)) return []; $v = json_decode((string)file_get_contents($file), true); return is_array($v) ? $v : []; }
function json_write(string $file, array $value): bool { @mkdir(dirname($file), 0777, true); return (bool)file_put_contents($file, json_encode($value, JSON_UNESCAPED_UNICODE)); }

// 站内动作桩：OpsDigest 只读这些数据，不发真邮件
function site_config_get(string $k, string $d = ''): string { return $k === 'admin_email' ? 'admin@example.com' : $d; }
function get_leads(): array { return [['姓名' => '甲', '时间' => date('Y-m-d 10:00:00')], ['姓名' => '乙', '时间' => date('Y-m-d 11:00:00')], ['姓名' => '丙', '时间' => '2020-01-01 00:00:00']]; }
function shop_all_orders(): array { return [['status' => 'paid', 'paid_at' => date('Y-m-d 12:00:00'), 'amount' => 199], ['status' => 'pending', 'created_at' => date('Y-m-d 12:00:00'), 'amount' => 999]]; }
$GLOBALS['MAIL_LOG'] = [];
function mail_send(string $to, string $subject, string $body): bool { $GLOBALS['MAIL_LOG'][] = ['to' => $to, 'subject' => $subject]; return true; }
// 流程定义：AutomationSystem 的 automation_get() 读 DATA_DIR/automation.json
json_write(DATA_DIR . '/automation.json', [
    ['id' => 'f1', 'name' => '欢迎流', 'enabled' => true],
    ['id' => 'f2', 'name' => '召回流', 'enabled' => false],
]);

// ai_usage 电表（真 SQLite，与线上一致）
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/AiBudget.php';
require_once __DIR__ . '/../lib/AutomationSystem.php';
require_once __DIR__ . '/../lib/OpsDigest.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " → {$d}" : '') . "\n"; } }

echo "\n── 正在执行的任务 ──\n";
json_write(DATA_DIR . '/automation-queue.json', [
    ['id' => 'aq1', 'flow_id' => 'f1', 'flow_name' => '欢迎流', 'step_index' => 2, 'run_at' => date('Y-m-d H:i:s', time() + 600), 'created_at' => date('Y-m-d H:i:s')],
    ['id' => 'aq2', 'flow_id' => 'f1', 'flow_name' => '欢迎流', 'step_index' => 3, 'run_at' => date('Y-m-d H:i:s', time() - 30), 'created_at' => date('Y-m-d H:i:s')],
    ['id' => 'aq3', 'flow_id' => 'f2', 'flow_name' => '召回流', 'step_index' => 0, 'run_at' => date('Y-m-d H:i:s', time() + 3600), 'created_at' => date('Y-m-d H:i:s')],
]);
$running = ops_running_tasks();
check('排队任务全部可见', count($running) === 3, (string)count($running));
check('按计划时间升序', $running[0]['id'] === 'aq2', $running[0]['id'] ?? '');
check('区分延时等待与到期待执行', $running[0]['state'] === 'due' && $running[2]['state'] === 'delaying');

echo "\n── 当日流程执行聚合 ──\n";
automation_log('f1', '进入流程');
automation_log('f1', 'BillionMail 邮件已发送给 a@b.c');
automation_log('f2', '打标签失败: x', 'error');
$tf = ops_today_flow_stats();
check('按流程聚合今天日志', count($tf) === 2, (string)count($tf));
$f1 = array_values(array_filter($tf, fn($f) => $f['id'] === 'f1'))[0] ?? [];
$f2 = array_values(array_filter($tf, fn($f) => $f['id'] === 'f2'))[0] ?? [];
check('流程名取自流程定义', ($f1['name'] ?? '') === '欢迎流');
check('失败数与执行数分开计', ($f1['steps'] ?? 0) === 2 && ($f1['errors'] ?? 0) === 0 && ($f2['errors'] ?? 0) === 1);
check('记录最近动作', str_contains((string)($f2['last_msg'] ?? ''), '打标签失败'));

echo "\n── token 每日 / 每小时 ──\n";
ai_usage_record(['feature' => '_ops_smoke', 'tier' => 'batch', 'model' => 'm1', 'in_tokens' => 100, 'out_tokens' => 50, 'ok' => true]);
$h = ops_token_hourly();
check('逐小时聚合有记录', count($h) >= 1, json_encode($h, JSON_UNESCAPED_UNICODE));
check('小时键为两位 HH', preg_match('/^\d{2}$/', (string)($h[0]['h'] ?? '')) === 1);
$dd = ops_token_daily(14);
check('逐日聚合包含今天', in_array(date('Y-m-d'), array_column($dd, 'k'), true));
$tk = ai_spend();
check('当日 token 汇总为 150', (int)$tk['tokens'] === 150, (string)$tk['tokens']);

echo "\n── 流量与转化 ──\n";
$t = ops_traffic_today();
check('当日 UV/PV/线索/订单', $t['leads'] === 2 && $t['orders'] === 1 && $t['revenue'] === 199.0, json_encode($t, JSON_UNESCAPED_UNICODE));

echo "\n── 简报构建与发送 ──\n";
$d = ops_digest_build_data();
$html = ops_digest_build_html($d, 'm');
foreach (['一、执行基本情况', '二、流量情况', '三、转化情况', '四、当日任务执行', '五、Token 消耗'] as $sec) {
    check("简报含「{$sec}」", str_contains($html, $sec));
}
check('简报含后台详情链接', str_contains($html, '/xmp/ops-stats'));
check('收件人回落到管理员邮箱', ops_digest_settings()['recipients'] === ['admin@example.com']);

// 幂等：窗口内第一次发送成功并落标记；第二次跳过
$cfg = ops_digest_settings();
$hour = (int)date('G');
$slot = ($hour >= $cfg['evening_hour']) ? 'e' : 'm';
$r1 = ops_digest_maybe_send();
check('窗口内发送成功', ($r1['status'] ?? '') === 'sent', json_encode($r1, JSON_UNESCAPED_UNICODE));
check('真的发了邮件给管理员', ($GLOBALS['MAIL_LOG'][0]['to'] ?? '') === 'admin@example.com');
$r2 = ops_digest_maybe_send();
check('同档当天不重发', ($r2['status'] ?? '') === 'already_sent', json_encode($r2, JSON_UNESCAPED_UNICODE));
check('标记落盘', !empty(json_read(DATA_DIR . '/ops-digest-sent.json')[$slot . ':' . date('Y-m-d')]));

// 设置保存：收件人清洗 + 时段约束
$saved = ops_digest_save(['recipients_raw' => 'a@x.com, bad@@x, b@y.com；c@z.com', 'morning_hour' => 7, 'evening_hour' => 6]);
check('非法邮箱被清洗', $saved['recipients'] === ['a@x.com', 'b@y.com', 'c@z.com'], json_encode($saved['recipients']));
check('傍晚必须晚于清晨', $saved['evening_hour'] > $saved['morning_hour'], "m={$saved['morning_hour']} e={$saved['evening_hour']}");

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail > 0 ? 1 : 0);
