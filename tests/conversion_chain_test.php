<?php
/**
 * 转化链路契约 —— CAPI worker 调度 / 漏斗可配置 / 不再有死步骤
 *   php tests/conversion_chain_test.php
 *
 * 之前：conv_process() 无调用方（回传只进不出）、漏斗硬编码含从未采集的 form_view。
 * 这里验证：worker 有无平台时安全跳过、有平台时按结果记 sent/failed 并重试到 failed、
 * 漏斗默认只用真实采集的事件且可配置。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-conv-' . getmypid());
@mkdir(DATA_DIR . '/cdp', 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ConversionApi.php';
require_once __DIR__ . '/../lib/FunnelDefinition.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "转化链路\n";

// ── 漏斗定义 ──
$def = funnel_steps_default();
check('默认漏斗不再含未采集的 form_view', !in_array('form_view', $def, true));
check('默认漏斗包含真实采集步骤', in_array('page_view', $def, true) && in_array('form_submit', $def, true) && in_array('purchase', $def, true));
check('未保存时读默认', funnel_steps() === $def);
$r = funnel_steps_save(['page_view', 'purchase']);
check('保存合法步骤', $r['ok'] && funnel_steps() === ['page_view', 'purchase']);
$r2 = funnel_steps_save(['page_view', 'not_an_event']);
check('少于 2 个有效步骤被拒', !$r2['ok']);

// ── CAPI worker ──
json_write(DATA_DIR . '/conversion_events.json', [
    ['id' => 'c1', 'event_name' => 'purchase', 'status' => 'pending', 'attempts' => 0, 'value' => 100],
]);
$skip = conv_process();
check('无启用平台 → 安全跳过', ($skip['skipped'] ?? '') === 'no_enabled_platform');

json_write(DATA_DIR . '/ad_platforms.json', [
    ['enabled' => true, 'platform' => 'unknown_xyz'],   // 无 endpoint → 不触发网络
]);
$r1 = conv_process();
check('有平台但发送失败 → 计 failed', ($r1['failed'] ?? 0) === 1 && ($r1['sent'] ?? -1) === 0);
$ev = json_read(DATA_DIR . '/conversion_events.json')[0];
check('事件 attempts 递增且状态仍 pending', ($ev['attempts'] ?? 0) === 1 && ($ev['status'] ?? '') === 'pending');
conv_process(); conv_process();
$ev = json_read(DATA_DIR . '/conversion_events.json')[0];
check('重试 3 次后标记 failed', ($ev['status'] ?? '') === 'failed');

// 已发送的不再重复处理
json_write(DATA_DIR . '/conversion_events.json', [['id' => 'c2', 'status' => 'sent']]);
$r2 = conv_process();
check('已 sent 的事件不再处理', ($r2['sent'] ?? 0) === 0 && ($r2['failed'] ?? 0) === 0);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
