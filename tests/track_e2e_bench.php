<?php
/**
 * 端到端写路径实测：真的调 CdpSystem::track()，不是复刻。
 *
 *   php tests/track_e2e_bench.php [已有行数]     默认 520000（生产量级）
 *
 * 与 tests/events_writepath_bench.php 的分工：
 *   - events_writepath_bench 复刻两个动作，用来「先量、定位病灶」，跑得快；
 *   - 本脚本启动真实环境、走完整 track() 链路（同意门→事件字典→身份合并→
 *     插件过滤→写入→画像更新→分群评估），用来「验收改动真的生效了」。
 *
 * 它在一个临时 DATA_DIR 上跑，不碰任何真实数据。
 */

$seed = (int)($argv[1] ?? 520000);
$tmp  = sys_get_temp_dir() . '/of-e2e-' . getmypid();
@mkdir($tmp . '/cdp', 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['REQUEST_URI'] = '/bench';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/CdpSystem.php';

echo "OpenFlow · 埋点写路径端到端实测\n" . str_repeat('=', 58) . "\n";
echo "临时数据目录：{$tmp}\n";

// ── 灌入已有事件，模拟生产表 ──
echo "灌入 " . number_format($seed) . " 行历史事件…";
$conn = Database::conn();
$conn->beginTransaction();
$st = $conn->prepare("INSERT INTO events (event,page,uid,member_id,props,ip,created_at,session_id,message_id,ts) VALUES (?,?,?,?,?,?,?,?,?,?)");
$props = json_encode(['src' => 'organic', 'utm' => 'x', 'b' => '某个中文属性值'], JSON_UNESCAPED_UNICODE);
$now = date('Y-m-d H:i:s');
for ($i = 0; $i < $seed; $i++) {
    $st->execute(['pageview', '/p/' . ($i % 500), 'v_' . ($i % 20000), '', $props, '1.2.3.4', $now, 's', 'seed_' . $i, time() * 1000]);
}
$conn->commit();
echo " 完成\n\n";

function ms(callable $fn): float { $t = microtime(true); $fn(); return (microtime(true) - $t) * 1000; }

// ── 单条 track() ──
$warm = ms(fn() => CdpSystem::track('pageview', ['src' => 'bench'], 'v_warm'));   // 预热（建表/索引/首次连接）
$single = [];
for ($i = 0; $i < 20; $i++) {
    $single[] = ms(fn() => CdpSystem::track('pageview', ['src' => 'bench', 'i' => $i], 'v_bench_' . $i));
}
sort($single);
$mid = $single[(int)(count($single) / 2)];
$p95 = $single[(int)(count($single) * 0.95)];

// ── 一次页面上报（20 条批量）──
$batch = [];
for ($i = 0; $i < 20; $i++) $batch[] = ['event' => 'pageview', 'properties' => ['i' => $i], 'visitor_id' => 'v_batch'];
$batchMs = ms(fn() => CdpSystem::trackBatch($batch));

$peak = memory_get_peak_usage(true) / 1048576;

printf("单条 track()   中位数 %8.2f ms      p95 %8.2f ms\n", $mid, $p95);
printf("批量 20 条     总计   %8.2f ms      每条 %6.2f ms\n", $batchMs, $batchMs / 20);
printf("进程峰值内存           %8.1f MB\n\n", $peak);

echo "对照（改造前实测，同样 52 万行）：单条 134.3 ms · 批量 20 条约 2686 ms · 26 MB\n";
$ok = $mid < 20 && $batchMs < 400;
echo $ok ? "\n✅ 写放大已消除\n" : "\n❌ 仍然偏慢，需要复查\n";

// 清理
exec('rm -rf ' . escapeshellarg($tmp));
exit($ok ? 0 : 1);
