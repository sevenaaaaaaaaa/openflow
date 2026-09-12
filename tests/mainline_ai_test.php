<?php
/**
 * 控制台 AI 判断契约 —— 白名单校验 / 安全边界 / 回执
 *   php tests/mainline_ai_test.php
 *
 * 小福的判断是"能落地的动作"，所以动作必须过白名单：AI 只能创建合法 Flow、
 * 打开站内路径、设定已注册指标。越权/外链/非法指标一律剔除。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-mlai-' . getmypid());
@mkdir(DATA_DIR . '/mainline', 0777, true);

function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    @mkdir(dirname($f), 0777, true);
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}

// stub AiCenter：MainlineAi 认为 AI 可用，但不真调模型
class AiCenter {
    public static array $queue = [];
    public static function isConfigured(): bool { return true; }
    public static function json(string $s, string $u, array $o = []): array {
        return array_shift(self::$queue) ?? ['ok' => false];
    }
}
// stub 业务快照（避免拉起 CDP/CRM/订单）
function business_context(): array { return ['digest' => '【交易】测试快照']; }

require_once __DIR__ . '/../lib/CopilotActions.php';
require_once __DIR__ . '/../lib/GrowthGoal.php';
require_once __DIR__ . '/../lib/MainlineAi.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else { $fail++; echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

$items = [
    ['id' => 'lead:overdue', 'lane' => 'now', 'title' => '线索超期', 'why' => 'x', 'severity' => 'critical', 'score' => 140, 'source' => '线索'],
    ['id' => 'review:pending', 'lane' => 'today', 'title' => '待审', 'why' => 'y', 'severity' => 'info', 'score' => 60, 'source' => '内容'],
];

echo "控制台 AI 判断\n";

// 基本结构
$ok = mainline_ai_validate([
    'headline' => '先处理超期线索',
    'reasoning' => '有 3 条线索跟进超期，直接影响成交。',
    'focus_id' => 'lead:overdue',
    'plan' => [['step' => '去跟进线索', 'action' => ['type' => 'open', 'label' => '去跟进', 'url' => '/xmp/crm?filter=due']]],
], $items);
check('合法判断通过', is_array($ok) && $ok['headline'] === '先处理超期线索' && count($ok['plan']) === 1);

check('空标题被拒', mainline_ai_validate(['headline' => '', 'reasoning' => 'x', 'plan' => []], $items) === null);

// focus_id 校验
$r = mainline_ai_validate(['headline' => 'h', 'reasoning' => 'r', 'focus_id' => 'not-exist', 'plan' => []], $items);
check('非法 focus_id 清空', ($r['focus_id'] ?? 'x') === '');

// 动作白名单
$r = mainline_ai_validate(['headline' => 'h', 'reasoning' => 'r', 'plan' => [
    ['step' => '恶意', 'action' => ['type' => 'exec_shell', 'cmd' => 'rm -rf /']],
    ['step' => '外链', 'action' => ['type' => 'open', 'label' => '走', 'url' => 'https://evil.com/x']],
    ['step' => '协议', 'action' => ['type' => 'open', 'label' => '走', 'url' => 'javascript:alert(1)']],
    ['step' => '站内', 'action' => ['type' => 'open', 'label' => '去CRM', 'url' => '/xmp/crm?filter=due']],
    ['step' => '多出来的一步', 'action' => ['type' => 'open', 'label' => 'x', 'url' => 'ftp://evil']],
]], $items);
check('未知动作/外链/协议被剔除，仅留站内', count($r['plan']) === 1 && $r['plan'][0]['url'] === '/xmp/crm?filter=due');
$r3 = mainline_ai_validate(['headline' => 'h', 'reasoning' => 'r', 'plan' => [
    ['step' => 'a', 'action' => ['type' => 'open', 'label' => 'a', 'url' => '/xmp/a']],
    ['step' => 'b', 'action' => ['type' => 'open', 'label' => 'b', 'url' => '/xmp/b']],
    ['step' => 'c', 'action' => ['type' => 'open', 'label' => 'c', 'url' => '/xmp/c']],
    ['step' => 'd', 'action' => ['type' => 'open', 'label' => 'd', 'url' => '/xmp/d']],
    ['step' => 'e', 'action' => ['type' => 'open', 'label' => 'e', 'url' => '/xmp/e']],
]], $items);
check('合法计划最多保留 3 步', count($r3['plan']) === 3);

// set_goal 指标白名单
$r = mainline_ai_validate(['headline' => 'h', 'reasoning' => 'r', 'plan' => [
    ['step' => '设定目标', 'action' => ['type' => 'set_goal', 'label' => '设目标', 'goal' => ['metric' => 'hack_metric', 'target' => 100]]],
    ['step' => '合法目标', 'action' => ['type' => 'set_goal', 'label' => '设目标', 'goal' => ['metric' => 'revenue', 'target' => 50000, 'title' => '月收入']]],
]], $items);
check('非法指标被剔除', count($r['plan']) === 1 && $r['plan'][0]['goal']['metric'] === 'revenue');

// create_flow 走 CopilotActions 同一道门
$r = mainline_ai_validate(['headline' => 'h', 'reasoning' => 'r', 'plan' => [
    ['step' => '坏流程', 'action' => ['type' => 'create_flow', 'label' => '建', 'flow' => ['name' => 'x', 'trigger' => 'boom', 'steps' => [['action' => 'exec']]]]],
    ['step' => '好流程', 'action' => ['type' => 'create_flow', 'label' => '建', 'flow' => ['name' => '欢迎', 'trigger' => 'member_register', 'steps' => [['action' => 'send_email', 'subject' => '欢迎', 'content' => 'hi']]]]],
]], $items);
check('非法 Flow 被剔除、合法 Flow 保留', count($r['plan']) === 1 && $r['plan'][0]['flow']['trigger'] === 'member_register');

// 回执日志
mainline_ai_log('open', '去跟进线索', ['url' => '/xmp/crm'], true);
mainline_ai_log('create_flow', '创建欢迎流程', ['flow_id' => 'f1'], true);
$rc = mainline_ai_receipts(5);
check('回执按时间倒序', count($rc) === 2 && $rc[0]['label'] === '创建欢迎流程');
check('回执含成功标记', ($rc[0]['ok'] ?? false) === true);

// 一句话指挥：指令 → 计划
AiCenter::$queue = [['ok' => true, 'data' => [
    'headline' => '给沉默会员发召回', 'reasoning' => '近 7 天活跃下降，值得召回。',
    'plan' => [['step' => '创建召回流程', 'action' => ['type' => 'create_flow', 'label' => '建召回流程', 'flow' => [
        'name' => '沉默会员召回', 'trigger' => 'login', 'steps' => [['action' => 'send_email', 'subject' => '想你了', 'content' => '回来看看']],
    ]]]],
]]];
$cmd = mainline_ai_command('给 7 天没来的会员发召回邮件', $items);
check('指令解析成可执行计划', ($cmd['ok'] ?? false) && count($cmd['judge']['plan']) === 1 && $cmd['judge']['plan'][0]['type'] === 'create_flow');
check('空指令被拒', (mainline_ai_command('   ', $items)['ok'] ?? true) === false);
AiCenter::$queue = [['ok' => true, 'data' => ['headline' => 'h', 'reasoning' => 'r', 'plan' => [['step' => '越权', 'action' => ['type' => 'exec_shell']]]]]];
$cmd2 = mainline_ai_command('删除所有数据', $items);
check('指令里的越权动作被清洗为纯确认', ($cmd2['ok'] ?? false) && $cmd2['judge']['plan'] === []);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
