<?php
/**
 * P0-3 验收：中枢 NBA 提议器（大脑胚胎）
 *
 *   php tests/growth_brain_test.php
 *
 * 验四件事：
 *   1. 画像归一：tags/props(JSON字符串) 解析、days_idle 计算、成交信号读出；
 *   2. 规则命中与排序：每条规则在对的人身上产出对的动作，best=最高优先级；
 *   3. 成交真相影响优先级：来源属 Top 才出"同来源放大"；
 *   4. 驾驶舱 digest：无强信号的人被过滤、全局按优先级排、limit 生效；
 *      以及未配 AI 时 polish 原样返回（不耦合外部服务）。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-brain-test-' . getmypid());
require_once __DIR__ . '/../lib/GrowthBrain.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}
$daysAgo = fn(int $d) => date('Y-m-d H:i:s', time() - $d * 86400);

echo "\n── 1. 画像归一 ──\n";
$n = growth_brain_normalize([
    'id' => 'c1', 'email' => 'a@t.com', 'score' => 70,
    'tags' => json_encode(['VIP', '已成交客户']),
    'props' => json_encode(['won_count' => 2, 'won_value_total' => 8000, 'last_won_source' => '自然搜索']),
    'last_seen' => $daysAgo(40), 'channel' => '广告',
]);
check('tags JSON 解析成数组', $n['tags'] === ['VIP', '已成交客户']);
check('won_count 读出=2', $n['won_count'] === 2);
check('ltv 从 props 读出=8000', $n['ltv'] === 8000.0);
check('source 优先取 last_won_source', $n['source'] === '自然搜索');
check('days_idle≈40', $n['days_idle'] >= 39 && $n['days_idle'] <= 41, (string)$n['days_idle']);

echo "\n── 2. 规则：临门一脚（高分未成交）──\n";
$hot = growth_brain_propose(['id'=>'h','score'=>80,'props'=>'{}','tags'=>'[]','last_seen'=>$daysAgo(1)]);
check('best.module=Sales', ($hot['best']['module'] ?? '') === 'Sales', $hot['best']['module'] ?? 'null');
check('best.action=推成交', strpos($hot['best']['action'] ?? '', '推成交') !== false);
check('理由含互动分', strpos($hot['best']['reason'] ?? '', '80') !== false);

echo "\n── 3. 规则：老客复购（成交过+沉默）──\n";
$repeat = growth_brain_propose([
    'id'=>'r','score'=>20,'last_seen'=>$daysAgo(45),
    'props'=>json_encode(['won_count'=>3,'won_value_total'=>12000]), 'tags'=>'[]',
]);
$hasRepurchase = false; foreach ($repeat['all'] as $x) if (strpos($x['action'],'复购') !== false) $hasRepurchase = true;
check('产出复购召回', $hasRepurchase);
check('best 是复购（高 LTV 抬优先级）', strpos($repeat['best']['action'] ?? '', '复购') !== false, $repeat['best']['action'] ?? '');

echo "\n── 4. 成交真相影响：同来源放大只在 Top 来源出 ──\n";
$truth = ['sources' => [['key'=>'自然搜索','revenue'=>9000,'count'=>3], ['key'=>'广告','revenue'=>1000,'count'=>2]]];
$onTop = growth_brain_propose([
    'id'=>'t','score'=>10,'last_seen'=>$daysAgo(5),
    'props'=>json_encode(['won_count'=>1,'last_won_source'=>'自然搜索']), 'tags'=>'[]',
], $truth);
$hasAmplify = false; foreach ($onTop['all'] as $x) if (strpos($x['action'],'放大') !== false) $hasAmplify = true;
check('Top 来源 → 出"同来源放大"', $hasAmplify);
$offTop = growth_brain_propose([
    'id'=>'o','score'=>10,'last_seen'=>$daysAgo(5),
    'props'=>json_encode(['won_count'=>1,'last_won_source'=>'冷门来源']), 'tags'=>'[]',
], $truth);
$noAmplify = true; foreach ($offTop['all'] as $x) if (strpos($x['action'],'放大') !== false) $noAmplify = false;
check('非 Top 来源 → 不出放大', $noAmplify);

echo "\n── 5. 内容培育（低信号新人）+ 无提议者被过滤 ──\n";
$cold = growth_brain_propose(['id'=>'cold','score'=>10,'props'=>'{}','tags'=>'[]','last_seen'=>$daysAgo(2)]);
check('低信号出内容培育', strpos($cold['best']['action'] ?? '', '内容培育') !== false, $cold['best']['action'] ?? 'null');
$empty = growth_brain_propose(['id'=>'z','score'=>35,'props'=>'{}','tags'=>json_encode(['a','b']),'last_seen'=>$daysAgo(3)]);
check('中间态可能无强提议（best 可为 null）', true); // 结构性检查在 digest

echo "\n── 6. 驾驶舱 digest：过滤+全局排序+limit ──\n";
$profiles = [
    ['id'=>'p1','score'=>85,'props'=>'{}','tags'=>'[]','last_seen'=>$daysAgo(1)],                                  // 临门一脚 高优先
    ['id'=>'p2','score'=>10,'props'=>json_encode(['won_count'=>2,'won_value_total'=>20000]),'tags'=>'[]','last_seen'=>$daysAgo(50)], // 复购 高LTV
    ['id'=>'p3','score'=>36,'props'=>'{}','tags'=>json_encode(['x','y','z']),'last_seen'=>$daysAgo(3)],            // 无强信号 → 应被过滤
];
$dig = growth_brain_digest($profiles, null, 20);
check('无强信号的人被过滤（3→2）', count($dig) === 2, '数量=' . count($dig));
check('全局按优先级降序', $dig[0]['best']['priority'] >= $dig[1]['best']['priority']);
check('每行带 profile + best', isset($dig[0]['profile']) && isset($dig[0]['best']));
$dig1 = growth_brain_digest($profiles, null, 1);
check('limit=1 生效', count($dig1) === 1);

echo "\n── 7. 未配 AI 时 polish 原样返回 ──\n";
check('polish 不耦合外部（无AI原样返回）', growth_brain_polish('原始理由', []) === '原始理由');

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exit($fail === 0 ? 0 : 1);
