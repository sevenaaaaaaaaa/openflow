<?php
/**
 * MainlineAi — 控制台的「小福判断」层
 *
 * 让 AI 基于**真实业务快照 + 今日行动队列**产出：今天最该做的一件事 + 理由 + 可执行计划。
 * 与规则引擎的区别：这是推理，不是查表；产出的是「能落地的动作」，不是一段文案。
 *
 * 安全：计划里的每个动作都过白名单校验（create_flow 走 CopilotActions 的同一道门，
 * open 限站内路径，set_goal 限已注册指标），并写回执日志。AI 未配置 → 诚实降级。
 */

function mainline_ai_file(): string { return DATA_DIR . '/mainline/ai.json'; }
function mainline_ai_log_file(): string { return DATA_DIR . '/mainline/ai-log.json'; }
if (!defined('MAINLINE_AI_TTL')) define('MAINLINE_AI_TTL', 1800);

function mainline_ai_cache(): ?array {
    $c = json_read(mainline_ai_file());
    return is_array($c) && !empty($c['judge']) ? $c : null;
}

/** 队列指纹：队列或关键数字变化 → 判断需要重算 */
function mainline_ai_fingerprint(array $items, array $ctx): string {
    $ids = array_map(fn($it) => $it['id'] . ':' . ($it['score'] ?? 0), $items);
    $nums = [$ctx['commerce']['revenue_30d'], $ctx['leads']['due'], $ctx['ops']['reviews_pending'], $ctx['audience']['active_7d']];
    return md5(implode('|', $ids) . '#' . implode(',', array_map('strval', $nums)));
}

/** 取判断（命中缓存则直接用；force 或有变化则重新推理） */
function mainline_ai_judge(array $items, bool $force = false): array {
    $ctx = business_context();
    $fp = mainline_ai_fingerprint($items, $ctx);
    $cache = mainline_ai_cache();
    if (!$force && $cache && ($cache['fingerprint'] ?? '') === $fp && (time() - (int)($cache['generated_at_ts'] ?? 0)) < MAINLINE_AI_TTL) {
        return ['ok' => true, 'judge' => $cache['judge'], 'cached' => true, 'generated_at' => $cache['generated_at'] ?? '', 'context' => $ctx];
    }

    if (!class_exists('AiCenter')) require_once __DIR__ . '/AiCenter.php';
    if (!AiCenter::isConfigured()) {
        return ['ok' => false, 'error' => 'AI 未配置', 'judge' => null, 'context' => $ctx];
    }

    // 队列摘要（只带模型需要的字段）
    $queue = [];
    foreach (array_slice($items, 0, 14) as $it) {
        $queue[] = ['id' => $it['id'], 'lane' => $it['lane'], 'title' => $it['title'], 'why' => $it['why'], 'severity' => $it['severity']];
    }

    $system = "你是 OpenFlow 的增长 COO「小福」。你面对的是一个一人公司的老板兼执行者——他最缺的是注意力和判断力。\n"
        . "根据【业务快照】和【今日行动队列】，给出今天最该做的一件事（要具体、可执行，不要泛泛而谈），"
        . "并给一份 1-3 步的行动计划，每步都要能一键执行。\n"
        . "可用动作类型（只能选这些）：\n"
        . "  create_flow —— 创建自动化流程，flow 结构同自动化引擎：{name,trigger,steps[]}，trigger 只能是 purchase/register/member_register/login/form_submit/newsletter_subscribed/course_complete/course_start/course_enroll/lesson_complete/page_view/article_view/element_click/download/share/segment_enter/segment_exit/refund/crm_stage_change/nps_submit/cron；steps 动作只能是 send_email(subject,content)/delay(delay_minutes)/notify(title)/add_tag(tag)/award_points(points)/inbox(title,content)/send_coupon(coupon_name,coupon_type,coupon_value,coupon_min)\n"
        . "  open —— 打开某个后台页面，url 用 /xmp/ 开头\n"
        . "  set_goal —— 设定本周目标，goal:{metric, target, title}，metric 只能是 revenue/won/members/leads\n"
        . "严格输出 JSON，不要多余文字：{\"headline\":\"今天先做这件事(≤40字)\",\"reasoning\":\"为什么是它(≤120字，引用快照里的真实数字)\",\"focus_id\":\"队列里最相关项的id或空字符串\",\"plan\":[{\"step\":\"这步做什么(≤30字)\",\"action\":{\"type\":\"...\",\"label\":\"按钮文案\",...}}]}";

    $user = "【业务快照】\n" . $ctx['digest'] . "\n\n【今日行动队列】\n" . json_encode($queue, JSON_UNESCAPED_UNICODE);

    try {
        $r = AiCenter::json($system, $user, ['max_tokens' => 1400, 'feature' => 'mainline_judge', 'tier' => 'admin']);
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => 'AI 调用失败', 'judge' => null, 'context' => $ctx];
    }
    if (empty($r['ok']) || empty($r['data'])) {
        return ['ok' => false, 'error' => (string)($r['error'] ?? 'AI 无有效返回'), 'judge' => null, 'context' => $ctx];
    }

    $judge = mainline_ai_validate((array)$r['data'], $items);
    if (!$judge) return ['ok' => false, 'error' => 'AI 返回未通过校验', 'judge' => null, 'context' => $ctx];

    $rec = ['generated_at' => date('Y-m-d H:i:s'), 'generated_at_ts' => time(), 'fingerprint' => $fp, 'judge' => $judge, 'digest' => $ctx['digest']];
    json_write(mainline_ai_file(), $rec);
    return ['ok' => true, 'judge' => $judge, 'cached' => false, 'generated_at' => $rec['generated_at'], 'context' => $ctx];
}

/** 站内路径安全校验：只允许单斜杠开头的相对路径，挡掉外链/协议相对/穿越 */
function mainline_ai_safe_url(string $url): bool {
    if ($url === '' || $url[0] !== '/') return false;
    if (substr($url, 0, 2) === '//') return false;
    if (strpos($url, '..') !== false) return false;
    return preg_match('#^/[A-Za-z0-9_\-/?=&.%]+$#', $url) === 1;
}

/** 白名单校验 AI 判断；非法动作剔除，没有任何可用动作则只留纯建议 */
function mainline_ai_validate(array $d, array $items): ?array {
    $headline = mb_substr(trim((string)($d['headline'] ?? '')), 0, 60);
    if ($headline === '') return null;
    $reasoning = mb_substr(trim((string)($d['reasoning'] ?? '')), 0, 300);

    $ids = array_column($items, 'id');
    $focus = (string)($d['focus_id'] ?? '');
    if ($focus !== '' && !in_array($focus, $ids, true)) $focus = '';

    // 动作白名单
    if (!class_exists('CopilotActions') && is_file(__DIR__ . '/CopilotActions.php')) require_once __DIR__ . '/CopilotActions.php';
    $metrics = function_exists('growth_goal_metrics') ? array_keys(growth_goal_metrics()) : ['revenue', 'won', 'members', 'leads'];

    $plan = [];
    foreach ((array)($d['plan'] ?? []) as $p) {
        if (!is_array($p)) continue;
        $step = mb_substr(trim((string)($p['step'] ?? '')), 0, 60);
        $a = (array)($p['action'] ?? []);
        $type = (string)($a['type'] ?? '');
        $clean = null;
        if ($type === 'create_flow' && !empty($a['flow']) && function_exists('copilot_validate_flow')) {
            $flow = copilot_validate_flow((array)$a['flow']);
            if ($flow) $clean = ['type' => 'create_flow', 'label' => mb_substr((string)($a['label'] ?? '创建流程'), 0, 20), 'flow' => $flow];
        } elseif ($type === 'open') {
            $url = (string)($a['url'] ?? '');
            if (mainline_ai_safe_url($url)) {
                $clean = ['type' => 'open', 'label' => mb_substr((string)($a['label'] ?? '去处理'), 0, 20), 'url' => $url];
            }
        } elseif ($type === 'set_goal') {
            $g = (array)($a['goal'] ?? []);
            $metric = (string)($g['metric'] ?? '');
            if (in_array($metric, $metrics, true) && (float)($g['target'] ?? 0) > 0) {
                $clean = ['type' => 'set_goal', 'label' => mb_substr((string)($a['label'] ?? '设定目标'), 0, 20),
                          'goal' => ['metric' => $metric, 'target' => (float)$g['target'], 'title' => mb_substr((string)($g['title'] ?? '增长目标'), 0, 40)]];
            }
        }
        if ($clean && $step !== '') { $clean['step'] = $step; $plan[] = $clean; }
    }
    return ['headline' => $headline, 'reasoning' => $reasoning, 'focus_id' => $focus, 'plan' => array_slice($plan, 0, 3)];
}

/** 执行一个 AI 计划动作（审批优先：由用户在控制台点击触发） */
function mainline_ai_execute(array $action): array {
    $type = (string)($action['type'] ?? '');
    $label = (string)($action['label'] ?? '');
    try {
        if ($type === 'open') {
            $url = (string)($action['url'] ?? '');
            if (!mainline_ai_safe_url($url)) return ['ok' => false, 'error' => '非法链接'];
            mainline_ai_log($type, $label, ['url' => $url], true);
            return ['ok' => true, 'kind' => 'open', 'url' => $url];
        }
        if ($type === 'create_flow') {
            if (!class_exists('CopilotActions')) require_once __DIR__ . '/CopilotActions.php';
            $r = copilot_create_flow((array)($action['flow'] ?? []));
            if (empty($r['ok'])) { mainline_ai_log($type, $label, ['error' => $r['error'] ?? ''], false); return ['ok' => false, 'error' => $r['error'] ?? '创建失败']; }
            mainline_ai_log($type, $label, ['flow_id' => $r['flow_id'], 'name' => $r['flow']['name'] ?? ''], true);
            return ['ok' => true, 'kind' => 'create_flow', 'flow_id' => $r['flow_id'], 'url' => '/xmp/automation'];
        }
        if ($type === 'set_goal') {
            require_once __DIR__ . '/GrowthGoal.php';
            $g = (array)($action['goal'] ?? []);
            $r = growth_goal_set(['title' => $g['title'] ?? '增长目标', 'metric' => $g['metric'] ?? 'revenue', 'target' => (float)($g['target'] ?? 0)]);
            mainline_ai_log($type, $label, ['goal' => $g], true);
            return ['ok' => true, 'kind' => 'set_goal', 'url' => '/xmp/brain'];
        }
    } catch (\Throwable $e) {
        mainline_ai_log($type, $label, ['error' => $e->getMessage()], false);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
    return ['ok' => false, 'error' => '未知动作'];
}

/** 回执日志：老板要的「看得见涟漪 / 留痕可回溯」 */
function mainline_ai_log(string $type, string $label, array $meta, bool $ok): void {
    $log = json_read(mainline_ai_log_file());
    if (!is_array($log)) $log = [];
    array_unshift($log, [
        'at' => date('Y-m-d H:i:s'), 'type' => $type, 'label' => $label,
        'ok' => $ok, 'meta' => $meta, 'by' => $_SESSION['admin_user'] ?? 'system',
    ]);
    json_write(mainline_ai_log_file(), array_slice($log, 0, 50));
}

function mainline_ai_receipts(int $limit = 6): array {
    $log = json_read(mainline_ai_log_file());
    return is_array($log) ? array_slice($log, 0, $limit) : [];
}
