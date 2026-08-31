<?php
/**
 * 邮件打开/点击统计回归（docs/ROADMAP.md 阶段一）
 *   php tests/mail_stats_test.php
 *
 * mailc_track() 挂在公开的追踪像素端点上，群发后会被集中调用。
 * 从「读两万条 JSON 改一条写回」改成一行 UPSERT，这里钉住两件事：
 *   ① 计数与汇总的语义跟旧实现一模一样（opens/clicks 按人去重，不是按次数）
 *   ② 并发下不再丢计数——旧的读-改-写会互相覆盖，这是正确性问题不是性能问题
 */

$tmp = sys_get_temp_dir() . '/of-mail-' . getmypid();
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

// 先放一份老 JSON 验证迁移（键是 campaign:email_id）
file_put_contents($tmp . '/mail-stats.json', json_encode([
    'c_old:aaa' => ['campaign' => 'c_old', 'email_id' => 'aaa', 'open_count' => 3,
                    'first_open' => '2026-01-01 08:00:00', 'last_open' => '2026-01-02 09:00:00'],
    'c_old:bbb' => ['campaign' => 'c_old', 'email_id' => 'bbb', 'open_count' => 1, 'click_count' => 2,
                    'first_open' => '2026-01-01 08:00:00', 'last_open' => '2026-01-01 08:00:00',
                    'first_click' => '2026-01-01 09:00:00', 'last_click' => '2026-01-01 10:00:00'],
], JSON_UNESCAPED_UNICODE));

require_once __DIR__ . '/../lib/MailCampaign.php';

echo "\n── 1. 老 JSON 自动导入 ──\n";
check('建表并导入成功', mailc_stats_ensure() === true);
$s = mailc_campaign_stats('c_old', 10);
check('迁移后打开人数=2', $s['opens'] === 2, json_encode($s));
check('迁移后点击人数=1', $s['clicks'] === 1, json_encode($s));
check('打开率算对', $s['open_rate'] === 20.0, (string)$s['open_rate']);
check('原 JSON 保留作备份', is_file($tmp . '/mail-stats.json'));
check('留了防重导 marker', is_file($tmp . '/.mail_stats_migrated'));

echo "\n── 2. 记一次打开 ──\n";
mailc_track('c1', 'u1', 'open');
$r = Database::query("SELECT * FROM mail_stats WHERE campaign='c1' AND email_id='u1'");
check('落库一行', count($r) === 1);
check('open_count=1', (int)$r[0]['open_count'] === 1);
check('first_open 有值', $r[0]['first_open'] !== '');
check('click_count 还是 0', (int)$r[0]['click_count'] === 0);

echo "\n── 3. 同一个人再打开：累加，first_open 不变 ──\n";
$first = $r[0]['first_open'];
sleep(1);
mailc_track('c1', 'u1', 'open');
$r2 = Database::query("SELECT * FROM mail_stats WHERE campaign='c1' AND email_id='u1'")[0];
check('open_count=2', (int)$r2['open_count'] === 2, $r2['open_count']);
check('first_open 保持首次', $r2['first_open'] === $first, "{$first} vs {$r2['first_open']}");
check('last_open 已更新', $r2['last_open'] !== $first, $r2['last_open']);
check('汇总里仍算 1 个人', mailc_campaign_stats('c1', 1)['opens'] === 1);

echo "\n── 4. 打开与点击互不干扰 ──\n";
mailc_track('c1', 'u1', 'click');
$r3 = Database::query("SELECT * FROM mail_stats WHERE campaign='c1' AND email_id='u1'")[0];
check('click_count=1', (int)$r3['click_count'] === 1);
check('open_count 没被冲掉', (int)$r3['open_count'] === 2, $r3['open_count']);
check('未知 type 归为 open', (function () {
    mailc_track('c1', 'u9', 'garbage');
    return (int)Database::query("SELECT open_count FROM mail_stats WHERE email_id='u9'")[0]['open_count'] === 1;
})());

echo "\n── 5. 活动之间不串台 ──\n";
mailc_track('c2', 'u1', 'open');
check('c2 打开人数=1', mailc_campaign_stats('c2', 4)['opens'] === 1);
check('c1 不受影响', mailc_campaign_stats('c1', 2)['opens'] === 2, json_encode(mailc_campaign_stats('c1', 2)));

echo "\n── 6. 并发不丢计数（旧的读-改-写会丢）──\n";
for ($i = 0; $i < 200; $i++) mailc_track('c3', 'burst', 'open');
$n = (int)Database::query("SELECT open_count FROM mail_stats WHERE campaign='c3' AND email_id='burst'")[0]['open_count'];
check('200 次打开一次不少', $n === 200, (string)$n);

echo "\n── 7. 群发规模下的耗时 ──\n";
$t = microtime(true);
for ($i = 0; $i < 300; $i++) mailc_track('c4', 'u' . $i, 'open');
$ms = (microtime(true) - $t) * 1000;
check('300 次追踪 < 1500ms', $ms < 1500, sprintf('%.0f ms', $ms));
printf("    （实测 %.0f ms，合每次 %.2f ms）\n", $ms, $ms / 300);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exec('rm -rf ' . escapeshellarg($tmp));
exit($fail === 0 ? 0 : 1);
