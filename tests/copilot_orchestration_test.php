<?php
/**
 * B7 Copilot 真 LLM 编排契约 —— 模板快速路径 + LLM 兜底 + 白名单安全边界
 *   php tests/copilot_orchestration_test.php
 *
 * 核心断言：AI 生成的流程必须过白名单校验才可能落库 —— 未知触发器/动作、
 * 空邮件主题、越界步骤数一律拒绝；数字问答只做路由、不编造数字。
 */

// 用 stub AiCenter 模拟「AI 已配置」，避免拉起真实模型/配置
class AiCenter {
    public static array $queue = [];
    public static function isConfigured(): bool { return true; }
    public static function json(string $s, string $u, array $o = []): array {
        return array_shift(self::$queue) ?? ['ok' => false];
    }
}

require_once __DIR__ . '/../lib/CopilotActions.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; }
    else { $fail++; echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

echo "B7 Copilot 真 LLM 编排\n";

echo "\n· 模板快速路径（确定性，不调模型）\n";
$tplWelcome = copilot_parse_flow('给新注册用户发欢迎邮件');
check('欢迎邮件模板命中', is_array($tplWelcome) && $tplWelcome['trigger'] === 'member_register');
$tplCoupon = copilot_parse_flow('购买后送优惠券');
check('购买发券模板命中', is_array($tplCoupon) && $tplCoupon['trigger'] === 'purchase' && $tplCoupon['steps'][1]['action'] === 'send_coupon');
$tplLead = copilot_parse_flow('帮我做线索跟进');
check('线索跟进触发器已修正为真实事件 form_submit', is_array($tplLead) && $tplLead['trigger'] === 'form_submit');

echo "\n· LLM 兜底 + 白名单校验\n";
AiCenter::$queue = [['ok' => true, 'data' => ['matched' => true, 'flow' => [
    'name' => '高价值客户关怀', 'trigger' => 'purchase',
    'steps' => [['action' => 'send_email', 'subject' => '感谢', 'content' => 'hi {name}', 'delay_minutes' => 0], ['action' => 'add_tag', 'tag' => 'VIP']],
]]]];
$llm = copilot_parse_flow('买了东西的高价值客户帮我打个VIP标签并感谢一下');
check('模板未命中 → LLM 编排成功', is_array($llm) && $llm['name'] === '高价值客户关怀' && count($llm['steps']) === 2);

AiCenter::$queue = [['ok' => true, 'data' => ['matched' => false]]];
check('LLM 判定无关 → null', copilot_parse_flow('帮我建一个流程但主题是天气') === null);
check('纯闲聊不触发流程模型调用', copilot_parse_flow('今天天气怎么样') === null);

echo "\n· 安全边界（AI 不能生成越权动作）\n";
check('未知触发器被拒', copilot_validate_flow(['name' => 'x', 'trigger' => 'delete_everything', 'steps' => [['action' => 'send_email', 'subject' => 's', 'content' => 'c']]]) === null);
check('未知动作被拒', copilot_validate_flow(['name' => 'x', 'trigger' => 'purchase', 'steps' => [['action' => 'exec_shell', 'cmd' => 'rm -rf /']]]) === null);
check('send_email 空主题被拒', copilot_validate_flow(['name' => 'x', 'trigger' => 'purchase', 'steps' => [['action' => 'send_email', 'content' => 'c']]]) === null);
check('add_tag 空标签被拒', copilot_validate_flow(['name' => 'x', 'trigger' => 'purchase', 'steps' => [['action' => 'add_tag']]]) === null);
check('award_points 非正分被拒', copilot_validate_flow(['name' => 'x', 'trigger' => 'purchase', 'steps' => [['action' => 'award_points', 'points' => 0]]]) === null);
check('超过 6 步被拒', copilot_validate_flow(['name' => 'x', 'trigger' => 'purchase', 'steps' => array_fill(0, 7, ['action' => 'notify', 'title' => 't'])]) === null);
check('空名称被拒', copilot_validate_flow(['name' => '', 'trigger' => 'purchase', 'steps' => [['action' => 'notify', 'title' => 't']]]) === null);

echo "\n· 白名单内清洗（钳位/裁剪）\n";
$clean = copilot_validate_flow(['name' => str_repeat('长', 100), 'trigger' => 'purchase', 'steps' => [
    ['action' => 'award_points', 'points' => 999999],
    ['action' => 'send_coupon', 'coupon_type' => '恶意类型', 'coupon_value' => 50],
]]);
check('名称裁剪到 60', is_array($clean) && mb_strlen($clean['name']) === 60);
check('积分钳位到 100000', $clean['steps'][0]['points'] === 100000);
check('非法券类型回落 fixed', $clean['steps'][1]['coupon_type'] === 'fixed');

echo "\n· 数据问答：只路由，不编造数字\n";
check('数据键含核心口径', array_key_exists('revenue', copilot_data_keys()) && array_key_exists('conversion_rate', copilot_data_keys()));
AiCenter::$queue = [['ok' => true, 'data' => ['key' => 'revenue']]];
check('LLM 路由返回合法键', copilot_llm_route_data('这个月生意怎么样') === 'revenue');
AiCenter::$queue = [['ok' => true, 'data' => ['key' => 'not_a_key']]];
check('LLM 返回非法键 → null', copilot_llm_route_data('随便问问') === null);
check('纯闲聊不触发数据模型调用', copilot_quick_data('你好呀今天过得开心吗') === null);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
