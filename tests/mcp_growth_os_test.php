<?php
/**
 * T2-13 验收：增长 OS 经 MCP 对外（mcp-server 工具清单与调用契约）
 *   php tests/mcp_growth_os_test.php
 *
 * 不启动完整 PHP 环境，只静态验证：工具已注册、有 schema、mcp_call 有分支、
 * 每个新工具都能在源码里找到对应实现（防"声明了却没实现"的空壳工具）。
 */
$src = file_get_contents(__DIR__ . '/../mcp-server.php');

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$newTools = ['growth_next_best_action','growth_goal_status','growth_conversion_truth','growth_ask_data'];
$ecoTools = ['contributions_list','contributions_recommend'];

echo "\n── 1. 增长 OS 工具已注册 ──\n";
foreach ($newTools as $t) {
    check("声明 {$t}", strpos($src, "'name' => '{$t}'") !== false);
}

echo "\n── 2. 每个工具都有实现分支（不是空壳）──\n";
foreach (array_merge($newTools, $ecoTools) as $t) {
    check("实现 case '{$t}'", strpos($src, "case '{$t}'") !== false);
}

echo "\n── 3. 契约完整性 ──\n";
check('ask_data 声明必填 question', strpos($src, "'required'=>['question']") !== false);
check('nba 支持 limit', preg_match("/growth_next_best_action.*?limit/s", $src) === 1);
check('工具总数 >= 18', substr_count($src, "'name' => '") >= 18, (string)substr_count($src, "'name' => '"));

echo "\n── 4. 安全：不暴露原始个体数据 ──\n";
// nba 输出应只含 who/action/module/reason/priority，不含 email 等
check('NBA 输出字段受控', strpos($src, "'who' =>") !== false && strpos($src, "'action' =>") !== false);
check('NBA 不直出 email 字段', preg_match("/growth_next_best_action.*?'email'\s*=>/s", $src) === 0);
check('ask_data 只回 answer', preg_match("/growth_ask_data.*?'answer'=>/s", $src) === 1);

echo "\n── 5. 依赖按需加载（不拖慢其它工具）──\n";
check('nba 内部 require GrowthBrain', preg_match("/case 'growth_next_best_action'.*?GrowthBrain\.php/s", $src) === 1);
check('ask_data 内部 require AskData', preg_match("/case 'growth_ask_data'.*?AskData\.php/s", $src) === 1);

echo "\n── 6. 语法可解析 ──\n";
exec('php -l ' . escapeshellarg(__DIR__ . '/../mcp-server.php') . ' 2>&1', $o, $rc);
check('php -l 通过', $rc === 0, implode(' ', $o));

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exit($fail===0?0:1);
