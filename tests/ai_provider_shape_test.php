<?php
/**
 * 供应商请求/响应形状验收（docs/ROADMAP.md 阶段一）
 *   php tests/ai_provider_shape_test.php
 *
 * 这里钉的是两个"一直是坏的、但没人发现"的地方：
 *   ① Claude 供应商分支把 system 塞进了 messages。Anthropic Messages API 的
 *      system 是**顶层参数**，messages 只接受 user/assistant——原来选 Claude
 *      会被直接 400 拒掉，也就是说这个供应商从来没能用过。
 *   ② api/ai-landing.php 读 $r['content']，而 AiCenter::chat() 返回的是 text，
 *      没有 content 这个键——所以一键生成落地页永远只会返回「AI 生成失败」。
 * 两个都是发不出真实请求就测不到的问题，所以这里用源码断言 + 纯函数断言把它们钉住。
 */

require_once __DIR__ . '/../lib/AiCenter.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

$ai = file_get_contents(__DIR__ . '/../lib/AiCenter.php');

echo "\n── 1. Claude 分支：system 必须是顶层参数 ──\n";
// 截出 claude 分支那段
$claudeBlock = '';
if (preg_match("/providerId === 'claude'\)\s*\{(.*?)\}\s*elseif/s", $ai, $m)) $claudeBlock = $m[1];
check('找得到 claude 分支', $claudeBlock !== '');
check('payload 里有顶层 system', strpos($claudeBlock, "'system' =>") !== false);
check("messages 里不再出现 role=>'system'", strpos($claudeBlock, "'role' => 'system'") === false, $claudeBlock);
check('messages 里只有 user', substr_count($claudeBlock, "'role' => 'user'") === 1);
check('带 max_tokens（Anthropic 必填）', strpos($claudeBlock, "'max_tokens'") !== false);

echo "\n── 2. 分档超时确实用上了 ──\n";
check('curl 用的是分档算出的 timeout', strpos($ai, 'CURLOPT_TIMEOUT => $timeout') !== false);
check('不再硬编码 90', strpos($ai, "CURLOPT_TIMEOUT => \$opts['timeout'] ?? 90") === false);
check('有连接超时上限', strpos($ai, 'CURLOPT_CONNECTTIMEOUT') !== false);

echo "\n── 3. 供应商返回的结构化错误要被识别 ──\n";
check("认 data['error']", strpos($ai, "isset(\$data['error'])") !== false);

echo "\n── 4. 每条返回路径都要记账 ──\n";
check('记账次数 ≥ 5 条路径', substr_count($ai, 'self::meter(') >= 5, (string)substr_count($ai, 'self::meter('));

echo "\n── 5. ai-landing 不再读不存在的 content 键 ──\n";
$landing = file_get_contents(__DIR__ . '/../api/ai-landing.php');
check("不再判 empty(\$r['content'])（那个键根本不存在）",
      strpos($landing, "empty(\$r['content'])") === false);
check("不再把 \$r['content'] 喂给 extractJson",
      strpos($landing, "extractJson(\$r['content'])") === false);
check("改读 \$r['text']", strpos($landing, "\$r['text']") !== false);
check('先判 ok 再判空', strpos($landing, "empty(\$r['ok'])") !== false);

echo "\n── 6. extractJson 要认数组（落地页要的就是数组）──\n";
$arr = AiCenter::extractJson('```json' . "\n" . '[{"type":"hero","title":"标题"}]' . "\n" . '```');
check('markdown 包裹的数组能解析', is_array($arr) && ($arr[0]['type'] ?? '') === 'hero', json_encode($arr));
$arr2 = AiCenter::extractJson('这是我的输出：[{"type":"cta"}] 以上。');
check('杂文里的数组能提取', is_array($arr2) && ($arr2[0]['type'] ?? '') === 'cta', json_encode($arr2));
$obj = AiCenter::extractJson('```json' . "\n" . '{"title":"仍然要认对象"}' . "\n" . '```');
check('对象仍然能解析（没被改坏）', is_array($obj) && ($obj['title'] ?? '') === '仍然要认对象', json_encode($obj));
check('纯文本返回 null', AiCenter::extractJson('完全没有 JSON') === null);

echo "\n── 7. 没有 AI 调用绕过统一入口 ──\n";
$geo = file_get_contents(__DIR__ . '/../lib/GeoSystem.php');
check('geo_ai_call 走 AiCenter', strpos($geo, 'AiCenter::chat(') !== false);
check('geo_ai_call 不再自建 chat/completions',
      preg_match("/function geo_ai_call.*?chat\/completions/s", $geo) === 0);
check('geo_ai_call 打了记账标签', strpos($geo, "'feature' => 'geo_writer'") !== false);

echo "\n── 8. 每个 AI 调用点都打了 feature 标签 ──\n";
$untagged = [];
foreach (['lib', 'admin', 'api'] as $dir) {
    foreach (glob(__DIR__ . '/../' . $dir . '/*.php') as $f) {
        // 先用 tokenizer 剥掉注释——文档注释里提到 AiCenter::chat() 不算调用点
        $code = '';
        foreach (token_get_all(file_get_contents($f)) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) { $code .= "\n"; continue; }
            $code .= is_array($t) ? $t[1] : $t;
        }
        $lines = explode("\n", $code);
        foreach ($lines as $i => $ln) {
            if (strpos($ln, 'AiCenter::chat(') === false && strpos($ln, 'AiCenter::json(') === false) continue;
            // opts 可能在调用前几行组好（如 geo_ai_call），所以前后都看
            $window = implode("\n", array_slice($lines, max(0, $i - 6), 16));
            if (strpos($window, "'feature'") === false) $untagged[] = basename($f) . ':' . ($i + 1);
        }
    }
}
check('没有漏标的调用点', empty($untagged), implode(' ', $untagged));

echo "\n── 9. 公开接口有限流 ──\n";
$sa = file_get_contents(__DIR__ . '/../api/site-agent.php');
check('site-agent 引入了限流器', strpos($sa, 'RateLimiter.php') !== false);
check('按 IP 限流', strpos($sa, 'RateLimiter::throttle') !== false);
check('问答档比转人工档宽松', strpos($sa, "\$action === 'handoff' ? 5 : 20") !== false);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exit($fail === 0 ? 0 : 1);
