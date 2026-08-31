<?php
/**
 * AI 电表与保险丝验收（docs/ROADMAP.md 阶段一第 2 件）
 *   php tests/ai_budget_test.php
 *
 * 钉住的性质：
 *   ① 记账：每次调用能按功能/档位/模型拆开，token 是实测值
 *   ② 单价：没配价就不猜（成本为 0），配了才算钱；支持前缀匹配
 *   ③ 保险丝：额度用尽拦住后续调用，且访客档先撞自己那道更低的闸
 *   ④ 分档超时：访客界面不允许用 90 秒
 *   ⑤ usage 解析：三家的字段名都能认
 */

$tmp = sys_get_temp_dir() . '/of-aib-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiBudget.php';
require_once __DIR__ . '/../lib/AiCenter.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

echo "\n── 1. 默认不设上限（不给别人的生产环境塞一个拍脑袋的数）──\n";
$b = ai_budget_settings();
check('日成本上限默认 0', $b['daily_cost_cap'] === 0.0);
check('日 token 上限默认 0', $b['daily_token_cap'] === 0);
check('访客调用次数默认 500（唯一默认开着的闸）', $b['public_call_cap'] === 500);
check('无消费时闸门放行', ai_budget_check('admin')['allowed'] === true);
check('访客档也放行', ai_budget_check('public')['allowed'] === true);

echo "\n── 2. 单价：没配就不猜 ──\n";
check('未配价的模型成本为 0', ai_estimate_cost('some-model', 1000000, 1000000) === 0.0);
ai_price_save('deepseek-chat', 1.0, 2.0);
check('配价后按百万 token 算', ai_estimate_cost('deepseek-chat', 1000000, 0) === 1.0);
check('输入输出分别计价', ai_estimate_cost('deepseek-chat', 500000, 500000) === 1.5,
      (string)ai_estimate_cost('deepseek-chat', 500000, 500000));
check('前缀匹配生效', ai_estimate_cost('deepseek-chat-0801', 1000000, 0) === 1.0,
      (string)ai_estimate_cost('deepseek-chat-0801', 1000000, 0));
check('别的模型仍不猜', ai_estimate_cost('gpt-4o', 1000000, 0) === 0.0);

echo "\n── 3. 记账：按功能拆得开 ──\n";
ai_usage_record(['feature' => 'site_agent', 'tier' => 'public', 'provider' => 'deepseek',
                 'model' => 'deepseek-chat', 'in_tokens' => 1000, 'out_tokens' => 500, 'ms' => 800, 'ok' => true]);
ai_usage_record(['feature' => 'geo_writer', 'tier' => 'batch', 'provider' => 'deepseek',
                 'model' => 'deepseek-chat', 'in_tokens' => 2000, 'out_tokens' => 4000, 'ms' => 9000, 'ok' => true]);
ai_usage_record(['feature' => 'site_agent', 'tier' => 'public', 'provider' => 'deepseek',
                 'model' => 'deepseek-chat', 'in_tokens' => 0, 'out_tokens' => 0, 'ms' => 12000, 'ok' => false, 'error' => '超时']);
$t = ai_spend();
check('今日 3 次调用', $t['calls'] === 3, (string)$t['calls']);
check('token 累加正确', $t['tokens'] === 7500, (string)$t['tokens']);
check('失败计入', $t['failed'] === 1, (string)$t['failed']);
// 1000+2000 输入 ×1 + 500+4000 输出 ×2 = 0.003 + 0.009 = 0.012
check('成本按单价推算', abs($t['cost'] - 0.012) < 0.0001, (string)$t['cost']);

$pub = ai_spend('', 'public');
check('访客档单独统计', $pub['calls'] === 2 && $pub['tokens'] === 1500, json_encode($pub));

$byF = ai_spend_by('feature', 7);
$m = [];
foreach ($byF as $r) $m[$r['k']] = $r;
check('按功能分组', isset($m['site_agent'], $m['geo_writer']), json_encode(array_keys($m)));
check('geo_writer 花得最多排最前', ($byF[0]['k'] ?? '') === 'geo_writer', json_encode($byF[0] ?? []));
check('平均耗时算得出', (float)$m['geo_writer']['avg_ms'] === 9000.0, (string)$m['geo_writer']['avg_ms']);

echo "\n── 4. 未配价模型会被点名 ──\n";
ai_usage_record(['feature' => 'x', 'tier' => 'admin', 'model' => 'mystery-v2', 'in_tokens' => 10, 'out_tokens' => 10, 'ok' => true]);
$np = array_column(ai_models_without_price(30), 'model');
check('mystery-v2 上榜', in_array('mystery-v2', $np, true), json_encode($np));
check('配过价的不上榜', !in_array('deepseek-chat', $np, true), json_encode($np));

echo "\n── 5. 保险丝：全站额度 ──\n";
ai_budget_save(['daily_token_cap' => 5000]);
check('超 token 上限后拦住', ai_budget_check('admin')['allowed'] === false);
check('给出可读原因', ai_budget_check('admin')['reason'] === 'daily_token_cap');
ai_budget_save(['daily_token_cap' => 0, 'daily_cost_cap' => 0.005]);
check('超成本上限后拦住', ai_budget_check('admin')['allowed'] === false);
check('原因是成本', ai_budget_check('admin')['reason'] === 'daily_cost_cap');
ai_budget_save(['daily_cost_cap' => 0]);
check('上限归零后恢复', ai_budget_check('admin')['allowed'] === true);

echo "\n── 6. 保险丝：访客档先撞自己那道更低的闸 ──\n";
ai_budget_save(['public_call_cap' => 2]);
check('访客档被拦（已 2 次）', ai_budget_check('public')['allowed'] === false);
check('后台档不受影响', ai_budget_check('admin')['allowed'] === true);
check('原因是访客调用数', ai_budget_check('public')['reason'] === 'public_call_cap');
check('提示语说清了降级行为',
      mb_strpos(ai_budget_check('public')['hint'], '仅检索站内知识') !== false,
      ai_budget_check('public')['hint']);
ai_budget_save(['public_call_cap' => 500]);

echo "\n── 7. AiCenter 闸门联动：额度用尽直接返回，不发请求 ──\n";
ai_budget_save(['daily_token_cap' => 1]);
$r = AiCenter::chat('系统', '用户', ['feature' => 'test', 'tier' => 'admin']);
check('返回失败', empty($r['ok']));
check('标记 budget_exceeded 供调用方降级', !empty($r['budget_exceeded']));
$before = ai_spend()['calls'];
AiCenter::chat('系统', '用户', ['feature' => 'test', 'tier' => 'admin']);
check('被拦下的调用不记账（没真发出去）', ai_spend()['calls'] === $before, (string)ai_spend()['calls']);
ai_budget_save(['daily_token_cap' => 0]);

echo "\n── 8. 分档超时：访客界面不许等 90 秒 ──\n";
check('public 档 ≤ 15 秒', AiCenter::TIER_TIMEOUTS['public'] <= 15, (string)AiCenter::TIER_TIMEOUTS['public']);
check('admin 档 ≤ 30 秒', AiCenter::TIER_TIMEOUTS['admin'] <= 30);
check('batch 档保留 90 秒', AiCenter::TIER_TIMEOUTS['batch'] === 90);
check('三档递增', AiCenter::TIER_TIMEOUTS['public'] < AiCenter::TIER_TIMEOUTS['admin']
                 && AiCenter::TIER_TIMEOUTS['admin'] < AiCenter::TIER_TIMEOUTS['batch']);

echo "\n── 9. usage 解析：三家字段名都要认 ──\n";
check('OpenAI 兼容', ai_extract_usage(['usage' => ['prompt_tokens' => 12, 'completion_tokens' => 34]]) === [12, 34]);
check('Anthropic', ai_extract_usage(['usage' => ['input_tokens' => 7, 'output_tokens' => 8]]) === [7, 8]);
check('只有总数时保守计入输出', ai_extract_usage(['usage' => ['total_tokens' => 99]]) === [0, 99]);
check('没有 usage 时归零', ai_extract_usage(['choices' => []]) === [0, 0]);

echo "\n── 10. 记账绝不能拖垮 AI 调用本身 ──\n";
$threw = false;
try { ai_usage_record(['feature' => str_repeat('长', 500), 'tier' => '不存在的档', 'model' => null]); }
catch (\Throwable $e) { $threw = true; }
check('脏数据不抛异常', $threw === false);
check('非法档位归一为 admin',
      count(Database::query("SELECT id FROM ai_usage WHERE tier NOT IN ('public','admin','batch')")) === 0);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exec('rm -rf ' . escapeshellarg($tmp));
exit($fail === 0 ? 0 : 1);
