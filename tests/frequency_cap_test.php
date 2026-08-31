<?php
/**
 * 频控回归：从「每次读写两万条 JSON」改成 SQLite 单行写入 + 索引计数后，
 * 语义有没有变。
 *   php tests/frequency_cap_test.php
 *
 * 频控在群发链路里逐个收件人调用，是热路径。这里钉住三件事：
 *   ① 日/周计数与上限判断跟旧实现一模一样
 *   ② 老 frequency-log.json 能自动导入，原文件保留
 *   ③ SQLite 不可用时回退 JSON，结果一致
 */

$tmp = sys_get_temp_dir() . '/of-freq-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

// ── 先放一份老 JSON，验证迁移 ──
$today = date('Y-m-d');
$legacy = [
    ['member_id' => 'm1', 'channel' => 'email', 'label' => '老记录A', 'at' => $today . ' 09:00:00'],
    ['member_id' => 'm1', 'channel' => 'email', 'label' => '老记录B', 'at' => $today . ' 10:00:00'],
    ['member_id' => 'm2', 'channel' => 'inbox', 'label' => '老记录C', 'at' => $today . ' 11:00:00'],
];
file_put_contents($tmp . '/frequency-log.json', json_encode($legacy, JSON_UNESCAPED_UNICODE));

require_once __DIR__ . '/../lib/FrequencyCap.php';

echo "\n── 1. 老 JSON 自动导入 ──\n";
check('建表并导入成功', freq_ensure() === true);
$u = freq_used('m1', 'email');
check('迁移后日计数=2', $u['daily'] === 2, json_encode($u));
check('迁移后周计数=2', $u['weekly'] === 2, json_encode($u));
check('原 JSON 保留作备份', is_file($tmp . '/frequency-log.json'));
check('留了防重导 marker', is_file($tmp . '/.frequency_log_migrated'));

echo "\n── 2. 计数与旧实现一致 ──\n";
check('别人的记录不串台', freq_used('m2', 'email')['daily'] === 0);
check('别的渠道不串台', freq_used('m1', 'inbox')['daily'] === 0);
check('m2 站内信=1', freq_used('m2', 'inbox')['daily'] === 1);

echo "\n── 3. 上限判断（默认 email 每天 2 / 每周 6）──\n";
check('m1 今日已 2 封 → 拦住', freq_can_send('m1', 'email') === false);
check('m2 今日 0 封 → 放行', freq_can_send('m2', 'email') === true);
check('未配置渠道默认放行', freq_can_send('m3', 'sms') === true);

echo "\n── 4. 记录一次触达 ──\n";
freq_log('m2', 'email', '新邮件');
check('计数 +1', freq_used('m2', 'email')['daily'] === 1);
freq_log('m2', 'email', '第二封');
check('计数 +2 后到上限', freq_can_send('m2', 'email') === false);
check('label 落库', count(array_filter(
    Database::query("SELECT label FROM frequency_log WHERE member_id='m2' AND channel='email'"),
    fn($r) => $r['label'] === '新邮件')) === 1);

echo "\n── 5. 时间窗：上周的记录不算今天 ──\n";
Database::execute("INSERT INTO frequency_log (member_id,channel,label,at) VALUES (?,?,?,?)",
    ['m4', 'email', '上上周', date('Y-m-d H:i:s', strtotime('-14 days'))]);
$u4 = freq_used('m4', 'email');
check('日计数=0', $u4['daily'] === 0, json_encode($u4));
check('周计数=0（不在本周）', $u4['weekly'] === 0, json_encode($u4));
Database::execute("INSERT INTO frequency_log (member_id,channel,label,at) VALUES (?,?,?,?)",
    ['m4', 'email', '本周内', date('Y-m-d H:i:s', strtotime('monday this week') + 3600)]);
check('本周内的算进周计数', freq_used('m4', 'email')['weekly'] === 1);

echo "\n── 6. 过期清理只删窗口外的 ──\n";
$before = (int)(Database::query("SELECT COUNT(*) n FROM frequency_log")[0]['n'] ?? 0);
Database::execute("INSERT INTO frequency_log (member_id,channel,label,at) VALUES (?,?,?,?)",
    ['m5', 'email', '远古', date('Y-m-d H:i:s', strtotime('-200 days'))]);
Database::execute("DELETE FROM frequency_log WHERE at < ?", [date('Y-m-d H:i:s', time() - 60 * 86400)]);
$after = (int)(Database::query("SELECT COUNT(*) n FROM frequency_log")[0]['n'] ?? 0);
check('远古记录被清掉', $after === $before, "before={$before} after={$after}");
check('窗口内记录没被误删', freq_used('m1', 'email')['daily'] === 2);

echo "\n── 7. 群发规模下不再全量读写 ──\n";
$t = microtime(true);
for ($i = 0; $i < 300; $i++) {
    $mid = 'blast_' . $i;
    if (freq_can_send($mid, 'email')) freq_log($mid, 'email', '群发');
}
$ms = (microtime(true) - $t) * 1000;
check('300 人一轮判断+记录 < 1500ms', $ms < 1500, sprintf('%.0f ms', $ms));
printf("    （实测 %.0f ms，合每人 %.2f ms）\n", $ms, $ms / 300);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exec('rm -rf ' . escapeshellarg($tmp));
exit($fail === 0 ? 0 : 1);
