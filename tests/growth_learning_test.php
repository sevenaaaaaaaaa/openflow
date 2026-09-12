<?php
/**
 * 自生长回流契约 —— 结果学习 + 提议加权 + 闭环接线
 *   php tests/growth_learning_test.php
 *
 * 自生长的关键是「结果改变下次行为」：有效打法提权、无效打法降权，
 * 且采纳后的完成/忽略真的回写决策轨迹。这条测试守住这两件事。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-glearn-' . getmypid());
@mkdir(DATA_DIR . '/growth', 0777, true);

function json_read(string $f): array { return is_file($f) ? ((array)json_decode((string)file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/GrowthLearning.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "自生长回流\n";

check('无历史 → 不加权', growth_learning_boost('MA', '老客复购召回') === 0);

// 有效 → 正向加权
growth_learning_verdict('MA', '老客复购召回', 'effective');
growth_learning_verdict('MA', '老客复购召回', 'done');
check('有效+完成 → 正加权', growth_learning_boost('MA', '老客复购召回') > 0);

// 无效 → 负向加权
growth_learning_verdict('Sales', '沉默高意向挽回', 'ineffective');
growth_learning_verdict('Sales', '沉默高意向挽回', 'dismissed');
check('无效+忽略 → 负加权', growth_learning_boost('Sales', '沉默高意向挽回') < 0);

// 钳位
for ($i = 0; $i < 20; $i++) growth_learning_verdict('Content', '内容培育 · 补全画像', 'effective');
$b = growth_learning_boost('Content', '内容培育 · 补全画像');
check('加权钳位在 +15 以内', $b <= 15 && $b > 0, (string)$b);

$snap = growth_learning_snapshot();
check('快照列出有效打法', in_array('老客复购召回', $snap['effective'], true));
check('快照列出无效打法', in_array('沉默高意向挽回', $snap['ineffective'], true));

// 模块级聚合
check('模块级得分存在', growth_learning_boost('MA') > 0 && growth_learning_boost('Sales') < 0);

// ── 闭环接线（结构性守卫，防回退）──
$ga = file_get_contents(__DIR__ . '/../lib/GrowthAction.php');
check('采纳时保存 dtrace_id', strpos($ga, "dtrace_id") !== false);
check('完成/忽略回写 dtrace_outcome', strpos($ga, 'dtrace_outcome(') !== false);
check('完成/忽略写入学习层', strpos($ga, 'growth_learning_verdict(') !== false);
$gb = file_get_contents(__DIR__ . '/../lib/GrowthBrain.php');
check('提议应用学习加权', strpos($gb, 'growth_learning_boost(') !== false);
$ma = file_get_contents(__DIR__ . '/../lib/MainlineAi.php');
check('控制台执行留痕 trace', strpos($ma, 'mainline_ai_record_trace(') !== false);
check('控制台回执可评估回流', strpos($ma, 'mainline_ai_evaluate(') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
