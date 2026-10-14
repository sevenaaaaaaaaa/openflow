<?php
/**
 * 后台 AI 任务的执行时段 + 日预算（单日加额 / 触顶邮件）验收
 *   php tests/ai_schedule_budget_test.php
 *
 * 钉住的性质：
 *   ① 工作日判断：周末、法定节假日不执行；调休上班日默认也不执行（可开）；后台补录优先
 *   ② 执行窗口：工作日 00:00–06:00 放行，06:00 整点起拒绝；放行时给出「距窗口结束的秒数」
 *   ③ 时段闸只管 cron 后台调用；manual=true 与非后台调用不受影响
 *   ④ 日预算 = 基础上限 + 今日加额；加额按日期记，换一天自动失效（第二天自动回调）
 *   ⑤ 触顶：拦下后续调用；邮件每个上限每天只发一次；加额后再触顶会再发一次；没收件人不崩
 */
// 每次运行一个全新的数据目录，跑完删掉。原先只按 PID 命名且从不清理：PID 复用时会撞上
// 同一天留下的旧目录（settings.json 里的加额/单价 + db 里当天已记的花费 + 已发提醒标记），
// §4–§6 的预算断言随之误红——表现为「本地偶发、换个工作区就红」。
$tmp = sys_get_temp_dir() . '/of-sched-' . getmypid() . '-' . bin2hex(random_bytes(4));
mkdir($tmp, 0777, true);
register_shutdown_function(static function () use ($tmp): void {
    if (!is_dir($tmp)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    @rmdir($tmp);
});
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

// 测试桩：记录发出的邮件（mail_send 允许宿主先行定义）
$GLOBALS['__mails'] = [];
function mail_send(string $to, string $subject, string $body, string $channel = ''): bool {
    $GLOBALS['__mails'][] = compact('to', 'subject', 'body');
    return true;
}

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiBudget.php';
require_once __DIR__ . '/../lib/AiCenter.php';
require_once __DIR__ . '/../lib/AiSchedule.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}
function at(string $s): int { return strtotime($s); }

echo "\n── 1. 日类型 ──\n";
check('2026-10-12 周一 = 工作日', AiSchedule::dayType('2026-10-12') === 'workday');
check('2026-10-11 周日 = 周末', AiSchedule::dayType('2026-10-11') === 'weekend');
check('2026-10-03 国庆(周六)= 节假日（不是普通周末）', AiSchedule::dayType('2026-10-03') === 'holiday');
check('2026-10-07 国庆最后一天(周三)= 节假日', AiSchedule::dayType('2026-10-07') === 'holiday');
check('2026-10-08 国庆后第一天(周四)= 工作日', AiSchedule::dayType('2026-10-08') === 'workday');
check('2026-10-10 周六调休上班 = makeup', AiSchedule::dayType('2026-10-10') === 'makeup');
check('2026-02-18 春节(周三)= 节假日', AiSchedule::dayType('2026-02-18') === 'holiday');
check('内置表覆盖 2026，不覆盖 2027', AiSchedule::yearCovered(2026) && !AiSchedule::yearCovered(2027));

echo "\n── 2. 执行窗口（00:00–06:00）──\n";
$d = AiSchedule::decision(at('2026-10-12 00:00:00'));
check('周一 00:00 放行', $d['allowed'] === true, $d['reason']);
check('周一 00:00 距窗口结束 6 小时', $d['seconds_left'] === 21600, (string)$d['seconds_left']);
$d = AiSchedule::decision(at('2026-10-12 05:59:30'));
check('周一 05:59:30 仍放行，只剩 30 秒', $d['allowed'] && $d['seconds_left'] === 30, json_encode($d));
check('周一 06:00:00 起拒绝（必须在 6 点前结束）', AiSchedule::decision(at('2026-10-12 06:00:00'))['allowed'] === false);
check('周一 12:00 拒绝（非重要任务只在凌晨）', AiSchedule::decision(at('2026-10-12 12:00:00'))['allowed'] === false);
check('周一 23:59 拒绝', AiSchedule::decision(at('2026-10-12 23:59:00'))['allowed'] === false);
check('周六 03:00 拒绝', AiSchedule::decision(at('2026-10-17 03:00:00'))['allowed'] === false);
check('周日 03:00 拒绝', AiSchedule::decision(at('2026-10-11 03:00:00'))['allowed'] === false);
check('国庆 03:00 拒绝', AiSchedule::decision(at('2026-10-05 03:00:00'))['allowed'] === false);
check('调休上班的周六 03:00 默认拒绝', AiSchedule::decision(at('2026-10-10 03:00:00'))['allowed'] === false);
check('周日深夜 23:59 拒绝，周一 00:00 放行（按日历日算）',
    !AiSchedule::decision(at('2026-10-11 23:59:00'))['allowed'] && AiSchedule::decision(at('2026-10-12 00:00:00'))['allowed']);
check('周五 05:00 放行，周六 00:00 拒绝',
    AiSchedule::decision(at('2026-10-16 05:00:00'))['allowed'] && !AiSchedule::decision(at('2026-10-17 00:00:00'))['allowed']);

echo "\n── 3. 后台可调 ──\n";
AiSchedule::save(['run_on_makeup' => true]);
check('打开「调休上班日也执行」后，调休周六 03:00 放行', AiSchedule::decision(at('2026-10-10 03:00:00'))['allowed'] === true);
AiSchedule::save(['run_on_makeup' => false, 'extra_holidays' => "2026-10-13\n2026-10-14, bad-date"]);
check('补录节假日生效（周二）', AiSchedule::decision(at('2026-10-13 03:00:00'))['allowed'] === false);
check('非法日期被丢弃', AiSchedule::settings()['extra_holidays'] === ['2026-10-13', '2026-10-14']);
AiSchedule::save(['extra_workdays' => '2026-10-17']);
check('补录工作日生效（周六临时上班）', AiSchedule::decision(at('2026-10-17 03:00:00'))['allowed'] === true);
check('补录节假日优先于补录工作日', (function () { AiSchedule::save(['extra_workdays' => '2026-10-13']); return AiSchedule::dayType('2026-10-13') === 'holiday'; })());
AiSchedule::save(['extra_holidays' => [], 'extra_workdays' => []]);
AiSchedule::save(['enabled' => false]);
check('关闭限制后任何时刻放行且不限时', (function () { $d = AiSchedule::decision(at('2026-10-17 15:00:00')); return $d['allowed'] && $d['seconds_left'] === null; })());
AiSchedule::save(['enabled' => true, 'window_start' => 1, 'window_end' => 5]);
check('自定义窗口 01:00–05:00：00:30 拒绝，01:00 放行，05:00 拒绝',
    !AiSchedule::decision(at('2026-10-12 00:30:00'))['allowed'] && AiSchedule::decision(at('2026-10-12 01:00:00'))['allowed'] && !AiSchedule::decision(at('2026-10-12 05:00:00'))['allowed']);
AiSchedule::save(['window_start' => 0, 'window_end' => 6]);
check('下一个窗口：周五白天 → 下周一 00:00（跳过周末）',
    date('Y-m-d H:i', AiSchedule::nextWindowStart(at('2026-10-16 12:00:00'))) === '2026-10-19 00:00');
check('下一个窗口：国庆前夜 → 10-08 00:00（跳过整个长假）',
    date('Y-m-d H:i', AiSchedule::nextWindowStart(at('2026-09-30 12:00:00'))) === '2026-10-08 00:00');

echo "\n── 4. AiCenter：时段闸只管后台调用 ──\n";
// 未配供应商：能走到「未配置」说明没被时段闸拦下；被拦下则带 scheduled_off
$GLOBALS['OF_AI_BACKGROUND'] = true;
$gated = AiSchedule::decision()['allowed'] === false;      // 当前真实时刻
if ($gated) {
    $r = AiCenter::chat('s', 'u', ['feature' => 't']);
    check('后台调用在窗口外：被拦且标记 scheduled_off', ($r['scheduled_off'] ?? false) === true && $r['ok'] === false, json_encode($r));
    $r = AiCenter::chat('s', 'u', ['feature' => 't', 'manual' => true]);
    check('manual=true 绕过时段闸', empty($r['scheduled_off']), json_encode($r));
} else {
    echo "  · 当前时刻恰在执行窗口内，跳过「窗口外被拦」断言（单测 §2 已覆盖判定逻辑）\n";
}
unset($GLOBALS['OF_AI_BACKGROUND']);
$r = AiCenter::chat('s', 'u', ['feature' => 't']);
check('非后台调用（人触发）不受时段限制', empty($r['scheduled_off']), json_encode($r));

echo "\n── 5. 日预算 = 基础 + 今日加额，次日自动回调 ──\n";
ai_price_save('deepseek-chat', 2.0, 3.0);
ai_budget_save(['daily_cost_cap' => 5, 'alert_email' => 'boss@example.com']);
check('基础上限 5 → 生效 5', ai_budget_effective_cap() === 5.0);
$r = ai_budget_boost(10);
check('加额 10 → 生效 15', $r['effective_cap'] === 15.0 && ai_budget_effective_cap() === 15.0);
$r = ai_budget_boost(2.5);
check('同一天再加 2.5 → 累加到 17.5', $r['effective_cap'] === 17.5);
$b = ai_budget_settings();
check('加额记的是「今天」', $b['boost_day'] === date('Y-m-d'));
check('换到明天：加额自动失效，回到基础 5（无需任何定时任务）', ai_budget_effective_cap($b, date('Y-m-d', time() + 86400)) === 5.0);
check('昨天的加额不影响今天', (function () use ($b) { $b['boost_day'] = date('Y-m-d', time() - 86400); return ai_budget_effective_cap($b) === 5.0; })());
ai_budget_boost(0);
check('取消加额 → 回到 5', ai_budget_effective_cap() === 5.0);
check('保存预算不会冲掉加额字段', (function () { ai_budget_boost(3); ai_budget_save(['daily_cost_cap' => 5]); return ai_budget_effective_cap() === 8.0; })());
ai_budget_boost(0);
check('基础上限为 0（不限）时加额无意义，仍为不限', (function () { ai_budget_save(['daily_cost_cap' => 0]); $e = ai_budget_effective_cap(); ai_budget_save(['daily_cost_cap' => 5]); return $e === 0.0; })());
check('非法提醒邮箱不会被保存', (function () { ai_budget_save(['alert_email' => 'not-an-email']); return ai_budget_settings()['alert_email'] === 'boss@example.com'; })());

echo "\n── 6. 触顶：拦下 + 邮件每个上限每天一次 ──\n";
check('未触顶放行', ai_budget_check('admin')['allowed'] === true);
// 花 4.9：2/百万 in + 3/百万 out → 1,000,000 in(2.0) + 0.9667M out(2.9)
ai_usage_record(['feature' => 'x', 'tier' => 'batch', 'model' => 'deepseek-chat', 'in_tokens' => 1000000, 'out_tokens' => 966667, 'ok' => true]);
check('花 ≈4.9 仍放行', ai_budget_check('admin')['allowed'] === true, (string)ai_spend()['cost']);
check('未触顶不发邮件', count($GLOBALS['__mails']) === 0);
ai_usage_record(['feature' => 'x', 'tier' => 'batch', 'model' => 'deepseek-chat', 'in_tokens' => 100000, 'out_tokens' => 0, 'ok' => true]);  // +0.2 → 5.1
check('越线的这一次记账后立刻发出提醒邮件', count($GLOBALS['__mails']) === 1, (string)count($GLOBALS['__mails']));
check('邮件发给配置的提醒邮箱', ($GLOBALS['__mails'][0]['to'] ?? '') === 'boss@example.com');
check('邮件主题含已用/上限', strpos($GLOBALS['__mails'][0]['subject'] ?? '', '5.00') !== false);
check('邮件说明「第二天自动恢复」', strpos($GLOBALS['__mails'][0]['body'] ?? '', '第二天自动恢复') !== false);
$g = ai_budget_check('admin');
check('触顶后所有档位的后续调用被拦', $g['allowed'] === false && $g['reason'] === 'daily_cost_cap' && ai_budget_check('public')['allowed'] === false);
for ($i = 0; $i < 5; $i++) ai_budget_check('admin');
check('被拦的重复调用不会重复发邮件', count($GLOBALS['__mails']) === 1);
ai_budget_boost(5);   // 生效 10
check('加额后放行', ai_budget_check('admin')['allowed'] === true);
check('加额后不发新邮件', count($GLOBALS['__mails']) === 1);
ai_usage_record(['feature' => 'x', 'tier' => 'batch', 'model' => 'deepseek-chat', 'in_tokens' => 0, 'out_tokens' => 2000000, 'ok' => true]);  // +6 → 11.1 > 10
check('加额用完再触顶：上限变了，再提醒一次', count($GLOBALS['__mails']) === 2);
check('第二封邮件写的是新的生效上限 10.00', strpos($GLOBALS['__mails'][1]['subject'] ?? '', '10.00') !== false);

echo "\n── 7. 没有收件人也不崩 ──\n";
$GLOBALS['__mails'] = [];
@unlink($tmp . '/ai-budget-alert.json');
ai_budget_save(['alert_email' => '']);
$s = json_read($tmp . '/settings.json'); unset($s['admin_email']); json_write($tmp . '/settings.json', $s);
ai_budget_alert(99.0, 10.0);
$mark = json_read($tmp . '/ai-budget-alert.json');
check('无收件人：不发信、不抛异常、记录原因', count($GLOBALS['__mails']) === 0 && !empty($mark['error']), json_encode($mark));
$s = json_read($tmp . '/settings.json'); $s['admin_email'] = 'site@example.com'; json_write($tmp . '/settings.json', $s);
check('回落到站点 admin_email', ai_budget_alert_recipient() === 'site@example.com');

echo "\n结果：{$pass} 通过 / {$fail} 失败\n";
exit($fail ? 1 : 0);
