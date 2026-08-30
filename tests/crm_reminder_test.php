<?php
/**
 * CRM 未跟进提醒验收
 *
 *   php tests/crm_reminder_test.php
 *
 * 关键行为：只提醒开放阶段里躺太久的线索、刚跟进的不打扰、已成交/流失不打扰、
 * 冷却期内不重复轰炸。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-crmrem-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

function json_read(string $f): array { return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : []; }
function json_write(string $f, array $d): bool { return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

$GLOBALS['SENT'] = [];
function notify_channels_send(string $t, string $m, string $l = ''): void { $GLOBALS['SENT'][] = [$t, $m, $l]; }

require_once __DIR__ . '/../lib/PluginSystem.php';

$src = file_get_contents(__DIR__ . '/../lib/CrmSystem.php');
function extract_fn(string $src, string $name): string {
    $at = strpos($src, "\nfunction {$name}(");
    if ($at === false) { fwrite(STDERR, "缺 {$name}\n"); exit(2); }
    $open = strpos($src, '{', $at); $depth = 0;
    for ($i = $open, $n = strlen($src); $i < $n; $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) return substr($src, $at, $i - $at + 1); }
    }
    exit(2);
}
foreach (['crm_file','crm_get','crm_save','crm_days_since_activity','crm_stale_leads','crm_send_followup_reminders'] as $fn) {
    eval(extract_fn($src, $fn));
}

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

$old = date('Y-m-d H:i:s', time() - 10 * 86400);
$recent = date('Y-m-d H:i:s', time() - 1 * 86400);
function seed(string $old, string $recent) {
    json_write(DATA_DIR . '/crm.json', ['leads' => [
        'a@t.com' => ['email'=>'a@t.com','name'=>'甲','stage'=>'contacted','owner'=>'张三','follow_ups'=>[],'created_at'=>$old],
        'b@t.com' => ['email'=>'b@t.com','name'=>'乙','stage'=>'qualified','owner'=>'张三','follow_ups'=>[['time'=>$recent]],'created_at'=>$old],
        'c@t.com' => ['email'=>'c@t.com','name'=>'丙','stage'=>'won','owner'=>'李四','follow_ups'=>[],'created_at'=>$old],
        'd@t.com' => ['email'=>'d@t.com','name'=>'丁','stage'=>'new','owner'=>'李四','follow_ups'=>[],'created_at'=>$old],
        'e@t.com' => ['email'=>'e@t.com','name'=>'戊','stage'=>'lost','owner'=>'王五','follow_ups'=>[],'created_at'=>$old],
    ]]);
    $GLOBALS['SENT'] = [];
}

echo "\n── 1. 挑选过期线索 ──\n";
seed($old, $recent);
$stale = crm_stale_leads(7);
check('甲（10天没跟进）入选', isset($stale['a@t.com']));
check('丁（新建10天）入选', isset($stale['d@t.com']));
check('乙（1天前刚跟进）不选', !isset($stale['b@t.com']));
check('丙（已成交）不选', !isset($stale['c@t.com']));
check('戊（已流失）不选', !isset($stale['e@t.com']));

echo "\n── 2. 天数口径：跟进时间优先于创建时间 ──\n";
check('甲距今约 10 天', crm_days_since_activity(['created_at'=>$old,'follow_ups'=>[]]) >= 9);
check('刚跟进的约 1 天', crm_days_since_activity(['created_at'=>$old,'follow_ups'=>[['time'=>$recent]]]) <= 2);

echo "\n── 3. 发提醒 ──\n";
seed($old, $recent);
$r = crm_send_followup_reminders(7);
check('提醒 2 条', $r['reminded'] === 2, (string)$r['reminded']);
check('按 2 个负责人分组', $r['owners'] === 2, (string)$r['owners']);
check('发了一条汇总通知', count($GLOBALS['SENT']) === 1);
check('标题含条数', strpos($GLOBALS['SENT'][0][0] ?? '', '2 条') !== false, $GLOBALS['SENT'][0][0] ?? '');
check('正文按负责人分组', strpos($GLOBALS['SENT'][0][1] ?? '', '张三') !== false && strpos($GLOBALS['SENT'][0][1] ?? '', '李四') !== false);
check('通知带 CRM 链接', ($GLOBALS['SENT'][0][2] ?? '') === '/xmp/crm');

echo "\n── 4. 冷却：同一天不重复轰炸 ──\n";
$GLOBALS['SENT'] = [];
$r2 = crm_send_followup_reminders(7);
check('冷却期内不再提醒', $r2['reminded'] === 0, (string)$r2['reminded']);
check('不再发通知', count($GLOBALS['SENT']) === 0);
$lead = crm_get()['leads']['a@t.com'];
check('线索被打上冷却戳', !empty($lead['last_reminder']));

echo "\n── 5. 无过期线索时安静 ──\n";
json_write(DATA_DIR . '/crm.json', ['leads' => [
    'x@t.com' => ['email'=>'x@t.com','stage'=>'new','follow_ups'=>[['time'=>$recent]],'created_at'=>$recent],
]]);
$GLOBALS['SENT'] = [];
$r3 = crm_send_followup_reminders(7);
check('没有过期线索 → 0 提醒', $r3['reminded'] === 0);
check('不发空通知', count($GLOBALS['SENT']) === 0);

foreach (glob(DATA_DIR . '/*') ?: [] as $f) @unlink($f);
@rmdir(DATA_DIR);

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
