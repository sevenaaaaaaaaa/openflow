<?php
/**
 * 可靠性契约 —— php tests/reliability_contract_test.php
 *
 * 盯两件「界面看起来正常、实际在骗人」的历史问题：
 *
 *  A. Webhook 投递：表单一直让人填「重试次数」，但发送函数里没有重试循环——
 *     对方抖一下事件就永久丢了，界面还显示一切正常。现在必须有
 *     幂等键 / 退避重投 / 死信 / cron 处理入口，缺一不可。
 *
 *  B. A/B 结论：统计量（z、p、置信区间、最小样本量）本来就算得对，
 *     但展示层不管显著与否都用大字报「+X% 转化提升」，等于教用户把噪声当结论。
 *     现在未显著必须中性呈现，并给出还差多少样本。
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  ✗ $msg\n"; } }

/* ══════════ A. Webhook 投递保障 ══════════ */
$whSrc  = file_get_contents("$root/lib/WebhookSystem.php");
$delSrc = file_get_contents("$root/lib/WebhookDelivery.php");
$cron   = file_get_contents("$root/api/cron.php");

ok(is_file("$root/lib/WebhookDelivery.php"), '缺少 lib/WebhookDelivery.php（投递保障层）');
ok(str_contains($whSrc, 'wh_enqueue('), 'trigger() 首投失败后没有入重试队列——「重试次数」又变成了摆设');
ok(str_contains($whSrc, 'X-Webhook-Delivery'), '投递没有带幂等键请求头，接收方无法去重');
ok(str_contains($whSrc, "'delivery_id' => \$deliveryId"), '请求体里没有幂等键');
ok(str_contains($whSrc, 'wh_log_attempt('), '每次投递尝试没有留痕，出问题查不了');
ok(preg_match('/public static function deliver\(/', $whSrc) === 1, 'WebhookSystem 缺少可带幂等键与尝试次数的 deliver()');
ok(!preg_match('/private static function send\(array \$webhook, string \$event, array \$payload\)/', $whSrc),
   '旧的无重试 send() 还在，应已被 deliver() 取代');
ok(str_contains($cron, 'wh_process_queue('), 'cron 没有处理重投队列——入了队也永远不会重发');

foreach (['wh_backoff', 'wh_enqueue', 'wh_to_dead', 'wh_process_queue', 'wh_replay_dead', 'wh_new_delivery_id'] as $fn) {
    ok(str_contains($delSrc, "function $fn("), "投递保障层缺少 $fn()");
}

// 退避必须是递增的，且首档不小于 30s（否则等于没有退避）
require_once "$root/lib/WebhookDelivery.php";
$prev = 0; $mono = true;
for ($i = 1; $i <= 5; $i++) { $d = wh_backoff($i); if ($d <= $prev) $mono = false; $prev = $d; }
ok($mono, '退避不是递增的');
ok(wh_backoff(1) >= 30, '第一档退避太短（' . wh_backoff(1) . 's），起不到退避作用');
ok(wh_backoff(99) === wh_backoff(5), '超出阶梯时应停在最后一档，不应无限增长');

// 幂等键要稳定可辨识
$id1 = wh_new_delivery_id(); $id2 = wh_new_delivery_id();
ok($id1 !== $id2, '幂等键重复了');
ok(str_starts_with($id1, 'whd_'), '幂等键缺少可辨识前缀');

// 后台要能看见并重放，否则死信等于黑洞
$adm = file_get_contents("$root/admin/webhooks.php");
ok(str_contains($adm, 'wh_dead_list('), '后台看不到死信');
ok(str_contains($adm, 'wh_queue_list('), '后台看不到待重投队列');
ok(str_contains($adm, "value=\"replay\""), '后台没有死信重放入口');
ok(str_contains($adm, 'wh_deliveries('), '后台看不到投递明细');

/* ══════════ B. A/B 结论呈现 ══════════ */
$ab = file_get_contents("$root/admin/abtests-stats.php");
ok(str_contains($ab, 'function ab_compute'), 'A/B 统计函数缺失');
ok(str_contains($ab, 'normalCdf'), '缺少正态分布 CDF，算不出 p 值');
ok(str_contains($ab, "'min_sample'"), '没有最小样本量估算');
ok(str_contains($ab, '还不能下结论'), '未显著时没有中性结论——又会把噪声当结论展示');
ok(str_contains($ab, '$sig      = $result[\'significant\'] === true;') || str_contains($ab, "\$sig = \$result['significant'] === true;"),
   '结论呈现没有跟着显著性走');
ok(str_contains($ab, '还需约'), '未显著时没有告诉用户还差多少样本');

// 把统计函数抽出来实算，确保数学没被改坏
preg_match('/function ab_compute.*?\n\}/s', $ab, $m1);
preg_match('/function normalCdf.*?\n\}/s', $ab, $m2);
ok(!empty($m1[0]) && !empty($m2[0]), '无法从页面提取统计函数（结构变了？）');
if (!empty($m1[0]) && !empty($m2[0]) && !function_exists('ab_compute')) {
    eval($m2[0]); eval($m1[0]);
    $mk = function (int $ai, int $ac, int $bi, int $bc) {
        return ab_compute(['t' => ['A' => ['impression' => ['_' => $ai], 'conversion' => ['_' => $ac]],
                                   'B' => ['impression' => ['_' => $bi], 'conversion' => ['_' => $bc]]]], 't');
    };
    ok(abs(normalCdf(1.96) - 0.975) < 0.002, 'normalCdf(1.96) 偏差过大，p 值不可信');
    $big = $mk(5000, 250, 5000, 400);
    ok($big['significant'] === true, '大样本明显差异竟判为不显著');
    ok($big['ci'][0] > 0, '大样本正向差异的置信区间不应跨 0');
    $small = $mk(50, 3, 50, 5);
    ok($small['significant'] === false, '小样本被误判为显著——这正是要防的事');
    ok($small['min_sample'] > 0, '未显著时没有给出所需样本量');
    $same = $mk(1000, 100, 1000, 100);
    ok($same['p'] > 0.9, '两组完全相同时 p 值应接近 1');
    $none = $mk(0, 0, 0, 0);
    ok($none['significant'] === false && $none['p'] === null, '无数据时不应给出显著结论');
}

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
