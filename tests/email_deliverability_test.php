<?php
/**
 * 邮件送达率 + 归因 契约
 *   php tests/email_deliverability_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-edeliv-' . getmypid());
@mkdir(DATA_DIR . '/email', 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/EmailDeliverability.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "邮件送达率\n";

check('邮箱归一化', email_norm(' Foo@Bar.COM ') === 'foo@bar.com');
check('初始不在抑制名单', !email_is_suppressed('a@b.com'));

email_suppress('a@b.com', 'manual');
check('加入后即被抑制', email_is_suppressed('A@B.com'));
check('抑制则不能营销发送', !email_can_marketing_send('a@b.com'));
email_unsuppress('a@b.com');
check('移出后恢复', email_can_marketing_send('a@b.com'));

email_record_bounce('bad@b.com', 'hard');
check('硬退信 → 自动抑制', email_is_suppressed('bad@b.com'));
email_record_bounce('soft@b.com', 'soft');
check('软退信 → 不抑制', !email_is_suppressed('soft@b.com'));

email_record_complaint('angry@b.com');
check('投诉 → 自动抑制', email_is_suppressed('angry@b.com'));

email_record_sent(10);
$st = email_deliverability_stats();
check('已发送计数', $st['sent'] === 10);
check('退信计数=2', $st['bounces'] === 2 && $st['hard_bounces'] === 1);
check('抑制名单计数=2（硬退信+投诉；退订/软退信不计）', $st['suppressed'] === 2);
check('退信率计算', $st['bounce_rate'] === 20.0);

// 有效期检查返回结构
$dns = email_dns_auth('example.com');
check('DNS 自检返回四项', isset($dns['spf'], $dns['dkim'], $dns['dmarc'], $dns['mx']));

// 归因
email_attr_record('nl_c1', 299, 'o1');
email_attr_record('nl_c1', 100, 'o2');
email_attr_record('nl_c2', 999, 'o3');
$rep = email_attribution_report();
check('归因按营收排序', ($rep[0]['campaign'] ?? '') === 'nl_c2' && (float)$rep[0]['revenue'] === 999.0);
check('同活动累加', (float)$rep[1]['revenue'] === 399.0 && (int)$rep[1]['orders'] === 2);

// 结构性守卫：发送路径接抑制闸门 + 归因接线
$auto = file_get_contents(__DIR__ . '/../lib/AutomationSystem.php');
check('自动化邮件接抑制闸门', strpos($auto, 'email_can_marketing_send(') !== false);
$mc = file_get_contents(__DIR__ . '/../lib/MailCampaign.php');
check('定时群发接抑制闸门', strpos($mc, 'email_can_marketing_send(') !== false);
$shop = file_get_contents(__DIR__ . '/../lib/ShopSystem.php');
check('订单记录邮件活动', strpos($shop, 'mail_campaign') !== false);
check('支付后归因邮件收入', strpos($shop, 'email_attr_record(') !== false);
$track = file_get_contents(__DIR__ . '/../api/mail-track.php');
check('点击打邮件活动标记', strpos($track, 'fc_mail_c') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
