<?php
/**
 * AI 挂了站点不能跟着挂（docs/ROADMAP.md 阶段一第 3 件）
 *   php tests/ai_resilience_test.php
 *
 * 这份测试守两条规矩，两条都是"结构性"的——不是修某个 bug，是让这类 bug 回不来：
 *
 *   规矩一：**任何调 AI 的接口都必须先验身份或限流。**
 *           AI 调用每次都花站长的钱。一个公开、免登录、直连模型的端点，
 *           等于把钱包挂在门外。已经踩过三次：site-agent（限流）、
 *           survey-ai（登录+权限）、assistant（登录）——后者还顺带把公司知识库
 *           和"创建自动化流程"对匿名请求开放了。
 *
 *   规矩二：**AI 不可用时，界面要照常显示，且不能谎报成功。**
 *           调用方拿到结果必须先判 ok；失败要么降级、要么明说没成，
 *           不能把失败对象当数据往下游传（GrowthFlywheel 原来就是这样）。
 */

require_once __DIR__ . '/../lib/AiCenter.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

/** 剥掉注释再看代码——文档注释里提到 AiCenter 不算调用。 */
function code_of(string $file): string {
    $out = '';
    foreach (token_get_all(file_get_contents($file)) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) { $out .= "\n"; continue; }
        $out .= is_array($t) ? $t[1] : $t;
    }
    return $out;
}

$root = __DIR__ . '/..';

echo "\n── 1. 规矩一：调 AI 的 API 端点必须有门 ──\n";
$AI_MARKS   = ['AiCenter::', 'geo_ai_call(', 'AIBusiness::', 'siteagent_answer(', 'askdata_ask('];
$GATE_MARKS = ['require_login(', 'require_perm(', 'has_perm(', 'admin_login', 'member_current(',
               'RateLimiter::', 'apiTokens', 'api_tokens', 'hash_equals('];
$ungated = [];
foreach (glob($root . '/api/*.php') as $f) {
    $code = code_of($f);
    $callsAi = false; foreach ($AI_MARKS   as $k) if (strpos($code, $k) !== false) { $callsAi = true; break; }
    if (!$callsAi) continue;
    $gated  = false; foreach ($GATE_MARKS as $k) if (strpos($code, $k) !== false) { $gated  = true; break; }
    if (!$gated) $ungated[] = basename($f);
}
check('没有"公开且直连 AI"的端点', empty($ungated), implode(' ', $ungated));

echo "\n── 2. 三个踩过坑的端点各自的门 ──\n";
$sa = code_of($root . '/api/site-agent.php');
check('site-agent：按 IP 限流（它本来就该公开）', strpos($sa, 'RateLimiter::throttle') !== false);
$sv = code_of($root . '/api/survey-ai.php');
check('survey-ai：要登录', strpos($sv, 'require_login(') !== false);
check('survey-ai：要 survey 权限', strpos($sv, "require_perm('survey')") !== false);
check('survey-ai：不再自建 curl', strpos($sv, 'curl_init') === false);
$as = code_of($root . '/api/assistant.php');
check('assistant：要登录', strpos($as, 'require_login(') !== false);
check('assistant：不再自建 curl', strpos($as, 'curl_init') === false);
check('assistant：登录检查在创建流程之前',
      strpos($as, 'require_login(') < strpos($as, 'copilot_create_flow'),
      '登录必须先于任何写操作');

echo "\n── 3. 全仓不再有绕过统一入口的 AI 直连 ──\n";
$direct = [];
foreach (array_merge(glob($root . '/lib/*.php'), glob($root . '/api/*.php'), glob($root . '/admin/*.php')) as $f) {
    if (basename($f) === 'AiCenter.php') continue;
    $code = code_of($f);
    if (strpos($code, '/chat/completions') !== false || strpos($code, 'chatcompletion_v2') !== false
        || strpos($code, 'anthropic-version') !== false) {
        $direct[] = basename($f);
    }
}
check('没有第二处自建的模型请求', empty($direct), implode(' ', $direct));

echo "\n── 4. 规矩二：拿到 AI 结果必须先判 ok ──\n";
$unguarded = [];
foreach (array_merge(glob($root . '/lib/*.php'), glob($root . '/api/*.php'), glob($root . '/admin/*.php')) as $f) {
    $lines = explode("\n", code_of($f));
    foreach ($lines as $i => $ln) {
        if (strpos($ln, 'AiCenter::chat(') === false && strpos($ln, 'AiCenter::json(') === false) continue;
        $w = implode("\n", array_slice($lines, $i, 20));
        // 认可三种守法：判 ok / 判 text 非空后回落 / 直接把 ok 透传给调用方
        $okGuard   = preg_match('/\[\s*.ok.\s*\]/', $w);
        $textGuard = preg_match('/\$\w+\s*!==\s*\'\'\s*\?/', $w);
        if (!$okGuard && !$textGuard) $unguarded[] = basename($f) . ':' . ($i + 1);
    }
}
check('每个调用点都判了 ok（或等价的空值回落）', empty($unguarded), implode(' ', $unguarded));

echo "\n── 5. 失败时不许谎报成功 ──\n";
$fw = code_of($root . '/lib/GrowthFlywheel.php');
check('飞轮：AI 失败时 status 不是 ok',
      preg_match("/empty\(\\\$resp\['ok'\]\)/", $fw) === 1);
check('飞轮：失败时降级为 degraded 并说明原因',
      strpos($fw, "'status' => 'degraded'") !== false);
check('飞轮：成功时传 data 而不是整个响应对象',
      strpos($fw, "'data' => \$resp['data'] ?? []") !== false);
$sg = code_of($root . '/lib/SkillGuard.php');
check('安全预审：AI 没审成会明写出来（不静默当通过）',
      strpos($sg, 'AI 预审未完成') !== false);

echo "\n── 6. 额度用尽是可识别的降级信号，不是普通报错 ──\n";
$ac = code_of($root . '/lib/AiCenter.php');
check('额度用尽返回 budget_exceeded 标记', strpos($ac, "'budget_exceeded' => true") !== false);
check('额度闸门在发请求之前', strpos($ac, 'ai_budget_check(') < strpos($ac, 'curl_init('));
check('SiteAgent 有无 AI 时的兜底答复',
      strpos(code_of($root . '/lib/SiteAgent.php'), '站内找到相关资料') !== false);
check('飞轮把额度用尽和一般失败分开提示', strpos($fw, 'budget_exceeded') !== false);

echo "\n── 7. 多轮上文清洗（会话历史是用户输入，不能带 system 混进去）──\n";
check('history 只允许 user/assistant', strpos($ac, "=== 'assistant' ? 'assistant' : 'user'") !== false);
check('三家分支都拼上了 history', substr_count($ac, '$history') >= 4, (string)substr_count($ac, '$history'));

echo "\n── 8. 访客侧超时不许长于后台批处理 ──\n";
check('public < batch', AiCenter::TIER_TIMEOUTS['public'] < AiCenter::TIER_TIMEOUTS['batch']);
check('public 档不超过 15 秒', AiCenter::TIER_TIMEOUTS['public'] <= 15);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exit($fail === 0 ? 0 : 1);
