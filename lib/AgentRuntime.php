<?php
/**
 * AgentRuntime — Agentic 运行时（工具链 + 计划/执行循环 + 重试自愈 + 审批门）
 *
 * 给一个"目标"，Agent 在**白名单工具**里自主选择并链式调用工具去完成它：
 *   计划 → 调工具 → 观察结果 → 决定下一步（或完成）；工具失败会重试，模型能据错误换路。
 * 工具体系统一复用现有能力：自动化原子动作 / 连接动作 / 插件 MCP 工具。
 * 风险门：safe 自动执行；moderate/high 停在"待批准"（人工点头后继续）。
 *
 * 调试/测试：设 $GLOBALS['AGENT_DECIDE_FN'] 可注入决策函数，不调模型。
 */

function agent_runs_file(): string { return DATA_DIR . '/agents/runs.json'; }

/** 统一工具库：name => ['desc','params'=>[k=>type],'risk','invoke'] */
function agent_tools(): array {
    $t = [
        'add_tag' => ['desc' => '给某个访客/会员打标签', 'params' => ['email' => 'string', 'tag' => 'string'], 'risk' => 'safe',
            'invoke' => fn(array $a) => agent_do_add_tag($a)],
        'add_segment' => ['desc' => '把某人加入 CDP 分群', 'params' => ['email' => 'string', 'segment_id' => 'string'], 'risk' => 'safe',
            'invoke' => fn(array $a) => agent_do_segment($a)],
        'award_points' => ['desc' => '给会员加积分', 'params' => ['member_id' => 'string', 'points' => 'int'], 'risk' => 'safe',
            'invoke' => fn(array $a) => agent_do_points($a)],
        'create_task' => ['desc' => '创建一条人工待办任务', 'params' => ['title' => 'string', 'assignee' => 'string', 'priority' => 'string'], 'risk' => 'safe',
            'invoke' => fn(array $a) => agent_do_task($a)],
        'set_goal' => ['desc' => '设定本周增长目标', 'params' => ['metric' => 'string', 'target' => 'number', 'title' => 'string'], 'risk' => 'safe',
            'invoke' => fn(array $a) => agent_do_goal($a)],
        'create_content' => ['desc' => '按标题/角度生成一篇内容草稿', 'params' => ['title' => 'string', 'angle' => 'string'], 'risk' => 'safe',
            'invoke' => fn(array $a) => agent_do_content($a)],
        'create_flow' => ['desc' => '创建一条自动化流程', 'params' => ['flow' => 'json'], 'risk' => 'moderate',
            'invoke' => fn(array $a) => agent_do_flow($a)],
        'update_lead' => ['desc' => '更新 CRM 线索（阶段/金额/负责人）', 'params' => ['email' => 'string', 'stage' => 'string', 'value' => 'number', 'owner' => 'string'], 'risk' => 'moderate',
            'invoke' => fn(array $a) => agent_do_update_lead($a)],
        'send_coupon' => ['desc' => '给某人发一张优惠券', 'params' => ['email' => 'string', 'name' => 'string', 'value' => 'number'], 'risk' => 'moderate',
            'invoke' => fn(array $a) => agent_do_coupon($a)],
        'publish_content' => ['desc' => '把某篇文章发布上线', 'params' => ['article_id' => 'string'], 'risk' => 'high',
            'invoke' => fn(array $a) => agent_do_publish($a)],
    ];
    // 连接动作（外部服务）：conn:{id}
    try {
        if (!function_exists('action_enabled')) require_once __DIR__ . '/ConnectionActions.php';
        if (function_exists('action_enabled')) {
            foreach (action_enabled() as $act) {
                $id = (string)($act['id'] ?? '');
                if ($id === '') continue;
                $t['conn:' . $id] = ['desc' => '外部连接动作：' . (string)($act['name'] ?? $id), 'params' => ['ctx' => 'json'], 'risk' => 'moderate',
                    'invoke' => fn(array $a) => agent_do_conn($id, $a)];
            }
        }
    } catch (\Throwable $e) {}
    // 插件注册的 MCP 工具：mcp:{name}
    if (class_exists('PluginSystem') && method_exists('PluginSystem', 'mcp_tools')) {
        foreach (PluginSystem::mcp_tools() as $name => $def) {
            $cb = $def['cb'] ?? null;
            if (!is_callable($cb)) continue;
            $t['mcp:' . $name] = ['desc' => (string)($def['desc'] ?? $name), 'params' => ['args' => 'json'], 'risk' => 'moderate',
                'invoke' => fn(array $a) => agent_do_mcp($cb, $a)];
        }
    }
    return $t;
}

/* ── 工具实现（薄封装，全部防御式）── */
function agent_do_add_tag(array $a): array {
    if (empty($a['email']) || empty($a['tag'])) return ['ok' => false, 'error' => '缺 email/tag'];
    if (!function_exists('cdp_find')) require_once __DIR__ . '/CdpSync.php';
    $c = cdp_find((string)$a['email']);
    if (!$c) return ['ok' => false, 'error' => '未找到该访客'];
    cdp_add_tag((string)($c['id'] ?? $c['visitor_id'] ?? ''), (string)$a['tag']);
    return ['ok' => true, 'result' => '已打标签 ' . $a['tag']];
}
function agent_do_segment(array $a): array {
    if (empty($a['email']) || empty($a['segment_id'])) return ['ok' => false, 'error' => '缺 email/segment_id'];
    require_once __DIR__ . '/AutomationSystem.php';
    automation_segment(['segment_id' => $a['segment_id']], ['email' => $a['email']], 'agent', true);
    return ['ok' => true, 'result' => '已加入分群 ' . $a['segment_id']];
}
function agent_do_points(array $a): array {
    if (empty($a['member_id']) || (int)($a['points'] ?? 0) <= 0) return ['ok' => false, 'error' => '缺 member_id/points'];
    require_once __DIR__ . '/Gamification.php';
    gamification_award((string)$a['member_id'], (int)$a['points'], 'agent');
    return ['ok' => true, 'result' => '已加 ' . (int)$a['points'] . ' 积分'];
}
function agent_do_task(array $a): array {
    if (empty($a['title'])) return ['ok' => false, 'error' => '缺 title'];
    $tasks = json_read(DATA_DIR . '/tasks.json'); if (!is_array($tasks)) $tasks = [];
    $tasks[] = ['id' => 'task_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6), 'title' => (string)$a['title'], 'description' => '', 'assignee' => (string)($a['assignee'] ?? 'admin'), 'assigner' => 'agent', 'priority' => in_array($a['priority'] ?? '', ['low', 'medium', 'high'], true) ? $a['priority'] : 'medium', 'status' => 'pending', 'progress' => 0, 'due_date' => '', 'comments' => [], 'created_at' => date('Y-m-d H:i:s'), 'completed_at' => ''];
    json_write(DATA_DIR . '/tasks.json', $tasks);
    return ['ok' => true, 'result' => '已创建任务：' . $a['title']];
}
function agent_do_goal(array $a): array {
    require_once __DIR__ . '/GrowthGoal.php';
    $metrics = array_keys(growth_goal_metrics());
    if (!in_array($a['metric'] ?? '', $metrics, true) || (float)($a['target'] ?? 0) <= 0) return ['ok' => false, 'error' => '指标非法或目标≤0'];
    growth_goal_set(['metric' => $a['metric'], 'target' => (float)$a['target'], 'title' => (string)($a['title'] ?? '增长目标')]);
    return ['ok' => true, 'result' => '已设目标 ' . $a['metric'] . '=' . (float)$a['target']];
}
function agent_do_content(array $a): array {
    if (empty($a['title'])) return ['ok' => false, 'error' => '缺 title'];
    require_once __DIR__ . '/Provisioner.php';
    $r = prov_create_content(['title' => (string)$a['title'], 'angle' => (string)($a['angle'] ?? ''), 'category' => 'insight']);
    return empty($r['ok']) ? ['ok' => false, 'error' => $r['error'] ?? '生成失败'] : ['ok' => true, 'result' => '已生成草稿 ' . $r['id'], 'ref' => $r['id']];
}
function agent_do_flow(array $a): array {
    $flow = is_array($a['flow'] ?? null) ? $a['flow'] : (json_decode((string)($a['flow'] ?? '{}'), true) ?: []);
    if (!class_exists('CopilotActions')) require_once __DIR__ . '/CopilotActions.php';
    $r = copilot_create_flow($flow);
    return empty($r['ok']) ? ['ok' => false, 'error' => $r['error'] ?? '创建失败'] : ['ok' => true, 'result' => '已创建流程 ' . $r['flow_id'], 'ref' => $r['flow_id']];
}
function agent_do_update_lead(array $a): array {
    if (empty($a['email'])) return ['ok' => false, 'error' => '缺 email'];
    require_once __DIR__ . '/CrmSystem.php';
    crm_ensure_lead((string)$a['email']);
    $patch = array_filter(['stage' => $a['stage'] ?? '', 'value' => isset($a['value']) ? (float)$a['value'] : null, 'owner' => $a['owner'] ?? ''], fn($v) => $v !== null && $v !== '');
    if ($patch) crm_update_lead((string)$a['email'], $patch);
    return ['ok' => true, 'result' => '已更新线索 ' . $a['email']];
}
function agent_do_coupon(array $a): array {
    if (empty($a['email'])) return ['ok' => false, 'error' => '缺 email'];
    require_once __DIR__ . '/CouponSystem.php';
    if (!function_exists('coupon_save')) return ['ok' => false, 'error' => '优惠券不可用'];
    $code = strtoupper('AGENT' . substr(bin2hex(random_bytes(3)), 0, 6));
    $r = coupon_save(['code' => $code, 'name' => (string)($a['name'] ?? '专属券'), 'type' => 'fixed', 'value' => (float)($a['value'] ?? 10), 'min_amount' => 0, 'max_uses' => 1, 'status' => 'active']);
    return empty($r['ok']) ? ['ok' => false, 'error' => '发券失败'] : ['ok' => true, 'result' => '已发券 ' . $code];
}
function agent_do_publish(array $a): array {
    if (empty($a['article_id']) || !function_exists('get_article')) return ['ok' => false, 'error' => '缺 article_id'];
    $art = get_article((string)$a['article_id']);
    if (!$art) return ['ok' => false, 'error' => '文章不存在'];
    $art['status'] = 'published'; $art['updated_at'] = date('Y-m-d H:i:s');
    if (empty($art['published_at'])) $art['published_at'] = date('Y-m-d H:i:s');
    save_article($art);
    return ['ok' => true, 'result' => '已发布 ' . ($art['title'] ?? $a['article_id'])];
}
function agent_do_conn(string $id, array $a): array {
    require_once __DIR__ . '/ConnectionActions.php';
    $ok = action_run_safe($id, (array)($a['ctx'] ?? []));
    return ['ok' => (bool)$ok, 'result' => $ok ? '连接动作已执行' : '', 'error' => $ok ? '' : '连接动作失败'];
}
function agent_do_mcp(callable $cb, array $a): array {
    try { $r = call_user_func($cb, (array)($a['args'] ?? [])); return ['ok' => true, 'result' => is_string($r) ? $r : json_encode($r, JSON_UNESCAPED_UNICODE)]; }
    catch (\Throwable $e) { return ['ok' => false, 'error' => $e->getMessage()]; }
}

/* ── 参数校验 ── */
function agent_validate_args(array $params, array $args): array {
    $out = [];
    foreach ($params as $k => $type) {
        if (!array_key_exists($k, $args)) continue;
        $v = $args[$k];
        if ($type === 'int') $out[$k] = (int)$v;
        elseif ($type === 'number') $out[$k] = (float)$v;
        elseif ($type === 'json') $out[$k] = is_array($v) ? $v : (json_decode((string)$v, true) ?: []);
        else $out[$k] = mb_substr((string)$v, 0, 500);
    }
    return $out;
}

/** 风险门：safe 自动；moderate/high 需人工批准（或自治级别允许） */
function agent_allowed(array $tool, array $opts = []): bool {
    $risk = (string)($tool['risk'] ?? 'high');
    if ($risk === 'safe') return true;
    if (!empty($opts['approve'])) return true;
    try {
        if (!function_exists('autonomy_can_auto')) require_once __DIR__ . '/AutonomyGuard.php';
        if (function_exists('autonomy_can_auto') && !empty($opts['auto'])) {
            $r = autonomy_can_auto(['action' => (string)($opts['tool'] ?? ''), 'module' => 'agent', 'cost' => 0]);
            return !empty($r['allow']);
        }
    } catch (\Throwable $e) {}
    return false;
}

/** 调一次工具（带重试：最多 3 次，退避 0.2s） */
function agent_invoke(array $tool, array $args): array {
    $err = '';
    for ($i = 0; $i < 3; $i++) {
        try {
            $r = call_user_func($tool['invoke'], $args);
            if (!empty($r['ok'])) return $r;
            $err = (string)($r['error'] ?? '失败');
        } catch (\Throwable $e) { $err = $e->getMessage(); }
        usleep(200000);
    }
    return ['ok' => false, 'error' => $err];
}

/** Agent 决策：注入函数优先，否则走 AI */
function agent_decide(string $goal, array $steps, string $catalog): ?array {
    if (isset($GLOBALS['AGENT_DECIDE_FN']) && is_callable($GLOBALS['AGENT_DECIDE_FN'])) {
        return call_user_func($GLOBALS['AGENT_DECIDE_FN'], $goal, $steps, $catalog);
    }
    if (!class_exists('AiCenter')) require_once __DIR__ . '/AiCenter.php';
    if (!AiCenter::isConfigured()) return null;
    $system = "你是 OpenFlow 自动化 Agent。目标：{$goal}\n可用工具：\n{$catalog}\n"
        . "每轮选择**下一个工具调用**，或宣布完成。严格输出 JSON：\n"
        . "{\"thought\":\"简述\",\"tool\":\"工具名\",\"args\":{...}} 或 {\"thought\":\"简述\",\"done\":true,\"summary\":\"结果\"}\n"
        . "只允许选择给定工具；args 必须匹配参数；已完成或无法继续时 done=true。";
    $hist = [];
    foreach ($steps as $s) $hist[] = ['tool' => $s['tool'], 'args' => $s['args'], 'ok' => $s['ok'], 'result' => mb_substr((string)($s['result'] ?? $s['error'] ?? ''), 0, 300)];
    try { $r = AiCenter::json($system, '已执行：' . json_encode($hist, JSON_UNESCAPED_UNICODE), ['max_tokens' => 800, 'feature' => 'agent_decide', 'tier' => 'admin']); }
    catch (\Throwable $e) { return null; }
    return empty($r['ok']) ? null : (array)($r['data'] ?? []);
}

/** 运行：计划→执行循环 */
function agent_run(string $goal, array $opts = []): array {
    $goal = trim($goal);
    if ($goal === '') return ['ok' => false, 'error' => '目标不能为空'];
    $tools = agent_tools();
    $lines = [];
    foreach ($tools as $name => $t) $lines[] = "- {$name}（{$t['desc']}；参数：" . implode(',', array_keys($t['params'])) . "；风险：{$t['risk']}）";
    $catalog = implode("\n", $lines);

    $runId = 'agent_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4);
    $maxSteps = max(1, min(10, (int)($opts['max_steps'] ?? 6)));
    $steps = [];
    $status = 'failed'; $summary = '';

    for ($i = 0; $i < $maxSteps; $i++) {
        $d = agent_decide($goal, $steps, $catalog);
        if (!$d) { $status = 'failed'; $summary = '决策失败（AI 不可用或返回异常）'; break; }
        if (!empty($d['done'])) { $status = 'completed'; $summary = (string)($d['summary'] ?? '完成'); break; }
        $name = (string)($d['tool'] ?? '');
        $thought = mb_substr((string)($d['thought'] ?? ''), 0, 200);
        if (!isset($tools[$name])) { $steps[] = ['thought' => $thought, 'tool' => $name, 'args' => [], 'ok' => false, 'error' => '工具不在白名单', 'attempts' => 0]; continue; }
        $args = agent_validate_args($tools[$name]['params'], (array)($d['args'] ?? []));
        if (!agent_allowed($tools[$name], ['approve' => !empty($opts['approve']), 'auto' => !empty($opts['auto']), 'tool' => $name])) {
            $pending = ['thought' => $thought, 'tool' => $name, 'args' => $args];
            $run = ['id' => $runId, 'goal' => $goal, 'status' => 'needs_approval', 'steps' => $steps, 'pending' => $pending, 'summary' => '该动作风险为「' . $tools[$name]['risk'] . '」，待人工批准', 'created_at' => date('Y-m-d H:i:s')];
            agent_store($run);
            return ['ok' => true, 'run' => $run];
        }
        $res = agent_invoke($tools[$name], $args);
        $steps[] = ['thought' => $thought, 'tool' => $name, 'args' => $args, 'ok' => !empty($res['ok']), 'result' => (string)($res['result'] ?? ''), 'error' => (string)($res['error'] ?? '')];
    }
    if ($status === 'failed' && count($steps) >= $maxSteps) { $status = 'max_steps'; $summary = '达到最大步数'; }
    $run = ['id' => $runId, 'goal' => $goal, 'status' => $status, 'steps' => $steps, 'pending' => null, 'summary' => $summary, 'created_at' => date('Y-m-d H:i:s')];
    agent_store($run);
    // 回流：成功完成的 agent 运行给学习层一个正向信号
    if ($status === 'completed') { try { require_once __DIR__ . '/GrowthLearning.php'; growth_learning_verdict('agent', $goal, 'done'); } catch (\Throwable $e) {} }
    return ['ok' => true, 'run' => $run];
}

/** 批准待执行动作并继续（执行 pending 后收尾；如需多步可再次 run） */
function agent_approve(string $runId, array $opts = []): array {
    $runs = agent_stored();
    foreach ($runs as $i => $run) {
        if (($run['id'] ?? '') !== $runId) continue;
        if (($run['status'] ?? '') !== 'needs_approval' || empty($run['pending'])) return ['ok' => false, 'error' => '该运行无需批准'];
        $tools = agent_tools();
        $p = $run['pending'];
        $tool = $tools[$p['tool']] ?? null;
        if (!$tool) return ['ok' => false, 'error' => '工具已失效'];
        $res = agent_invoke($tool, (array)$p['args']);
        $run['steps'][] = ['thought' => (string)($p['thought'] ?? ''), 'tool' => $p['tool'], 'args' => $p['args'], 'ok' => !empty($res['ok']), 'result' => (string)($res['result'] ?? ''), 'error' => (string)($res['error'] ?? ''), 'approved' => true];
        $run['pending'] = null;
        $run['status'] = !empty($res['ok']) ? 'completed' : 'failed';
        $run['summary'] = !empty($res['ok']) ? '已按批准执行' : ('执行失败：' . ($res['error'] ?? ''));
        $runs[$i] = $run;
        json_write(agent_runs_file(), $runs);
        return ['ok' => true, 'run' => $run];
    }
    return ['ok' => false, 'error' => '运行不存在'];
}

function agent_store(array $run): void {
    $runs = agent_stored();
    array_unshift($runs, $run);
    json_write(agent_runs_file(), array_slice($runs, 0, 50));
}
function agent_stored(): array { $d = json_read(agent_runs_file()); return is_array($d) ? $d : []; }
