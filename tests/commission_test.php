<?php
/**
 * T0-5 验收：统一分成/结算政策层（CommissionPolicy）
 *
 *   php tests/commission_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-comm-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { if (!is_file($f)) return []; $d = json_decode((string)file_get_contents($f), true); return is_array($d) ? $d : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/CommissionPolicy.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 默认值沿用现状（行为不变）──\n";
check('平台费率默认 0.10', commission_platform_rate() === 0.10);
check('平台费 ¥1000 → ¥100', commission_platform_fee(1000) === 100.0);
check('默认分销 20%', commission_distribution_rate() === 20.0);
check('最低提现 100', commission_min_withdraw() === 100.0);

echo "\n── 2. 统一分账口径 ──\n";
$s = commission_split(1000, true);   // 有推广人，默认 20%
check('平台费 100', $s['platform_fee'] === 100.0);
check('分销佣金 200', $s['commission'] === 200.0);
check('作者到手 700', $s['author_amount'] === 700.0, (string)$s['author_amount']);
$s2 = commission_split(1000, false);  // 无推广人
check('无推广人佣金 0', $s2['commission'] === 0.0);
check('无推广人作者到手 900', $s2['author_amount'] === 900.0);
$s3 = commission_split(1000, true, 30);  // 单品覆盖 30%
check('单品覆盖分销率 30%→¥300', $s3['commission'] === 300.0);
check('覆盖后作者 600', $s3['author_amount'] === 600.0);

echo "\n── 3. 保存后各处生效 ──\n";
commission_policy_save(['platform_fee_rate' => 0.15, 'distribution_rate' => 10, 'min_withdraw' => 50]);
check('平台费率变 0.15', commission_platform_rate() === 0.15);
check('平台费 ¥1000 → ¥150', commission_platform_fee(1000) === 150.0);
check('分销率变 10%', commission_distribution_rate() === 10.0);
check('提现变 50', commission_min_withdraw() === 50.0);
$s4 = commission_split(1000, true);
check('新政策分账：150/100/750', $s4['platform_fee']===150.0 && $s4['commission']===100.0 && $s4['author_amount']===750.0, json_encode($s4));

echo "\n── 4. 边界钳制 ──\n";
commission_policy_save(['platform_fee_rate' => 5, 'distribution_rate' => 999, 'min_withdraw' => -10]);
check('费率钳到 ≤0.9', commission_platform_rate() === 0.9);
check('分销钳到 ≤100', commission_distribution_rate() === 100.0);
check('提现钳到 ≥0', commission_min_withdraw() === 0.0);

echo "\n── 5. 分账不为负 ──\n";
commission_policy_save(['platform_fee_rate' => 0.9, 'distribution_rate' => 100]);
$s5 = commission_split(100, true);  // 平台90 + 分销100 > 100
check('作者到手钳到 0(不为负)', $s5['author_amount'] === 0.0, (string)$s5['author_amount']);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@unlink(DATA_DIR . '/commission.json'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
